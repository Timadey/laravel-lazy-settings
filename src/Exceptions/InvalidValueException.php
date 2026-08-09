<?php

declare(strict_types=1);

namespace Timadey\LazySettings\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when a value does not match a setting's declared type.
 */
class InvalidValueException extends InvalidArgumentException
{
    public static function for(mixed $value, string $context, string $type): self
    {
        return new self(
            'Invalid value [' . self::describe($value) . '] for ' . $context . '; expected ' . $type . '.'
        );
    }

    protected static function describe(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            return json_encode($value) ?: gettype($value);
        }

        return (string) $value;
    }
}