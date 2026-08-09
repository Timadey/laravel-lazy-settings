<?php

declare(strict_types=1);

namespace Timadey\LazySettings\Exceptions;

use RuntimeException;

/**
 * Thrown when a setting enum's declared schema (a declared default, allowed
 * value, or type) is invalid. Raised at boot time so bad declarations surface
 * loudly on first touch, not as a silent wrong value in production.
 */
class SchemaException extends RuntimeException
{
    public static function for(string $case, string $reason): self
    {
        return new self("Invalid declaration for {$case}: {$reason}");
    }
}