<?php

namespace Sumeetghimire\AiOrchestrator\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Sumeetghimire\AiOrchestrator\Support\ModelResolver;

class AiStatusCommand extends Command
{
    protected $signature = 'ai:status';

    protected $description = 'Show configuration and health information for the Laravel AI Orchestrator.';

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

        $cacheHits = Cache::get('ai:metrics.cache_hits', 0);
        $cacheStores = Cache::get('ai:metrics.cache_stores', 0);
        $cacheEnabled = Config::get('ai.cache.enabled') ? 'Enabled' : 'Disabled';
        $cacheTtl = Config::get('ai.cache.ttl');

        $logModel = ModelResolver::log();
        $lastLog = $logModel::query()->latest('created_at')->first();
        $lastSuccess = $lastLog
            ? Carbon::parse($lastLog->created_at)->diffForHumans()
            : 'No successful requests yet';

        $rows = [
            ['Default Provider', $default],
            ['Default Model', $defaultConfig['model'] ?? 'n/a'],
            ['Fallback Provider', $fallback ?: '—'],
            ['Fallback Model', $fallbackConfig['model'] ?? ($fallback ? 'n/a' : '—')],
            ['Default Status', $this->resolveStatus($defaultConfig)],
            ['Fallback Status', $fallback ? $this->resolveStatus($fallbackConfig) : '—'],
            ['Cache', sprintf('%s (TTL: %ss)', $cacheEnabled, $cacheTtl)],
            ['Cache Hits', $cacheHits],
            ['Cache Stores', $cacheStores],
            ['Last Successful Response', $lastSuccess],
        ];

        $this->table(['Setting', 'Value'], $rows);

        return self::SUCCESS;
    }

    protected function resolveStatus(array $config): string
    {
        if (empty($config)) {
            return '⚠ Missing configuration';
        }

        if (array_key_exists('api_key', $config) && empty($config['api_key'])) {
            return '⚠ Missing API key';
        }

        if (array_key_exists('base_url', $config) && empty($config['base_url'])) {
            return '⚠ Missing base URL';
        }

        return '✅ Ready';
    }
}

