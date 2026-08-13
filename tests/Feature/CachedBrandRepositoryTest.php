<?php

declare(strict_types=1);

use Byrcsc\Whitelabel\Contracts\BrandRepository;
use Byrcsc\Whitelabel\Drivers\CachedBrandRepository;
use Byrcsc\Whitelabel\Drivers\ConfigBrandRepository;
use Byrcsc\Whitelabel\Drivers\DatabaseBrandRepository;
use Byrcsc\Whitelabel\Models\BrandRecord;
use Illuminate\Cache\CacheManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    config()->set('whitelabel.driver', 'database');
    config()->set('whitelabel.default', 'default');
});

function cached(): BrandRepository
{
    return app(BrandRepository::class);
}

/**
 * How many queries the work runs against the brands table.
 *
 * Only that table: the application's own cache store may itself be the
 * database, and those reads are not what this is measuring.
 *
 * @param  callable(): mixed  $work
 */
function brandQueriesFor(callable $work): int
{
    $table = (new BrandRecord)->getTable();

    DB::connection()->flushQueryLog();
    DB::connection()->enableQueryLog();

    $work();

    $queries = DB::connection()->getQueryLog();

    DB::connection()->disableQueryLog();

    return count(array_filter(
        $queries,
        static fn (array $query): bool => str_contains((string) $query['query'], '"'.$table.'"')
            || str_contains((string) $query['query'], '`'.$table.'`'),
    ));
}

it('wraps the database driver but not the config driver', function (): void {
    expect(cached())->toBeInstanceOf(CachedBrandRepository::class);

    config()->set('whitelabel.driver', 'config');
    app()->forgetInstance(Byrcsc\Whitelabel\BrandRepositoryManager::class);

    expect(app(BrandRepository::class))->toBeInstanceOf(ConfigBrandRepository::class);
});

it('leaves the driver untouched when caching is switched off', function (): void {
    config()->set('whitelabel.cache.enabled', false);

    expect(cached())->toBeInstanceOf(DatabaseBrandRepository::class);
});

it('reads a brand from the database once, however often it is found', function (): void {
    cached()->create('default', ['name' => 'Default']);
    cached()->create('acme', ['name' => 'Acme', 'domain' => 'app.acme.com']);

    $repository = cached();

    // Two reads on the cold path: the brand itself, and the default brand
    // the driver loads to build its fallback. Both land in the cache, so
    // everything after that is free.
    expect(brandQueriesFor(function () use ($repository): void {
        $repository->find('acme');
        $repository->find('acme');
        $repository->find('acme');
        $repository->find('default');
    }))->toBe(2);
});

it('reads a domain from the database once, however often it is looked up', function (): void {
    cached()->create('default', ['name' => 'Default']);
    cached()->create('acme', ['name' => 'Acme', 'domain' => 'app.acme.com']);

    $repository = cached();

    // One indexed lookup by domain, plus the default brand behind the
    // fallback. No listing of every brand to build an index.
    expect(brandQueriesFor(function () use ($repository): void {
        $repository->findByDomain('app.acme.com');
        $repository->findByDomain('app.acme.com');
        $repository->findByDomain('APP.ACME.COM');
    }))->toBe(2);
});

it('answers repeated existence checks from the cache', function (): void {
    cached()->create('default', ['name' => 'Default']);

    $repository = cached();

    expect(brandQueriesFor(function () use ($repository): void {
        $repository->has('default');
        $repository->has('default');
        $repository->has('default');
    }))->toBe(1);
});

it('does not list every brand to answer a domain lookup', function (): void {
    cached()->create('default', ['name' => 'Default']);

    foreach (['acme', 'globex', 'initech'] as $id) {
        cached()->create($id, ['name' => ucfirst($id), 'domain' => "app.{$id}.test"]);
    }

    $repository = cached();
    $repository->findByDomain('app.globex.test');

    expect(Cache::get('whitelabel:cached'))->toBe(['globex', 'default']);
});

it('keeps other brands cached when the default brand is written', function (): void {
    cached()->create('default', ['colors' => ['primary' => '#000000']]);
    cached()->create('acme', ['name' => 'Acme']);
    cached()->find('acme');

    cached()->update('default', ['colors' => ['primary' => '#ffffff']]);

    // Definitions are cached, not merged brands, so only the default's own
    // entry needed busting.
    expect(Cache::get('whitelabel:brand:acme'))->toBe(['name' => 'Acme'])
        ->and(Cache::get('whitelabel:brand:default'))->toBeNull()
        ->and(cached()->find('acme')?->color('primary'))->toBe('#ffffff');
});

it('does not cache a brand that is not there', function (): void {
    cached()->create('default', ['name' => 'Default']);

    cached()->find('default');
    cached()->find('ghost');

    expect(Cache::get('whitelabel:brand:ghost'))->toBeNull()
        ->and(Cache::get('whitelabel:cached'))->toBe(['default']);
});

