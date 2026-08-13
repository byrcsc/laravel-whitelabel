<?php

declare(strict_types=1);

use Byrcsc\Whitelabel\Brand;
use Byrcsc\Whitelabel\Whitelabel;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('public');
    Storage::fake('s3');

    config()->set('whitelabel.default', 'default');
    config()->set('whitelabel.brands', [
        'default' => [
            'name' => 'Default',
            'colors' => ['primary' => '#000000', 'secondary' => '#111111'],
            'logo' => ['disk' => 'public', 'path' => 'brands/default/logo.svg'],
            'favicon' => ['disk' => 'public', 'path' => 'brands/default/favicon.ico'],
        ],
        'acme' => [
            'name' => 'Acme',
            'colors' => ['primary' => '#7c3aed'],
            'logo' => 'https://cdn.acme.com/logo.svg',
            'settings' => ['og_image' => ['disk' => 's3', 'path' => 'og/acme.png']],
        ],
        'bare' => ['name' => 'Bare', 'logo' => '', 'favicon' => '', 'colors' => ['primary' => '', 'secondary' => '']],
    ]);
});

function activate(string $id): Brand
{
    return app(Whitelabel::class)->activate($id);
}

describe('asset URLs', function (): void {
    it('builds a URL from a disk and a path', function (): void {
        $brand = activate('default');

        expect($brand->logoUrl())->toBe(Storage::disk('public')->url('brands/default/logo.svg'));
    });

    it('passes an absolute URL through untouched', function (): void {
        $brand = activate('acme');

        expect($brand->logoUrl())->toBe('https://cdn.acme.com/logo.svg');
    });

    it('falls back to the configured default disk when the asset names none', function (): void {
        config()->set('whitelabel.assets.disk', 's3');
        config()->set('whitelabel.brands.acme.logo', 'brands/acme/logo.svg');

        $brand = activate('acme');

        expect($brand->logoUrl())->toBe(Storage::disk('s3')->url('brands/acme/logo.svg'));
    });

    it('reaches an asset in the settings bag by its bare key', function (): void {
        $brand = activate('acme');

        expect($brand->assetUrl('og_image'))->toBe(Storage::disk('s3')->url('og/acme.png'))
            ->and($brand->assetUrl('settings.og_image'))->toBe(Storage::disk('s3')->url('og/acme.png'))
            ->and($brand->assetUrl('logo'))->toBe('https://cdn.acme.com/logo.svg')
            ->and($brand->assetUrl('nope'))->toBeNull();
    });

    it('inherits an asset it does not define', function (): void {
        $brand = activate('acme');

        expect($brand->faviconUrl())->toBe(Storage::disk('public')->url('brands/default/favicon.ico'));
    });

    it('returns nothing for an asset the brand cleared', function (): void {
        $brand = activate('bare');

        expect($brand->logoUrl())->toBeNull()
            ->and($brand->faviconUrl())->toBeNull();
    });
});

describe('the styles component', function (): void {
    it('emits every colour as a custom property', function (): void {
        activate('default');

        expect(Blade::render('<x-whitelabel::styles />'))
            ->toContain('--brand-primary:#000000;')
            ->toContain('--brand-secondary:#111111;')
            ->toContain(':root{')
            ->toContain('</style>');
    });

    it('forwards attributes to the style tag', function (): void {
        activate('default');

        expect(Blade::render('<x-whitelabel::styles nonce="abc123" />'))->toContain('nonce="abc123"');
    });

    it('writes a value containing an ampersand or a quote unmangled', function (): void {
        config()->set('whitelabel.brands.default.colors', ['accent' => "var(--x, 'a & b')"]);

        activate('default');

        $rendered = Blade::render('<x-whitelabel::styles />');

        expect($rendered)->toContain("--brand-accent:var(--x, 'a & b');");
        expect($rendered)->not->toContain('&amp;');
    });

    it('strips a value that would comment out the rest of the block', function (): void {
        config()->set('whitelabel.brands.default.colors', ['primary' => '#000 /* ', 'secondary' => '#111']);

        activate('default');

        expect(Blade::render('<x-whitelabel::styles />'))
            ->not->toContain('/*')
            ->toContain('--brand-secondary:#111;');
    });

    it('includes colours that arrive by fallback', function (): void {
        activate('acme');

        expect(Blade::render('<x-whitelabel::styles />'))
            ->toContain('--brand-primary:#7c3aed;')
            ->toContain('--brand-secondary:#111111;');
    });

    it('respects the configured prefix', function (): void {
        config()->set('whitelabel.css.prefix', 'theme');
        activate('default');

        expect(Blade::render('<x-whitelabel::styles />'))->toContain('--theme-primary:#000000;');
    });

    it('takes a prefix per usage', function (): void {
        activate('default');

        expect(Blade::render('<x-whitelabel::styles prefix="ui" />'))->toContain('--ui-primary:#000000;');
    });

    it('renders nothing when the brand has no colours', function (): void {
        activate('bare');

        expect(trim(Blade::render('<x-whitelabel::styles />')))->toBe('');
    });

    it('refuses to let a colour close the style block', function (): void {
        config()->set('whitelabel.brands.acme.colors', [
            'primary' => '#fff</style><script>alert(1)</script>',
            'bad name' => '#000',
        ]);

        activate('acme');

        $rendered = Blade::render('<x-whitelabel::styles />');

        expect($rendered)->not->toContain('<script>');
        expect($rendered)->not->toContain('</style><');
        expect($rendered)->not->toContain('bad name');
    });
});

