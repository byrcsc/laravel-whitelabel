<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Byrcsc\Whitelabel\Brand;
use Byrcsc\Whitelabel\Contracts\ProvidesBrand;
use Spatie\Multitenancy\Models\Tenant as BaseTenant;

/**
 * A tenant that carries its brand in one JSON column.
 *
 * One of the three recipes in the README. The other two — reading the tenant's
 * own columns, and a `brand_id` into the package's table — differ only in what
 * `brand()` reads from.
 *
 * @property string $name
 * @property string $slug
 * @property array<string, mixed>|null $brand
 */
class Tenant extends BaseTenant implements ProvidesBrand
{
    // Named apart from Spatie's default so the package's own Spatie tests,
    // which use `tenants`, and the demo can coexist in one test run.
    protected $table = 'workbench_tenants';

    protected $guarded = [];

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['brand' => 'array'];
    }

    public function brand(): ?Brand
    {
        return $this->brand === null ? null : new Brand($this->slug, $this->brand);
    }
}
