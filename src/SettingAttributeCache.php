<?php

declare(strict_types=1);

namespace Timadey\LazySettings;

use Timadey\LazySettings\Attributes\Setting;

/**
 * Runtime cache for resolved #[Setting] attribute instances.
 *
 * Enums cannot declare properties, so attribute instances resolved by
 * HasSettingAttributes are cached here (keyed by case FQCN) instead.
 */
class SettingAttributeCache
{
    /**
     * @var array<string, Setting>
     */
    private static array $attributes = [];

    public static function remember(string $key, \Closure $resolver): Setting
    {
        return self::$attributes[$key] ??= $resolver();
    }
}