<?php

declare(strict_types=1);

namespace Timadey\LazySettings\Concerns;

use BackedEnum;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Timadey\LazySettings\Casts\ValueCaster;
use Timadey\LazySettings\Contracts\SettingKey;
use Timadey\LazySettings\Exceptions\InvalidValueException;
use Timadey\LazySettings\Exceptions\SchemaException;

/**
 * Engine shared by every settings store.
 *
 * The consuming class configures a static surface:
 *
 *   protected static string $enum         = VendorSettingsEnum::class;
 *   protected static string $table        = 'vendor_settings';
 *   protected static ?string $scopeColumn = 'vendor_id'; // null = global scope
 *   protected static bool $coerce         = false;        // strict by default
 *
 * Values are typed via the enum's SettingKey contract driven by the declared
 * SettingType. Storage is lazy: a row is only written when set() is called;
 * unset keys fall back to the enum's declared default and are never
 * persisted. The full schema is validated once per enum at first touch so a
 * bad declared default fails loudly at boot, not as a silent wrong value.
 */
trait LazySettings
{
    /**
     * Enum classes whose schema has already been validated this request.
     *
     * @var array<string, true>
     */
    private static array $validatedSchemas = [];

    /**
     * Table to read/write setting rows from.
     */
    protected static string $table = 'settings';

    /**
     * Backed enum implementing SettingKey for the store's schema.
     */
    protected static string $enum;

    /**
     * Column used to scope the store per entity (e.g. 'vendor_id').
     * Null means the store is globally scoped (single shared settings).
     */
    protected static ?string $scopeColumn = null;

    /**
     * Whether to coerce values instead of throwing on type mismatches.
     */
    protected static bool $coerce = false;

    /**
     * Cache TTL in seconds (default 10 days).
     */
    protected static int $cacheTtl = 864000;

    /**
     * Laravel cache store name; null means the app default store.
     */
    protected static ?string $cacheStore = null;

    /**
     * Whether to maintain created_at/updated_at on insert and updated_at on
     * update. Disable for tables that predate the package's timestamps
     * migration columns.
     */
    protected static bool $timestamps = true;

    /**
     * Get a typed setting by enum, falling back to the (cast) declared default.
     */
    public static function get(SettingKey $setting, ...$scope): mixed
    {
        static::validateSchema();

        $raw = static::cachedFlat(...$scope)[$setting->value] ?? null;

        if ($raw === null) {
            return ValueCaster::default($setting);
        }

        return ValueCaster::fromStorage($setting, static::decrypt($setting, $raw));
    }

    /**
     * Get a setting by string key (escape hatch for keys without an enum case).
     */
    public static function getByKey(string $key, ...$scope): mixed
    {
        static::validateSchema();

        $flat = static::cachedFlat(...$scope);
        $raw = $flat[$key] ?? null;

        $case = static::caseForKey($key);

        if ($case === null) {
            return $raw;
        }

        if ($raw === null) {
            return ValueCaster::default($case);
        }

        return ValueCaster::fromStorage($case, static::decrypt($case, $raw));
    }

    /**
     * Get multiple settings in a single cached read (no extra queries).
     *
     * @param  array<int, SettingKey|string>  $keys
     * @return array<string, mixed>  keyed by enum->value / string key
     */
    public static function getSettingsByKeys(array $keys, ...$scope): array
    {
        static::validateSchema();

        $flat = static::cachedFlat(...$scope);

        $result = [];

        foreach ($keys as $key) {
            $case = is_string($key) ? static::caseForKey($key) : $key;
            $mapKey = $case instanceof BackedEnum ? $case->value : $key;
            $raw = $flat[$mapKey] ?? null;

            $result[$mapKey] = $case === null
                ? $raw
                : ($raw === null ? ValueCaster::default($case) : ValueCaster::fromStorage($case, static::decrypt($case, $raw)));
        }

        return $result;
    }

    /**
     * All settings as a flat [key => typed value] map (defaults merged in).
     */
    public static function allSettings(...$scope): array
    {
        static::validateSchema();

        $flat = static::cachedFlat(...$scope);

        $defaults = static::cases()
            ->mapWithKeys(fn (SettingKey $case) => [$case->value => ValueCaster::default($case)]);

        return $defaults->merge($flat)
            ->map(function (mixed $value, string $key) use ($flat) {
                $case = static::caseForKey($key);

                return $case instanceof SettingKey && array_key_exists($key, $flat) && $value !== null
                    ? ValueCaster::fromStorage($case, static::decrypt($case, $value))
                    : $value;
            })
            ->all();
    }

    /**
     * All stored settings raw (no defaults, no casting).
     */
    public static function allRaw(...$scope): array
    {
        return static::cachedFlat(...$scope);
    }

    /**
     * Persist a setting, creating the row lazily. Calls flushCache on write.
     */
    public static function set(SettingKey $key, mixed $value, ...$scope)
    {
        static::validateSchema();

        $stored = ValueCaster::toStorage($key, $value, static::isStrict());

        $result = static::table(...$scope)
            ->updateOrInsert(
                static::identityFor($key->value, ...$scope),
                fn (bool $exists) => array_merge(
                    ['value' => static::encrypt($key, $stored)],
                    static::timestampsFor($exists)
                )
            );

        static::flushCache(...$scope);

        return $result;
    }

    /**
     * Persist a setting by string key (no enum case needed).
     */
    public static function setByKey(string $key, mixed $value, ...$scope)
    {
        static::validateSchema();

        $case = static::caseForKey($key);

        $stored = $case instanceof SettingKey && $case->value === $key
            ? ValueCaster::toStorage($case, $value, static::isStrict())
            : (string) $value;

        $result = static::table(...$scope)
            ->updateOrInsert(
                static::identityFor($key, ...$scope),
                fn (bool $exists) => array_merge(
                    ['value' => static::encrypt($case, $stored)],
                    static::timestampsFor($exists)
                )
            );

        static::flushCache(...$scope);

        return $result;
    }

