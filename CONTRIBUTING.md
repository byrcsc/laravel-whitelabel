# Contributing

Thanks for helping improve Laravel Whitelabel.

## Before you start

- **Usage question or an idea?** Open a
  [discussion](https://github.com/byrcsc/laravel-whitelabel/discussions) first.
- **Reproducible bug?** Open an
  [issue](https://github.com/byrcsc/laravel-whitelabel/issues) with a failing
  test or a short reproduction.
- **Security problem?** Do not open a public issue. See
  [SECURITY.md](SECURITY.md).

Check the "Out of scope" section of the README before proposing a feature.
Changes to those boundaries will not be accepted as feature requests.

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

Run `composer clear` to remove the generated Testbench application.

## The three checks

A pull request needs all three of these green. CI runs the same commands.

```bash
composer test      # Pest, the full suite
composer analyse   # PHPStan at max level, no baseline
composer format    # Laravel Pint, writes fixes in place
```

Run `composer format` last so it does not fight with your editor.

## Pull requests

- Keep each pull request focused on one concern.
- Add a test for every behaviour change.
- Add a line under `[Unreleased]` in [CHANGELOG.md](CHANGELOG.md) for anything
  a user would notice.
- Public API additions need an entry in the versioned documentation and the
  public API surface test. If the README or documentation does not describe
  it, it is internal.
- Keep the commit history readable. Squashing before review is fine.

## Commits and branches

Branch from `main` using `feat/`, `fix/`, `docs/`, `refactor/`, or `chore/`.
Use [Conventional Commits](https://www.conventionalcommits.org/) for commit
messages.

## What counts as a breaking change

Anything documented in the README, versioned documentation, or surface test is
public API and is covered by semantic versioning. Everything else, including
the internal Eloquent model, can change in a patch release.
