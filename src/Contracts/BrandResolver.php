<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel\Contracts;

use Byrcsc\Whitelabel\Brand;

/**
 * One link in the chain that decides which brand is active.
 *
 * The chain is the `whitelabel.resolvers` config array, in order. The first
 * resolver to return a brand wins; a resolver that cannot answer returns null
 * and never throws for want of context it does not have.
 */
interface BrandResolver
{
    public function resolve(): ?Brand;
}
