<?php

namespace Sumeetghimire\AiOrchestrator\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Sumeetghimire\AiOrchestrator\Models\AiLog;

class AiUsageCommand extends Command
{
    protected $signature = 'ai:usage {--provider=}';

    protected $description = 'Display token and cost usage statistics per provider.';

    public function handle(): int
    {
        $query = AiLog::query();

        if ($provider = $this->option('provider')) {
            $query->where('provider', $provider);
        }

        $usage = $query->select(
            'provider',
            DB::raw('COUNT(*) as requests'),
            DB::raw('SUM(tokens) as tokens'),
            DB::raw('SUM(cost) as cost')
        )
            ->groupBy('provider')
            ->orderBy('provider')
            ->get();

        if ($usage->isEmpty()) {
            $this->warn('No usage data available yet.');
            return self::SUCCESS;
        }

        $rows = $usage->map(function ($row) {
            return [
                $row->provider,
                number_format((int) $row->requests),
                number_format((int) ($row->tokens ?? 0)),
                '$' . number_format((float) ($row->cost ?? 0), 2),
            ];
        });

        $this->table(['Provider', 'Requests', 'Tokens', 'Cost (USD)'], $rows);

        return self::SUCCESS;
    }
}

