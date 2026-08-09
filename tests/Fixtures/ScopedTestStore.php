<?php

declare(strict_types=1);

namespace Timadey\LazySettings\Tests\Fixtures;

use Timadey\LazySettings\SettingsStore;

class ScopedTestStore extends SettingsStore
{
    protected static string $table = 'scoped_test_settings';

    protected static string $enum = TestSettings::class;

    protected static ?string $scopeColumn = 'vendor_id';

    protected static bool $coerce = false;
}