<?php

namespace Sumeetghimire\AiOrchestrator\Http\Controllers;

use Illuminate\Http\Request;
use Sumeetghimire\AiOrchestrator\Facades\Ai;
use Sumeetghimire\AiOrchestrator\Support\ModelResolver;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $period = $request->get('period', 'today');
        $userId = $request->get('user_id');
        $logModel = ModelResolver::log();
        $query = $logModel::query();

        if ($userId) {
            $query->where('user_id', $userId);
        }
        switch ($period) {
            case 'today':
                $query->whereDate('created_at', Carbon::today());
                break;
            case 'week':
                $query->whereBetween('created_at', [
                    Carbon::now()->startOfWeek(),
                    Carbon::now()->endOfWeek(),
                ]);
                break;
            case 'month':
                $query->whereBetween('created_at', [
                    Carbon::now()->startOfMonth(),
                    Carbon::now()->endOfMonth(),
                ]);
                break;
            case 'all':
                break;
        }
        $totalCost = (float) $query->sum('cost');
        $totalTokens = (int) $query->sum('tokens');
        $totalRequests = $query->count();
        $cachedRequests = $query->where('cached', true)->count();
        $avgDuration = (float) $query->avg('duration');
        $providerStats = $logModel::query()
            ->when($period === 'today', fn($q) => $q->whereDate('created_at', Carbon::today()))
            ->when($period === 'week', fn($q) => $q->whereBetween('created_at', [
                Carbon::now()->startOfWeek(),
                Carbon::now()->endOfWeek(),
            ]))
            ->when($period === 'month', fn($q) => $q->whereBetween('created_at', [
                Carbon::now()->startOfMonth(),
                Carbon::now()->endOfMonth(),
            ]))
            ->when($userId, fn($q) => $q->where('user_id', $userId))
            ->selectRaw('provider, COUNT(*) as count, SUM(cost) as total_cost, SUM(tokens) as total_tokens')
            ->groupBy('provider')
            ->get();
        $recentLogs = $query->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();
        $dailyStats = $logModel::query()
            ->when($period !== 'all', function ($q) use ($period) {
                if ($period === 'today') {
                    $q->whereDate('created_at', Carbon::today());
                } elseif ($period === 'week') {
                    $q->whereBetween('created_at', [
                        Carbon::now()->startOfWeek(),
                        Carbon::now()->endOfWeek(),
                    ]);
                } elseif ($period === 'month') {
                    $q->whereBetween('created_at', [
                        Carbon::now()->startOfMonth(),
                        Carbon::now()->endOfMonth(),
                    ]);
                }
            })
            ->when($userId, fn($q) => $q->where('user_id', $userId))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as requests, SUM(cost) as cost, SUM(tokens) as tokens')
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        return view('ai-orchestrator::dashboard.index', compact(
            'totalCost',
            'totalTokens',
            'totalRequests',
            'cachedRequests',
            'avgDuration',
            'providerStats',
            'recentLogs',
            'dailyStats',
            'period',
            'userId'
        ));
    }

    public function logs(Request $request)
    {
        $logModel = ModelResolver::log();
        $logs = $logModel::query()
            ->when($request->get('provider'), fn($q, $provider) => $q->where('provider', $provider))
            ->when($request->get('user_id'), fn($q, $userId) => $q->where('user_id', $userId))
            ->when($request->get('cached') !== null, fn($q) => $q->where('cached', $request->get('cached')))
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return view('ai-orchestrator::dashboard.logs', compact('logs'));
    }

    public function api(Request $request)
    {
        $period = $request->get('period', 'today');
        $userId = $request->get('user_id');

        $usage = Ai::usage();

        if ($period === 'today') {
            $usage->today();
        } elseif ($period === 'week') {
            $usage->thisWeek();
        } elseif ($period === 'month') {
            $usage->thisMonth();
        }

        if ($userId) {
            $usage->user($userId);
        }

        $totalCost = $usage->sum('cost');
        $totalTokens = $usage->sum('tokens');
        $requestCount = $usage->count();
        $logs = $usage->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_cost' => $totalCost,
                'total_tokens' => $totalTokens,
                'request_count' => $requestCount,
                'logs' => $logs->take(100)->map(function ($log) {
                    return [
                        'id' => $log->id,
                        'provider' => $log->provider,
                        'model' => $log->model,
                        'tokens' => $log->tokens,
                        'cost' => $log->cost,
                        'cached' => $log->cached,
                        'duration' => $log->duration,
                        'created_at' => $log->created_at->toDateTimeString(),
                    ];
                }),
            ],
        ]);
    }
}

