<?php

use Illuminate\Support\Facades\Route;
use Sumeetghimire\AiOrchestrator\Http\Controllers\DashboardController;
use Sumeetghimire\AiOrchestrator\Http\Controllers\TraceController;

// Only register dashboard routes if enabled in config
if (config('ai.dashboard.enabled', false)) {
    $prefix = config('ai.dashboard.prefix', 'ai-orchestrator');
    $middleware = config('ai.dashboard.middleware', 'web');

    Route::prefix($prefix)
        ->middleware($middleware)
        ->name('ai-orchestrator.')
        ->group(function () {
            Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
            Route::get('/logs', [DashboardController::class, 'logs'])->name('logs');
            Route::get('/api', [DashboardController::class, 'api'])->name('api');
            
            // Tracing routes (only available when tracing is enabled)
            if (config('ai.tracing.enable_tracing', false)) {
                // List all traces
                Route::get('/traces', [TraceController::class, 'index'])->name('traces.index');
                
                // Get trace statistics
                Route::get('/traces/stats', [TraceController::class, 'stats'])->name('traces.stats');
                
                // View a specific trace
                Route::get('/traces/{traceId}', [TraceController::class, 'show'])->name('traces.show');
                
                // Get replays for a trace
                Route::get('/traces/{traceId}/replays', [TraceController::class, 'replays'])->name('traces.replays');
                
                // Execute a replay
                Route::post('/traces/{traceId}/replay', [TraceController::class, 'replay'])->name('traces.replay');
            }
        });
}

