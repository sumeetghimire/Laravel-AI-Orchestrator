<?php

use Illuminate\Support\Facades\Route;
use Laravel\AiOrchestrator\Http\Controllers\DashboardController;

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
        });
}

