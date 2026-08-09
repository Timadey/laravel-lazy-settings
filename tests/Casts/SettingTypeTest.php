<?php

use Timadey\LazySettings\Casts\SettingType;
use Timadey\LazySettings\Exceptions\InvalidValueException;

it('is an exhausted enum of the supported storage types', function () {
    expect(SettingType::cases())->toHaveCount(6);
    expect(array_column(SettingType::cases(), 'value'))->toBe(['int', 'float', 'bool', 'string', 'json', 'enum']);
});

it('serialises each type to storage', function () {
    expect(SettingType::Int->toStorage(42))->toBe('42');
    expect(SettingType::Float->toStorage(12.5))->toBe('12.5');
    expect(SettingType::Bool->toStorage(true))->toBe('1');
    expect(SettingType::String->toStorage('x'))->toBe('x');
    expect(SettingType::Json->toStorage(['a' => 1]))->toBe('{"a":1}');
    expect(SettingType::Enum->toStorage('zepalink', ['zepalink', 'monnify']))->toBe('zepalink');
});

it('casts each type back from storage', function () {
    expect(SettingType::Int->fromStorage('42'))->toBe(42);
    expect(SettingType::Float->fromStorage('12.5'))->toBe(12.5);
    expect(SettingType::Bool->fromStorage('1'))->toBeTrue();
    expect(SettingType::Bool->fromStorage('0'))->toBeFalse();
    expect(SettingType::Json->fromStorage('{"a":1}'))->toBe(['a' => 1]);
    expect(SettingType::Enum->fromStorage('zepalink'))->toBe('zepalink');
});

it('throws on numeric mismatch in strict mode', function () {
    SettingType::Int->toStorage('nope');
})->throws(InvalidValueException::class);

it('coerces numeric mismatch when not strict', function () {
    expect(SettingType::Int->toStorage('nope', strict: false))->toBe('0');
    expect(SettingType::Float->toStorage('nope', strict: false))->toBe('0');
});

it('throws on disallowed enum value in strict mode', function () {
    SettingType::Enum->toStorage('nope', allowed: ['zepalink', 'monnify']);
})->throws(InvalidValueException::class);

it('allows any value for enum without an allowed list', function () {
    expect(SettingType::Enum->toStorage('anything'))->toBe('anything');
});

it('handles null raw values', function () {
    expect(SettingType::Int->fromStorage(null))->toBeNull();
});