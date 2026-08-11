<?php

declare(strict_types=1);

use Byrcsc\Whitelabel\BrandAsset;
use Byrcsc\Whitelabel\Contracts\BrandRepository;
use Byrcsc\Whitelabel\Drivers\DatabaseBrandRepository;
use Byrcsc\Whitelabel\Events\BrandCreated;
use Byrcsc\Whitelabel\Events\BrandDeleted;
use Byrcsc\Whitelabel\Events\BrandUpdated;
use Byrcsc\Whitelabel\Exceptions\BrandAlreadyExists;
use Byrcsc\Whitelabel\Exceptions\InvalidBrandDefinition;
use Byrcsc\Whitelabel\Exceptions\UnknownBrand;
use Byrcsc\Whitelabel\Models\BrandRecord;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    config()->set('whitelabel.driver', 'database');
    config()->set('whitelabel.default', 'default');
});

function stored(): BrandRepository
{
    return app(BrandRepository::class);
}

$acme = [
    'name' => 'Acme',
    'domain' => 'app.acme.com',
    'logo' => ['disk' => 'public', 'path' => 'brands/acme/logo.svg'],
    'favicon' => 'https://cdn.acme.com/favicon.ico',
    'colors' => ['primary' => '#7c3aed', 'secondary' => '#0ea5e9'],
    'mail' => ['from_name' => 'Acme', 'from_address' => 'hello@acme.com'],
    'settings' => ['support_url' => 'https://support.acme.com', 'seats' => 10],
];

it('resolves the database driver from config', function (): void {
    expect(app(BrandRepository::class))->toBeInstanceOf(DatabaseBrandRepository::class);
});

it('round-trips a brand through find', function () use ($acme): void {
    stored()->create('acme', $acme);

    $brand = stored()->find('acme');

    expect($brand?->name())->toBe('Acme')
        ->and($brand?->domain())->toBe('app.acme.com')
        ->and($brand?->logo())->toEqual(new BrandAsset('brands/acme/logo.svg', 'public'))
        ->and($brand?->favicon())->toEqual(new BrandAsset('https://cdn.acme.com/favicon.ico'))
        ->and($brand?->colors())->toBe(['primary' => '#7c3aed', 'secondary' => '#0ea5e9'])
        ->and($brand?->mailFromName())->toBe('Acme')
        ->and($brand?->mailFromAddress())->toBe('hello@acme.com')
        ->and($brand?->setting('support_url'))->toBe('https://support.acme.com')
        ->and($brand?->setting('seats'))->toBe(10);
});

it('round-trips a brand through findByDomain', function () use ($acme): void {
    stored()->create('acme', $acme);

    expect(stored()->findByDomain('APP.ACME.COM')?->toArray())
        ->toBe(stored()->find('acme')?->toArray());
});

it('keeps the difference between an absent key and a cleared one', function (): void {
    stored()->create('default', ['name' => 'Default', 'colors' => ['primary' => '#000000']]);
    stored()->create('bare', []);
    stored()->create('cleared', ['name' => '']);

    expect(stored()->find('bare')?->name())->toBe('Default')
        ->and(stored()->find('cleared')?->name())->toBe('')
        ->and(stored()->find('cleared')?->color('primary'))->toBe('#000000');
});

it('wires the default brand in as the per-key fallback', function (): void {
    stored()->create('default', ['colors' => ['primary' => '#000000', 'secondary' => '#111111']]);
    stored()->create('acme', ['colors' => ['primary' => '#7c3aed']]);

    expect(stored()->find('acme')?->colors())->toBe(['primary' => '#7c3aed', 'secondary' => '#111111']);
});

it('lists brands and answers existence checks', function (): void {
    stored()->create('acme', ['name' => 'Acme']);
    stored()->create('default', ['name' => 'Default']);

    expect(array_keys(stored()->all()))->toBe(['acme', 'default'])
        ->and(stored()->has('acme'))->toBeTrue()
        ->and(stored()->has('nope'))->toBeFalse()
        ->and(stored()->find('nope'))->toBeNull()
        ->and(stored()->findByDomain('nope.test'))->toBeNull();
});

it('replaces the stored definition on update', function () use ($acme): void {
    stored()->create('acme', $acme);

    $updated = stored()->update('acme', ['name' => 'Acme Inc']);

    expect($updated->name())->toBe('Acme Inc')
        ->and($updated->domain())->toBeNull()
        ->and($updated->colors())->toBe([])
        ->and(stored()->find('acme')?->name())->toBe('Acme Inc');
});

it('deletes a brand and reports whether one was there', function (): void {
    stored()->create('acme', ['name' => 'Acme']);

    expect(stored()->delete('acme'))->toBeTrue()
        ->and(stored()->delete('acme'))->toBeFalse()
        ->and(stored()->find('acme'))->toBeNull();
});

it('refuses to update a brand that does not exist', function (): void {
    expect(fn () => stored()->update('nope', ['name' => 'Nope']))
        ->toThrow(UnknownBrand::class, 'There is no brand with the identifier [nope].');
});

