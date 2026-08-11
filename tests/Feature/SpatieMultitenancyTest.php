<?php

declare(strict_types=1);

use Byrcsc\Whitelabel\Contracts\BrandRepository;
use Byrcsc\Whitelabel\Events\BrandActivated;
use Byrcsc\Whitelabel\Events\BrandDeactivated;
use Byrcsc\Whitelabel\Resolvers\TenantResolver;
use Byrcsc\Whitelabel\Spatie\SwitchTenantBrandTask;
use Byrcsc\Whitelabel\Tests\Fixtures\BrandedJob;
use Byrcsc\Whitelabel\Tests\Fixtures\BrandIdTenant;
use Byrcsc\Whitelabel\Tests\Fixtures\ColumnTenant;
use Byrcsc\Whitelabel\Tests\Fixtures\JsonTenant;
use Byrcsc\Whitelabel\Tests\Fixtures\PlainTenant;
use Byrcsc\Whitelabel\Tests\Fixtures\RecordActiveBrand;
use Byrcsc\Whitelabel\Whitelabel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\Models\Tenant;

uses()->group('multitenancy');

beforeEach(function (): void {
    config()->set('whitelabel.default', 'default');
    config()->set('whitelabel.brands', [
        'default' => ['name' => 'Default', 'colors' => ['primary' => '#000000']],
        'stored' => ['name' => 'Stored'],
    ]);
    config()->set('multitenancy.switch_tenant_tasks', [SwitchTenantBrandTask::class]);

    Schema::create('tenants', function (Blueprint $table): void {
        $table->id();
        $table->string('slug');
        $table->string('brand_name')->nullable();
        $table->string('brand_color')->nullable();
        $table->string('brand_id')->nullable();
        $table->json('brand')->nullable();
    });
});

function switching(): SwitchTenantBrandTask
{
    return app(SwitchTenantBrandTask::class);
}

function tenantWhitelabel(): Whitelabel
{
    return app(Whitelabel::class);
}

describe('the switch task', function (): void {
    it('activates the brand of a tenant that provides one', function (): void {
        $tenant = ColumnTenant::create(['slug' => 'acme', 'brand_name' => 'Acme']);

        $tenant->makeCurrent();

        expect(tenantWhitelabel()->current()?->name())->toBe('Acme');
    });

    it('deactivates the brand when the tenant is forgotten', function (): void {
        ColumnTenant::create(['slug' => 'acme', 'brand_name' => 'Acme'])->makeCurrent();

        Tenant::forgetCurrent();

        expect(tenantWhitelabel()->current()?->id())->toBe('default');
    });

    it('ignores a tenant that does not provide a brand', function (): void {
        PlainTenant::create(['slug' => 'plain'])->makeCurrent();

        expect(tenantWhitelabel()->current()?->id())->toBe('default');
    });

    it('leaves resolution alone when a providing tenant has no brand yet', function (): void {
        ColumnTenant::create(['slug' => 'acme'])->makeCurrent();

        expect(tenantWhitelabel()->current()?->id())->toBe('default');
    });

    it('is idempotent, the way a re-run switch task in a worker must be', function (): void {
        $tenant = ColumnTenant::create(['slug' => 'acme', 'brand_name' => 'Acme']);

        switching()->makeCurrent($tenant);

        Event::fake([BrandActivated::class, BrandDeactivated::class]);

        switching()->makeCurrent($tenant);
        switching()->makeCurrent($tenant);

        Event::assertNothingDispatched();

        expect(tenantWhitelabel()->current()?->name())->toBe('Acme');
    });

    it('swaps the brand when a second tenant becomes current', function (): void {
        ColumnTenant::create(['slug' => 'acme', 'brand_name' => 'Acme'])->makeCurrent();
        ColumnTenant::create(['slug' => 'globex', 'brand_name' => 'Globex'])->makeCurrent();

        expect(tenantWhitelabel()->current()?->name())->toBe('Globex');
    });
});

describe('the tenant resolver', function (): void {
    it('answers with the current tenant brand without the switch task', function (): void {
        config()->set('multitenancy.switch_tenant_tasks', []);

        ColumnTenant::create(['slug' => 'acme', 'brand_name' => 'Acme'])->makeCurrent();

        expect(app(TenantResolver::class)->resolve()?->name())->toBe('Acme')
            ->and(tenantWhitelabel()->current()?->name())->toBe('Acme');
    });

    it('answers with nothing when no tenant is current', function (): void {
        expect(app(TenantResolver::class)->resolve())->toBeNull();
    });

    it('loses to an explicit activation, matching the chain order', function (): void {
        ColumnTenant::create(['slug' => 'acme', 'brand_name' => 'Acme'])->makeCurrent();

        tenantWhitelabel()->activate('stored');

        expect(tenantWhitelabel()->current()?->name())->toBe('Stored');
    });
});

