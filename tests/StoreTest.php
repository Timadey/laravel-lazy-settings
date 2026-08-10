<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Timadey\LazySettings\Tests\Fixtures\CoerciveTestStore;
use Timadey\LazySettings\Tests\Fixtures\NoTimestampsStore;
use Timadey\LazySettings\Tests\Fixtures\ScopedTestStore;
use Timadey\LazySettings\Tests\Fixtures\TestSettings;
use Timadey\LazySettings\Tests\Fixtures\TestStore;

beforeEach(function () {
    Cache::flush();
    DB::table('test_settings')->truncate();
    DB::table('scoped_test_settings')->truncate();
    DB::table('coercive_test_settings')->truncate();
    DB::table('attribute_test_settings')->truncate();
});

it('reads defaults for unset settings without writing rows', function () {
    expect(TestStore::get(TestSettings::SITE_NAME))->toBe('Default Corp');
    expect(TestStore::get(TestSettings::ENABLED))->toBeTrue();

    expect(DB::table('test_settings')->count())->toBe(0);
});

it('persists a typed value lazily on set', function () {
    TestStore::set(TestSettings::SITE_NAME, 'Acme Corp');

    expect(DB::table('test_settings')->count())->toBe(1);
    expect(DB::table('test_settings')->where('key', 'site_name')->value('value'))->toBe('Acme Corp');
    expect(TestStore::get(TestSettings::SITE_NAME))->toBe('Acme Corp');
});

it('casts values back to their declared types', function () {
    TestStore::set(TestSettings::COUNTER, 7);
    expect(TestStore::get(TestSettings::COUNTER))->toBe(7);

    TestStore::set(TestSettings::RATE, 12.5);
    expect(TestStore::get(TestSettings::RATE))->toBe(12.5);

    TestStore::set(TestSettings::ENABLED, true);
    expect(TestStore::get(TestSettings::ENABLED))->toBeTrue();

    TestStore::set(TestSettings::TAGS, ['x', 'y']);
    expect(TestStore::get(TestSettings::TAGS))->toBe(['x', 'y']);
});

it('throws on invalid enum values in strict mode', function () {
    TestStore::set(TestSettings::PROVIDER, 'not-allowed');
})->throws(InvalidArgumentException::class);

it('coerces instead of throwing in non-strict mode', function () {
    CoerciveTestStore::set(TestSettings::COUNTER, 'nope');

    expect(CoerciveTestStore::get(TestSettings::COUNTER))->toBe(0);
});

it('scopes per vendor and keeps caches isolated', function () {
    ScopedTestStore::set(TestSettings::SITE_NAME, 'Vendor One', 1);
    ScopedTestStore::set(TestSettings::SITE_NAME, 'Vendor Two', 2);

    expect(ScopedTestStore::get(TestSettings::SITE_NAME, 1))->toBe('Vendor One');
    expect(ScopedTestStore::get(TestSettings::SITE_NAME, 2))->toBe('Vendor Two');

    $rows = DB::table('scoped_test_settings')->get();
    expect($rows)->toHaveCount(2);
    expect($rows->pluck('vendor_id')->all())->toBe([1, 2]);
});

it('falls back to defaults per scope with no row pollution', function () {
    expect(ScopedTestStore::get(TestSettings::SITE_NAME, 99))->toBe('Default Corp');
    expect(DB::table('scoped_test_settings')->count())->toBe(0);
});

it('fetches multiple keys in a single cached read', function () {
    TestStore::set(TestSettings::SITE_NAME, 'Acme');
    TestStore::set(TestSettings::ENABLED, true);

    Cache::flush();

    $result = TestStore::getSettingsByKeys(['site_name', 'enabled']);

    expect($result)->toBe([
        'site_name' => 'Acme',
        'enabled' => true,
    ]);
});

it('forgets a key and busts cache', function () {
    TestStore::set(TestSettings::SITE_NAME, 'Acme');

    expect(TestStore::forget(TestSettings::SITE_NAME))->toBeTrue();
    expect(DB::table('test_settings')->count())->toBe(0);
    expect(TestStore::get(TestSettings::SITE_NAME))->toBe('Default Corp');
});

it('exposes all settings with defaults merged', function () {
    TestStore::set(TestSettings::SITE_NAME, 'Acme');

    $all = TestStore::allSettings();

    expect($all['site_name'])->toBe('Acme');
    expect($all['enabled'])->toBeTrue();
    expect($all['counter'])->toBe(0);
});

it('supports string-key escape hatch without an enum case', function () {
    TestStore::setByKey('custom_thing', 'value');

    expect(TestStore::getByKey('custom_thing'))->toBe('value');
});

it('returns declared defaults already cast to their PHP type', function () {
    expect(TestStore::get(TestSettings::RATE))->toBe(0.0);
    expect(TestStore::get(TestSettings::COUNTER))->toBe(0);
    expect(TestStore::get(TestSettings::ENABLED))->toBeTrue();
});

it('uses distinct cache keys per scope', function () {
    ScopedTestStore::set(TestSettings::SITE_NAME, 'One', 1);

    $keyOne = 'lazy-settings.scoped_test_settings.vendor_id:1';
    $keyTwo = 'lazy-settings.scoped_test_settings.vendor_id:2';

    expect(Cache::has($keyOne))->toBeFalse();

    ScopedTestStore::get(TestSettings::SITE_NAME, 1);
    expect(Cache::has($keyOne))->toBeTrue();
    expect(Cache::has($keyTwo))->toBeFalse();

    ScopedTestStore::set(TestSettings::SITE_NAME, 'Two', 2);
    expect(Cache::has($keyTwo))->toBeFalse();
});

it('stamps created_at and updated_at on first insert', function () {
    TestStore::set(TestSettings::SITE_NAME, 'Acme Corp');

    $row = DB::table('test_settings')->where('key', 'site_name')->first();

    expect($row->created_at)->not->toBeNull();
    expect($row->updated_at)->not->toBeNull();
    expect($row->created_at)->toBe($row->updated_at);
});

it('bumps updated_at but preserves created_at on update', function () {
    TestStore::set(TestSettings::SITE_NAME, 'Acme Corp');

    $first = DB::table('test_settings')->where('key', 'site_name')->first();
    $beforeCreated = $first->created_at;
    $beforeUpdated = $first->updated_at;

    $this->travel(1)->hour();
    TestStore::set(TestSettings::SITE_NAME, 'New Corp');

    $second = DB::table('test_settings')->where('key', 'site_name')->first();

    expect($second->created_at)->toBe($beforeCreated);
    expect($second->updated_at)->toBeGreaterThan($beforeUpdated);
});

it('leaves timestamps null when the store opts out', function () {
    NoTimestampsStore::set(TestSettings::SITE_NAME, 'Acme Corp');

    $row = DB::table('test_settings')->where('key', 'site_name')->first();

    expect($row->created_at)->toBeNull();
    expect($row->updated_at)->toBeNull();
});