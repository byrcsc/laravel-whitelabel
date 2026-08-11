<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel\Resolvers;

use Byrcsc\Whitelabel\Brand;
use Byrcsc\Whitelabel\Contracts\BrandResolver;
use Byrcsc\Whitelabel\Contracts\ProvidesBrand;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;

/**
 * Answers with the brand of the current Spatie Multitenancy tenant.
 *
 * The current tenant is read out of the container under the key Spatie's own
 * config names, so this class refers to no Spatie type at all. Without
 * `spatie/laravel-multitenancy` installed there is no such config key, the
 * resolver answers with nothing, and the chain carries on.
 *
 * A tenant that does not implement {@see ProvidesBrand}, or that returns null
 * from it, is treated the same way: no answer, next resolver.
 */
final class TenantResolver implements BrandResolver
{
    public function __construct(
        private readonly Container $container,
        private readonly Config $config,
    ) {}

    public function resolve(): ?Brand
    {
        $tenant = $this->currentTenant();

        return $tenant?->brand();
    }

    private function currentTenant(): ?ProvidesBrand
    {
        $key = $this->config->get('multitenancy.current_tenant_container_key');

        if (! is_string($key) || $key === '' || ! $this->container->bound($key)) {
            return null;
        }

        $tenant = $this->container->make($key);

        return $tenant instanceof ProvidesBrand ? $tenant : null;
    }
}
