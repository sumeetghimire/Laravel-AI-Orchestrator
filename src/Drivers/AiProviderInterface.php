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
     * Generate an image from a prompt.
     */
    public function generateImage(string $prompt, array $options = []): array;

    /**
     * Create embeddings from text.
     */
    public function embedText(string|array $text, array $options = []): array;

    /**
     * Transcribe audio to text (speech-to-text).
     */
    public function transcribeAudio(string $audioPath, array $options = []): array;

    /**
     * Convert text to speech (text-to-speech).
     */
    public function textToSpeech(string $text, array $options = []): string;

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

