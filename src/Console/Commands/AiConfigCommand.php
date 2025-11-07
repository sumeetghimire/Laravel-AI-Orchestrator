<?php

namespace Sumeetghimire\AiOrchestrator\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

class AiConfigCommand extends Command
{
    protected $signature = 'ai:config';

    protected $description = 'Display the current AI orchestrator configuration and feature toggles.';

    public function handle(): int
    {
        $default = Config::get('ai.default');
        $fallback = Config::get('ai.fallback');
        $providers = Config::get('ai.providers', []);

        if (!$default) {
            $this->error('No default provider configured. Please set AI_DRIVER in your environment.');
            return self::FAILURE;
        }

        $defaultConfig = $providers[$default] ?? [];
        $fallbackConfig = $fallback ? ($providers[$fallback] ?? []) : [];

        $rows = [
            ['Default Provider', $default],
            ['Default Model', $defaultConfig['model'] ?? 'n/a'],
            ['Fallback Provider', $fallback ?: '—'],
            ['Fallback Model', $fallbackConfig['model'] ?? ($fallback ? 'n/a' : '—')],
            ['Cache Enabled', Config::get('ai.cache.enabled') ? 'Yes' : 'No'],
            ['Cache TTL (seconds)', Config::get('ai.cache.ttl')],
            ['Logging Enabled', Config::get('ai.logging.enabled') ? 'Yes' : 'No'],
            ['Logging Driver', Config::get('ai.logging.driver')],
            ['Dashboard Enabled', Config::get('ai.dashboard.enabled') ? 'Yes' : 'No'],
            ['Dashboard Middleware', Config::get('ai.dashboard.middleware')],
            ['Dashboard Prefix', Config::get('ai.dashboard.prefix')],
            ['Structured Output', 'Available (runtime feature)'],
        ];

        $this->table(['Configuration', 'Value'], $rows);

        return self::SUCCESS;
    }
}