    /**
     * Forget a setting (delete row + bust cache).
     */
    public static function forget(SettingKey|string $key, ...$scope): bool
    {
        $key = $key instanceof BackedEnum ? $key->value : $key;

        $affected = static::table(...$scope)
            ->where('key', $key)
            ->delete();

        static::flushCache(...$scope);

        return $affected > 0;
    }

    /**
     * Forget the cached flat map for the given scope.
     */
    public static function flushCache(...$scope): void
    {
        Cache::store(static::cacheStoreName())->forget(static::cacheKey(...$scope));
    }

    /**
     * Whether strict mode (throw on mismatch) is enabled.
     */
    public static function isStrict(): bool
    {
        return ! config('lazy-settings.coerce', false) && ! static::$coerce;
    }

    /**
     * Timestamp columns to persist for the given write. Fresh rows stamp both
     * created_at and updated_at; existing rows only bump updated_at so the
     * original created_at is preserved. Returns an empty array when the store
     * opts out of timestamp maintenance.
     */
    protected static function timestampsFor(bool $exists): array
    {
        if (! static::$timestamps) {
            return [];
        }

        $now = now();

        return $exists
            ? ['updated_at' => $now]
            : ['created_at' => $now, 'updated_at' => $now];
    }

    /**
     * Encrypt a stored value at rest when the key declares encryption.
     * Nulls and unencrypted keys pass through untouched.
     */
    protected static function encrypt(?SettingKey $key, ?string $stored): ?string
    {
        if ($stored === null || $key === null || ! $key->encrypt()) {
            return $stored;
        }

        return Crypt::encryptString($stored);
    }

    /**
     * Decrypt a stored value on read when the key declares encryption.
     * Nulls and unencrypted keys pass through untouched. Values that fail to
     * decrypt throw (fail hard) rather than leaking raw bytes.
     */
    protected static function decrypt(SettingKey $key, mixed $raw): mixed
    {
        if ($raw === null || ! $key->encrypt()) {
            return $raw;
        }

        return Crypt::decryptString((string) $raw);
    }

    /**
     * All enum cases implementing SettingKey for this store, as an array.
     *
     * @return array<int, SettingKey>
     */
    public static function enumCases(): array
    {
        return static::$enum::cases();
    }

    /**
     * All enum cases implementing SettingKey for this store.
     *
     * @return Collection<int, SettingKey>
     */
    protected static function cases(): Collection
    {
        return collect(static::enumCases());
    }

    /**
     * Find an enum case by its backing value, or null.
     */
    public static function caseForKey(string $key): ?SettingKey
    {
        return collect(static::enumCases())->first(fn (SettingKey $case) => $case->value === $key);
    }

    /**
     * Validate every declared default once per enum at first touch.
     *
     * Iterates the schema and pushes every default through the full cast
     * pipeline; a bad default (wrong type, out-of-allowed, null-but-not
     * nullable) throws a SchemaException that names the case.
     */
    protected static function validateSchema(): void
    {
        $enumClass = static::$enum;

        if (isset(static::$validatedSchemas[$enumClass])) {
            return;
        }

        foreach (static::enumCases() as $case) {
            try {
                ValueCaster::default($case);
            } catch (InvalidValueException $e) {
                throw SchemaException::for($case::class . '::' . $case->name, $e->getMessage());
            }
        }

        static::$validatedSchemas[$enumClass] = true;
    }

    /**
     * Load the flat raw [key => value] map for the scope (cached).
     */
    protected static function cachedFlat(...$scope): array
    {
        return Cache::store(static::cacheStoreName())->remember(
            static::cacheKey(...$scope),
            static::cacheTtl(),
            fn () => static::table(...$scope)
                ->pluck('value', 'key')
                ->map(fn ($row) => $row === null ? null : (string) $row)
                ->all()
        );
    }

    /**
     * Query builder scoped for the store's scope column and provided scopes.
     */
    protected static function table(...$scope): \Illuminate\Database\Query\Builder
    {
        $query = DB::table(static::$table);

        foreach (static::scopeWheres(...$scope) as $column => $value) {
            $query->where($column, $value);
        }

        return $query;
    }

    /**
     * Resolve [column => value] lookup conditions from the variadic scope.
     */
    protected static function scopeWheres(...$scope): array
    {
        if (static::$scopeColumn === null) {
            return [];
        }

        $values = array_values($scope);

        if (count($values) === 1) {
            return [static::$scopeColumn => $values[0]];
        }

        return [];
    }

    /**
     * Identity (key + scope wheres) for updateOrCreate.
     */
    protected static function identityFor(string $key, ...$scope): array
    {
        return array_merge(['key' => $key], static::scopeWheres(...$scope));
    }

    /**
     * Cache TTL for the flat map.
     */
    protected static function cacheTtl(): int
    {
        return (int) config('lazy-settings.cache.ttl', static::$cacheTtl);
    }

    /**
     * Cache store name to use.
     */
    protected static function cacheStoreName(): ?string
    {
        return static::$cacheStore ?? config('lazy-settings.cache.store');
    }

    /**
     * Cache key for a given scope.
     */
    protected static function cacheKey(...$scope): string
    {
        $prefix = 'lazy-settings.' . static::$table;

        $wheres = static::scopeWheres(...$scope);

        if (empty($wheres)) {
            return $prefix;
        }

        $parts = [];
        foreach ($wheres as $column => $value) {
            $parts[] = $column . ':' . (string) $value;
        }

        return $prefix . '.' . implode('.', $parts);
    }
}