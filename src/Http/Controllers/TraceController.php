<?php

namespace Sumeetghimire\AiOrchestrator\Http\Controllers;

use Illuminate\Http\Request;
use Sumeetghimire\AiOrchestrator\Facades\Ai;
use Sumeetghimire\AiOrchestrator\Support\ModelResolver;
use Carbon\Carbon;

class TraceController extends Controller
{
    /**
     * Get the trace model class.
     */
    protected function getTraceModel(): string
    {
        return ModelResolver::trace();
    }

    /**
     * List all traces with filtering.
     */
    public function index(Request $request)
    {
        $traceModel = $this->getTraceModel();
        $query = $traceModel::query();

        // Filter by provider
        if ($request->has('provider')) {
            $query->where('provider', $request->get('provider'));
        }

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->get('user_id'));
        }

        // Filter by prompt identifier
        if ($request->has('prompt_identifier')) {
            $query->where('prompt_identifier', $request->get('prompt_identifier'));
        }

        // Filter by period
        $period = $request->get('period', 'all');
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
        }

        // Filter replays only
        if ($request->get('replays_only') === 'true') {
            $query->whereNotNull('parent_trace_id');
        }

        // Filter originals only (no replays)
        if ($request->get('originals_only') === 'true') {
            $query->whereNull('parent_trace_id');
        }

        $traces = $query->orderBy('created_at', 'desc')
            ->paginate(50);

        // Get statistics
        $traceModel = $this->getTraceModel();
        $stats = [
            'total_traces' => $traceModel::count(),
            'total_replays' => $traceModel::whereNotNull('parent_trace_id')->count(),
            'total_cost' => $traceModel::sum('estimated_cost'),
            'total_tokens' => $traceModel::sum('input_tokens') + $traceModel::sum('output_tokens'),
        ];

        // If JSON request, return JSON
        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'stats' => $stats,
                'traces' => $traces->map(function ($trace) {
                    return $this->formatTrace($trace);
                }),
                'pagination' => [
                    'current_page' => $traces->currentPage(),
                    'last_page' => $traces->lastPage(),
                    'per_page' => $traces->perPage(),
                    'total' => $traces->total(),
                ],
            ]);
        }

        // Return view (if you create a view)
        return response()->json([
            'success' => true,
            'message' => 'Trace listing available via API. Use ?format=json or Accept: application/json header.',
            'stats' => $stats,
            'traces' => $traces->map(function ($trace) {
                return $this->formatTrace($trace);
            }),
        ]);
    }

    /**
     * Show a specific trace.
     */
    public function show(string $traceId, Request $request)
    {
        try {
            $traceModel = $this->getTraceModel();
            $trace = $traceModel::findOrFail($traceId);

            $formatted = $this->formatTrace($trace, true);

            // Get replay traces
            $replays = $trace->replayTraces()->orderBy('created_at', 'desc')->get();
            $formatted['replays'] = $replays->map(function ($replay) {
                return $this->formatTrace($replay);
            });

            // Get parent trace if this is a replay
            if ($trace->parent_trace_id) {
                $parent = $traceModel::find($trace->parent_trace_id);
                if ($parent) {
                    $formatted['parent_trace'] = $this->formatTrace($parent);
                }
            }

            return response()->json([
                'success' => true,
                'trace' => $formatted,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Trace not found',
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Get replay traces for a specific trace.
     */
    public function replays(string $traceId, Request $request)
    {
        try {
            $traceModel = $this->getTraceModel();
            $trace = $traceModel::findOrFail($traceId);
            $replays = $trace->replayTraces()->orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'original_trace_id' => $traceId,
                'replay_count' => $replays->count(),
                'replays' => $replays->map(function ($replay) {
                    return $this->formatTrace($replay);
                }),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Trace not found',
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Execute a replay.
     */
    public function replay(string $traceId, Request $request)
    {
        try {
            $traceModel = $this->getTraceModel();
            $trace = $traceModel::findOrFail($traceId);

            // Build replay with optional overrides
            $replay = Ai::replay($traceId);

            if ($request->has('provider')) {
                $replay->withProvider($request->get('provider'));
            }

            if ($request->has('model')) {
                $replay->withModel($request->get('model'));
            }

            if ($request->has('prompt_identifier')) {
                $replay->withPrompt(
                    $request->get('prompt_identifier'),
                    $request->get('prompt_version')
                );
            }

            if ($request->has('options')) {
                $replay->withOptions($request->get('options'));
            }

            // Execute replay
            $response = $replay->run()->toText();

            // Get the new replay trace
            $newReplayTrace = $traceModel::where('parent_trace_id', $traceId)
                ->orderBy('created_at', 'desc')
                ->first();

            return response()->json([
                'success' => true,
                'original_trace_id' => $traceId,
                'replay_trace_id' => $newReplayTrace?->trace_id,
                'response' => $response,
                'overrides' => [
                    'provider' => $request->get('provider'),
                    'model' => $request->get('model'),
                    'prompt_identifier' => $request->get('prompt_identifier'),
                    'prompt_version' => $request->get('prompt_version'),
                    'options' => $request->get('options'),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get trace statistics.
     */
    public function stats(Request $request)
    {
        $period = $request->get('period', 'all');
        $traceModel = $this->getTraceModel();
        $query = $traceModel::query();

        // Apply period filter
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
        }

        // Filter by user if provided
        if ($request->has('user_id')) {
            $query->where('user_id', $request->get('user_id'));
        }

        $stats = [
            'total_traces' => (clone $query)->count(),
            'total_replays' => (clone $query)->whereNotNull('parent_trace_id')->count(),
            'total_originals' => (clone $query)->whereNull('parent_trace_id')->count(),
            'total_cost' => (float) (clone $query)->sum('estimated_cost'),
            'total_input_tokens' => (int) (clone $query)->sum('input_tokens'),
            'total_output_tokens' => (int) (clone $query)->sum('output_tokens'),
            'total_tokens' => (int) ((clone $query)->sum('input_tokens') + (clone $query)->sum('output_tokens')),
            'avg_cost_per_trace' => (float) (clone $query)->avg('estimated_cost'),
            'provider_breakdown' => (clone $query)
                ->selectRaw('provider, COUNT(*) as count, SUM(estimated_cost) as total_cost, SUM(input_tokens + output_tokens) as total_tokens')
                ->groupBy('provider')
                ->get()
                ->map(function ($item) {
                    return [
                        'provider' => $item->provider,
                        'count' => $item->count,
                        'total_cost' => (float) $item->total_cost,
                        'total_tokens' => (int) $item->total_tokens,
                    ];
                }),
        ];

        return response()->json([
            'success' => true,
            'period' => $period,
            'stats' => $stats,
        ]);
    }

    /**
     * Format trace for API response.
     */
    protected function formatTrace($trace, bool $includeDetails = false): array
    {
        $data = [
            'trace_id' => $trace->trace_id,
            'parent_trace_id' => $trace->parent_trace_id,
            'provider' => $trace->provider,
            'model' => $trace->model,
            'prompt_identifier' => $trace->prompt_identifier,
            'prompt_version' => $trace->prompt_version,
            'input_tokens' => $trace->input_tokens,
            'output_tokens' => $trace->output_tokens,
            'estimated_cost' => (float) $trace->estimated_cost,
            'retry_count' => $trace->retry_count,
            'created_at' => $trace->created_at->toDateTimeString(),
            'is_replay' => $trace->parent_trace_id !== null,
        ];

        if ($includeDetails) {
            $data['sanitized_input'] = $trace->sanitized_input;
            $data['validation_errors'] = $trace->validation_errors;
            $data['schema_errors'] = $trace->schema_errors;
            $data['final_output'] = $trace->final_output;
            $data['metadata'] = $trace->metadata;
            $data['tools_registered'] = $trace->tools_registered;
            $data['tools_called'] = $trace->tools_called;
        }

        return $data;
    }
}

