<?php

declare(strict_types=1);

namespace Timadey\LazySettings\Tests\Fixtures;

use Timadey\LazySettings\SettingsStore;

class EncryptedStore extends SettingsStore
{
    protected static string $table = 'encrypted_test_settings';

    protected static string $enum = EncryptedSettings::class;
}