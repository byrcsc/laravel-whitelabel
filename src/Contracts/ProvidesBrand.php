<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel\Contracts;

use Byrcsc\Whitelabel\Brand;

/**
 * Implemented by a Spatie Multitenancy tenant that has a brand.
 *
 * The tenant decides where its brand comes from. It can build one from its own
 * columns, hydrate one from a JSON column, or look one up in the package's own
 * table by a `brand_id`. The package never touches Spatie's schema and never
 * assumes which of those you chose.
 *
 * Returning null means "no brand of my own", and resolution carries on down
 * the chain to the request domain and the configured default.
 */
interface ProvidesBrand
{
    public function brand(): ?Brand;
}
