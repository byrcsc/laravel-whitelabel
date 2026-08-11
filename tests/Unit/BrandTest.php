<?php

declare(strict_types=1);

use Byrcsc\Whitelabel\Brand;
use Byrcsc\Whitelabel\BrandAsset;
use Byrcsc\Whitelabel\Exceptions\InvalidBrandDefinition;

/**
 * @param  array<array-key, mixed>  $definition
 */
function brandFrom(string $id, array $definition, ?Brand $fallback = null): Brand
{
    return new Brand($id, $definition, $fallback);
}

describe('core accessors', function (): void {
    it('exposes every core field', function (): void {
        $brand = brandFrom('acme', [
            'name' => 'Acme',
            'domain' => 'app.acme.com',
            'logo' => ['disk' => 'public', 'path' => 'brands/acme/logo.svg'],
            'favicon' => 'https://cdn.acme.com/favicon.ico',
            'colors' => ['primary' => '#7c3aed', 'secondary' => '#0ea5e9'],
            'mail' => ['from_name' => 'Acme', 'from_address' => 'hello@acme.com'],
            'settings' => ['support_url' => 'https://support.acme.com'],
        ]);

        expect($brand->id())->toBe('acme')
            ->and($brand->name())->toBe('Acme')
            ->and($brand->domain())->toBe('app.acme.com')
            ->and($brand->logo())->toEqual(new BrandAsset('brands/acme/logo.svg', 'public'))
            ->and($brand->favicon()?->isAbsoluteUrl())->toBeTrue()
            ->and($brand->colors())->toBe(['primary' => '#7c3aed', 'secondary' => '#0ea5e9'])
            ->and($brand->color('primary'))->toBe('#7c3aed')
            ->and($brand->color('tertiary', '#000'))->toBe('#000')
            ->and($brand->mailFromName())->toBe('Acme')
            ->and($brand->mailFromAddress())->toBe('hello@acme.com')
            ->and($brand->settings())->toBe(['support_url' => 'https://support.acme.com'])
            ->and($brand->setting('support_url'))->toBe('https://support.acme.com');
    });

    it('returns null for core fields the brand never defines', function (): void {
        $brand = brandFrom('bare', []);

        expect($brand->name())->toBeNull()
            ->and($brand->domain())->toBeNull()
            ->and($brand->logo())->toBeNull()
            ->and($brand->favicon())->toBeNull()
            ->and($brand->colors())->toBe([])
            ->and($brand->mailFromName())->toBeNull()
            ->and($brand->settings())->toBe([]);
    });

    it('lowercases the domain so host matching is case insensitive', function (): void {
        expect(brandFrom('acme', ['domain' => 'App.Acme.COM'])->domain())->toBe('app.acme.com');
    });

    it('validates its definition, so an invalid brand cannot be constructed', function (): void {
        expect(fn () => new Brand('acme', ['name' => null]))
            ->toThrow(InvalidBrandDefinition::class, 'Brand [acme] sets [name] to null');
    });

    it('reads id through the same accessor as everything else', function (): void {
        $brand = brandFrom('acme', ['name' => 'Acme']);

        expect($brand->get('id'))->toBe('acme')
            ->and($brand->has('id'))->toBeTrue();
    });
});

describe('dot notation', function (): void {
    it('reaches core fields, nested core maps, and the settings bag', function (): void {
        $brand = brandFrom('acme', [
            'name' => 'Acme',
            'colors' => ['primary' => '#7c3aed'],
            'mail' => ['from_address' => 'hello@acme.com'],
            'settings' => ['support' => ['url' => 'https://support.acme.com']],
        ]);

        expect($brand->get('name'))->toBe('Acme')
            ->and($brand->get('colors.primary'))->toBe('#7c3aed')
            ->and($brand->get('mail.from_address'))->toBe('hello@acme.com')
            ->and($brand->get('settings.support.url'))->toBe('https://support.acme.com')
            ->and($brand->setting('support.url'))->toBe('https://support.acme.com');
    });

    it('returns the given default for an unknown key', function (): void {
        $brand = brandFrom('acme', []);

        expect($brand->get('colors.primary', '#fallback'))->toBe('#fallback')
            ->and($brand->has('colors.primary'))->toBeFalse();
    });

    it('reaches assets stored in the settings bag', function (): void {
        $brand = brandFrom('acme', [
            'settings' => ['og_image' => ['disk' => 's3', 'path' => 'og.png']],
        ]);

        expect($brand->asset('settings.og_image'))->toEqual(new BrandAsset('og.png', 's3'));
    });
});

