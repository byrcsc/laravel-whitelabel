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

The install command publishes `config/whitelabel.php` and asks whether you want
the brands migration too. Run it again any time: it leaves files it has already
published alone, unless you pass `--force`. To script it, say up front which
you want:

```bash
php artisan whitelabel:install --database --no-interaction
```

Those two, plus `whitelabel:clear`, are the entire artisan surface.

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

An asset can also be written as a plain string: a path on the default disk, or
an absolute URL, which passes through untouched. The default disk is
`whitelabel.assets.disk`.

The package generates URLs and nothing more: it never checks that a file exists
or that a disk is public. Brand assets are expected to live on a publicly
accessible disk.

## Blade components

Three components, all of which render nothing at all when the brand has no such
value after fallback:

```blade
<x-whitelabel::styles />
<x-whitelabel::logo class="h-8" alt="Home" />
<x-whitelabel::favicon />
```

`styles` emits the colour set as CSS custom properties in a `<style>` block:

```html
<style>:root{--brand-primary:#7c3aed;--brand-secondary:#0ea5e9;}</style>
```

The `brand` prefix comes from `whitelabel.css.prefix`, and
`<x-whitelabel::styles prefix="theme" />` overrides it for one usage. Consume
them wherever you would use any custom property:

```css
.button { background: var(--brand-primary); }
```

```js
// tailwind.config.js
theme: { extend: { colors: { brand: 'var(--brand-primary)' } } }
```

`logo` renders an `img` whose `alt` defaults to the brand's name, and `favicon`
renders a `link rel="icon"` whose `type` is guessed from the file extension.
Both forward any attributes you give them.

To change the markup, publish the views and edit them:

```bash
php artisan vendor:publish --tag=whitelabel-views
```

## Mail and notifications

The active brand is available in every mail and notification mail view, through
the same `brand()` helper and `Whitelabel` facade as your web views.

Laravel's markdown mail is branded out of the box: the header shows the brand's
logo instead of the application name, and the primary button is painted in the
brand's primary colour. A brand with neither gets exactly what Laravel renders
today. Turn the whole thing off with `whitelabel.mail.markdown => false`.

To change the markup rather than switch it off, start from the package's
copies:

```bash
php artisan vendor:publish --tag=whitelabel-views
```

That writes them to `resources/views/vendor/whitelabel/mail/html`. Copy the
ones you want into `resources/views/vendor/mail/html`, which is where Laravel
looks first and therefore wins over both the package and Laravel itself. Keep
the `Byrcsc\Whitelabel\Mail\BrandedMarkdown` calls if you want the branding —
publishing Laravel's own views with `--tag=laravel-mail` instead gives you
unbranded markup, which is a fine choice as long as it is the one you meant.

### Sending as the brand

Sender override is opt-in:

```php
'mail' => ['override_from' => true],
```

With it on, mail sent while a brand is active goes out from that brand's
`from_name` and `from_address`. Mail sent with no brand active, or from a brand
that names no sender, is left alone.

**It is off by default for a reason.** Sending from a domain whose SPF, DKIM,
and DMARC records do not authorise your mail provider is how mail stops being
delivered — silently, to spam, and with damage to the sending domain's
reputation that outlasts the mistake. Verify each brand's domain with your
provider first, then turn it on. The flag overrides the sender on every message
while it is enabled, including mailables that set their own `from()`.

## Queued work

A worker has no request, so it has no domain to resolve a brand from. Add the
`BrandAware` trait and the job runs with whichever brand was active when it was
dispatched:

```php
use Byrcsc\Whitelabel\Queue\BrandAware;

class SendWelcomeEmail implements ShouldQueue
{
    use Queueable, BrandAware;
}
```

It works the same on a queued mailable, a queued notification, and a queued
listener. The brand's identifier travels in the queue payload, beside the job
rather than inside it, so nothing changes about how your job serialises.

Three rules finish the picture:

- **No brand at dispatch, no brand restored.** The job resolves normally, which
  in a worker means the configured default.
- **A brand that has since been deleted fails the job**, with
  `CapturedBrandMissing`, rather than quietly sending mail that looks like
  somebody else. At the default single try that means `failed_jobs`; give the
  job more tries if you would rather it waited for the brand to come back.
- **With Spatie, you rarely need the trait.** A tenant-aware job restores its
  tenant, and the switch task activates that tenant's brand. Where both apply,
  the captured brand wins. Note Spatie's own
  `queues_are_tenant_aware_by_default`: while that is on, a job dispatched with
  no current tenant is discarded before it runs, whatever this package does.

Console commands are the same story without the trait: no override, so
`whitelabel.default` decides. `Whitelabel::activate()` at the top of a command
is the way to say otherwise.

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
queued jobs alike. Forgetting the tenant deactivates it. A tenant that does not
implement `ProvidesBrand`, or that returns null from it, changes nothing and
resolution carries on to the request domain and the configured default.

The package never touches Spatie's schema. The tenant decides where its brand
comes from, and all three of these are supported:

```php
use Byrcsc\Whitelabel\Brand;
use Byrcsc\Whitelabel\Contracts\BrandRepository;
use Byrcsc\Whitelabel\Contracts\ProvidesBrand;
use Spatie\Multitenancy\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant implements ProvidesBrand
{
    // From the tenant's own columns.
    public function brand(): ?Brand
    {
        if ($this->brand_name === null) {
            return null;
        }

        return new Brand($this->slug, [
            'name' => $this->brand_name,
            'colors' => ['primary' => $this->brand_color ?? ''],
        ]);
    }

    // From one JSON column, cast to an array.
    public function brandFromJson(): ?Brand
    {
        return $this->brand_definition === null
            ? null
            : new Brand($this->slug, $this->brand_definition);
    }

    // From a brand_id into the package's own table.
    public function brandFromRepository(): ?Brand
    {
        return $this->brand_id === null
            ? null
            : app(BrandRepository::class)->find($this->brand_id);
    }
}
```

