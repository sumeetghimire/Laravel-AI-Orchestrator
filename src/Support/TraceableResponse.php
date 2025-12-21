<?php

namespace Sumeetghimire\AiOrchestrator\Support;

use Sumeetghimire\AiOrchestrator\AiOrchestrator;

/**
 * TraceableResponse provides a fluent interface for explicitly traced AI calls.
 * When tracing is enabled in config, all calls are automatically traced anyway.
 * This class allows explicit control and method chaining.
 */
class TraceableResponse
{
    protected AiOrchestrator $orchestrator;

    public function __construct(AiOrchestrator $orchestrator)
    {
        $this->orchestrator = $orchestrator;
    }

    /**
     * Create a prompt request (with tracing).
     */
    public function prompt(string $prompt, array $variables = []): Response
    {
        return $this->orchestrator->prompt($prompt, $variables);
    }

    /**
     * Create a chat request (with tracing).
     */
    public function chat(array $messages): Response
    {
        return $this->orchestrator->chat($messages);
    }

    /**
     * Create an image generation request (with tracing).
     */
    public function image(string $prompt, array $options = []): Response
    {
        return $this->orchestrator->image($prompt, $options);
    }

    /**
     * Create an embedding request (with tracing).
     */
    public function embed(string|array $text, array $options = []): Response
    {
        return $this->orchestrator->embed($text, $options);
    }

    /**
     * Create a transcription request (with tracing).
     */
    public function transcribe(string $audioPath, array $options = []): Response
    {
        return $this->orchestrator->transcribe($audioPath, $options);
    }

    /**
     * Create a text-to-speech request (with tracing).
     */
    public function speak(string $text, array $options = []): Response
    {
        return $this->orchestrator->speak($text, $options);
    }
}

