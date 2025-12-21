<?php

namespace Sumeetghimire\AiOrchestrator\Support;

use Sumeetghimire\AiOrchestrator\AiOrchestrator;
use Sumeetghimire\AiOrchestrator\Support\Response;
use InvalidArgumentException;

class ReplayManager
{
    protected TraceRepository $repository;
    protected AiOrchestrator $orchestrator;
    protected TraceManager $traceManager;

    public function __construct(
        TraceRepository $repository,
        AiOrchestrator $orchestrator,
        TraceManager $traceManager
    ) {
        $this->repository = $repository;
        $this->orchestrator = $orchestrator;
        $this->traceManager = $traceManager;
    }

    /**
     * Create a replay response builder from a trace ID.
     */
    public function replay(string $traceId): ReplayResponse
    {
        $trace = $this->repository->find($traceId);

        if (!$trace) {
            throw new InvalidArgumentException("Trace not found: {$traceId}");
        }

        return new ReplayResponse($this->orchestrator, $trace, $this->traceManager);
    }
}

