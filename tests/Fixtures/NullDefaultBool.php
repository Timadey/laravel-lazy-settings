<?php

declare(strict_types=1);

namespace Timadey\LazySettings\Tests\Fixtures;

use Timadey\LazySettings\Attributes\Setting;
use Timadey\LazySettings\Casts\SettingType;
use Timadey\LazySettings\Concerns\HasSettingAttributes;
use Timadey\LazySettings\Contracts\SettingKey;

/**
 * Non-nullable bool that defaults to null -> should fail schema validation.
 */
enum NullDefaultBool: string implements SettingKey
{
    use HasSettingAttributes;

    #[Setting(type: SettingType::Bool, default: null)]
    case FLAG = 'flag';
}