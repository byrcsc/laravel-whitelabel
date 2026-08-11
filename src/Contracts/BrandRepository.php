<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel\Contracts;

use Byrcsc\Whitelabel\Brand;
use Byrcsc\Whitelabel\Exceptions\InvalidBrandDefinition;
use Byrcsc\Whitelabel\Exceptions\UnsupportedBrandOperation;

/**
 * Where brands come from.
 *
 * Every driver implements this contract and hydrates the same immutable
 * {@see Brand} objects, with the default brand already wired in as their
 * per-key fallback.
 *
 * Read-only drivers, such as the config driver, throw
 * {@see UnsupportedBrandOperation} from the write methods.
 */
interface BrandRepository
{
    /**
     * Every brand this driver knows, keyed by identifier.
     *
     * @return array<string, Brand>
     */
    public function all(): array;

    public function find(string $id): ?Brand;

    public function findByDomain(string $domain): ?Brand;

    public function has(string $id): bool;

    /**
     * @param  array<array-key, mixed>  $definition
     *
     * @throws InvalidBrandDefinition
     * @throws UnsupportedBrandOperation
     */
    public function create(string $id, array $definition): Brand;

    /**
     * Replace the stored definition of an existing brand.
     *
     * @param  array<array-key, mixed>  $definition
     *
     * @throws InvalidBrandDefinition
     * @throws UnsupportedBrandOperation
     */
    public function update(string $id, array $definition): Brand;

    /**
     * @return bool Whether a brand was there to delete.
     *
     * @throws UnsupportedBrandOperation
     */
    public function delete(string $id): bool;

    /**
     * Drop whatever the driver is holding, so the next read goes to the source.
     *
     * Writes invalidate what they touch on their own; this is the manual
     * escape hatch behind `whitelabel:clear`, and what a test calls after
     * rewriting brand configuration. A driver that holds nothing does nothing.
     */
    public function flush(): void;
}