describe('fallback to the default brand', function (): void {
    $default = fn (): Brand => brandFrom('default', [
        'name' => 'Default',
        'logo' => ['disk' => 'public', 'path' => 'logo.svg'],
        'colors' => ['primary' => '#000000', 'secondary' => '#111111'],
        'mail' => ['from_name' => 'Default', 'from_address' => 'hello@default.test'],
        'settings' => ['support_url' => 'https://support.default.test', 'locale' => 'en'],
        'domain' => 'default.test',
    ]);

    it('falls back for a key the brand leaves out', function () use ($default): void {
        $brand = brandFrom('acme', ['name' => 'Acme'], $default());

        expect($brand->name())->toBe('Acme')
            ->and($brand->logo())->toEqual(new BrandAsset('logo.svg', 'public'))
            ->and($brand->mailFromName())->toBe('Default');
    });

    it('falls back one nested key at a time', function () use ($default): void {
        $brand = brandFrom('acme', [
            'colors' => ['primary' => '#7c3aed'],
            'settings' => ['support_url' => 'https://support.acme.com'],
        ], $default());

        expect($brand->colors())->toBe(['primary' => '#7c3aed', 'secondary' => '#111111'])
            ->and($brand->setting('locale'))->toBe('en')
            ->and($brand->setting('support_url'))->toBe('https://support.acme.com');
    });

    it('inherits the disk of an asset when only the path is overridden', function () use ($default): void {
        $brand = brandFrom('acme', ['logo' => ['path' => 'acme.svg']], $default());

        expect($brand->logo())->toEqual(new BrandAsset('acme.svg', 'public'));
    });

    it('treats a set-but-empty value as cleared and does not fall back', function () use ($default): void {
        $brand = brandFrom('acme', [
            'name' => '',
            'logo' => '',
            'colors' => ['primary' => ''],
        ], $default());

        expect($brand->name())->toBe('')
            ->and($brand->logo())->toBeNull()
            ->and($brand->color('primary'))->toBe('')
            ->and($brand->color('secondary'))->toBe('#111111');
    });

    it('clears an inherited list but leaves an inherited map alone', function (): void {
        $default = brandFrom('default', [
            'colors' => ['primary' => '#000000'],
            'settings' => ['tags' => ['a', 'b']],
        ]);

        $brand = brandFrom('acme', [
            'colors' => [],
            'settings' => ['tags' => []],
        ], $default);

        expect($brand->colors())->toBe(['primary' => '#000000'])
            ->and($brand->setting('tags'))->toBe([]);
    });

    it('never inherits the domain', function () use ($default): void {
        $brand = brandFrom('acme', ['name' => 'Acme'], $default());

        expect($brand->domain())->toBeNull()
            ->and($brand->get('domain'))->toBeNull();
    });

    it('rebinds the fallback without mutating the original', function () use ($default): void {
        $brand = brandFrom('acme', ['name' => 'Acme']);
        $rebound = $brand->withFallback($default());

        expect($brand->color('primary'))->toBeNull()
            ->and($rebound->color('primary'))->toBe('#000000')
            ->and($rebound->definition())->toBe($brand->definition());
    });

    it('exposes its own definition separately from the effective one', function () use ($default): void {
        $brand = brandFrom('acme', ['name' => 'Acme'], $default());

        expect($brand->definition())->toBe(['name' => 'Acme'])
            ->and($brand->toArray())->toHaveKeys(['id', 'name', 'colors', 'mail', 'settings'])
            ->and($brand->toArray()['id'])->toBe('acme')
            ->and($brand->toArray())->not->toHaveKey('domain');
    });
});
