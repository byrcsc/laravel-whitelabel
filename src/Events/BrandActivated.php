<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel\Events;

use Byrcsc\Whitelabel\Brand;

/**
 * A brand became the active one, by resolution or by explicit activation.
 */
final class BrandActivated
{
    public function __construct(public readonly Brand $brand) {}
}
