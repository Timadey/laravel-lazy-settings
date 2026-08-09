<?php

declare(strict_types=1);

namespace Timadey\LazySettings\Tests\Fixtures;

use Timadey\LazySettings\Attributes\Setting;
use Timadey\LazySettings\Casts\SettingType;
use Timadey\LazySettings\Concerns\HasSettingAttributes;
use Timadey\LazySettings\Contracts\SettingKey;

/**
 * Pure attribute-style enum: schema declared with #[Setting] and the contract
 * satisfied by the HasSettingAttributes trait. Any method can be overridden
 * for dynamic behaviour, falling back to the attribute via use-alias.
 */
enum AttributeSettings: string implements SettingKey
{
    use HasSettingAttributes {
        default as staticDefault;
        allowed as staticAllowed;
        type as staticType;
    }

    #[Setting(type: SettingType::String, default: 'Attribute Corp')]
    case SITE_NAME = 'site_name';

    #[Setting(type: SettingType::Bool, default: true)]
    case ENABLED = 'enabled';

    #[Setting(type: SettingType::Float, default: 0.0)]
    case RATE = 'rate';

    #[Setting(type: SettingType::Int, default: 0)]
    case COUNTER = 'counter';

    #[Setting(type: SettingType::Enum, allowed: ['zepalink', 'monnify'], default: 'zepalink')]
    case PROVIDER = 'provider';

    #[Setting(type: SettingType::String)]
    case LABEL = 'label';

    // Dynamic default: resolved per read, attribute value via staticDefault().
    public function default(): mixed
    {
        return match ($this) {
            self::LABEL => 'custom-' . date('Y'),
            default => $this->staticDefault(),
        };
    }

    // Everything else stays on the attribute.
    public function type(): SettingType
    {
        return $this->staticType();
    }

    public function allowed(): array
    {
        return $this->staticAllowed();
    }
}