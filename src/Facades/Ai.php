<?php

namespace Laravel\AiOrchestrator\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Laravel\AiOrchestrator\Support\Response prompt(string $prompt, array $variables = [])
 * @method static \Laravel\AiOrchestrator\Support\Response chat(array $messages)
 * @method static \Laravel\AiOrchestrator\Support\UsageTracker usage()
 *
 * @see \Laravel\AiOrchestrator\AiOrchestrator
 */
class Ai extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'ai.orchestrator';
    }
}

