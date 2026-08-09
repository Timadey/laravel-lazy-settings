<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Timadey\LazySettings\Tests\Fixtures\AttributeSettings;
use Timadey\LazySettings\Tests\Fixtures\AttributeTestStore;

beforeEach(function () {
    Cache::flush();
    DB::table('attribute_test_settings')->truncate();
});

it('reads attribute-declared typed defaults', function () {
    expect(AttributeTestStore::get(AttributeSettings::SITE_NAME))->toBe('Attribute Corp');
    expect(AttributeTestStore::get(AttributeSettings::ENABLED))->toBeTrue();
    expect(AttributeTestStore::get(AttributeSettings::RATE))->toBe(0.0);
    expect(AttributeTestStore::get(AttributeSettings::COUNTER))->toBe(0);

    expect(DB::table('attribute_test_settings')->count())->toBe(0);
});

it('enforces allowed values declared via attribute in strict mode', function () {
    AttributeTestStore::set(AttributeSettings::PROVIDER, 'not-allowed');
})->throws(InvalidArgumentException::class);

it('uses method override for dynamic defaults, falling back to attribute', function () {
    expect(AttributeTestStore::get(AttributeSettings::LABEL))->toBe('custom-' . date('Y'));

    expect(DB::table('attribute_test_settings')->count())->toBe(0);
});

it('persists and round-trips attribute-typed values', function () {
    AttributeTestStore::set(AttributeSettings::COUNTER, 7);
    AttributeTestStore::set(AttributeSettings::PROVIDER, 'monnify');

    expect(AttributeTestStore::get(AttributeSettings::COUNTER))->toBe(7);
    expect(AttributeTestStore::get(AttributeSettings::PROVIDER))->toBe('monnify');
});

it('is valid for the attribute enum schema', function () {
    expect(AttributeTestStore::get(AttributeSettings::SITE_NAME))->toBe('Attribute Corp');
});