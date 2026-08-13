<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel\Events;

use Byrcsc\Whitelabel\Brand;

/**
 * A brand stopped being the active one, by being replaced or forgotten.
 */
final class BrandDeactivated
{
    public function __construct(public readonly Brand $brand) {}
}
