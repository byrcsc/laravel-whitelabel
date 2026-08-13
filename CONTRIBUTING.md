# Contributing

Thanks for wanting to help. This package is maintained by one person, so
replies can take a while. Everything gets read.

## Before you start

- **Usage question or an idea?** Open a
  [discussion](https://github.com/byrcsc/laravel-whitelabel/discussions) first.
- **Reproducible bug?** Open an
  [issue](https://github.com/byrcsc/laravel-whitelabel/issues) with a failing
  test or a short reproduction.
- **Security problem?** Do not open a public issue. See
  [SECURITY.md](SECURITY.md).

Check the "Out of scope" section of the README before proposing a feature.
Some things are deliberately excluded and will be declined no matter how good
the implementation is.

## Setup

```bash
git clone https://github.com/byrcsc/laravel-whitelabel
cd laravel-whitelabel
composer install
```

You need PHP 8.3 or newer. Nothing else.

To boot the demo application in `workbench/`:

```bash
composer build
php vendor/bin/testbench serve
```

## The three checks

A pull request needs all three of these green. CI runs the same commands.

```bash
composer test      # Pest, the full suite
composer analyse   # PHPStan at max level, no baseline
composer format    # Laravel Pint, writes fixes in place
```

Run `composer format` last so it does not fight with your editor.

## Pull requests

- One concern per pull request. Small is easier to review than complete.
- Add a test. A behaviour change without a test will be asked for one.
- Add a line under `[Unreleased]` in [CHANGELOG.md](CHANGELOG.md) for anything
  a user would notice.
- Public API additions need a line in `README.md` and an entry in the public
  API surface test. If the README does not describe it, it is internal.
- Keep the commit history readable. Squashing before review is fine.

## What counts as a breaking change

Anything documented in the README or the surface test is public API and is
covered by semantic versioning. Everything else, including the internal
Eloquent model, can change in a patch release.
