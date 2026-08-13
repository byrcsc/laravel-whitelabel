# Changelog

All notable changes to `laravel-whitelabel` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- The immutable `Brand` object, with dot-notation access across the fixed core
  and the settings bag, and per-key fallback to the default brand.
- `BrandAsset`, the disk-and-path pair behind a brand's logo and favicon.
- `BrandDefinition`, the brand schema every driver validates against.
- The `BrandRepository` contract and the read-only config driver behind it.
- `BrandRepositoryManager`, for registering a brand driver of your own.
- The database driver: a publishable `brands` migration and full CRUD through
  the repository contract.
- `BrandCreated`, `BrandUpdated`, and `BrandDeleted`, fired by repository
  writes.
- `BrandAlreadyExists` and `UnknownBrand`, so a collision or a missing brand
  never surfaces as a raw query exception.
- Lazy runtime resolution through a configurable resolver chain, with the
  `OverrideResolver`, `TenantResolver`, `DomainResolver`, and `DefaultResolver`
  shipped in it.
- The `Whitelabel` facade and the `brand()` helper.
- `BrandActivated` and `BrandDeactivated`, fired once per lifecycle transition.
- The optional `EagerResolveBrand` middleware, which resolves at request start
  and shares the brand with every view.
- A caching layer in front of every writable driver: per-brand entries stored
  forever, a domain index, and invalidation on write.
- The `whitelabel:clear` command.
- The Spatie Multitenancy integration: the `ProvidesBrand` interface,
  `SwitchTenantBrandTask`, and a `TenantResolver` that reads the current tenant
  without naming a Spatie class.
- The `BrandAware` trait, which runs a queued job, mailable, or notification
  with the brand that was active when it was dispatched, and
  `CapturedBrandMissing` for when that brand has since been deleted.
- Storage-backed asset URLs: `Brand::logoUrl()`, `faviconUrl()`, and
  `assetUrl()`, with `whitelabel.assets.disk` as the default disk.
- The `<x-whitelabel::styles />`, `<x-whitelabel::logo />`, and
  `<x-whitelabel::favicon />` components, and publishable views behind them.
- Branded markdown mail: the brand's logo in the header and its primary colour
  on the primary button, behind `whitelabel.mail.markdown`.
- The opt-in `whitelabel.mail.override_from`, which sends mail from the active
  brand's own name and address.
- The `InteractsWithBrands` testing trait, with `actingWithBrand()` and
  `defineBrand()`.
- The `whitelabel:install` command.
