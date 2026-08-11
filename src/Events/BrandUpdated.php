<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel\Events;

use Byrcsc\Whitelabel\Brand;

/**
 * A brand's stored definition was replaced through a writable repository.
 */
final class BrandUpdated
{
    public function __construct(public readonly Brand $brand) {}
}
