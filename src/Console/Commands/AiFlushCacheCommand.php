<?php

namespace Sumeetghimire\AiOrchestrator\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class AiFlushCacheCommand extends Command
{
    protected $signature = 'ai:flush-cache';

    protected $description = 'Clear cached AI responses and reset cache metrics.';

    public function handle(): int
    {
        $keys = Cache::get('ai:cache.keys', []);

        $removed = 0;
        foreach ($keys as $key) {
            if (Cache::forget($key)) {
                $removed++;
            }
        }

        Cache::forever('ai:cache.keys', []);
        Cache::forget('ai:metrics.cache_hits');
        Cache::forget('ai:metrics.cache_stores');

        $this->info('✅ Cleared ' . $removed . ' cached responses.');
        $this->line('Cache metrics have been reset.');

        return self::SUCCESS;
    }
}

