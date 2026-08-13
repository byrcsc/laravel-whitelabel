<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel\Resolvers;

use Byrcsc\Whitelabel\Brand;
use Byrcsc\Whitelabel\Contracts\BrandResolver;
use Byrcsc\Whitelabel\Whitelabel;

/**
 * Answers with the brand passed to `Whitelabel::activate()`.
 *
 * First in the chain, so an explicit activation beats the tenant, the request
 * domain, and the configured default.
 */
final class OverrideResolver implements BrandResolver
{
    public function __construct(private readonly Whitelabel $whitelabel) {}

    public function resolve(): ?Brand
    {
        return $this->whitelabel->overridden();
    }
}
