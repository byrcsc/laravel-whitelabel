<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel\Events;

use Byrcsc\Whitelabel\Brand;

/**
 * A brand was created through a writable brand repository.
 */
final class BrandCreated
{
    public function __construct(public readonly Brand $brand) {}
}
