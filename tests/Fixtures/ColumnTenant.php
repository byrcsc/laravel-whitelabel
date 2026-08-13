<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel\Tests\Fixtures;

use Byrcsc\Whitelabel\Brand;
use Byrcsc\Whitelabel\Contracts\ProvidesBrand;

/**
 * Recipe one: the brand is built from the tenant's own columns.
 */
class ColumnTenant extends TenantFixture implements ProvidesBrand
{
    public function brand(): ?Brand
    {
        $name = $this->getAttribute('brand_name');

        if (! is_string($name) || $name === '') {
            return null;
        }

        $color = $this->getAttribute('brand_color');

        return new Brand($this->slug, [
            'name' => $name,
            'colors' => is_string($color) && $color !== '' ? ['primary' => $color] : [],
        ]);
    }
}
