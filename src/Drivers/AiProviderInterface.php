<?php

namespace Sumeetghimire\AiOrchestrator\Drivers;

interface AiProviderInterface
{
    /**
     * Complete a prompt.
     */
    public function complete(string $prompt, array $options = []): array;

    /**
     * Chat completion.
     */
    public function chat(array $messages, array $options = []): array;

    /**
     * Stream chat completion.
     */
    public function streamChat(array $messages, callable $callback, array $options = []): void;

    /**
     * Get model name.
     */
    public function getModel(): string;

    /**
     * Calculate cost for tokens.
     */
    public function calculateCost(int $inputTokens, int $outputTokens): float;

    /**
     * Get provider name.
     */
    public function getName(): string;
}

