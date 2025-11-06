<?php

namespace Sumeetghimire\AiOrchestrator;

use Illuminate\Support\ServiceProvider;

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
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__ . '/../config/ai.php' => config_path('ai.php'),
        ], 'ai-config');

        // Publish migrations
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'ai-migrations');

        // Publish views
        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/ai-orchestrator'),
        ], 'ai-views');

        // Publish assets (logo, etc.)
        $this->publishes([
            __DIR__ . '/../public/images' => public_path('vendor/ai-orchestrator/images'),
        ], 'ai-assets');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Load routes
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

        // Load views
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'ai-orchestrator');
    }
}

