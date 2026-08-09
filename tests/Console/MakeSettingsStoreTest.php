<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->tmpApp = sys_get_temp_dir() . '/ls-make-' . uniqid();
    $this->app->useAppPath($this->tmpApp);
    $this->app->useDatabasePath($this->tmpApp . '/database');
});

afterEach(function () {
    File::deleteDirectory($this->tmpApp);
});

it('generates the store, enum and migration in separate locations', function () {
    $this->artisan('make:settings-store', ['name' => 'PlatformSettings'])
        ->expectsOutputToContain('Settings store [PlatformSettings] created')
        ->expectsOutputToContain('Enum [PlatformSettingsEnum] created')
        ->expectsOutputToContain('Table [platform_settings] migration generated')
        ->assertExitCode(0);

    $store = File::get($this->tmpApp . '/Models/PlatformSettings.php');
    $enum = File::get($this->tmpApp . '/Enums/PlatformSettingsEnum.php');

    expect($store)
        ->toContain('namespace App\Models;')
        ->toContain('use App\Enums\PlatformSettingsEnum;')
        ->toContain('protected static string $enum = PlatformSettingsEnum::class;');

    expect($enum)
        ->toContain('namespace App\Enums;')
        ->toContain('enum PlatformSettingsEnum: string implements SettingKey');

    expect(
        count(File::glob($this->tmpApp . '/database/migrations/*_create_platform_settings_table.php'))
    )->toBe(1);
});

it('writes the scope column when --scope is passed', function () {
    $this->artisan('make:settings-store', ['name' => 'VendorSettings', '--scope' => 'vendor_id'])
        ->assertExitCode(0);

    $store = File::get($this->tmpApp . '/Models/VendorSettings.php');

    expect($store)->toContain("protected static ?string \$scopeColumn = 'vendor_id';");
});

it('respects custom store_path and enum_path config', function () {
    config()->set('lazy-settings.store_path', 'Models/Admin');
    config()->set('lazy-settings.enum_path', 'Enums/Admin');

    $this->artisan('make:settings-store', ['name' => 'PlatformSettings'])
        ->assertExitCode(0);

    $store = File::get($this->tmpApp . '/Models/Admin/PlatformSettings.php');
    $enum = File::get($this->tmpApp . '/Enums/Admin/PlatformSettingsEnum.php');

    expect($store)->toContain('namespace App\Models\Admin;');
    expect($enum)->toContain('namespace App\Enums\Admin;');
});

it('fails when the store or enum already exists', function () {
    File::ensureDirectoryExists($this->tmpApp . '/Models');
    File::put($this->tmpApp . '/Models/PlatformSettings.php', '<?php\n');

    $this->artisan('make:settings-store', ['name' => 'PlatformSettings'])
        ->expectsOutputToContain('Store already exists')
        ->assertExitCode(1);
});