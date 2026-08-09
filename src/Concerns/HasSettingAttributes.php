<?php

declare(strict_types=1);

namespace Timadey\LazySettings\Concerns;

use ReflectionEnumUnitCase;
use Timadey\LazySettings\Attributes\Setting;
use Timadey\LazySettings\Casts\SettingType;
use Timadey\LazySettings\SettingAttributeCache;

/**
 * Satisfies the SettingKey contract from the #[Setting] attribute.
 *
 *   enum GeneralSettings: string implements SettingKey
 *   {
 *       use HasSettingAttributes;
 *
 *       #[Setting(type: SettingType::Float, default: 0.0)]
 *       case RATE = 'rate';
 *   }
 *
 * Any of the four contract methods can be overridden in the enum; use a trait
 * alias to fall back to the attribute value for the other cases:
 *
 *   use HasSettingAttributes { default as staticDefault; }
 *
 *   public function default(): mixed
 *   {
 *       return match ($this) {
 *           self::CUG_MONTH => now()->format('M'),
 *           default => $this->staticDefault(),
 *       };
 *   }
 */
trait HasSettingAttributes
{
    public function type(): SettingType
    {
        return $this->lazySettingAttribute()->type;
    }

    public function default(): mixed
    {
        return $this->lazySettingAttribute()->default;
    }

    public function allowed(): array
    {
        return $this->lazySettingAttribute()->allowed;
    }

    public function nullable(): bool
    {
        return $this->lazySettingAttribute()->nullable;
    }

    public function encrypt(): bool
    {
        return $this->lazySettingAttribute()->encrypt;
    }

    private function lazySettingAttribute(): Setting
    {
        $key = static::class . '::' . $this->name;

        return SettingAttributeCache::remember($key, fn () => $this->resolveSettingAttribute());
    }

    private function resolveSettingAttribute(): Setting
    {
        $attributes = (new ReflectionEnumUnitCase(static::class, $this->name))
            ->getAttributes(Setting::class);

        $attribute = $attributes[0] ?? null;

        return $attribute?->newInstance() ?? new Setting();
    }
}