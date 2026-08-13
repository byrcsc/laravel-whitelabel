<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel;

use Byrcsc\Whitelabel\Contracts\BrandRepository;
use Byrcsc\Whitelabel\Contracts\BrandResolver;
use Byrcsc\Whitelabel\Events\BrandActivated;
use Byrcsc\Whitelabel\Events\BrandDeactivated;
use Byrcsc\Whitelabel\Exceptions\UnknownBrand;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Which brand the application is wearing right now.
 *
 * Resolution is lazy: the resolver chain runs the first time {@see current()}
 * is called and the answer is kept for the rest of the request, job, or
 * command. No middleware is required.
 */
class Whitelabel
{
    private ?Brand $active = null;

    private bool $resolved = false;

    /**
     * The brand set by {@see activate()}, which wins over every resolver.
     */
    private ?Brand $override = null;

    /**
     * Definitions registered at runtime, ahead of any driver.
     *
     * Definitions rather than brands, so a brand defined before the default
     * brand changes does not keep serving the default it was born with.
     *
     * @var array<string, array<array-key, mixed>>
     */
    private array $defined = [];

    public function __construct(
        private readonly Container $container,
        private readonly Config $config,
    ) {}

    /**
     * The active brand, resolving it on first access.
     */
    public function current(): ?Brand
    {
        if ($this->resolved) {
            return $this->active;
        }

        $this->resolved = true;

        return $this->transitionTo($this->runChain());
    }

    /**
     * Whether the chain has already run, without running it.
     */
    public function isResolved(): bool
    {
        return $this->resolved;
    }

    /**
     * Make a brand the active one, overriding every resolver.
     *
     * @throws UnknownBrand when an identifier names no brand.
     */
    public function activate(Brand|string $brand): Brand
    {
        $brand = $brand instanceof Brand ? $brand : $this->brandNamed($brand);

        $this->override = $brand;

        // Re-run the chain rather than assigning directly, so OverrideResolver
        // wins by being first rather than by being special. Move it and the
        // chain behaves the way it reads.
        $this->resolved = false;

        return $this->current() ?? $brand;
    }

    /**
     * Drop the active brand and any override, so the chain runs again.
     */
    public function forget(): void
    {
        $this->override = null;
        $this->resolved = false;

        $this->transitionTo(null);
    }

    /**
     * Register a brand from an array, ahead of the configured driver.
     *
     * The brand is resolvable by identifier for the rest of the process and is
     * never persisted, whichever driver is configured.
     *
     * @param  array<array-key, mixed>  $definition
     */
    public function define(string $id, array $definition = []): Brand
    {
        $this->defined[$id] = $definition;

        return $this->definedBrand($id) ?? new Brand($id, $definition);
    }

    /**
     * Forget the active brand, any override, and every defined brand.
     *
     * The package calls this itself between queued jobs and between Octane
     * requests, so one brand's state never reaches the next piece of work.
     */
    public function flush(): void
    {
        $this->forget();

        $this->defined = [];
    }

    /**
     * The brand passed to {@see activate()}, which is what OverrideResolver
     * answers with.
     */
    public function overridden(): ?Brand
    {
        return $this->override;
    }

    /**
     * Look a brand up by identifier: defined brands first, then the driver.
     */
    public function find(string $id): ?Brand
    {
        return $this->definedBrand($id) ?? $this->brands()->find($id);
    }

    /**
     * Look a brand up by the host it answers on, defined brands first.
     */
    public function findByDomain(string $domain): ?Brand
    {
        $domain = mb_strtolower($domain);

        foreach (array_keys($this->defined) as $id) {
            $brand = $this->definedBrand($id);

            if ($brand?->domain() === $domain) {
                return $brand;
            }
        }

        return $this->brands()->findByDomain($domain);
    }

    /**
     * Build a runtime-defined brand, against the default brand as it is now.
     */
    private function definedBrand(string $id): ?Brand
    {
        if (! array_key_exists($id, $this->defined)) {
            return null;
        }

        $fallback = $id === $this->defaultBrandId() ? null : $this->defaultBrand();

        return new Brand($id, $this->defined[$id], $fallback);
    }

    private function brandNamed(string $id): Brand
    {
        return $this->find($id) ?? throw UnknownBrand::named($id);
    }

    /**
     * Walk the chain, building each resolver only when its turn comes.
     *
     * Resolvers are constructed one at a time on purpose: the first answer
     * wins, and a resolver further down the chain should not pay for a
     * repository it never gets to ask.
     */
    private function runChain(): ?Brand
    {
        /** @var mixed $configured */
        $configured = $this->config->get('whitelabel.resolvers', []);

        foreach (is_array($configured) ? $configured : [] as $entry) {
            $resolver = is_string($entry) ? $this->container->make($entry) : $entry;

            if (! $resolver instanceof BrandResolver) {
                continue;
            }

            $brand = $resolver->resolve();

            if ($brand !== null) {
                return $brand;
            }
        }

        return null;
    }

    /**
     * Move to a new active brand, firing one event per transition.
     */
    private function transitionTo(?Brand $brand): ?Brand
    {
        $previous = $this->active;

        if ($previous?->id() === $brand?->id()) {
            $this->active = $brand;

            return $brand;
        }

        $this->active = $brand;

        // Resolved per dispatch rather than held: this manager is a singleton
        // that outlives an Event::fake() swapping the dispatcher underneath it.
        $events = $this->container->make(Dispatcher::class);

        if ($previous !== null) {
            $events->dispatch(new BrandDeactivated($previous));
        }

        if ($brand !== null) {
            $events->dispatch(new BrandActivated($brand));
        }

        return $brand;
    }

    private function defaultBrand(): ?Brand
    {
        $id = $this->defaultBrandId();

        return $id === null ? null : $this->find($id);
    }

    private function defaultBrandId(): ?string
    {
        $id = $this->config->get('whitelabel.default');

        return is_string($id) && $id !== '' ? $id : null;
    }

    private function brands(): BrandRepository
    {
        return $this->container->make(BrandRepository::class);
    }
}
