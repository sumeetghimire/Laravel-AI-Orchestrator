<?php

namespace Sumeetghimire\AiOrchestrator\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

class AiProvidersCommand extends Command
{
    protected $signature = 'ai:providers';

    protected $description = 'List configured AI providers, their models, and configuration status.';

    public function handle(): int
    {
        $providers = Config::get('ai.providers', []);

        if (empty($providers)) {
            $this->warn('No providers are configured.');
            return self::SUCCESS;
        }

        $rows = collect($providers)->map(function (array $config, string $name) {
            $driver = $config['driver'] ?? 'n/a';
            $model = $config['model'] ?? ($config['base_url'] ?? 'n/a');

            return [
                $name,
                $model,
                $this->guessType($driver),
                $this->resolveStatus($config),
            ];
        });

        $this->table(['Provider', 'Model', 'Type', 'Status'], $rows);

        return self::SUCCESS;
    }

    protected function guessType(string $driver): string
    {
        return match ($driver) {
            'openai', 'anthropic', 'gemini', 'huggingface' => 'text/chat',
            'replicate' => 'multimodal',
            'ollama' => 'local',
            default => 'unknown',
        };
    }

    protected function resolveStatus(array $config): string
    {
        if (array_key_exists('api_key', $config) && empty($config['api_key'])) {
            return '⚠ Missing API key';
        }

        if (array_key_exists('base_url', $config) && empty($config['base_url'])) {
            return '⚠ Missing base URL';
        }

        return '✅ Active';
    }
}

