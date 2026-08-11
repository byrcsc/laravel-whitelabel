<?php

declare(strict_types=1);

use Byrcsc\Whitelabel\Brand;
use Byrcsc\Whitelabel\BrandAsset;
use Byrcsc\Whitelabel\Contracts\BrandRepository;
use Byrcsc\Whitelabel\Exceptions\InvalidBrandDefinition;
use Byrcsc\Whitelabel\Exceptions\UnknownBrand;
use Byrcsc\Whitelabel\Exceptions\UnsupportedBrandOperation;

beforeEach(function (): void {
    config()->set('whitelabel.default', 'default');
    config()->set('whitelabel.brands', [
        'default' => [
            'name' => 'Default',
            'colors' => ['primary' => '#000000', 'secondary' => '#111111'],
            'logo' => ['disk' => 'public', 'path' => 'logo.svg'],
        ],
        'acme' => [
            'name' => 'Acme',
            'domain' => 'app.acme.com',
            'colors' => ['primary' => '#7c3aed'],
        ],
    ]);
});

function brands(): BrandRepository
{
    return app(BrandRepository::class);
}

it('hydrates a config brand into a Brand with every accessor working', function (): void {
    $brand = brands()->find('acme');

    expect($brand)->toBeInstanceOf(Brand::class)
        ->and($brand?->name())->toBe('Acme')
        ->and($brand?->domain())->toBe('app.acme.com')
        ->and($brand?->color('primary'))->toBe('#7c3aed');
});

it('wires the default brand in as the per-key fallback', function (): void {
    $brand = brands()->find('acme');

    expect($brand?->color('secondary'))->toBe('#111111')
        ->and($brand?->logo())->toEqual(new BrandAsset('logo.svg', 'public'));
});

it('does not give the default brand a fallback of its own', function (): void {
    $default = brands()->find('default');

    expect($default)->not->toBeNull()
        // Nothing is inherited, so the effective view is the definition plus id.
        ->and($default?->toArray())->toBe(['id' => 'default'] + ($default?->definition() ?? []));
});

it('returns null for an unknown brand', function (): void {
    expect(brands()->find('nope'))->toBeNull()
        ->and(brands()->has('nope'))->toBeFalse()
        ->and(brands()->has('acme'))->toBeTrue();
});

it('lists every brand keyed by identifier', function (): void {
    expect(array_keys(brands()->all()))->toBe(['default', 'acme']);
});

it('finds a brand by domain, case insensitively', function (): void {
    expect(brands()->findByDomain('APP.ACME.COM')?->id())->toBe('acme')
        ->and(brands()->findByDomain('unknown.test'))->toBeNull();
});

it('does not match a brand that only inherits the default domain', function (): void {
    config()->set('whitelabel.brands.default.domain', 'default.test');

    expect(brands()->findByDomain('default.test')?->id())->toBe('default');
});

it('falls back to the application name for the default brand', function (): void {
    config()->set('app.name', 'Workbench');
    config()->set('whitelabel.brands', ['default' => [], 'acme' => []]);

    expect(brands()->find('default')?->name())->toBe('Workbench')
        ->and(brands()->find('acme')?->name())->toBe('Workbench');
});

it('validates definitions on hydration and names the brand', function (): void {
    config()->set('whitelabel.brands', ['acme' => ['name' => null]]);

    expect(fn () => brands()->all())
        ->toThrow(InvalidBrandDefinition::class, 'Brand [acme] sets [name] to null');
});

it('rejects a brand definition that is not an array', function (): void {
    config()->set('whitelabel.brands', ['acme' => 'Acme']);

    expect(fn () => brands()->all())
        ->toThrow(InvalidBrandDefinition::class, 'must be an array of brand keys, string given');
});

it('refuses a default brand identifier that names no brand', function (): void {
    config()->set('whitelabel.default', 'missing');

    expect(fn () => brands()->all())->toThrow(
        UnknownBrand::class,
        'whitelabel.default names the brand [missing], which is not defined. Define it, '
        .'or point whitelabel.default at one of: [acme], [default].',
    );
});

it('serves nothing rather than complaining when no brands are configured', function (): void {
    config()->set('whitelabel.brands', []);

    expect(brands()->all())->toBe([])
        ->and(brands()->find('acme'))->toBeNull();
});

it('rehydrates when the brand config changes underneath it', function (): void {
    $repository = brands();

    expect($repository->find('acme')?->name())->toBe('Acme');

    config()->set('whitelabel.brands.acme.name', 'Acme Inc');

    expect($repository->find('acme')?->name())->toBe('Acme Inc');
});

it('rehydrates after an explicit flush', function (): void {
    $repository = brands();
    $repository->all();
    $repository->flush();

    expect($repository->find('acme')?->name())->toBe('Acme');
});

it('throws a documented exception on every write', function (string $operation, array $arguments): void {
    expect(fn () => brands()->{$operation}(...$arguments))
        ->toThrow(
            UnsupportedBrandOperation::class,
            "The [config] brand driver is read-only and cannot [{$operation}] brands.",
        );
})->with([
    'create' => ['create', ['new', ['name' => 'New']]],
    'update' => ['update', ['acme', ['name' => 'New']]],
    'delete' => ['delete', ['acme']],
]);