describe('the brand source recipes', function (): void {
    it('builds a brand from the tenant own columns', function (): void {
        ColumnTenant::create([
            'slug' => 'acme',
            'brand_name' => 'Acme',
            'brand_color' => '#7c3aed',
        ])->makeCurrent();

        expect(tenantWhitelabel()->current()?->name())->toBe('Acme')
            ->and(tenantWhitelabel()->current()?->color('primary'))->toBe('#7c3aed');
    });

    it('hydrates a brand from a JSON column', function (): void {
        JsonTenant::create([
            'slug' => 'globex',
            'brand' => ['name' => 'Globex', 'colors' => ['primary' => '#0ea5e9']],
        ])->makeCurrent();

        expect(tenantWhitelabel()->current()?->name())->toBe('Globex')
            ->and(tenantWhitelabel()->current()?->color('primary'))->toBe('#0ea5e9');
    });

    it('looks a brand up by identifier through the repository', function (): void {
        BrandIdTenant::create(['slug' => 'initech', 'brand_id' => 'stored'])->makeCurrent();

        expect(tenantWhitelabel()->current()?->name())->toBe('Stored')
            ->and(tenantWhitelabel()->current()?->color('primary'))->toBe('#000000');
    });

    it('carries on down the chain when a brand_id names no brand', function (): void {
        BrandIdTenant::create(['slug' => 'initech', 'brand_id' => 'missing'])->makeCurrent();

        expect(tenantWhitelabel()->current()?->id())->toBe('default');
    });
});

it('runs a tenant-aware queued job with the tenant brand', function (): void {
    // Spatie rehydrates the tenant in the worker through the configured tenant
    // model, so that model is the one that has to implement ProvidesBrand.
    config()->set('multitenancy.tenant_model', ColumnTenant::class);
    config()->set('multitenancy.queues_are_tenant_aware_by_default', true);
    config()->set('queue.default', 'database');
    app()->bind(IsTenant::class, ColumnTenant::class);

    ColumnTenant::create(['slug' => 'acme', 'brand_name' => 'Acme'])->makeCurrent();

    RecordActiveBrand::$seen = null;
    dispatch(new RecordActiveBrand);

    Tenant::forgetCurrent();
    tenantWhitelabel()->flush();

    expect(Artisan::call('queue:work', ['--once' => true, '--stop-when-empty' => true]))->toBe(0)
        ->and(RecordActiveBrand::$seen)->toBe('Acme');
});

it('lets the brand captured by BrandAware beat the tenant, activating once', function (): void {
    config()->set('multitenancy.tenant_model', ColumnTenant::class);
    config()->set('multitenancy.queues_are_tenant_aware_by_default', true);
    config()->set('queue.default', 'database');
    app()->bind(IsTenant::class, ColumnTenant::class);

    ColumnTenant::create(['slug' => 'acme', 'brand_name' => 'Acme'])->makeCurrent();

    // The job is dispatched with a different brand explicitly active.
    tenantWhitelabel()->activate('stored');

    BrandedJob::$seen = null;
    dispatch(new BrandedJob);

    Tenant::forgetCurrent();
    tenantWhitelabel()->flush();

    Event::fake([BrandActivated::class]);

    expect(Artisan::call('queue:work', ['--once' => true, '--stop-when-empty' => true]))->toBe(0)
        ->and(BrandedJob::$seen)->toBe('stored');

    Event::assertDispatchedTimes(BrandActivated::class, 1);
});

it('keeps an explicit activation when a tenant is forgotten', function (): void {
    $tenant = ColumnTenant::create(['slug' => 'acme', 'brand_name' => 'Acme']);
    $tenant->makeCurrent();

    tenantWhitelabel()->activate('stored');

    Tenant::forgetCurrent();

    expect(tenantWhitelabel()->current()?->name())->toBe('Stored');
});

it('takes back only the brand it activated itself', function (): void {
    ColumnTenant::create(['slug' => 'acme', 'brand_name' => 'Acme'])->makeCurrent();

    expect(tenantWhitelabel()->current()?->name())->toBe('Acme');

    Tenant::forgetCurrent();

    expect(tenantWhitelabel()->current()?->id())->toBe('default');
});

it('gives a hand-built tenant brand the default brand to fall back to', function (): void {
    // The recipes return `new Brand(...)` with no fallback of their own.
    ColumnTenant::create(['slug' => 'acme', 'brand_name' => 'Acme'])->makeCurrent();

    expect(tenantWhitelabel()->current()?->name())->toBe('Acme')
        ->and(tenantWhitelabel()->current()?->color('primary'))->toBe('#000000');
});

it('resolves both integration classes from the container', function (): void {
    expect(app(BrandRepository::class))->not->toBeNull()
        ->and(app(TenantResolver::class))->toBeInstanceOf(TenantResolver::class)
        ->and(app(SwitchTenantBrandTask::class))->toBeInstanceOf(SwitchTenantBrandTask::class);
});
