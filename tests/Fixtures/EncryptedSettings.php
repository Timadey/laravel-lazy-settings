<?php

declare(strict_types=1);

namespace Timadey\LazySettings\Tests\Fixtures;

use Timadey\LazySettings\Attributes\Setting;
use Timadey\LazySettings\Casts\SettingType;
use Timadey\LazySettings\Concerns\HasSettingAttributes;
use Timadey\LazySettings\Contracts\SettingKey;

enum EncryptedSettings: string implements SettingKey
{
    use HasSettingAttributes;

    #[Setting(type: SettingType::String, default: 'Default Corp')]
    case SITE_NAME = 'site_name';

    #[Setting(type: SettingType::String, default: 'zepalink')]
    case PROVIDER = 'provider';

    #[Setting(type: SettingType::String, default: '', encrypt: true)]
    case MTN_TOKEN = 'mtn_token';

    #[Setting(type: SettingType::String, encrypt: true, nullable: true)]
    case WEBHOOK_SECRET = 'webhook_secret';
}