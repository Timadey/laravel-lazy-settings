<?php

declare(strict_types=1);

namespace Timadey\LazySettings\Tests\Fixtures;

use Timadey\LazySettings\SettingsStore;

class BadStore extends SettingsStore
{
    protected static string $table = 'bad_test_settings';

    protected static string $enum = BadSettings::class;
}