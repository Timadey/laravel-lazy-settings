<?php

declare(strict_types=1);

namespace Timadey\LazySettings\Tests\Fixtures;

use Timadey\LazySettings\SettingsStore;

class CoerciveTestStore extends SettingsStore
{
    protected static string $table = 'coercive_test_settings';

    protected static string $enum = TestSettings::class;

    protected static bool $coerce = true;
}