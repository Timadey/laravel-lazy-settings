<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Timadey\LazySettings\Tests\Fixtures\EncryptedSettings;
use Timadey\LazySettings\Tests\Fixtures\EncryptedStore;

beforeEach(function () {
    Cache::flush();
    DB::table('encrypted_test_settings')->truncate();
});

it('stores encrypted keys as ciphertext but plain keys verbatim', function () {
    EncryptedStore::set(EncryptedSettings::MTN_TOKEN, 'secret-token');
    EncryptedStore::set(EncryptedSettings::SITE_NAME, 'Acme');

    $stored = DB::table('encrypted_test_settings')->where('key', 'mtn_token')->value('value');

    expect($stored)->not->toBe('secret-token');
    expect(Crypt::decryptString($stored))->toBe('secret-token');

    expect(DB::table('encrypted_test_settings')->where('key', 'site_name')->value('value'))->toBe('Acme');
});

it('returns decrypted values on read', function () {
    EncryptedStore::set(EncryptedSettings::MTN_TOKEN, 'secret-token');

    expect(EncryptedStore::get(EncryptedSettings::MTN_TOKEN))->toBe('secret-token');
    expect(EncryptedStore::get(EncryptedSettings::SITE_NAME))->toBe('Default Corp');
});

it('decrypts from the cache after a fresh fetch', function () {
    EncryptedStore::set(EncryptedSettings::MTN_TOKEN, 'secret-token');

    Cache::flush();
    EncryptedStore::get(EncryptedSettings::MTN_TOKEN);
    expect(Cache::has('lazy-settings.encrypted_test_settings'))->toBeTrue();

    expect(EncryptedStore::get(EncryptedSettings::MTN_TOKEN))->toBe('secret-token');
});

it('decrypts inside batch and all reads', function () {
    EncryptedStore::set(EncryptedSettings::MTN_TOKEN, 'secret-token');
    EncryptedStore::set(EncryptedSettings::SITE_NAME, 'Acme');

    $batch = EncryptedStore::getSettingsByKeys(['mtn_token', 'site_name']);
    expect($batch['mtn_token'])->toBe('secret-token');
    expect($batch['site_name'])->toBe('Acme');

    $all = EncryptedStore::allSettings();
    expect($all['mtn_token'])->toBe('secret-token');
    expect($all['site_name'])->toBe('Acme');
});

it('allows nullable encrypted keys to round-trip null', function () {
    EncryptedStore::set(EncryptedSettings::WEBHOOK_SECRET, null);

    expect(EncryptedStore::get(EncryptedSettings::WEBHOOK_SECRET))->toBeNull();

    $row = DB::table('encrypted_test_settings')->where('key', 'webhook_secret')->first();
    expect($row)->not->toBeNull();
    expect($row->value)->toBeNull();
});

it('fails hard when an encrypted key holds non-ciphertext', function () {
    DB::table('encrypted_test_settings')->insert([
        'key' => 'mtn_token',
        'value' => 'plaintext-still',
    ]);

    EncryptedStore::get(EncryptedSettings::MTN_TOKEN);
})->throws(DecryptException::class);

it('still enforces nullability before encrypting', function () {
    EncryptedStore::set(EncryptedSettings::MTN_TOKEN, null);
})->throws(InvalidArgumentException::class);

it('leaves schema validation unaffected by encryption', function () {
    EncryptedStore::get(EncryptedSettings::MTN_TOKEN);

    expect(EncryptedStore::get(EncryptedSettings::SITE_NAME))->toBe('Default Corp');
});