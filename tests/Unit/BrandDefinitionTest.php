<?php

declare(strict_types=1);

use Byrcsc\Whitelabel\BrandDefinition;
use Byrcsc\Whitelabel\Exceptions\InvalidBrandDefinition;

it('rejects an explicit null and says what to do instead', function (): void {
    expect(fn () => BrandDefinition::validate('acme', ['name' => null]))
        ->toThrow(
            InvalidBrandDefinition::class,
            'Brand [acme] sets [name] to null. Remove the key to fall back to the default brand, '
            .'or use an empty value to clear it.',
        );
});

it('rejects an explicit null nested in the settings bag', function (): void {
    expect(fn () => BrandDefinition::validate('acme', ['settings' => ['support' => ['url' => null]]]))
        ->toThrow(InvalidBrandDefinition::class, '[settings.support.url] to null');
});

it('rejects an unknown top-level key and lists the known ones', function (): void {
    expect(fn () => BrandDefinition::validate('acme', ['color' => []]))
        ->toThrow(
            InvalidBrandDefinition::class,
            'Brand [acme] defines an unknown key [color]. Expected one of: '
            .'[colors], [domain], [favicon], [logo], [mail], [name], [settings].',
        );
});

it('rejects an unknown mail key', function (): void {
    expect(fn () => BrandDefinition::validate('acme', ['mail' => ['reply_to' => 'x@acme.com']]))
        ->toThrow(InvalidBrandDefinition::class, 'unknown key [mail.reply_to]');
});

it('rejects an unknown asset key', function (): void {
    expect(fn () => BrandDefinition::validate('acme', ['logo' => ['bucket' => 'acme']]))
        ->toThrow(InvalidBrandDefinition::class, 'unknown key [logo.bucket]');
});

it('names the offending path and type for a wrong scalar type', function (): void {
    expect(fn () => BrandDefinition::validate('acme', ['colors' => ['primary' => 7]]))
        ->toThrow(InvalidBrandDefinition::class, '[colors.primary] must be a string, int given');
});

it('rejects a domain carrying a scheme or a path', function (): void {
    expect(fn () => BrandDefinition::validate('acme', ['domain' => 'https://app.acme.com']))
        ->toThrow(InvalidBrandDefinition::class, 'must be a bare host');
});

it('rejects a settings bag that is not keyed by name', function (): void {
    expect(fn () => BrandDefinition::validate('acme', ['settings' => ['first', 'second']]))
        ->toThrow(InvalidBrandDefinition::class, 'must be keyed by name at the top level');
});

it('rejects a settings value that is neither scalar nor array', function (): void {
    expect(fn () => BrandDefinition::validate('acme', ['settings' => ['handler' => new stdClass]]))
        ->toThrow(InvalidBrandDefinition::class, '[settings.handler] must be a scalar or an array');
});

it('keeps lists inside the settings bag intact', function (): void {
    $definition = BrandDefinition::validate('acme', ['settings' => ['tags' => ['a', 'b']]]);

    expect($definition)->toHaveKey('settings.tags', ['a', 'b']);
});

it('accepts every core key', function (): void {
    $definition = BrandDefinition::validate('acme', [
        'name' => 'Acme',
        'domain' => 'app.acme.com',
        'logo' => 'brands/acme/logo.svg',
        'favicon' => ['disk' => 'public', 'path' => 'favicon.ico'],
        'colors' => ['primary' => '#7c3aed'],
        'mail' => ['from_name' => 'Acme', 'from_address' => 'hello@acme.com'],
        'settings' => ['support_url' => 'https://support.acme.com', 'seats' => 10, 'beta' => true],
    ]);

    expect($definition)->toHaveCount(7)
        ->toHaveKey('settings.seats', 10)
        ->toHaveKey('settings.beta', true);
});
