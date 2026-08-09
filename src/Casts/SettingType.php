<?php

declare(strict_types=1);

namespace Timadey\LazySettings\Casts;

use Timadey\LazySettings\Exceptions\InvalidValueException;

/**
 * Storage types a setting can declare.
 *
 * Each case owns its own serialisation logic, so the engine never switches
 * on magic strings (an exhausted match on this enum is checked by static
 * analysis, and a typo is impossible).
 */
enum SettingType: string
{
    case Int = 'int';
    case Float = 'float';
    case Bool = 'bool';
    case String = 'string';
    case Json = 'json';
    case Enum = 'enum';

    /**
     * Serialize a non-null runtime value to its storage string.
     *
     * @param  array<int|string, mixed>  $allowed  allowed values for 'enum' type
     * @param  bool  $strict  throw instead of best-effort coercing on mismatch
     * @param  string  $context  human-readable key/case, used in exceptions
     */
    public function toStorage(mixed $value, array $allowed = [], bool $strict = true, string $context = 'setting'): string
    {
        return match ($this) {
            self::Int => $this->numeric($value, $strict, $context, 'int'),
            self::Float => $this->numeric($value, $strict, $context, 'float'),
            self::Bool => $this->boolValue($value, $strict, $context),
            self::String => (string) $value,
            self::Json => $this->jsonValue($value, $strict, $context),
            self::Enum => $this->enumValue($value, $allowed, $strict, $context),
        };
    }

    /**
     * Cast a raw stored value back to its runtime PHP representation.
     */
    public function fromStorage(mixed $raw): mixed
    {
        if ($raw === null) {
            return null;
        }

        return match ($this) {
            self::Int => (int) $raw,
            self::Float => (float) $raw,
            self::Bool => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
            self::Json => json_decode((string) $raw, true),
            self::String, self::Enum => $raw,
        };
    }

    private function numeric(mixed $value, bool $strict, string $context, string $type): string
    {
        if (is_numeric($value)) {
            return $type === 'int' ? (string) (int) $value : (string) (float) $value;
        }

        if (! $strict) {
            return $type === 'int' ? (string) (int) $value : (string) (float) $value;
        }

        throw InvalidValueException::for($value, $context, $type);
    }

    private function boolValue(mixed $value, bool $strict, string $context): string
    {
        if ($value === 1 || $value === true || $value === '1') {
            return '1';
        }

        if ($value === 0 || $value === false || $value === '0') {
            return '0';
        }

        if (is_string($value)) {
            $normalized = strtolower($value);

            if (in_array($normalized, ['true', 'yes', 'y'], true)) {
                return '1';
            }

            if (in_array($normalized, ['false', 'no', 'n'], true)) {
                return '0';
            }
        }

        if (! $strict) {
            return (bool) $value ? '1' : '0';
        }

        throw InvalidValueException::for($value, $context, 'bool');
    }

    private function jsonValue(mixed $value, bool $strict, string $context): string
    {
        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]';
        }

        if (is_string($value) && json_decode($value, true) !== null) {
            return $value;
        }

        if (! $strict) {
            return json_encode([$value], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]';
        }

        throw InvalidValueException::for($value, $context, 'json');
    }

    private function enumValue(mixed $value, array $allowed, bool $strict, string $context): string
    {
        if (empty($allowed) || in_array($value, $allowed, true)) {
            return (string) $value;
        }

        if (! $strict) {
            return (string) $value;
        }

        throw InvalidValueException::for($value, $context, 'enum');
    }
}