describe('the logo component', function (): void {
    it('renders an img tag with the brand name as alt text', function (): void {
        activate('acme');

        expect(Blade::render('<x-whitelabel::logo />'))
            ->toContain('src="https://cdn.acme.com/logo.svg"')
            ->toContain('alt="Acme"');
    });

    it('forwards attributes', function (): void {
        activate('acme');

        expect(Blade::render('<x-whitelabel::logo class="h-8" id="mark" />'))
            ->toContain('class="h-8"')
            ->toContain('id="mark"');
    });

    it('takes alt text of its own', function (): void {
        activate('acme');

        expect(Blade::render('<x-whitelabel::logo alt="Home" />'))->toContain('alt="Home"');
    });

    it('renders nothing when the brand has no logo', function (): void {
        activate('bare');

        expect(trim(Blade::render('<x-whitelabel::logo />')))->toBe('');
    });
});

describe('the favicon component', function (): void {
    it('renders a link tag with the type guessed from the path', function (): void {
        activate('default');

        expect(Blade::render('<x-whitelabel::favicon />'))
            ->toContain('rel="icon"')
            ->toContain('type="image/x-icon"')
            ->toContain(Storage::disk('public')->url('brands/default/favicon.ico'));
    });

    it('takes an explicit type', function (): void {
        activate('default');

        expect(Blade::render('<x-whitelabel::favicon type="image/png" />'))->toContain('type="image/png"');
    });

    it('forwards attributes and lets a caller override the rel', function (): void {
        activate('default');

        $rendered = Blade::render('<x-whitelabel::favicon rel="apple-touch-icon" sizes="180x180" />');

        expect($rendered)->toContain('rel="apple-touch-icon"')
            ->toContain('sizes="180x180"');
        expect($rendered)->not->toContain('rel="icon"');
    });

    it('leaves the type out when the path does not say', function (): void {
        config()->set('whitelabel.brands.default.favicon', 'https://cdn.acme.com/icon');

        activate('default');

        expect(Blade::render('<x-whitelabel::favicon />'))->not->toContain('type=');
    });

    it('renders nothing when the brand has no favicon', function (): void {
        activate('bare');

        expect(trim(Blade::render('<x-whitelabel::favicon />')))->toBe('');
    });
});

it('renders nothing at all when no brand resolves', function (): void {
    config()->set('whitelabel.resolvers', []);

    expect(trim(Blade::render('<x-whitelabel::styles /><x-whitelabel::logo /><x-whitelabel::favicon />')))
        ->toBe('');
});

it('lets a published view override the shipped one', function (): void {
    activate('acme');

    $published = resource_path('views/vendor/whitelabel/components/logo.blade.php');

    File::ensureDirectoryExists(dirname($published));
    File::put($published, '<span>published logo</span>');

    try {
        expect(Blade::render('<x-whitelabel::logo />'))->toContain('published logo');
    } finally {
        File::deleteDirectory(resource_path('views/vendor/whitelabel'));
    }
});
