# Changelog

All notable changes to `timadey/laravel-lazy-settings` are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.1] - 2026-08-10

### Added

- Timestamp management: `set()` / `setByKey()` now maintain `created_at` and `updated_at`.
  - Fresh writes stamp both columns; subsequent writes bump `updated_at` while preserving the original `created_at`.
  - Legacy tables without timestamp columns can opt out per store via `protected static bool $timestamps = false;`.
- `enum_path` config key (default `Enums`) so `make:settings-store` writes generated enums to a separate location from store classes.

### Changed

- `set()` / `setByKey()` now persist through `updateOrInsert`'s callable `$values` form, branching on insert vs. update so timestamps are written correctly.

## [0.1.0] - 2026-08-09

First release.

### Added

- Enum-driven settings stores (`SettingsStore` base class + `LazySettings` engine).
- Lazy persistence: a row is only written on `set()`, unmatched reads fall back to declared enum defaults.
- Typed values via `SettingType` casting (`string`, `bool`, `int`, `float`, `array`, `enum`, `nullable`) with strict mode that throws on mismatches (`config('lazy-settings.coerce')` / per-store `$coerce`).
- Per-scope settings through a `$scopeColumn` (e.g. `vendor_id`) with isolated caching.
- Flat-map caching (`cache.store` / `cache.ttl` config, per-store `$cacheStore` / `$cacheTtl` overrides).
- At-rest encryption for keys declaring `encrypt()`.
- Attribute-driven schema resolution via `#[Setting]` attributes (`HasSettingAttributes`).
- Schema validation at first touch — invalid declared defaults fail loudly.
- Console commands:
  - `make:settings-store` — generates a store, its enum and table migration.
  - `settings:table` — generates a migration for an existing table.
  - `settings:sync` — interactively syncs missing settings from a store enum.
- Tests (Pest) covering casts, encryption, attributes, schema validation, console commands and storage behaviour.