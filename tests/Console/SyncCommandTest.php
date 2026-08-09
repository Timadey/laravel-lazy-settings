<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Timadey\LazySettings\Tests\Fixtures\ScopedTestStore;
use Timadey\LazySettings\Tests\Fixtures\TestSettings;
use Timadey\LazySettings\Tests\Fixtures\TestStore;

beforeEach(function () {
    Cache::flush();
    DB::table('test_settings')->truncate();
    DB::table('scoped_test_settings')->truncate();
});

it('prompts for and inserts missing settings', function () {
    $this->artisan('settings:sync', ['--store' => TestStore::class])
        ->expectsQuestion('SITE_NAME (site_name) | type: string | Enter value (Enter to skip)', 'Acme')
        ->expectsQuestion('ENABLED (enabled) | type: bool | Enter true/false, yes/no, y/n, 1/0 (Enter to skip)', 'yes')
        ->expectsQuestion('RATE (rate) | type: float | Enter value (Enter to skip)', '12.5')
        ->expectsQuestion('COUNTER (counter) | type: int | Enter value (Enter to skip)', '5')
        ->expectsQuestion('MAX_ATTEMPTS (max_attempts) | type: float | Enter value (Enter to skip)', '3')
        ->expectsQuestion('PROVIDER (provider) | type: enum | allowed: zepalink, monnify | Enter value (Enter to skip)', 'zepalink')
        ->expectsQuestion('TAGS (tags) | type: json | Enter comma-separated values (Enter to skip)', 'a, b')
        ->expectsQuestion('PROFILE (profile) | type: json | Enter comma-separated values (Enter to skip)', '')
        ->assertExitCode(0);

    expect(DB::table('test_settings')->where('key', 'site_name')->value('value'))->toBe('Acme');
    expect(DB::table('test_settings')->where('key', 'enabled')->value('value'))->toBe('1');
    expect(DB::table('test_settings')->where('key', 'counter')->value('value'))->toBe('5');
    expect(DB::table('test_settings')->where('key', 'provider')->value('value'))->toBe('zepalink');
    expect(DB::table('test_settings')->where('key', 'tags')->value('value'))->toBe('["a","b"]');
});

it('skips settings that already exist unless --all is given', function () {
    TestStore::set(TestSettings::SITE_NAME, 'Existing');
    TestStore::set(TestSettings::ENABLED, false);

    expect(
        $this->artisan('settings:sync', [
            '--store' => TestStore::class,
            '--no-interaction' => true,
        ])->run()
    )->toBe(1);
});

it('rejects non-interactive usage', function () {
    $this->artisan('settings:sync', [
        '--store' => ScopedTestStore::class,
        '--scope' => '7',
        '--no-interaction' => true,
    ])
        ->expectsOutputToContain('requires an interactive terminal')
        ->assertExitCode(1);
});