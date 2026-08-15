# Laravel Whitelabel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/byrcsc/laravel-whitelabel.svg?style=flat-square)](https://packagist.org/packages/byrcsc/laravel-whitelabel)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/byrcsc/laravel-whitelabel/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/byrcsc/laravel-whitelabel/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub PHPStan Action Status](https://img.shields.io/github/actions/workflow/status/byrcsc/laravel-whitelabel/phpstan.yml?branch=main&label=phpstan&style=flat-square)](https://github.com/byrcsc/laravel-whitelabel/actions?query=workflow%3APHPStan+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/byrcsc/laravel-whitelabel.svg?style=flat-square)](https://packagist.org/packages/byrcsc/laravel-whitelabel)

Give every white-label client its own branding and settings across web pages,
emails, and background jobs in one Laravel application.

Laravel Whitelabel stores each client's logo, favicon, colours, mail sender,
and application settings as a brand. It selects one active brand for each
request or job, so every part of the application reads the same client
configuration.

The package manages branding only. Your application remains responsible for
tenants, users, billing, authorization, and content.

| Laravel | Tested PHP versions |
|---|---|
| 12.x | 8.3, 8.4 |
| 13.x | 8.3, 8.4 |

Tested with SQLite, MySQL, and PostgreSQL. The optional integration supports
`spatie/laravel-multitenancy` 4.x.

## Installation

Install the package and publish its configuration:

```bash
composer require byrcsc/laravel-whitelabel
php artisan whitelabel:install
```

The installer can also publish the migration for database-managed brands:

```bash
php artisan whitelabel:install --database --no-interaction
php artisan migrate
```

## How it works

A brand is an immutable definition with fixed fields for its name, domain,
logo, favicon, colours, and mail sender, plus a settings array for values your
application owns.

The default config driver reads definitions from `config/whitelabel.php`. The
database driver stores them in a table and supports create, update, and delete
operations. Both return the same `Brand` object through the
`BrandRepository` contract.

The active brand comes from the first resolver that finds one. The default
order checks an explicit activation, the current Spatie tenant, the request
domain, and finally the configured default brand. Values omitted by a brand
inherit from the default brand one key at a time.

## Quick start

Brands can live in configuration or the database. This shortest example uses
the config driver, which fits brands managed through deployments. Use the
[database driver][database-brands] when customers create or edit their branding
at runtime; the Blade code below stays the same.

Define a default brand and a domain-specific brand in
`config/whitelabel.php`:

```php
'default' => 'default',

'brands' => [
    'default' => [
        'name' => 'Example',
        'logo' => ['disk' => 'public', 'path' => 'brands/default/logo.svg'],
        'favicon' => ['disk' => 'public', 'path' => 'brands/default/favicon.svg'],
        'colors' => ['primary' => '#111827'],
    ],

    'acme' => [
        'name' => 'Acme',
        'domain' => 'acme.test',
        'logo' => ['disk' => 'public', 'path' => 'brands/acme/logo.svg'],
        'colors' => ['primary' => '#7c3aed'],
        'settings' => ['support_url' => 'https://support.acme.test'],
    ],
],
```

Render the active brand in a shared Blade layout:

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

A request for `acme.test` uses Acme's name, logo, colour, and support URL. Its
missing favicon falls back to the default brand's favicon.

## What is included

- Config and database repositories behind one public contract.
- An ordered resolver chain for explicit, tenant, domain, and default brands.
- Per-key fallback with deliberate clearing for empty values.
- Storage-backed logo, favicon, and custom asset URLs.
- Blade components for logos, favicons, and CSS custom properties.
- Branded Markdown mail and an opt-in sender override.
- Brand context restoration for jobs, mailables, notifications, and listeners.
- Optional Spatie Multitenancy integration.
- Runtime definitions, testing helpers, lifecycle events, and cache management.

## Documentation

The [versioned documentation][documentation] contains the full setup, guides,
behavior details, and API reference:

