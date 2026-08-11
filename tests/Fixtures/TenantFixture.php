<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel\Tests\Fixtures;

use Spatie\Multitenancy\Models\Tenant;

/**
 * The base for the tenant fixtures behind the documented brand recipes.
 *
 * This file references Spatie types, so it is only ever autoloaded by tests in
 * the `multitenancy` group, which the no-Spatie CI job excludes.
 *
 * @property string $slug
 * @property string|null $brand_name
 * @property string|null $brand_color
 * @property string|null $brand_id
 * @property array<string, mixed>|null $brand
 */
abstract class TenantFixture extends Tenant
{
    protected $table = 'tenants';

    protected $guarded = [];

    public $timestamps = false;
}
