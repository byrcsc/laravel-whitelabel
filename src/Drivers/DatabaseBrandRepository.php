<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel\Drivers;

use Byrcsc\Whitelabel\Brand;
use Byrcsc\Whitelabel\BrandDefinition;
use Byrcsc\Whitelabel\Contracts\BrandRepository;
use Byrcsc\Whitelabel\Events\BrandCreated;
use Byrcsc\Whitelabel\Events\BrandDeleted;
use Byrcsc\Whitelabel\Events\BrandUpdated;
use Byrcsc\Whitelabel\Exceptions\BrandAlreadyExists;
use Byrcsc\Whitelabel\Exceptions\UnknownBrand;
use Byrcsc\Whitelabel\Models\BrandRecord;
use Closure;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Stores brands in a table, with a programmatic management API.
 *
 * Reads are uncached by design: wrap this driver in the caching decorator to
 * make repeated lookups cheap.
 */
final class DatabaseBrandRepository implements BrandRepository
{
    public const NAME = 'database';

    public function __construct(
        private readonly Config $config,
        private readonly Dispatcher $events,
    ) {}

    public function all(): array
    {
        $records = BrandRecord::query()->orderBy('identifier')->get();

        $defaultId = $this->defaultBrandId();
        $defaultRecord = $records->firstWhere('identifier', $defaultId);
        $default = $defaultRecord === null ? null : $this->toBrand($defaultRecord, null);

        $brands = [];

        foreach ($records as $record) {
            $brands[$record->identifier] = $record->identifier === $defaultId && $default !== null
                ? $default
                : $this->toBrand($record, $default);
        }

        return $brands;
    }

    public function find(string $id): ?Brand
    {
        if ($id === $this->defaultBrandId()) {
            return $this->defaultBrand();
        }

        $record = BrandRecord::query()->firstWhere('identifier', $id);

        return $record === null ? null : $this->hydrate($record);
    }

    public function findByDomain(string $domain): ?Brand
    {
        $record = BrandRecord::query()->firstWhere('domain', mb_strtolower($domain));

        return $record === null ? null : $this->hydrate($record);
    }

    public function has(string $id): bool
    {
        return BrandRecord::query()->where('identifier', $id)->exists();
    }

    public function create(string $id, array $definition): Brand
    {
        $columns = BrandColumns::fromDefinition(BrandDefinition::validate($id, $definition));

        $record = $this->write($id, $columns, fn (): BrandRecord => $this->transaction(
            static function () use ($id, $columns): BrandRecord {
                $record = new BrandRecord;
                $record->identifier = $id;
                $record->forceFill($columns)->save();

                return $record;
            }
        ));

        $brand = $this->hydrate($record);

        $this->events->dispatch(new BrandCreated($brand));

        return $brand;
    }

    public function update(string $id, array $definition): Brand
    {
        $columns = BrandColumns::fromDefinition(BrandDefinition::validate($id, $definition));

        $record = $this->write($id, $columns, fn (): BrandRecord => $this->transaction(
            static function () use ($id, $columns): BrandRecord {
                $record = BrandRecord::query()->where('identifier', $id)->lockForUpdate()->first();

                if ($record === null) {
                    throw UnknownBrand::named($id);
                }

                $record->forceFill($columns)->save();

                return $record;
            }
        ));

        $brand = $this->hydrate($record);

        $this->events->dispatch(new BrandUpdated($brand));

        return $brand;
    }

    public function delete(string $id): bool
    {
        $brand = $this->transaction(function () use ($id): ?Brand {
            $record = BrandRecord::query()->where('identifier', $id)->lockForUpdate()->first();

            if ($record === null) {
                return null;
            }

            $brand = $this->hydrate($record);

            $record->delete();

            return $brand;
        });

        if ($brand === null) {
            return false;
        }

        $this->events->dispatch(new BrandDeleted($brand));

        return true;
    }

    public function flush(): void
    {
        // Every read goes to the database, so there is nothing to drop.
    }

    /**
     * Run a write, turning a unique-key collision into a domain-level failure.
     *
     * @param  array<string, mixed>  $columns
     * @param  callable(): BrandRecord  $write
     */
    private function write(string $id, array $columns, callable $write): BrandRecord
    {
        try {
            return $write();
        } catch (UniqueConstraintViolationException $exception) {
            $domain = $columns[BrandDefinition::DOMAIN] ?? null;

            if (is_string($domain) && $this->domainCollided($exception)) {
                throw BrandAlreadyExists::onDomain($domain, $id, $exception);
            }

            throw BrandAlreadyExists::withIdentifier($id, $exception);
        }
    }

    private function domainCollided(UniqueConstraintViolationException $exception): bool
    {
        $driverMessage = $exception->errorInfo[2] ?? null;

        return is_string($driverMessage)
            && str_contains($driverMessage, BrandDefinition::DOMAIN);
    }

    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $work
     * @return TReturn
     */
    private function transaction(Closure $work): mixed
    {
        return (new BrandRecord)->getConnection()->transaction($work);
    }

    /**
     * Turn a row into a brand, looking the default brand up if it is needed.
     */
    private function hydrate(BrandRecord $record): Brand
    {
        return $record->identifier === $this->defaultBrandId()
            ? $this->toBrand($record, null)
            : $this->toBrand($record, $this->defaultBrand());
    }

    /**
     * Turn a row into a brand against an already-loaded default brand.
     *
     * The default brand is its own fallback-free self, so a write to it does
     * not hand it a stale copy of the row it just replaced.
     */
    private function toBrand(BrandRecord $record, ?Brand $default): Brand
    {
        return new Brand(
            $record->identifier,
            BrandColumns::toDefinition($record),
            $record->identifier === $this->defaultBrandId() ? null : $default,
        );
    }

    private function defaultBrand(): ?Brand
    {
        $id = $this->defaultBrandId();

        if ($id === null) {
            return null;
        }

        $record = BrandRecord::query()->firstWhere('identifier', $id);

        return $record === null ? null : new Brand($id, BrandColumns::toDefinition($record));
    }

    private function defaultBrandId(): ?string
    {
        $id = $this->config->get('whitelabel.default');

        return is_string($id) && $id !== '' ? $id : null;
    }
}
