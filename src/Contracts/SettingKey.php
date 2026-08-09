<?php

declare(strict_types=1);

namespace Timadey\LazySettings\Contracts;

use Timadey\LazySettings\Casts\SettingType;

/**
 * Implement on any backed enum whose cases are setting keys.
 *
 * The case's ->value is the storage key. The declared type drives casting,
 * validation and defaults. Every method has a default implementation supplied
 * by the HasSettingAttributes trait when using the #[Setting] attribute; a
 * minimal enum only needs to override what it actually uses.
 */
interface SettingKey
{
    /**
     * Storage type for the key. Drives caster behaviour and allowed values.
     */
    public function type(): SettingType;

    /**
     * App-side default returned when the key has never been written.
     * NOT persisted to the database.
     */
    public function default(): mixed;

    /**
     * Allowed values for a SettingType::Enum key. Empty array = nothing enforced.
     */
    public function allowed(): array;

    /**
     * Whether a null value is permitted. When false a null write throws
     * (respecting the store's strictness).
     */
    public function nullable(): bool;

    /**
     * Whether the stored value should be encrypted at rest with the app's
     * Crypt. When true the persisted and cached value is ciphertext and is
     * decrypted on read. Defaults are never encrypted (not persisted).
     */
    public function encrypt(): bool;
}