Pick one and call it `brand()`; the other two are named apart only so they can
sit in one example. Point `multitenancy.tenant_model` at this class — Spatie
rehydrates the tenant through it inside a queued job, so a base tenant model
there means no brand in the worker.

A brand you build by hand does not need to carry the default brand: the
package wires it in as the fallback when the brand becomes active, so a tenant
that names only a colour still gets the default brand's logo and settings.

Without Spatie installed nothing changes and nothing breaks. `TenantResolver`
stays in the chain and answers with nothing: it reads the current tenant out of
the container under the key Spatie's own config names, so it refers to no
Spatie class at all. `SwitchTenantBrandTask` does refer to Spatie types, but
the only thing that ever names it is your `config/multitenancy.php`, which does
not exist unless Spatie does. CI runs the suite with the package uninstalled to
keep it that way.

The tenant wins over the request domain and the configured default, and loses
to an explicit `Whitelabel::activate()` — that is just the shipped chain order,
and you can change it.

## Testing helpers

Add the trait to your test case and give a test a brand:

```php
use Byrcsc\Whitelabel\Testing\InteractsWithBrands;

$this->actingWithBrand(['name' => 'Acme', 'colors' => ['primary' => '#000']]);
$this->actingWithBrand('acme');                     // one the driver knows
$this->defineBrand('spare', ['name' => 'Spare']);   // without activating it
```

No database, no configuration, and whichever driver the application uses. A
brand defined this way still falls back to the default brand for anything it
leaves out, and everything is dropped at the end of each test.

The same two calls are available outside a test:

```php
Whitelabel::define('acme', [...]);   // register a Brand from an array, no DB
Whitelabel::activate('acme');
```

The database driver ships a model factory for integration tests that do want
rows:

```php
use Byrcsc\Whitelabel\Models\BrandRecord;

BrandRecord::factory()->identifiedBy('acme')->create(['name' => 'Acme']);
BrandRecord::factory()->bare()->create();   // a brand that defines nothing
```

`BrandRecord` is the one internal class the package asks you to name, and only
here. Everything the package hands back is a `Brand`.

Events are plain Laravel events, so `Event::fake()` covers assertions:

```php
Event::fake([BrandCreated::class]);

app(BrandRepository::class)->create('acme', ['name' => 'Acme']);

Event::assertDispatched(BrandCreated::class);
```

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

## The public API

Everything below is covered by semantic versioning and pinned by
`tests/Feature/PublicApiTest.php`. Anything not here is internal and can change
in a patch release.

**`Byrcsc\Whitelabel\Brand`** — the brand itself.

```php
$brand->id();               $brand->name();             $brand->domain();
$brand->get($key, $default) $brand->has($key);
$brand->colors();           $brand->color($name, $default);
$brand->settings();         $brand->setting($key, $default);
$brand->logo();             $brand->favicon();          $brand->asset($key);
$brand->logoUrl();          $brand->faviconUrl();       $brand->assetUrl($key);
$brand->mailFromName();     $brand->mailFromAddress();
$brand->definition();       $brand->fallback();         $brand->withFallback($other);
$brand->toArray();
```

**`Byrcsc\Whitelabel\BrandAsset`** — `fromDefinition()`, `isAbsoluteUrl()`,
`url()`, `toArray()`, and the readonly `path` and `disk`.

**`Byrcsc\Whitelabel\BrandDefinition`** — the schema a custom driver validates
against: `validate()`, `inherit()`, and the key constants.

**`Byrcsc\Whitelabel\Whitelabel`**, and the `Facades\Whitelabel` in front of it
— `current()`, `isResolved()`, `activate()`, `forget()`, `flush()`,
`define()`, `find()`, `findByDomain()`, `overridden()`. Plus the global
`brand()` helper.

**Contracts** — `BrandRepository` (`all`, `find`, `findByDomain`, `has`,
`create`, `update`, `delete`, `flush`), `BrandResolver` (`resolve`),
`ProvidesBrand` (`brand`).

**Drivers** — `ConfigBrandRepository`, `DatabaseBrandRepository`,
`CachedBrandRepository` (plus `inner()`), and `BrandRepositoryManager`
(`driver()`, `extend()`, `getDefaultDriver()`).

**Resolvers** — `OverrideResolver`, `TenantResolver`, `DomainResolver`,
`DefaultResolver`.

**Events** — `BrandActivated`, `BrandDeactivated`, `BrandCreated`,
`BrandUpdated`, `BrandDeleted`. Each has a readonly `$brand`.

**Exceptions**, all implementing `WhitelabelException` —
`InvalidBrandDefinition`, `UnknownBrand`, `UnsupportedBrandOperation`,
`BrandAlreadyExists`, `CapturedBrandMissing`.

**The rest** — `Queue\BrandAware`, `Http\Middleware\EagerResolveBrand`,
`Spatie\SwitchTenantBrandTask`, `Mail\BrandedMarkdown` (what a published mail
view calls), `Testing\InteractsWithBrands` (`actingWithBrand`, `defineBrand`),
the three `View\Components`, and `Models\BrandRecord::factory()` with its
`identifiedBy()` and `bare()` states.

**Commands** — `whitelabel:install` (`--database`, `--force`) and
`whitelabel:clear`. **Publish tags** — `whitelabel-config`,
`whitelabel-views`, `whitelabel-migrations`. **Config keys** — everything in
`config/whitelabel.php`.

`Models\BrandRecord` is the one internal class named here, and only for its
factory: nothing the package returns is ever a `BrandRecord`.

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
