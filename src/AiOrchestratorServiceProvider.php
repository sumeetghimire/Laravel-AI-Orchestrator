<?php

namespace Sumeetghimire\AiOrchestrator;

use Illuminate\Support\ServiceProvider;
use Sumeetghimire\AiOrchestrator\Console\Commands\AiConfigCommand;
use Sumeetghimire\AiOrchestrator\Console\Commands\AiFlushCacheCommand;
use Sumeetghimire\AiOrchestrator\Console\Commands\AiProvidersCommand;
use Sumeetghimire\AiOrchestrator\Console\Commands\AiStatusCommand;
use Sumeetghimire\AiOrchestrator\Console\Commands\AiTestCommand;
use Sumeetghimire\AiOrchestrator\Console\Commands\AiUsageCommand;
use Sumeetghimire\AiOrchestrator\Support\TraceRepository;
use Sumeetghimire\AiOrchestrator\Support\TraceManager;
use Sumeetghimire\AiOrchestrator\Support\ReplayManager;

class AiOrchestratorServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/ai.php',
            'ai'
        );

        $this->app->singleton('ai.orchestrator', function ($app) {
            return new AiOrchestrator($app['config']['ai']);
        });

        // Register tracing services
        $this->app->singleton('ai.trace.repository', function ($app) {
            return new TraceRepository();
        });

        $this->app->singleton('ai.trace.manager', function ($app) {
            $config = $app['config']['ai']['tracing'] ?? [];
            return new TraceManager(
                $app->make('ai.trace.repository'),
                $config
            );
        });

        $this->app->singleton('ai.replay.manager', function ($app) {
            return new ReplayManager(
                $app->make('ai.trace.repository'),
                $app->make('ai.orchestrator'),
                $app->make('ai.trace.manager')
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/ai.php' => config_path('ai.php'),
        ], 'ai-config');
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'ai-migrations');
        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/ai-orchestrator'),
        ], 'ai-views');
        $this->publishes([
            __DIR__ . '/../public/images' => public_path('vendor/ai-orchestrator/images'),
        ], 'ai-assets');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'ai-orchestrator');

        if ($this->app->runningInConsole()) {
            $this->commands([
                AiTestCommand::class,
                AiStatusCommand::class,
                AiConfigCommand::class,
                AiUsageCommand::class,
                AiProvidersCommand::class,
                AiFlushCacheCommand::class,
            ]);
        }
    }
}

