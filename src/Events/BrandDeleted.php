<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel\Events;

use Byrcsc\Whitelabel\Brand;

/**
 * A brand was deleted through a writable brand repository.
 *
 * The brand carried here is the one that was removed, as it was last stored.
 */
final class BrandDeleted
{
    public function __construct(public readonly Brand $brand) {}
}
