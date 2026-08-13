<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel\Tests\Fixtures;

use Byrcsc\Whitelabel\Brand;
use Byrcsc\Whitelabel\Contracts\BrandRepository;

/**
 * A writable in-memory driver, standing in for a driver an application would
 * register on the manager itself.
 */
final class ArrayBrandRepository implements BrandRepository
{
    /**
     * @param  array<string, Brand>  $brands
     */
    public function __construct(private array $brands = []) {}

    public function all(): array
    {
        return $this->brands;
    }

    public function find(string $id): ?Brand
    {
        return $this->brands[$id] ?? null;
    }

    public function findByDomain(string $domain): ?Brand
    {
        foreach ($this->brands as $brand) {
            if ($brand->domain() === mb_strtolower($domain)) {
                return $brand;
            }
        }

        return null;
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->brands);
    }

    public function create(string $id, array $definition): Brand
    {
        return $this->brands[$id] = new Brand($id, $definition);
    }

    public function update(string $id, array $definition): Brand
    {
        return $this->create($id, $definition);
    }

    public function delete(string $id): bool
    {
        $existed = $this->has($id);

        unset($this->brands[$id]);

        return $existed;
    }

    public function flush(): void
    {
        // Nothing is held beyond the brands themselves.
    }
}
