<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel\Tests\Fixtures;

use Byrcsc\Whitelabel\Brand;
use Byrcsc\Whitelabel\Contracts\BrandRepository;
use Byrcsc\Whitelabel\Contracts\ProvidesBrand;

/**
 * Recipe three: a foreign key into the package's own brands table.
 */
class BrandIdTenant extends TenantFixture implements ProvidesBrand
{
    public function brand(): ?Brand
    {
        $id = $this->getAttribute('brand_id');

        return is_string($id) && $id !== '' ? app(BrandRepository::class)->find($id) : null;
    }
}
