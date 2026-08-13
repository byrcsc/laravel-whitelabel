<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel\Drivers;

use Byrcsc\Whitelabel\Brand;
use Byrcsc\Whitelabel\Contracts\BrandRepository;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;

/**
 * Makes brand lookup cheap, without the driver underneath knowing.
 *
 * Entries are per brand, keyed by identifier and stored forever: brands change
 * when someone changes them, not when a clock runs out. A separate
 * domain-to-identifier index answers `findByDomain`, and grows one entry at a
 * time as domains are looked up rather than by loading every brand.
 * Definitions are cached rather than `Brand` objects, so a brand never carries
 * a frozen copy of a default brand that has since been edited.
 *
 * Writes bust the brand they touched and the domain index. That is the only
 * invalidation path, apart from {@see flush()} behind `whitelabel:clear`.
 *
 * The cache store is resolved from the factory on every call rather than held.
 * That is what lets Spatie's `PrefixCacheTask` swap the store underneath a
 * long-lived repository when a tenant becomes current.
 */
final class CachedBrandRepository implements BrandRepository
{
    /**
     * The default brand, resolved at most once per instance.
     */
    private ?Brand $default = null;

    private bool $defaultLoaded = false;

    public function __construct(
        private readonly BrandRepository $repository,
        private readonly CacheFactory $cache,
        private readonly Config $config,
    ) {}

    /**
     * The driver underneath, for callers that need to know what they wrap.
     */
    public function inner(): BrandRepository
    {
        return $this->repository;
    }

    /**
     * Every brand, straight from the driver.
     *
     * Listing brands is a management operation, not the hot path, and warming
     * the cache from it would write an entry per brand every time somebody
     * opened an index page.
     */
    public function all(): array
    {
        return $this->repository->all();
    }

    public function find(string $id): ?Brand
    {
        $definition = $this->definition($id);

        return $definition === null ? null : $this->hydrate($id, $definition);
    }

    public function findByDomain(string $domain): ?Brand
    {
        $domain = mb_strtolower($domain);

        $known = $this->domainIndex()[$domain] ?? null;

        if (is_string($known)) {
            return $this->find($known);
        }

        $brand = $this->repository->findByDomain($domain);

        if ($brand === null) {
            return null;
        }

        $this->rememberWithFallback($brand);
        $this->rememberDomain($domain, $brand->id());

        return $brand;
    }

    public function has(string $id): bool
    {
        return $this->definition($id) !== null;
    }

    public function create(string $id, array $definition): Brand
    {
        return $this->written($this->repository->create($id, $definition));
    }

    public function update(string $id, array $definition): Brand
    {
        return $this->written($this->repository->update($id, $definition));
    }

    public function delete(string $id): bool
    {
        $deleted = $this->repository->delete($id);

        $this->bust($id);

        return $deleted;
    }

    public function flush(): void
    {
        $store = $this->store();

        // The registry covers brands that have since been deleted, the driver
        // covers brands the registry lost to a concurrent write. Between them
        // no forever entry is left behind.
        $identifiers = array_unique([
            ...$this->cachedIdentifiers(),
            ...array_keys($this->repository->all()),
        ]);

        foreach ($identifiers as $id) {
            $store->forget($this->brandKey($id));
        }

        $store->forget($this->domainIndexKey());
        $store->forget($this->registryKey());

        $this->forgetDefault();

        $this->repository->flush();
    }

    /**
     * A brand's own definition, from the cache when it is there.
     *
     * A brand that does not exist is not cached. Identifiers reach this from
     * job payloads and from `Whitelabel::activate()`, and caching every miss
     * forever would let a typo, or a loop, fill the store with dead keys.
     *
     * @return array<string, mixed>|null
     */
    private function definition(string $id): ?array
    {
        /** @var mixed $cached */
        $cached = $this->store()->get($this->brandKey($id));

        if (is_array($cached)) {
            /** @var array<string, mixed> $cached */
            return $cached;
        }

        $brand = $this->repository->find($id);

        if ($brand === null) {
            return null;
        }

        $this->rememberWithFallback($brand);

        return $brand->definition();
    }

