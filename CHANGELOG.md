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
