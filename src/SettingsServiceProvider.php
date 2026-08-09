<?php

declare(strict_types=1);

namespace Timadey\LazySettings;

use Illuminate\Support\ServiceProvider;
use Timadey\LazySettings\Console\MakeSettingsStore;
use Timadey\LazySettings\Console\SyncCommand;
use Timadey\LazySettings\Console\TableCommand;

class SettingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/lazy-settings.php', 'lazy-settings');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeSettingsStore::class,
                SyncCommand::class,
                TableCommand::class,
            ]);

            $this->publishes([
                __DIR__ . '/../config/lazy-settings.php' => config_path('lazy-settings.php'),
            ], 'lazy-settings-config');
        }
    }
}