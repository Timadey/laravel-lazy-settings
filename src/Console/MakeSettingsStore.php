<?php

declare(strict_types=1);

namespace Timadey\LazySettings\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeSettingsStore extends Command
{
    protected $signature = 'make:settings-store {name : Store class name, e.g. VendorSettings} {--scope= : Scope column name, e.g. vendor_id}';

    protected $description = 'Create a new settings store class, its enum and table migration';

    public function handle(): int
    {
        $name = $this->argument('name');

        if (! preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $name)) {
            $this->error("Invalid store name [{$name}].");

            return self::FAILURE;
        }

        $storePath = $this->storePath($name);
        $enum = $name . 'Enum';
        $enumPath = $this->enumPath($enum);

        if (File::exists($storePath)) {
            $this->error("Store already exists at [{$storePath}].");

            return self::FAILURE;
        }

        if (File::exists($enumPath)) {
            $this->error("Enum already exists at [{$enumPath}].");

            return self::FAILURE;
        }

        $table = $this->tableFor($name);
        $scope = $this->option('scope');
        $enumClass = $this->enumNamespace() . '\\' . $enum;

        File::ensureDirectoryExists(dirname($storePath));
        File::ensureDirectoryExists(dirname($enumPath));

        File::put(
            $storePath,
            $this->replaceStub(
                $this->storeStub(),
                $name,
                $table,
                $enum,
                $scope,
                $this->storeNamespace(),
                $enumClass
            )
        );

        File::put(
            $enumPath,
            $this->replaceStub(
                $this->enumStub(),
                $enum,
                $table,
                $enum,
                $scope,
                $this->enumNamespace(),
                $enumClass
            )
        );

        $tableCommand = app(TableCommand::class);
        $tableCommand->setOutput($this->output);
        $tableCommand->forceCreate($table, $scope);

        $this->info("Settings store [{$name}] created at " . dirname($storePath) . '.');
        $this->info("Enum [{$enum}] created at " . dirname($enumPath) . '.');
        $this->info("Table [{$table}] migration generated.");

        return self::SUCCESS;
    }

    protected function tableFor(string $name): string
    {
        $base = (string) Str::of($name)->before('Settings')->snake();

        return $base === '' ? 'settings' : $base . '_settings';
    }

    protected function storePath(string $name): string
    {
        $path = str_replace('\\', '/', trim((string) config('lazy-settings.store_path', 'Models'), '/'));

        return app_path($path . '/' . $name . '.php');
    }

    protected function enumPath(string $name): string
    {
        $path = str_replace('\\', '/', trim((string) config('lazy-settings.enum_path', 'Enums'), '/'));

        return app_path($path . '/' . $name . '.php');
    }

    protected function storeNamespace(): string
    {
        return $this->namespaceFor('store_path', 'Models');
    }

    protected function enumNamespace(): string
    {
        return $this->namespaceFor('enum_path', 'Enums');
    }

    protected function namespaceFor(string $key, string $default): string
    {
        $root = trim($this->rootNamespace(), '\\');
        $path = trim((string) config("lazy-settings.{$key}", $default), '\\');

        return $root . '\\' . str_replace(['/', '\\'], '\\', $path);
    }

    protected function rootNamespace(): string
    {
        $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);

        foreach ($composer['autoload']['psr-4'] ?? [] as $namespace => $path) {
            if (is_dir(base_path($path))) {
                return $namespace;
            }
        }

        return 'App\\';
    }

    protected function replaceStub(
        string $stub,
        string $name,
        string $table,
        string $enum,
        ?string $scope,
        string $namespace,
        ?string $enumClass = null
    ): string {
        $scoped = $scope !== null && $scope !== '';
        $scopeOverride = $scoped
            ? "protected static ?string \$scopeColumn = '{$scope}';"
            : '// protected static ?string $scopeColumn = null; // null = globally scoped';

        return str_replace(
            ['{{ namespace }}', '{{ class }}', '{{ table }}', '{{ enum }}', '{{ enum_class }}', '{{ scope_column }}'],
            [$namespace, $name, $table, $enum, $enumClass ?? $namespace . '\\' . $enum, $scopeOverride],
            $stub
        );
    }

    protected function storeStub(): string
    {
        return File::get(dirname(__DIR__, 2) . '/stubs/store.stub');
    }

    protected function enumStub(): string
    {
        return File::get(dirname(__DIR__, 2) . '/stubs/enum.stub');
    }
}