- [Installation and setup][installation]
- [Quick start][quick-start]
- [Brand definitions and fallback][brand-definitions]
- [Brand resolution][brand-resolution]
- [Blade components][blade-components]
- [Mail and notifications][mail]
- [Queues][queues]
- [Spatie Multitenancy][multitenancy]
- [Database brands][database-brands]
- [Configuration reference][configuration]
- [Public API reference][public-api]
- [Testing][testing]
- [Troubleshooting][troubleshooting]

## Development

The local checks mirror CI:

```bash
composer install
composer test
composer analyse
vendor/bin/pint --test
```

PHPStan runs at `max` with no baseline. Tests run against SQLite locally and
against MySQL and PostgreSQL in CI. The public API test pins the supported
classes and methods. See [CONTRIBUTING.md](CONTRIBUTING.md) for the full
development workflow.

`workbench/` is a bootable demo application that exercises both repositories,
the resolver chain, Blade components, branded mail, queued work, Spatie
Multitenancy, and the testing helpers. Run `composer build`, then follow the
[workbench demo loop](workbench/README.md).

## Out of scope

The package will not include:

- Any admin or management UI.
- Asset uploads, image manipulation, or favicon generation.
- Theme or CSS compilation, including per-brand Tailwind builds.
- Per-brand routes, feature flags, content localization, or Blade directories.
- Tenancy, user management, billing, authorization, or database isolation.

## Versioning

The package follows [semantic versioning](https://semver.org/spec/v2.0.0.html).

- Upgrading within `1.x` is safe. Nothing you use will break.
- Only a new major version, like `2.0.0`, can break your code.
- If the README or the documentation describes it, it is safe to build on.
  If they don't, treat it as internal and expect it to change.

Bug fixes go into the newest version only. To get a fix, upgrade to it.

## Questions and issues

- **Stuck, or have an idea?** Start a
  [discussion](https://github.com/byrcsc/laravel-whitelabel/discussions). Usage
  questions and feature ideas both live there.
- **Found a bug you can reproduce?**
  [Open an issue](https://github.com/byrcsc/laravel-whitelabel/issues). A
  failing test is the fastest way to a fix, and a short reproduction is the
  next best thing.
- **Found a security problem?** Please don't open a public issue. See
  [SECURITY.md](SECURITY.md) for how to report it privately.
- **Planning a pull request?** [CONTRIBUTING.md](CONTRIBUTING.md) covers the
  setup and the three checks it needs to pass.

This package is maintained by one person, so replies can take a while.
Everything gets read.

## Credits

- [Ryan Catapang](https://github.com/byrcsc)
- [All contributors](https://github.com/byrcsc/laravel-whitelabel/graphs/contributors)

## License

MIT. See [LICENSE.md](LICENSE.md). Changelog in [CHANGELOG.md](CHANGELOG.md).

[blade-components]: https://docs.rcsc.dev/laravel-whitelabel/v1/blade-components
[brand-definitions]: https://docs.rcsc.dev/laravel-whitelabel/v1/brand-definitions
[brand-resolution]: https://docs.rcsc.dev/laravel-whitelabel/v1/brand-resolution
[configuration]: https://docs.rcsc.dev/laravel-whitelabel/v1/configuration
[database-brands]: https://docs.rcsc.dev/laravel-whitelabel/v1/database-brands
[documentation]: https://docs.rcsc.dev/laravel-whitelabel/v1/introduction
[installation]: https://docs.rcsc.dev/laravel-whitelabel/v1/installation
[mail]: https://docs.rcsc.dev/laravel-whitelabel/v1/mail
[multitenancy]: https://docs.rcsc.dev/laravel-whitelabel/v1/multitenancy
[public-api]: https://docs.rcsc.dev/laravel-whitelabel/v1/public-api
[queues]: https://docs.rcsc.dev/laravel-whitelabel/v1/queues
[quick-start]: https://docs.rcsc.dev/laravel-whitelabel/v1/quick-start
[testing]: https://docs.rcsc.dev/laravel-whitelabel/v1/testing
[troubleshooting]: https://docs.rcsc.dev/laravel-whitelabel/v1/troubleshooting
