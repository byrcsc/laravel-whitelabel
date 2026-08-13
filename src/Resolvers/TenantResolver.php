<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel\Resolvers;

use Byrcsc\Whitelabel\Brand;
use Byrcsc\Whitelabel\Contracts\BrandResolver;

/**
 * Answers with the brand of the current Spatie Multitenancy tenant.
 *
 * Safe to leave in the chain when `spatie/laravel-multitenancy` is not
 * installed: it answers with nothing rather than referring to a class that is
 * not there.
 */
final class TenantResolver implements BrandResolver
{
    public function resolve(): ?Brand
    {
        return null;
    }
}
