<?php

declare(strict_types=1);

namespace Timadey\LazySettings\Tests\Fixtures;

use Timadey\LazySettings\Casts\SettingType;
use Timadey\LazySettings\Contracts\SettingKey;

/**
 * Deliberately broken schema: RATE declares int but defaults to a non-numeric
 * string, COIN declares bool but defaults to null while not nullable.
 * Used to prove schema validation fires at boot.
 */
enum BadSettings: string implements SettingKey
{
    case RATE = 'rate';
    case COIN = 'coin';

    public function type(): SettingType
    {
        return match ($this) {
            self::RATE => SettingType::Int,
            self::COIN => SettingType::Bool,
        };
    }

    public function default(): mixed
    {
        return match ($this) {
            self::RATE => 'not-a-number',
            self::COIN => null,
        };
    }

    public function allowed(): array
    {
        return [];
    }

    public function nullable(): bool
    {
        return false;
    }

    public function encrypt(): bool
    {
        return false;
    }
}