<?php

namespace Sumeetghimire\AiOrchestrator\Support;

use Illuminate\Support\Facades\Config;
use Sumeetghimire\AiOrchestrator\Models\AiMemory;

class MemoryStore
{
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

        $excess = AiMemory::where('session_key', $sessionKey)->count() - $max;
        if ($excess <= 0) {
            return;
        }

        AiMemory::where('session_key', $sessionKey)
            ->orderBy('id')
            ->limit($excess)
            ->delete();
    }
}