it('surfaces a duplicate identifier as a domain-level exception', function (): void {
    stored()->create('acme', ['name' => 'Acme']);

    expect(fn () => stored()->create('acme', ['name' => 'Acme Again']))
        ->toThrow(
            BrandAlreadyExists::class,
            'A brand with the identifier [acme] already exists. Update it instead of creating it.',
        );
});

it('surfaces a duplicate domain as a domain-level exception', function (): void {
    stored()->create('acme', ['domain' => 'app.acme.com']);

    expect(fn () => stored()->create('other', ['domain' => 'app.acme.com']))
        ->toThrow(
            BrandAlreadyExists::class,
            'The domain [app.acme.com] already belongs to another brand, so it cannot be given to [other].',
        );
});

it('surfaces a duplicate domain on update too', function (): void {
    stored()->create('acme', ['domain' => 'app.acme.com']);
    stored()->create('other', ['domain' => 'app.other.com']);

    expect(fn () => stored()->update('other', ['domain' => 'app.acme.com']))
        ->toThrow(BrandAlreadyExists::class, 'The domain [app.acme.com] already belongs to another brand');
});

it('validates definitions exactly like the config driver, null included', function (): void {
    expect(fn () => stored()->create('acme', ['name' => null]))
        ->toThrow(InvalidBrandDefinition::class, 'Brand [acme] sets [name] to null')
        ->and(fn () => stored()->create('acme', ['color' => []]))
        ->toThrow(InvalidBrandDefinition::class, 'unknown key [color]')
        ->and(BrandRecord::query()->count())->toBe(0);
});

it('fires the three repository events with the affected brand', function (): void {
    Event::fake([BrandCreated::class, BrandUpdated::class, BrandDeleted::class]);

    stored()->create('acme', ['name' => 'Acme']);
    stored()->update('acme', ['name' => 'Acme Inc']);
    stored()->delete('acme');

    Event::assertDispatched(
        BrandCreated::class,
        fn (BrandCreated $event): bool => $event->brand->id() === 'acme' && $event->brand->name() === 'Acme',
    );

    Event::assertDispatched(
        BrandUpdated::class,
        fn (BrandUpdated $event): bool => $event->brand->name() === 'Acme Inc',
    );

    Event::assertDispatched(
        BrandDeleted::class,
        fn (BrandDeleted $event): bool => $event->brand->name() === 'Acme Inc',
    );
});

it('fires nothing when a delete finds no brand', function (): void {
    Event::fake([BrandDeleted::class]);

    stored()->delete('nope');

    Event::assertNotDispatched(BrandDeleted::class);
});

it('hydrates brands made with the shipped factory', function (): void {
    BrandRecord::factory()->identifiedBy('acme')->create(['name' => 'Acme']);

    expect(stored()->find('acme')?->name())->toBe('Acme')
        ->and(stored()->findByDomain('acme.test')?->id())->toBe('acme');
});

it('lets the factory build a brand that defines nothing', function (): void {
    BrandRecord::factory()->identifiedBy('default')->bare()->create();
    BrandRecord::factory()->identifiedBy('acme')->bare()->create(['name' => 'Acme']);

    expect(stored()->find('acme')?->name())->toBe('Acme')
        ->and(stored()->find('acme')?->colors())->toBe([]);
});

it('lets more than one brand clear its domain', function (): void {
    stored()->create('acme', ['domain' => '']);
    stored()->create('other', ['domain' => '']);

    expect(stored()->find('acme')?->domain())->toBeNull()
        ->and(stored()->find('other')?->domain())->toBeNull();
});

it('keeps a bare path replacing an inherited asset rather than merging into it', function (): void {
    stored()->create('default', ['logo' => ['disk' => 'public', 'path' => 'default.svg']]);
    stored()->create('acme', ['logo' => 'https://cdn.acme.com/logo.svg']);

    expect(stored()->find('acme')?->logo())
        ->toEqual(new BrandAsset('https://cdn.acme.com/logo.svg'));
});

it('lets a brand override only the disk of an inherited asset', function (): void {
    stored()->create('default', ['logo' => ['disk' => 'public', 'path' => 'logo.svg']]);
    stored()->create('acme', ['logo' => ['disk' => 's3']]);

    expect(stored()->find('acme')?->logo())->toEqual(new BrandAsset('logo.svg', 's3'));
});

it('does not give the default brand a stale copy of itself after an update', function (): void {
    stored()->create('default', ['name' => 'Default', 'colors' => ['primary' => '#000000']]);

    $updated = stored()->update('default', ['name' => 'Renamed']);

    expect($updated->name())->toBe('Renamed')
        ->and($updated->colors())->toBe([])
        ->and(stored()->find('default')?->colors())->toBe([]);
});

it('serves brands before the default brand row exists', function (): void {
    $acme = stored()->create('acme', ['name' => 'Acme']);

    expect($acme->name())->toBe('Acme')
        ->and(stored()->find('acme')?->name())->toBe('Acme')
        ->and(array_keys(stored()->all()))->toBe(['acme']);
});

it('honours a custom table name', function (): void {
    config()->set('whitelabel.database.table', 'whitelabel_brands');

    expect((new BrandRecord)->getTable())->toBe('whitelabel_brands');
});
