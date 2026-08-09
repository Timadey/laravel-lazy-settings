<?php

declare(strict_types=1);

namespace Timadey\LazySettings;

use Timadey\LazySettings\Concerns\LazySettings;

/**
 * Base store that consumers extend, e.g.:
 *
 *   class VendorSettings extends SettingsStore
 *   {
 *       protected static string $enum         = VendorSettingsEnum::class;
 *       protected static string $table        = 'vendor_settings';
 *       protected static ?string $scopeColumn = 'vendor_id';
 *   }
 *
 * The static API (get()/set()/...) is inherited from the LazySettings trait.
 */
class SettingsStore
{
    use LazySettings;
}