    /**
     * Cache a brand, and the default brand the driver loaded behind it.
     *
     * The driver already paid for the fallback in order to build this brand,
     * so caching it here is free and saves the next lookup a query.
     */
    private function rememberWithFallback(Brand $brand): void
    {
        $this->remember($brand);

        $fallback = $brand->fallback();

        if ($fallback !== null) {
            $this->remember($fallback);
        }
    }

    /**
     * @return array<string, string>
     */
    private function domainIndex(): array
    {
        /** @var mixed $cached */
        $cached = $this->store()->get($this->domainIndexKey(), []);

        if (! is_array($cached)) {
            return [];
        }

        /** @var array<string, string> $cached */
        return $cached;
    }

    private function rememberDomain(string $domain, string $id): void
    {
        $this->store()->forever($this->domainIndexKey(), [...$this->domainIndex(), $domain => $id]);
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function hydrate(string $id, array $definition): Brand
    {
        $defaultId = $this->defaultBrandId();

        return $id === $defaultId
            ? new Brand($id, $definition)
            : new Brand($id, $definition, $this->defaultBrand());
    }

    /**
     * The default brand, looked up at most once per instance.
     *
     * Memoised rather than cached: a default brand that does not exist yet is
     * not a cacheable answer, and without this every other lookup would pay a
     * driver query for the same missing brand.
     */
    private function defaultBrand(): ?Brand
    {
        if ($this->defaultLoaded) {
            return $this->default;
        }

        $this->defaultLoaded = true;

        $id = $this->defaultBrandId();

        return $this->default = $id === null ? null : $this->find($id);
    }

    private function forgetDefault(): void
    {
        $this->default = null;
        $this->defaultLoaded = false;
    }

    private function written(Brand $brand): Brand
    {
        $this->bust($brand->id());

        return $brand;
    }

    /**
     * Drop what a write to this brand could have invalidated.
     *
     * The domain index goes too, because a brand can arrive on, leave, or move
     * between domains, and the index is the only place that mapping lives.
     */
    private function bust(string $id): void
    {
        $this->store()->forget($this->brandKey($id));
        $this->store()->forget($this->domainIndexKey());

        $this->forgetDefault();
    }

    private function remember(Brand $brand): void
    {
        $this->store()->forever($this->brandKey($brand->id()), $brand->definition());

        $identifiers = $this->cachedIdentifiers();

        if (! in_array($brand->id(), $identifiers, true)) {
            $identifiers[] = $brand->id();

            $this->store()->forever($this->registryKey(), $identifiers);
        }
    }

    /**
     * Which brands this store has entries for, so `flush()` can be exact.
     *
     * Cache stores cannot be enumerated and tags are not available on every
     * one of them, so the package keeps its own list rather than reaching for
     * `Cache::flush()` and taking the application's own entries with it. The
     * list is advisory: `flush()` also asks the driver, so a concurrent write
     * losing an entry here cannot strand a key.
     *
     * @return list<string>
     */
    private function cachedIdentifiers(): array
    {
        /** @var mixed $identifiers */
        $identifiers = $this->store()->get($this->registryKey(), []);

        if (! is_array($identifiers)) {
            return [];
        }

        return array_values(array_filter($identifiers, is_string(...)));
    }

    private function brandKey(string $id): string
    {
        return $this->prefix().':brand:'.$id;
    }

    private function domainIndexKey(): string
    {
        return $this->prefix().':domains';
    }

    private function registryKey(): string
    {
        return $this->prefix().':cached';
    }

    private function prefix(): string
    {
        $prefix = $this->config->get('whitelabel.cache.prefix');

        return is_string($prefix) && $prefix !== '' ? $prefix : 'whitelabel';
    }

    private function defaultBrandId(): ?string
    {
        $id = $this->config->get('whitelabel.default');

        return is_string($id) && $id !== '' ? $id : null;
    }

    private function store(): Cache
    {
        $store = $this->config->get('whitelabel.cache.store');

        return $this->cache->store(is_string($store) && $store !== '' ? $store : null);
    }
}
