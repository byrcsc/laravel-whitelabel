<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel\Tests\Fixtures;

use Byrcsc\Whitelabel\Brand;
use Byrcsc\Whitelabel\Contracts\ProvidesBrand;

/**
 * Recipe two: the whole definition lives in one JSON column.
 */
class JsonTenant extends TenantFixture implements ProvidesBrand
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['brand' => 'array'];
    }

    public function brand(): ?Brand
    {
        $definition = $this->brand;

        return $definition === null ? null : new Brand($this->slug, $definition);
    }
}
