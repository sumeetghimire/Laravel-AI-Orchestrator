<?php

namespace Sumeetghimire\AiOrchestrator\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Sumeetghimire\AiOrchestrator\Models\AiMemory;

class MemoryStore
{
    protected string $driver;
    protected ?string $cacheStore;

    public function __construct()
    {
        $this->driver = strtolower((string) Config::get('ai.memory.driver', 'database'));
        $this->cacheStore = Config::get('ai.memory.cache_store');
    }

    public function enabled(): bool
    {
        return (bool) Config::get('ai.memory.enabled', true);
    }

    /**
     * @return array<int, array{role:string,content:string}>
     */
    public function history(string $sessionKey): array
    {
        if (!$this->enabled()) {
            return [];
        }

        if ($this->usesCache()) {
            $history = $this->cacheStore()->get($this->cacheKey($sessionKey), []);
            return collect($history)
                ->map(fn (array $entry) => [
                    'role' => $entry['role'] ?? 'user',
                    'content' => $entry['content'] ?? '',
                ])
                ->toArray();
        }

        return AiMemory::query()
            ->where('session_key', $sessionKey)
            ->orderBy('id')
            ->get(['role', 'content'])
            ->map(fn (AiMemory $memory) => [
                'role' => $memory->role,
                'content' => $memory->content,
            ])
            ->toArray();
    }

    public function append(string $sessionKey, string $role, string $content, array $metadata = []): void
    {
        if (!$this->enabled()) {
            return;
        }

        if ($this->usesCache()) {
            $history = $this->cacheStore()->get($this->cacheKey($sessionKey), []);
            $history[] = [
                'role' => $role,
                'content' => $content,
                'metadata' => $metadata ?: null,
            ];
            $history = $this->trimArray($history);
            $this->cacheStore()->forever($this->cacheKey($sessionKey), $history);
            return;
        }

        AiMemory::create([
            'session_key' => $sessionKey,
            'role' => $role,
            'content' => $content,
            'metadata' => $metadata ?: null,
        ]);

        $this->trim($sessionKey);
    }

    protected function trim(string $sessionKey): void
    {
        $max = (int) Config::get('ai.memory.max_messages', 50);
        if ($max <= 0) {
            return;
        }

        if ($this->usesCache()) {
            $history = $this->cacheStore()->get($this->cacheKey($sessionKey), []);
            $history = $this->trimArray($history);
            $this->cacheStore()->forever($this->cacheKey($sessionKey), $history);
            return;
        }

        $excess = AiMemory::where('session_key', $sessionKey)->count() - $max;
        if ($excess <= 0) {
            return;
        }

        AiMemory::where('session_key', $sessionKey)
            ->orderBy('id')
            ->limit($excess)
            ->delete();
    }

    protected function usesCache(): bool
    {
        return $this->driver === 'cache';
    }

    protected function cacheStore()
    {
        $store = $this->cacheStore ?? config('cache.default');
        return Cache::store($store);
    }

    protected function cacheKey(string $sessionKey): string
    {
        return 'ai:memory:' . $sessionKey;
    }

    protected function trimArray(array $history): array
    {
        $max = (int) Config::get('ai.memory.max_messages', 50);
        if ($max <= 0) {
            return $history;
        }

        $excess = count($history) - $max;
        if ($excess <= 0) {
            return $history;
        }

        return array_slice($history, $excess);
    }
}

