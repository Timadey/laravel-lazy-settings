<?php

declare(strict_types=1);

namespace Timadey\LazySettings\Console;

use Illuminate\Console\Command;
use Timadey\LazySettings\Casts\SettingType;
use Timadey\LazySettings\Contracts\SettingKey;
use Timadey\LazySettings\Contracts\SettingLabels;

class SyncCommand extends Command
{
    protected $signature = 'settings:sync {--all : Prompt for all settings, including existing} {--store= : Store class to sync (FQCN)} {--scope= : Scope value for scoped stores}';

    protected $description = 'Sync missing settings from a store enum into its table with interactive prompts';

    public function handle(): int
    {
        $storeClass = $this->option('store');

        if (! $storeClass) {
            $this->error('No settings store class provided. Use --store=Fully\Qualified\StoreClass.');

            return self::FAILURE;
        }

        $scope = $this->option('scope');
        $scopeArgs = $scope !== null ? [$scope] : [];
        $promptAll = $this->option('all');

        if (! $this->input->isInteractive()) {
            $this->error('settings:sync requires an interactive terminal. Drop --no-interaction.');

            return self::FAILURE;
        }

        $cases = $storeClass::enumCases();
        $existing = $storeClass::allRaw(...$scopeArgs);

        if ($promptAll) {
            $count = count($cases);
            $this->warn("You are about to review {$count} settings.");
            $this->warn('Press Enter to skip any setting.');
            if (! $this->confirm('Continue?', true)) {
                $this->info('Aborted.');

                return self::SUCCESS;
            }
        }

        $toInsert = [];
        $toUpdate = [];

        foreach ($cases as $case) {
            $key = $case->value;
            $hasExisting = array_key_exists($key, $existing);

            if (! $promptAll && $hasExisting) {
                continue;
            }

            $currentDisplay = null;
            if ($hasExisting) {
                $currentDisplay = $this->display($storeClass::get($case, ...$scopeArgs));
            }

            $prompt = $this->buildPrompt($case, $key, $currentDisplay, (bool) $promptAll);

            while (true) {
                $input = $this->ask($prompt);

                if ($input === null || trim((string) $input) === '') {
                    break;
                }

                $parsed = $this->parseInput($case, (string) $input);

                if ($parsed['valid'] === false) {
                    $this->error($parsed['message']);
                    continue;
                }

                if ($hasExisting) {
                    $toUpdate[$key] = $parsed['value'];
                } else {
                    $toInsert[$key] = $parsed['value'];
                }
                break;
            }
        }

        foreach ($toInsert as $key => $value) {
            $case = $storeClass::caseForKey($key);
            $storeClass::set($case, $value, ...$scopeArgs);
        }

        foreach ($toUpdate as $key => $value) {
            $storeClass::setByKey($key, $value, ...$scopeArgs);
        }

        $storeClass::flushCache(...$scopeArgs);

        $this->info('Settings sync complete.');
        $this->info('Inserted: ' . count($toInsert) . ', Updated: ' . count($toUpdate) . '.');

        return self::SUCCESS;
    }

    protected function buildPrompt(SettingKey $case, string $key, ?string $currentDisplay, bool $promptAll): string
    {
        $label = $case instanceof SettingLabels ? $case->label() : $case->name;

        $parts = [];
        $parts[] = "{$label} ({$key})";
        $parts[] = 'type: ' . $case->type()->value;

        $allowed = $case->allowed();
        if (! empty($allowed)) {
            $parts[] = 'allowed: ' . implode(', ', $allowed);
        }

        if ($promptAll && $currentDisplay !== null) {
            $parts[] = "current: {$currentDisplay}";
        }

        $suffix = 'Enter value';
        if ($case->type() === SettingType::Json) {
            $suffix = 'Enter comma-separated values';
        } elseif ($case->type() === SettingType::Bool) {
            $suffix = 'Enter true/false, yes/no, y/n, 1/0';
        }

        return implode(' | ', $parts) . " | {$suffix} (Enter to skip)";
    }

    protected function parseInput(SettingKey $case, string $input): array
    {
        $type = $case->type();
        $allowed = $case->allowed();
        $value = trim($input);

        return match ($type) {
            SettingType::Int => is_numeric($value)
                ? ['valid' => true, 'value' => (int) $value]
                : ['valid' => false, 'message' => 'Invalid integer. Please enter a number.'],

            SettingType::Float => is_numeric($value)
                ? ['valid' => true, 'value' => (float) $value]
                : ['valid' => false, 'message' => 'Invalid float. Please enter a number.'],

            SettingType::Bool => $this->parseBool($value),

            SettingType::Enum => empty($allowed) || in_array($value, $allowed, true)
                ? ['valid' => true, 'value' => $value]
                : ['valid' => false, 'message' => 'Invalid value. Allowed: ' . implode(', ', $allowed)],

            SettingType::Json => $this->parseJsonList($value, $allowed),

            SettingType::String => ['valid' => true, 'value' => $value],
        };
    }

    protected function parseBool(string $value): array
    {
        $normalized = strtolower($value);

        if (in_array($normalized, ['1', 'true', 'yes', 'y'], true)) {
            return ['valid' => true, 'value' => 1];
        }

        if (in_array($normalized, ['0', 'false', 'no', 'n'], true)) {
            return ['valid' => true, 'value' => 0];
        }

        return [
            'valid' => false,
            'message' => 'Invalid boolean. Use true/false, yes/no, y/n, or 1/0.',
        ];
    }

    protected function parseJsonList(string $value, array $allowed): array
    {
        $items = array_values(array_filter(array_map('trim', explode(',', $value)), fn ($v) => $v !== ''));

        if (! empty($allowed)) {
            $invalid = array_values(array_diff($items, $allowed));

            if (! empty($invalid)) {
                return [
                    'valid' => false,
                    'message' => 'Invalid values: ' . implode(', ', $invalid) . '. Allowed: ' . implode(', ', $allowed),
                ];
            }
        }

        return ['valid' => true, 'value' => $items];
    }

    protected function display(mixed $value): string
    {
        if (is_array($value)) {
            return implode(', ', $value);
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }
}