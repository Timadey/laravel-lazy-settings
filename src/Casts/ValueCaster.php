<?php

declare(strict_types=1);

namespace Timadey\LazySettings\Casts;

use Timadey\LazySettings\Contracts\SettingKey;
use Timadey\LazySettings\Exceptions\InvalidValueException;

/**
 * Thin facade between the engine and SettingType.
 *
 * Ownership splits cleanly: null/nullable policy lives here (it depends on
 * the key's contract methods), while primitive serialisation belongs to the
 * SettingType enum (exhaustive, statically checkable).
 */
class ValueCaster
{
    /**
     * Cast a value BEFORE storage. Throws by default; coerces when $strict is false.
     *
     * Returns the string to persist in the single `value` column.
     */
    public static function toStorage(SettingKey $setting, mixed $value, bool $strict = true): ?string
    {
        if ($value === null) {
            if (! $setting->nullable()) {
                throw new InvalidValueException(
                    'Setting [' . static::describe($setting) . '] does not allow null.'
                );
            }

            return null;
        }

        return $setting->type()->toStorage($value, $setting->allowed(), $strict, static::describe($setting));
    }

    /**
     * Cast a raw DB value back to its runtime PHP representation.
     */
    public static function fromStorage(SettingKey $setting, mixed $raw): mixed
    {
        return $setting->type()->fromStorage($raw);
    }

    /**
     * Cast a key's declared default through the full pipeline so a bad default
     * is caught at the harness's schema validation instead of leaking raw.
     *
     * Nullable-and-null defaults round-trip to null.
     */
    public static function default(SettingKey $setting): mixed
    {
        $stored = static::toStorage($setting, $setting->default(), strict: true);

        return $stored === null ? null : static::fromStorage($setting, $stored);
    }

    protected static function describe(SettingKey $setting): string
    {
        return $setting::class . '::' . $setting->name;
    }
}