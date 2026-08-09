<?php

declare(strict_types=1);

namespace Timadey\LazySettings\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class TableCommand extends Command
{
    protected $signature = 'settings:table {table? : Table name (default: settings)} {--scope= : Scope column, e.g. vendor_id}';

    protected $description = 'Create a migration stub for a settings table';

    public function handle(): int
    {
        $this->forceCreate($this->argument('table') ?? 'settings', $this->option('scope'));

        return self::SUCCESS;
    }

    /**
     * Create a migration for the given table; shared with make:settings-store.
     */
    public function forceCreate(string $table, ?string $scope): string
    {
        $path = database_path('migrations/' . date('Y_m_d_His') . '_create_' . $table . '_table.php');

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $this->stubFor($table, $scope));

        $this->info("Migration created at {$path}");

        return $path;
    }

    protected function stubFor(string $table, ?string $scope): string
    {
        $stub = File::get(dirname(__DIR__, 2) . '/stubs/migration.stub');

        if ($scope) {
            $scopeLine = "\$table->unsignedBigInteger('{$scope}')->nullable()->index();";
            $uniqueColumns = "'{$scope}', 'key'";
        } else {
            $scopeLine = '';
            $uniqueColumns = "'key'";
        }

        return str_replace(
            ['{{ table }}', '{{ scope_column }}', '{{ unique_columns }}'],
            [$table, $scopeLine, $uniqueColumns],
            $stub
        );
    }
}