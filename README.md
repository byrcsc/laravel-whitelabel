# Laravel Whitelabel

> 🚧 **Work in progress — not released.** This README describes the package as
> it is intended to work. Nothing is on Packagist yet and the API may change.

<!-- Badges go live with the first release.
[![Latest Version on Packagist](https://img.shields.io/packagist/v/byrcsc/laravel-whitelabel.svg?style=flat-square)](https://packagist.org/packages/byrcsc/laravel-whitelabel)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/byrcsc/laravel-whitelabel/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/byrcsc/laravel-whitelabel/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub PHPStan Action Status](https://img.shields.io/github/actions/workflow/status/byrcsc/laravel-whitelabel/phpstan.yml?branch=main&label=phpstan&style=flat-square)](https://github.com/byrcsc/laravel-whitelabel/actions?query=workflow%3APHPStan+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/byrcsc/laravel-whitelabel.svg?style=flat-square)](https://packagist.org/packages/byrcsc/laravel-whitelabel)
-->

Laravel Whitelabel manages brands: the name, logo, favicon, colours, sender
identity, and settings your application presents to whoever is looking at it.
It resolves the active brand at runtime, from an explicit override, the current
[Spatie Multitenancy](https://github.com/spatie/laravel-multitenancy) tenant,
the request domain, or a configured default, and carries that brand into Blade
views, mail, notifications, and queued jobs.

Everything else stays yours. The package decides how the application looks and
speaks, never who the tenant is, who the user is, or what they pay. It works
with Spatie Multitenancy when present and works identically without it.

| Laravel | Tested PHP versions |
|---|---|
| 12.x | 8.3, 8.4 |
| 13.x | 8.3, 8.4 |

## Installation

```bash
composer require byrcsc/laravel-whitelabel
php artisan whitelabel:install
```

The install command publishes `config/whitelabel.php` and, if you opt into the
database driver, the migrations.

## What a brand is

A brand is the complete visual and verbal identity the application wears for
one audience: a name, a logo, a favicon, a colour set, a mail sender, and an
open settings bag for anything else. Whitelabel is what the package does;
brand is the thing it manages.

Brands come from a driver behind one repository contract. The config driver
reads them from `config/whitelabel.php`. The database driver stores them in a
table with a programmatic management API. A Spatie tenant can provide its own
brand directly. Whichever driver hydrates it, your code always receives the
same immutable `Brand` object.

One brand is active per request, job, or console command. Resolution walks an
ordered chain and stops at the first answer: explicit runtime override, then
the current Spatie tenant, then the request domain, then the configured
default. A brand only overrides what it defines; any key it leaves out falls
back to the default brand, key by key.

## Quick start

Define a brand in `config/whitelabel.php`:

```php
'default' => 'acme',

'brands' => [
    'acme' => [
        'name' => 'Acme',
        'domain' => 'app.acme.com',
        'logo' => ['disk' => 'public', 'path' => 'brands/acme/logo.svg'],
        'favicon' => ['disk' => 'public', 'path' => 'brands/acme/favicon.ico'],
        'colors' => ['primary' => '#7c3aed', 'secondary' => '#0ea5e9'],
        'mail' => ['from_name' => 'Acme', 'from_address' => 'hello@acme.com'],
        'settings' => ['support_url' => 'https://support.acme.com'],
    ],
],
```

Use it in a layout:

```blade
<head>
    <title>{{ brand('name') }}</title>
    <x-whitelabel::favicon />
    <x-whitelabel::styles />
</head>
<body>
    <x-whitelabel::logo class="h-8" />
    <a href="{{ brand('settings.support_url') }}">Support</a>
</body>
```

`<x-whitelabel::styles />` emits the brand colours as CSS custom properties
(`--brand-primary`, `--brand-secondary`), ready for your own CSS or Tailwind
utilities to consume.

## Resolving the active brand

Resolution is lazy: the chain runs the first time the brand is accessed, so
there is no required middleware. The chain is a config array of resolver
classes you can reorder or extend:

```php
'resolvers' => [
    Byrcsc\Whitelabel\Resolvers\OverrideResolver::class,
    Byrcsc\Whitelabel\Resolvers\TenantResolver::class,
    Byrcsc\Whitelabel\Resolvers\DomainResolver::class,
    Byrcsc\Whitelabel\Resolvers\DefaultResolver::class,
],
```

Read or set the brand programmatically:

```php
use Byrcsc\Whitelabel\Facades\Whitelabel;

Whitelabel::current();          // the active Brand
Whitelabel::activate('acme');   // explicit override, wins over every resolver
```

An optional `EagerResolveBrand` middleware resolves at request start and
shares the brand with all views, for applications that want resolution
failures to surface early.

`BrandActivated` and `BrandDeactivated` fire on resolution lifecycle changes.
The database driver additionally fires `BrandCreated`, `BrandUpdated`, and
`BrandDeleted` from its repository.

## Brand assets

Assets are `disk` and `path` pairs resolved through Laravel Storage:

```php
brand()->logoUrl();
brand()->faviconUrl();
brand()->assetUrl('og_image');   // any asset stored in the settings bag
```

Absolute URLs pass through untouched. The package generates URLs and nothing
more: it never checks that a file exists or that a disk is public. Brand
assets are expected to live on a publicly accessible disk.

## Mail and notifications

The active brand is available in every mail view, and the markdown mail theme
picks up the brand colours and logo. Queued mailables and notifications render
with the brand that was active when they were dispatched: Spatie tenant-aware
jobs restore it automatically through the tenant switch task, and the
`BrandAware` trait covers jobs outside Spatie.

Sender override is opt-in. With `whitelabel.mail.override_from` enabled, mail
sent while a brand is active uses the brand's from name and address. It is off
by default because sending from a domain you have not verified breaks SPF and
DMARC; enable it deliberately, per the documentation.

## Spatie Multitenancy

The integration is optional and additive. Implement `ProvidesBrand` on your
tenant model and register the switch task:

```php
// config/multitenancy.php
'switch_tenant_tasks' => [
    Byrcsc\Whitelabel\Spatie\SwitchTenantBrandTask::class,
],
```

Making a tenant current activates its brand, in requests and in tenant-aware
queued jobs alike. The package never touches Spatie's schema: the tenant
decides whether its brand comes from its own columns, a JSON column, or a
`brand_id` into the package's table. Without Spatie installed, the
integration classes are simply never registered.

## Testing helpers

```php
use Byrcsc\Whitelabel\Testing\InteractsWithBrands;

$this->actingWithBrand(['name' => 'Acme', 'colors' => ['primary' => '#000']]);

Whitelabel::define('acme', [...]);   // register a Brand from an array, no DB
Whitelabel::activate('acme');
```

The database driver ships a model factory. Events are plain Laravel events,
so `Event::fake()` covers assertions.

## Out of scope

The package will never include:

- Admin or management UI, including Filament and Nova plugins. The
  documentation includes a Filament resource recipe instead.
- Asset uploads or image manipulation, such as favicon generation or logo
  resizing. The package serves URLs for assets you already stored.
- Theme or CSS compilation, including per-brand Tailwind builds. The package
  exposes values; your build pipeline is yours.
- Per-brand routes, feature flags, or content localization.
- Per-brand Blade template directories.
- Tenancy, user management, billing, or database isolation.

## Versioning

The package follows [semantic versioning](https://semver.org/spec/v2.0.0.html).

- Upgrading within `1.x` is safe. Nothing you use will break.
- Only a new major version, like `2.0.0`, can break your code.
- If the README or the documentation describes it, it is safe to build on.
  If they don't, treat it as internal and expect it to change.

Bug fixes go into the newest version only. To get a fix, upgrade to it.

## Questions and issues

- **Stuck, or have an idea?** Start a
  [discussion](https://github.com/byrcsc/laravel-whitelabel/discussions). Usage questions
  and feature ideas both live there.
- **Found a bug you can reproduce?**
  [Open an issue](https://github.com/byrcsc/laravel-whitelabel/issues). A failing test is
  the fastest way to a fix, and a short reproduction is the next best thing.
- **Found a security problem?** Please don't open a public issue. See
  [SECURITY.md](SECURITY.md) for how to report it privately.
- **Planning a pull request?** [CONTRIBUTING.md](CONTRIBUTING.md) covers the
  setup and the three checks it needs to pass.

This package is maintained by one person, so replies can take a while.
Everything gets read.

## License

MIT. See [LICENSE.md](LICENSE.md). Changelog in [CHANGELOG.md](CHANGELOG.md).
