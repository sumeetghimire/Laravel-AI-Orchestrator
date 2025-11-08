<?php

namespace Sumeetghimire\AiOrchestrator\Support;

use Sumeetghimire\AiOrchestrator\AiOrchestrator;
use Sumeetghimire\AiOrchestrator\Support\Response;

class MemorySession
{
    public function __construct(
        protected AiOrchestrator $orchestrator,
        protected string $sessionKey,
        protected array $options = []
    ) {
    }

    public function prompt(string $prompt, array $variables = []): Response
    {
        return $this->orchestrator
            ->prompt($prompt, $variables)
            ->withMemory($this->sessionKey, $this->options);
    }

    public function chat(array $messages): Response
    {
        return $this->orchestrator
            ->chat($messages)
            ->withMemory($this->sessionKey, $this->options);
    }
}

