<?php

declare(strict_types=1);

namespace Timadey\LazySettings\Tests\Fixtures;

use Timadey\LazySettings\Casts\SettingType;
use Timadey\LazySettings\Contracts\SettingKey;

enum TestSettings: string implements SettingKey
{
    case SITE_NAME = 'site_name';
    case ENABLED = 'enabled';
    case RATE = 'rate';
    case COUNTER = 'counter';
    case MAX_ATTEMPTS = 'max_attempts';
    case PROVIDER = 'provider';
    case TAGS = 'tags';
    case PROFILE = 'profile';

    public function type(): SettingType
    {
        return match ($this) {
            self::SITE_NAME => SettingType::String,
            self::ENABLED => SettingType::Bool,
            self::RATE, self::MAX_ATTEMPTS => SettingType::Float,
            self::COUNTER => SettingType::Int,
            self::PROVIDER => SettingType::Enum,
            self::TAGS, self::PROFILE => SettingType::Json,
        };
    }

    public function default(): mixed
    {
        return match ($this) {
            self::SITE_NAME => 'Default Corp',
            self::ENABLED => true,
            self::RATE => 0.0,
            self::COUNTER => 0,
            self::MAX_ATTEMPTS => 3.0,
            self::PROVIDER => 'zepalink',
            self::TAGS => ['tag-a'],
            self::PROFILE => null,
        };
    }

    public function allowed(): array
    {
        return match ($this) {
            self::PROVIDER => ['zepalink', 'monnify'],
            default => [],
        };
    }

    public function nullable(): bool
    {
        return in_array($this, [self::PROFILE], true);
    }

    public function encrypt(): bool
    {
        return false;
    }
}