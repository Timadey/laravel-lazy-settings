# Laravel Lazy Settings

Enum-driven settings for Laravel. Use it two ways: **app-global admin settings** (one shared copy) or **per-entity settings** (the same schema, isolated per user, vendor, or tenant). Define every setting once as a backed enum — type, default, allowed values, nullable, and encryption all live on one case. Rows are only written when a value actually changes; unset keys return the declared default.

| At a glance                                        |                                                                    |
| -------------------------------------------------- | ------------------------------------------------------------------ |
| **Lazy persistence**                               | unset keys return the default and never hit the database.          |
| **Enum is the schema**                             | type, default, allowed list, nullable, encryption in one place.    |
| **Global or per-entity**                           | one shared copy, or isolated copies per user/vendor/tenant.        |
| **Strict, validated, typed**                       | bad writes throw; bad declared defaults throw at first touch.      |
| **Encrypted at rest, per key**                     | app `Crypt` (your `APP_KEY`), never leaked on a failed decrypt.    |

> **Why not just spatie?** spatie is excellent for app-global, typed settings. This package adds what spatie structurally can't: **the enum as the schema** (with display metadata for admin panels) and **per-entity scoping** (one enum, one entry, many isolated copies). Both still work for plain global settings — more at the end.

## Contents

1. [Quick start](#quick-start)
2. [Global app settings (admin panel)](#global-app-settings-admin-panel)
3. [Per-entity settings (scoped)](#per-entity-settings-scoped)
4. [Declaring the schema](#declaring-the-schema)
5. [Validation & strictness](#validation--strictness)
6. [Encryption](#encryption)
7. [Cache & troubleshooting](#cache--troubleshooting)
8. [CLI](#cli)
9. [API reference](#api-reference)
10. [UI add-on, tests, license](#ui-add-on-tests-license)
## Requirements

- PHP ^8.2
- Laravel ^10 | ^11 | ^12

## Installation

```bash
composer require timadey/laravel-lazy-settings
```

Optionally publish the config:

```bash
php artisan vendor:publish --tag=lazy-settings-config
```

> `coerce` disables strict throwing, `store_path`/`enum_path` set where `make:settings-store` writes stores and enums, `cache` tunes the cache.

### Config & per-store overrides

Every config key can be overridden on an individual store — the store-level override **always wins** over the config value:

| Config key    | Default    | Override on a store                              |
| ------------- | ---------- | ------------------------------------------------ |
| `coerce`      | `false`    | `protected static bool $coerce = true;`          |
| `store_path`  | `Models`   | *(codegen only — no store override)*             |
| `enum_path`   | `Enums`    | *(codegen only — no store override)*             |
| `cache.store` | `null`     | `protected static ?string $cacheStore = 'redis';`|
| `cache.ttl`   | `864000`   | `protected static int $cacheTtl = 60;`           |

```php
class PlatformSettings extends SettingsStore
{
    protected static string $table = 'platform_settings';

    protected static string $enum = PlatformSettingsEnum::class;

    protected static bool $coerce = true;                    // strict -> coerce
    protected static ?string $cacheStore = 'redis';          // use redis, not the app default
    protected static int $cacheTtl = 60;                     // 60s instead of 10 days

    // protected static bool $timestamps = false;            // legacy table w/o timestamp columns
}
```

Stores maintain `created_at`/`updated_at` by default: fresh writes stamp both, later writes bump `updated_at` while preserving `created_at`. Point a store at a table missing those columns by setting `protected static bool $timestamps = false;`.

<details>
<summary>Published config</summary>

```php
return [
    'coerce' => false,          // best-effort coercion instead of throwing
    'store_path' => 'Models',   // where make:settings-store writes stores (relative to app/)
    'enum_path' => 'Enums',     // where make:settings-store writes enums (relative to app/)

    'cache' => [
        'store' => null,       // null = the app default cache store
        'ttl' => 864000,       // 10 days
    ],
];
```

</details>

## Quick start

Build an app-global settings store for a settings page. Two commands, one enum, done.

```bash
php artisan make:settings-store PlatformSettings
```

This creates `app/Models/PlatformSettings.php` and `app/Enums/PlatformSettingsEnum.php`. Fill the enum cases:

```php
namespace App\Enums;

use Timadey\LazySettings\Attributes\Setting;
use Timadey\LazySettings\Casts\SettingType;
use Timadey\LazySettings\Concerns\HasSettingAttributes;
use Timadey\LazySettings\Contracts\SettingKey;

enum PlatformSettingsEnum: string implements SettingKey
{
    use HasSettingAttributes;

    #[Setting(type: SettingType::String, default: 'My App')]
    case SITE_NAME = 'site_name';

    #[Setting(type: SettingType::Bool, default: true)]
    case MAINTENANCE_MODE = 'maintenance_mode';

    #[Setting(type: SettingType::Float, default: 1.50)]
    case REBATE_DEPLOY_FEE = 'rebate_deploy_fee';

    #[Setting(type: SettingType::Int, default: 3)]
    case MAX_LOGIN_ATTEMPTS = 'max_login_attempts';

    #[Setting(type: SettingType::Enum, allowed: ['zepalink', 'monnify'], default: 'zepalink')]
    case PAYMENT_PROVIDER = 'payment_provider';

    #[Setting(type: SettingType::Json, default: ['buy_data', 'buy_airtime'])]
    case SPY_PAGE_RESTRICTIONS = 'spy_page_restrictions';

    #[Setting(type: SettingType::String, default: '', encrypt: true)]
    case WEBHOOK_SECRET = 'webhook_secret';
}
```

Read and write — no scope argument anywhere:

```php
use App\Enums\PlatformSettingsEnum;
use App\Models\PlatformSettings;

// Read
PlatformSettings::get(PlatformSettingsEnum::MAINTENANCE_MODE);      // true
PlatformSettings::getByKey('site_name');                           // 'My App'
PlatformSettings::allSettings();                                   // every key: value or default

// Write
PlatformSettings::set(PlatformSettingsEnum::MAX_LOGIN_ATTEMPTS, 5);
PlatformSettings::setByKey('maintenance_mode', false);
```

You're done. Unset keys return the declared default; `set()` inserts or updates lazily and busts the cache.

## Global app settings (admin panel)

The quick start is the global case end-to-end. This section shows the details you'll touch as the panel grows.

### The generated store

`make:settings-store PlatformSettings` writes (all global — no scope column):

```php
namespace App\Models;

use App\Enums\PlatformSettingsEnum;
use Timadey\LazySettings\SettingsStore;

class PlatformSettings extends SettingsStore
{
    protected static string $table = 'platform_settings';

    protected static string $enum = PlatformSettingsEnum::class;

    // protected static ?string $scopeColumn = null; // null = globally scoped
}
```

And a migration file — run `php artisan migrate`:

```php
Schema::create('platform_settings', function (Blueprint $table) {
    $table->id();
    $table->string('key');
    $table->text('value')->nullable();
    $table->timestamps();
    $table->unique(['key']);
});
```

### What the generator writes

The one command produces three files, fully wired so it works out of the box:

- **Store** — `app/Models/<Name>.php`, extends `Timadey\LazySettings\SettingsStore`, sets `$table` and `$enum` (importing the enum, since it lives in the `Enums` namespace). `$scopeColumn` is set when you pass `--scope=`, otherwise written as a **commented** line (`// protected static ?string $scopeColumn = null;`) so the global intent is visible. The optional overrides (`$coerce`, `$cacheStore`, `$cacheTtl`) aren't written — add them yourself per the table above.
- **Enum** — `app/Enums/<Name>Enum.php`, imports `Setting`, `SettingType`, `HasSettingAttributes`, and `SettingKey`; includes `use HasSettingAttributes;` and one starter case you replace with your own schema. Both `store_path` and `enum_path` config keys control where each file goes (defaults `Models` / `Enums`).
- **Migration** — the full table above; `unique(['key'])` for global, `unique([scope, 'key'])` for scoped.

### Render and save a settings form

`allSettings()` gives you every key with stored value or default — the whole page in one cached query:

```php
class PlatformSettingsController extends Controller
{
    public function edit()
    {
        return view('admin.settings', ['settings' => PlatformSettings::allSettings()]);
    }

    public function update(Request $request)
    {
        PlatformSettings::setByKey('site_name', $request->string('site_name'));
        PlatformSettings::set(PlatformSettingsEnum::MAINTENANCE_MODE, $request->boolean('maintenance_mode'));

        return back()->with('status', 'Saved.');
    }
}
```

- `setByKey()` lets the form post plain strings — cast to the key's declared type at the model boundary, not by hand.
- Writes validate: `PlatformSettings::set(PlatformSettingsEnum::MAX_LOGIN_ATTEMPTS, 'many')` throws `InvalidArgumentException`.
- `forget()` deletes a row; it returns `true` if a row existed.
- Cache key is `lazy-settings.platform_settings` — one flat map for the whole app, busted on every write.

## Per-entity settings (scoped)

Need the same schema per user or vendor? Add a scope column.

```bash
php artisan make:settings-store UserPreferences --scope=user_id
```

The store now reads and writes through `user_id`:

```php
use App\Enums\UserPreferencesEnum;
use Timadey\LazySettings\SettingsStore;

class UserPreferences extends SettingsStore
{
    protected static string $table = 'user_preferences_settings';

    protected static string $enum = UserPreferencesEnum::class;

    protected static ?string $scopeColumn = 'user_id';
}
```

The migration gains the scope column and merges it into the unique key:

```php
$table->unsignedBigInteger('user_id')->nullable()->index();
$table->unique(['user_id', 'key']);
```

Read and write now take the scope value (`$userId`) as the trailing argument:

```php
use App\Enums\UserPreferencesEnum;
use App\Models\UserPreferences;

UserPreferences::set(UserPreferencesEnum::THEME, 'dark', 1);
UserPreferences::get(UserPreferencesEnum::THEME, 1); // 'dark'
UserPreferences::get(UserPreferencesEnum::THEME, 2); // default — not 'dark'
```

Each scope value is fully isolated. A scoped read never falls back to another scope's data — only to the enum default. Each scope caches its own map:

```
lazy-settings.user_preferences_settings.user_id:1
lazy-settings.user_preferences_settings.user_id:2
```

Writes bust only that scope's cache, so user B's cached map is untouched when user A saves. Use whatever entity you scope by — `user_id`, `vendor_id`, `tenant_id`.

## Declaring the schema

### Attribute style (recommended)

`#[Setting(...)]` per case + `HasSettingAttributes`. Override any contract method and fall back to the attribute with a trait alias:

```php
use HasSettingAttributes { default as staticDefault; }

#[Setting(type: SettingType::String)]
case MONTHLY_QUOTA = 'monthly_quota';

public function default(): mixed
{
    return match ($this) {
        self::MONTHLY_QUOTA => now()->month,
        default => $this->staticDefault(),
    };
}
```

### Method style

Write the contract by hand. `type()` must return a real `SettingType` case — the engine matches exhaustively, so a typo is impossible rather than a silent fallback.

```php
enum UserPreferencesEnum: string implements SettingKey
{
    case THEME = 'theme';

    public function type(): SettingType { return SettingType::String; }
    public function default(): mixed { return 'light'; }
    public function allowed(): array { return []; }
    public function nullable(): bool { return false; }
    public function encrypt(): bool { return false; }
}
```

### Contract reference

| Method       | Returns      | Meaning                                                        |
| ------------ | ------------ | -------------------------------------------------------------- |
| `type()`     | `SettingType`| Storage type; drives casting, validation and the sync prompt. |
| `default()`  | `mixed`      | Value returned before the key is ever written. Never persisted. |
| `allowed()`  | `array`      | Whitelist for `Enum` (and `Json`) keys; empty = nothing enforced. |
| `nullable()` | `bool`       | Whether `null` is a legal write. Default `false`.              |
| `encrypt()`  | `bool`       | Whether the stored value is encrypted at rest. Default `false`. |

```php
// storage <-> PHP
enum SettingType: string
{
    case Int; case Float; case Bool;
    case String; case Json; case Enum;
}
```

## Validation & strictness

### Declared defaults are validated at boot

On first touch, every declared default is pushed through the cast pipeline. A wrong-typed default throws immediately, naming the case:

```
Invalid declaration for App\Models\PlatformSettingsEnum::MAX_LOGIN_ATTEMPTS: Invalid value [lots] for App\Models\PlatformSettingsEnum::MAX_LOGIN_ATTEMPTS; expected int.
```

`SchemaException` at first touch beats a silent wrong value in production.

### Writes are strict by default

A mismatched write throws `InvalidArgumentException`:

```
Invalid value [many] for App\Models\PlatformSettingsEnum::MAX_LOGIN_ATTEMPTS; expected int.
```

Opt out per store with `protected static bool $coerce = true;`, or globally with `config('lazy-settings.coerce')` — both cast best-effort instead of throwing.

## Encryption

Per-case `encrypt: true` stores ciphertext using your app's `Crypt` (your `APP_KEY`):

```php
#[Setting(type: SettingType::String, default: '', encrypt: true)]
case WEBHOOK_SECRET = 'webhook_secret';
```

- The persisted row **and** cache hold ciphertext; plaintext only exists in memory between decrypt-on-read and use.
- **Defaults are never encrypted** — not persisted, readable in the enum.
- **Fail hard**: a value that isn't valid ciphertext throws `DecryptException` on read — no raw bytes leaked.
- **Migration caveat**: only add `encrypt: true` to a key whose existing rows are already ciphertext. Existing plaintext rows throw on first read — call `set()` on those keys once first.
- `allRaw()` returns stored bytes as-is (ciphertext for encrypted keys). Key rotation is Laravel's built-in `old_key` array config.

## Cache & troubleshooting

- **TTL**: `config('lazy-settings.cache.ttl')` (default 10 days), or `protected static int $cacheTtl` per store — see [Config & per-store overrides](#config--per-store-overrides).
- **Store**: `config('lazy-settings.cache.store')`, or `protected static ?string $cacheStore`.
- **Busting**: any write, or `flushCache()` for a scope; `php artisan cache:clear` clears everything.
- **Stale cache after raw SQL**: if you touch the settings table directly, flush that scope's cache before reading.
- **`DecryptException` after enabling encryption**: rotate existing rows through `set()` once; if a row is genuinely corrupt, delete and re-`set()`.
- **Strict vs coerce**: `'5'` stored for an Int setting reads back as `5` in both modes — the difference is only whether a mismatched *write* throws up front.

## CLI

```bash
# Interactive bootstrap of missing settings (per store, optional scope):
php artisan settings:sync --store=App\Models\PlatformSettings

# Include already-set settings and re-prompt:
php artisan settings:sync --store=App\Models\UserPreferences --scope=7 --all

# Generate a table migration:
php artisan settings:table platform_settings
php artisan settings:table user_preferences_settings --scope=user_id

# Generate store + enum + migration in one command:
php artisan make:settings-store PlatformSettings
php artisan make:settings-store UserPreferences --scope=user_id
```

`settings:sync` prompts only for missing keys unless `--all` is passed, uses a setting's `label()` when the enum implements `Timadey\LazySettings\Contracts\SettingLabels` (else the case name), and refuses a non-interactive terminal (`--no-interaction`).

## API reference

All methods are **static**. `...$scope` is one scope value for a scoped store (e.g. `$userId`), or nothing for a global store.

| Method                                                          | Returns                      | Notes                                                                  |
| --------------------------------------------------------------- | ---------------------------- | ---------------------------------------------------------------------- |
| `get(SettingKey $setting, ...$scope)`                           | `mixed`                      | Typed value, or the cast default if unset.                             |
| `getByKey(string $key, ...$scope)`                              | `mixed`                      | Same, keyed by storage string; no enum case required.                  |
| `getSettingsByKeys(array<int, SettingKey\|string>, ...$scope)`  | `array<string, mixed>`       | One cached read; keyed by storage string.                              |
| `allSettings(...$scope)`                                        | `array<string, mixed>`       | Every declared key, stored value or default, cast.                     |
| `allRaw(...$scope)`                                             | `array<string, string\|null>`| Raw stored bytes — no defaults, no casting (ciphertext if encrypted).  |
| `set(SettingKey $key, mixed $value, ...$scope)`                 | `bool`                       | `updateOrInsert`; validates, casts, encrypts, flushes cache.           |
| `setByKey(string $key, mixed $value, ...$scope)`                | `bool`                       | Like `set`, keyed by string.                                           |
| `forget(SettingKey\|string $key, ...$scope)`                    | `bool`                       | Deletes the row + busts cache (`true` if a row existed).               |
| `flushCache(...$scope)`                                         | `void`                       | Forget this scope's cached map.                                        |
| `isStrict()`                                                    | `bool`                       | `true` when enforcement is on (neither store nor config coerces).      |
| `enumCases()`                                                   | `SettingKey[]`               | Every enum case implementing the contract.                             |
| `caseForKey(string $key)`                                       | `SettingKey\|null`           | Reverse lookup by storage string.                                      |

## UI add-on, tests, license

- **UI add-on (planned, not yet published)**: a companion package for admin panels (display names, groups, dropdown options, `structure()`) is on the roadmap. Core stays fully functional without it. The optional `Timadey\LazySettings\Contracts\SettingLabels` marker already exists so a future add-on is detected automatically via interface checks.

- **Why not just spatie (the details)?** spatie gives you real PHP property typing, property-level locking, encryption, and versioned data migrations — real wins for app-global settings. This package takes a different trade: the enum *is* the schema (no per-setting migration, one source of truth that can also render an admin panel), and the same schema serves N isolated entities with zero extra tables. You give up native property typing and spatie's migration workflows in exchange for zero-schema-friction and per-entity scoping.

- **Tests**: `composer install && ./vendor/bin/pest`.

- **License**: MIT — see [LICENSE.md](LICENSE.md).