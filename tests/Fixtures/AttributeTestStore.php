<?php

declare(strict_types=1);

namespace Timadey\LazySettings\Tests\Fixtures;

use Timadey\LazySettings\SettingsStore;

class AttributeTestStore extends SettingsStore
{
    protected static string $table = 'attribute_test_settings';

    protected static string $enum = AttributeSettings::class;
}