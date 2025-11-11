<?php

namespace Sumeetghimire\AiOrchestrator\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Carbon\Carbon;

class UsageTracker
{
    protected ?int $userId = null;
    protected ?string $provider = null;
    protected ?Carbon $fromDate = null;
    protected ?Carbon $toDate = null;
    protected string $logModel;

    public function __construct()
    {
        $this->logModel = ModelResolver::log();
    }

    /**
     * Filter by user.
     */
    public function user(int $userId): self
    {
        $this->userId = $userId;
        return $this;
    }

    /**
     * Filter by provider.
     */
    public function provider(string $provider): self
    {
        $this->provider = $provider;
        return $this;
    }

    /**
     * Filter by today.
     */
    public function today(): self
    {
        $this->fromDate = Carbon::today();
        $this->toDate = Carbon::today()->endOfDay();
        return $this;
    }

    /**
     * Filter by this week.
     */
    public function thisWeek(): self
    {
        $this->fromDate = Carbon::now()->startOfWeek();
        $this->toDate = Carbon::now()->endOfWeek();
        return $this;
    }

    /**
     * Filter by this month.
     */
    public function thisMonth(): self
    {
        $this->fromDate = Carbon::now()->startOfMonth();
        $this->toDate = Carbon::now()->endOfMonth();
        return $this;
    }

    /**
     * Filter by date range.
     */
    public function between(Carbon $from, Carbon $to): self
    {
        $this->fromDate = $from;
        $this->toDate = $to;
        return $this;
    }

    /**
     * Get query builder.
     */
    public function query(): Builder
    {
        /** @var Builder $query */
        $model = $this->logModel;
        $query = $model::query();

        if ($this->userId !== null) {
            $query->where('user_id', $this->userId);
        }

        if ($this->provider !== null) {
            $query->where('provider', $this->provider);
        }

        if ($this->fromDate !== null) {
            $query->where('created_at', '>=', $this->fromDate);
        }

        if ($this->toDate !== null) {
            $query->where('created_at', '<=', $this->toDate);
        }

        return $query;
    }

    /**
     * Sum a column.
     */
    public function sum(string $column): float
    {
        return (float) $this->query()->sum($column);
    }

    /**
     * Get count.
     */
    public function count(): int
    {
        return $this->query()->count();
    }

    /**
     * Get average.
     */
    public function avg(string $column): float
    {
        return (float) $this->query()->avg($column);
    }

    /**
     * Get all records.
     */
    public function get(): Collection
    {
        return $this->query()->get();
    }

    /**
     * Get paginated results.
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->query()->paginate($perPage);
    }
}

