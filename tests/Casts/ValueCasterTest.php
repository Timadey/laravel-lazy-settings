<?php

use Timadey\LazySettings\Casts\ValueCaster;
use Timadey\LazySettings\Tests\Fixtures\TestSettings;

it('casts each type before storage', function () {
    expect(ValueCaster::toStorage(TestSettings::SITE_NAME, 'Acme'))->toBe('Acme');
    expect(ValueCaster::toStorage(TestSettings::ENABLED, 1))->toBe('1');
    expect(ValueCaster::toStorage(TestSettings::ENABLED, false))->toBe('0');
    expect(ValueCaster::toStorage(TestSettings::COUNTER, '42'))->toBe('42');
    expect(ValueCaster::toStorage(TestSettings::RATE, '9.5'))->toBe('9.5');
    expect(ValueCaster::toStorage(TestSettings::TAGS, ['a', 'b']))->toBe('["a","b"]');
    expect(ValueCaster::toStorage(TestSettings::PROVIDER, 'zepalink'))->toBe('zepalink');
});

it('casts each type after reading from storage', function () {
    expect(ValueCaster::fromStorage(TestSettings::ENABLED, '1'))->toBeTrue();
    expect(ValueCaster::fromStorage(TestSettings::ENABLED, '0'))->toBeFalse();
    expect(ValueCaster::fromStorage(TestSettings::COUNTER, '42'))->toBe(42);
    expect(ValueCaster::fromStorage(TestSettings::RATE, '9.5'))->toBe(9.5);
    expect(ValueCaster::fromStorage(TestSettings::TAGS, '["a","b"]'))->toBe(['a', 'b']);
    expect(ValueCaster::fromStorage(TestSettings::PROVIDER, 'monnify'))->toBe('monnify');
});

it('throws on invalid type in strict mode', function () {
    ValueCaster::toStorage(TestSettings::COUNTER, 'not-a-number');
})->throws(InvalidArgumentException::class);

it('throws on non-allowed enum value in strict mode', function () {
    ValueCaster::toStorage(TestSettings::PROVIDER, 'suspicious');
})->throws(InvalidArgumentException::class);

it('throws on null when not nullable', function () {
    ValueCaster::toStorage(TestSettings::SITE_NAME, null);
})->throws(InvalidArgumentException::class);

it('allows null when nullable', function () {
    expect(ValueCaster::toStorage(TestSettings::PROFILE, null))->toBeNull();
});

it('coerces instead of throwing when not strict', function () {
    expect(ValueCaster::toStorage(TestSettings::COUNTER, 'nope', strict: false))->toBe('0');
    expect(ValueCaster::toStorage(TestSettings::PROVIDER, 'suspicious', strict: false))->toBe('suspicious');
});