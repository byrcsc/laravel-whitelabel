<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel\Tests\Fixtures;

use Byrcsc\Whitelabel\Brand;
use Byrcsc\Whitelabel\Contracts\BrandResolver;

/**
 * A resolver that answers with whatever the test put in it, and counts how
 * often the chain asked.
 */
final class FixedBrandResolver implements BrandResolver
{
    public static ?Brand $brand = null;

    public static int $calls = 0;

    public function resolve(): ?Brand
    {
        self::$calls++;

        return self::$brand;
    }
}
