<?php

namespace Sumeetghimire\AiOrchestrator\Support;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;

class TraceRepository
{
    /**
     * Get the trace model class.
     */
    protected function getModelClass(): string
    {
        return ModelResolver::trace();
    }

    /**
     * Get a new query builder instance.
     */
    protected function query()
    {
        $modelClass = $this->getModelClass();
        return $modelClass::query();
    }

    /**
     * Create a new trace record.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): Model
    {
        if (!isset($data['trace_id'])) {
            $data['trace_id'] = (string) Str::uuid();
        }

        $modelClass = $this->getModelClass();
        return $modelClass::create($data);
    }

    /**
     * Find a trace by ID.
     */
    public function find(string $traceId): ?Model
    {
        return $this->query()->find($traceId);
    }

    /**
     * Update a trace record.
     *
     * @param string $traceId
     * @param array<string, mixed> $data
     */
    public function update(string $traceId, array $data): bool
    {
        $trace = $this->find($traceId);
        
        if (!$trace) {
            return false;
        }

        return $trace->update($data);
    }

    /**
     * Get traces by user ID.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Model>
     */
    public function findByUser(int $userId, int $limit = 50)
    {
        return $this->query()
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get traces by provider.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Model>
     */
    public function findByProvider(string $provider, int $limit = 50)
    {
        return $this->query()
            ->where('provider', $provider)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get replay traces for a given trace.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Model>
     */
    public function getReplays(string $traceId)
    {
        return $this->query()
            ->where('parent_trace_id', $traceId)
            ->orderBy('created_at', 'desc')
            ->get();
    }
}