it('serves fresh data on the first read after an update', function (): void {
    cached()->create('default', ['name' => 'Default']);
    cached()->create('acme', ['name' => 'Acme']);
    cached()->find('acme');

    cached()->update('acme', ['name' => 'Acme Inc']);

    expect(cached()->find('acme')?->name())->toBe('Acme Inc');
});

it('serves fresh data on the first read after the default brand changes', function (): void {
    cached()->create('default', ['colors' => ['primary' => '#000000']]);
    cached()->create('acme', ['name' => 'Acme']);

    expect(cached()->find('acme')?->color('primary'))->toBe('#000000');

    cached()->update('default', ['colors' => ['primary' => '#ffffff']]);

    expect(cached()->find('acme')?->color('primary'))->toBe('#ffffff');
});

it('serves nothing on the first read after a delete', function (): void {
    cached()->create('default', ['name' => 'Default']);
    cached()->create('acme', ['name' => 'Acme']);
    cached()->find('acme');

    cached()->delete('acme');

    expect(cached()->find('acme'))->toBeNull();
});

it('follows a domain moving from one brand to another', function (): void {
    cached()->create('default', ['name' => 'Default']);
    cached()->create('acme', ['name' => 'Acme', 'domain' => 'app.acme.com']);
    cached()->create('globex', ['name' => 'Globex']);

    expect(cached()->findByDomain('app.acme.com')?->id())->toBe('acme');

    cached()->update('acme', ['name' => 'Acme']);
    cached()->update('globex', ['name' => 'Globex', 'domain' => 'app.acme.com']);

    expect(cached()->findByDomain('app.acme.com')?->id())->toBe('globex');
});

it('empties every package key and nothing else', function (): void {
    Cache::forever('unrelated', 'keep me');

    cached()->create('default', ['name' => 'Default']);
    cached()->create('acme', ['name' => 'Acme', 'domain' => 'app.acme.com']);
    cached()->findByDomain('app.acme.com');

    expect(Cache::get('whitelabel:brand:acme'))->toBeArray()
        ->and(Cache::get('whitelabel:domains'))->toBeArray();

    expect(Artisan::call('whitelabel:clear'))->toBe(Command::SUCCESS);

    expect(Cache::get('whitelabel:brand:acme'))->toBeNull()
        ->and(Cache::get('whitelabel:brand:default'))->toBeNull()
        ->and(Cache::get('whitelabel:domains'))->toBeNull()
        ->and(Cache::get('whitelabel:cached'))->toBeNull()
        ->and(Cache::get('unrelated'))->toBe('keep me');
});

it('honours a configured key prefix', function (): void {
    config()->set('whitelabel.cache.prefix', 'tenant-brands');

    cached()->create('default', ['name' => 'Default']);
    cached()->find('default');

    expect(Cache::get('tenant-brands:brand:default'))->toBeArray()
        ->and(Cache::get('whitelabel:brand:default'))->toBeNull();
});

it('follows a Spatie-style cache prefix switch', function (): void {
    config()->set('cache.default', 'database');

    $switchTenant = function (string $prefix): void {
        // What PrefixCacheTask does when a tenant becomes current: it rewrites
        // the prefix and forgets the driver on the same manager.
        config()->set('cache.prefix', $prefix);

        app(CacheManager::class)->forgetDriver(config()->string('cache.default'));
    };

    // The repository is resolved before the switch and kept across it, the way
    // a long-lived worker process would hold it.
    $repository = cached();

    $switchTenant('tenant_a');
    $repository->create('default', ['name' => 'Default']);
    $repository->create('acme', ['name' => 'Tenant A']);

    expect($repository->find('acme')?->name())->toBe('Tenant A');

    $switchTenant('tenant_b');

    expect(Cache::get('whitelabel:brand:acme'))->toBeNull();

    $repository->update('acme', ['name' => 'Tenant B']);

    expect($repository->find('acme')?->name())->toBe('Tenant B');

    $switchTenant('tenant_a');

    expect(Cache::get('whitelabel:brand:acme'))->toBe(['name' => 'Tenant A']);
});

it('says nothing was cleared when the driver does not cache', function (): void {
    config()->set('whitelabel.driver', 'config');
    config()->set('whitelabel.brands', ['default' => ['name' => 'Default']]);

    expect(Artisan::call('whitelabel:clear'))->toBe(Command::SUCCESS)
        ->and(Artisan::output())->toContain('does not cache anything');
});

it('reaches the driver underneath', function (): void {
    $repository = cached();

    expect($repository)->toBeInstanceOf(CachedBrandRepository::class)
        ->and(innerRepository($repository))->toBeInstanceOf(DatabaseBrandRepository::class);
});
