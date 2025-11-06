<?php

namespace Sumeetghimire\AiOrchestrator\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Sumeetghimire\AiOrchestrator\Support\Response prompt(string $prompt, array $variables = [])
 * @method static \Sumeetghimire\AiOrchestrator\Support\Response chat(array $messages)
 * @method static \Sumeetghimire\AiOrchestrator\Support\UsageTracker usage()
 *
 * @see \Sumeetghimire\AiOrchestrator\AiOrchestrator
 */
class Ai extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'ai.orchestrator';
    }
}

