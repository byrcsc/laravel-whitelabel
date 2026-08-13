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
same immutable `Brand` object. Read it with one accessor:

```php
$brand->name();
$brand->get('colors.primary');
$brand->get('settings.support_url');
$brand->fallback();              // the default brand behind it, if any
```

One brand is active per request, job, or console command. Resolution walks an
ordered chain and stops at the first answer: explicit runtime override, then
the current Spatie tenant, then the request domain, then the configured
default.

A brand only overrides what it defines; any key it leaves out falls back to the
default brand, key by key. Maps such as `colors`, `mail`, and `settings` fall
back one entry at a time, so naming a single colour keeps the rest of the set.
Three rules complete the picture:

- **Empty means cleared.** A key set to an empty string, or to an empty list,
  is deliberately blank and does not fall back. An empty *map*, such as
  `'colors' => []`, says nothing at all: clear colours one at a time.
- **Null is an error.** Remove the key to inherit it; the drivers reject an
  explicit null with a message saying so.
- **`domain` never falls back.** It decides which brand a request belongs to,
  so inheriting it would make every brand claim the default brand's host.

Registering a driver of your own is one call, and everything downstream keeps
working through the contract:

```php
use Byrcsc\Whitelabel\BrandRepositoryManager;

app(BrandRepositoryManager::class)->extend('redis', fn () => new RedisBrandRepository);
```

Set `whitelabel.driver` to `redis` to use it. Read-only drivers, config
included, throw `UnsupportedBrandOperation` from the write methods.

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

## Choosing a driver

The **config driver** is the default. Brands live in `config/whitelabel.php`,
they are version-controlled and reviewable, Laravel's config cache already
makes them free to read, and there is no table and no migration. It is
read-only: the write methods throw. Reach for it when brands change at the
speed of deploys.

The **database driver** stores brands in a table and gives you a programmatic
management API. Reach for it when brands are created by people rather than by
pull requests: a signup flow, an admin screen, a tenant provisioning job.

Switch with one config key, after publishing the migration:

```php
'driver' => 'database',
```

```bash
php artisan vendor:publish --tag=whitelabel-migrations
php artisan migrate
```

The table name and connection are configurable under `whitelabel.database`.
Set them before you publish, since the migration reads them too.

## Managing brands

Both drivers sit behind the same contract, so this is how you read brands
whichever one you use:

```php
use Byrcsc\Whitelabel\Contracts\BrandRepository;

$brands = app(BrandRepository::class);

$brands->all();                          // array<string, Brand>
$brands->find('acme');                   // ?Brand
$brands->findByDomain('app.acme.com');   // ?Brand
$brands->has('acme');                    // bool
```

The database driver adds the writes:

```php
$brands->create('acme', [
    'name' => 'Acme',
    'domain' => 'app.acme.com',
    'colors' => ['primary' => '#7c3aed'],
]);

$brands->update('acme', ['name' => 'Acme Inc']);   // replaces the definition
$brands->delete('acme');                            // bool
```

`update()` replaces the whole definition rather than patching it, so a key you
leave out goes back to inheriting from the default brand. Both writes validate
their definition exactly as the config driver does, `null` rejection included.

Failures are typed, never raw query exceptions: `BrandAlreadyExists` for a
duplicate identifier or a domain that belongs to another brand, `UnknownBrand`
for updating a brand that is not there, and `InvalidBrandDefinition` for a
definition the schema rejects. All of them implement `WhitelabelException`.

Writes fire `BrandCreated`, `BrandUpdated`, and `BrandDeleted`, each carrying
the affected `Brand`.

## Caching

The database driver is wrapped in a cache, so a brand is read from the
database once and served from the cache after that. Entries are per brand,
keyed by identifier, and stored forever: a brand changes when someone changes
it, not when a clock runs out. A separate domain index answers `findByDomain`.

The index grows one domain at a time as hosts are looked up, so a cold lookup
costs the driver's own indexed query rather than a listing of every brand.

Every write busts the brand it touched and the domain index. That is the only
invalidation path, which means the next read after an update, a delete, or a
domain move is already fresh — there is no window to wait out.

The escape hatch, for a definition changed outside the package:

```bash
php artisan whitelabel:clear
```

It forgets every key the package owns and nothing else; your application's own
cache entries survive.

The store, the key prefix, and caching itself are configurable under
`whitelabel.cache`. The config driver is never cached — Laravel's config cache
already covers it. If you use `spatie/laravel-multitenancy` with its
`PrefixCacheTask`, leave `store` null so brands land in the prefixed store the
task sets up, and one tenant's brands stay out of another tenant's cache.

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

Each resolver answers with a brand or with nothing, and the first answer wins.
`OverrideResolver` answers with whatever you activated. `TenantResolver`
answers with the current Spatie tenant's brand, and with nothing when Spatie is
not installed. `DomainResolver` matches the request host, and answers with
nothing outside an HTTP request rather than matching the placeholder host
Laravel synthesises for console commands and queue workers. `DefaultResolver`
answers with `whitelabel.default`, which is why a console command still has a
brand.

Write your own by implementing `BrandResolver` and adding it to the array:

```php
use Byrcsc\Whitelabel\Brand;
use Byrcsc\Whitelabel\Contracts\BrandResolver;
use Byrcsc\Whitelabel\Facades\Whitelabel;

class HeaderResolver implements BrandResolver
{
    public function resolve(): ?Brand
    {
        return Whitelabel::find(request()->header('X-Brand', ''));
    }
}
```

Order is the whole mechanism: nothing is special-cased, so moving
`OverrideResolver` below `DomainResolver` really does make the request host
beat `Whitelabel::activate()`. Resolvers are constructed one at a time as
their turn comes, so a resolver the chain never reaches costs nothing.

If you resolve brands by domain, read the trust-boundary note in
[SECURITY.md](SECURITY.md) about `TrustProxies` first.

Read or set the brand programmatically:

```php
use Byrcsc\Whitelabel\Facades\Whitelabel;

Whitelabel::current();          // the active Brand, or null
Whitelabel::activate('acme');   // explicit override, wins over every resolver
Whitelabel::forget();           // drop it, and resolve again on next access
Whitelabel::find('acme');       // look one up without activating it
Whitelabel::isResolved();       // has the chain run yet?
```

`activate()` takes an identifier or a `Brand`. An identifier that names no
brand throws `UnknownBrand`.

An optional `EagerResolveBrand` middleware resolves at request start and
shares the brand with all views as `$brand`, for applications that want
resolution failures to surface early:

```php
Route::middleware(Byrcsc\Whitelabel\Http\Middleware\EagerResolveBrand::class)
    ->group(base_path('routes/web.php'));
```

`BrandActivated` and `BrandDeactivated` fire on resolution lifecycle changes,
once per transition: activating a second brand fires deactivation for the
first and activation for the second, and activating the brand that is already
the active one fires nothing. "Nothing active yet" is a real state, so under
lazy resolution the first `activate()` of a request fires activation alone.
The database driver additionally fires `BrandCreated`, `BrandUpdated`, and
`BrandDeleted` from its repository.

The active brand is per request, per job, and per command. The package clears
it between queued jobs and between Octane requests itself, so a brand never
survives into the next piece of work. `Whitelabel::flush()` does the same by
hand, and is what the testing helpers call between tests.

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
