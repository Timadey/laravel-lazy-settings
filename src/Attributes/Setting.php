<?php

declare(strict_types=1);

namespace Timadey\LazySettings\Attributes;

use Attribute;
use Timadey\LazySettings\Casts\SettingType;

/**
 * Declares a setting case's static schema inline.
 *
 *   #[Setting(type: SettingType::Float, default: 0.0)]
 *   case RATE = 'rate';
 *
 *   #[Setting(type: SettingType::String, encrypt: true)]
 *   case MTN_TOKEN = 'mtn_token';
 *
 * Combined with the HasSettingAttributes trait it satisfies the SettingKey
 * contract. When a case needs dynamic metadata (a default deriving from
 * now(), an allowed() list reading config), override the matching method in
 * the enum and fall back to the trait's implementation via a use-alias.
 */
#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
final class Setting
{
    public function __construct(
        public readonly SettingType $type = SettingType::String,
        public readonly mixed $default = null,
        public readonly array $allowed = [],
        public readonly bool $nullable = false,
        public readonly bool $encrypt = false,
    ) {
    }
}