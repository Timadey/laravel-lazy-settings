<?php

declare(strict_types=1);

namespace Timadey\LazySettings\Tests\Fixtures;

use Timadey\LazySettings\Attributes\Setting;
use Timadey\LazySettings\SettingsStore;

class NullDefaultStore extends SettingsStore
{
    protected static string $table = 'null_default_test_settings';

    protected static string $enum = NullDefaultBool::class;
}