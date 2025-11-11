<?php

namespace Sumeetghimire\AiOrchestrator;

use Sumeetghimire\AiOrchestrator\Support\Response;
use Sumeetghimire\AiOrchestrator\Support\UsageTracker;
use Sumeetghimire\AiOrchestrator\Drivers\AiProviderInterface;
use Sumeetghimire\AiOrchestrator\Drivers\DriverFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Sumeetghimire\AiOrchestrator\Support\MemorySession;

class AiOrchestrator
{
    protected array $config;
    protected ?int $userId = null;
    protected DriverFactory $driverFactory;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->driverFactory = new DriverFactory($config);
    }

    /**
     * Create a prompt request.
     */
    public function prompt(string $prompt, array $variables = []): Response
    {
        $resolvedPrompt = $this->resolveVariables($prompt, $variables);

        return new Response(
            $this,
            $resolvedPrompt,
            'prompt'
        );
    }

    /**
     * Create a chat request.
     */
    public function chat(array $messages): Response
    {
        return new Response(
            $this,
            $messages,
            'chat'
        );
    }

    /**
     * Generate an image from a prompt.
     */
    public function image(string $prompt, array $options = []): Response
    {
        return new Response(
            $this,
            $prompt,
            'image',
            $options
        );
    }

    /**
     * Create embeddings from text.
     */
    public function embed(string|array $text, array $options = []): Response
    {
        return new Response(
            $this,
            $text,
            'embedding',
            $options
        );
    }

    /**
     * Transcribe audio to text.
     */
    public function transcribe(string $audioPath, array $options = []): Response
    {
        return new Response(
            $this,
            $audioPath,
            'transcribe',
            $options
        );
    }

    /**
     * Convert text to speech.
     */
    public function speak(string $text, array $options = []): Response
    {
        return new Response(
            $this,
            $text,
            'speech',
            $options
        );
    }

    /**
     * Get usage tracker.
     */
    public function usage(): UsageTracker
    {
        return new UsageTracker();
    }

    public function remember(string $sessionKey, array $options = []): MemorySession
    {
        return new MemorySession($this, $sessionKey, $options);
    }

    /**
     * Resolve variables in prompt string.
     */
    protected function resolveVariables(string $prompt, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $prompt = str_replace('{' . $key . '}', is_array($value) ? json_encode($value) : $value, $prompt);
        }

        return $prompt;
    }

    /**
     * Get provider instance.
     */
    public function getProvider(string $providerName): AiProviderInterface
    {
        return $this->driverFactory->make($providerName);
    }

    /**
     * Get default provider name.
     */
    public function getDefaultProvider(): string
    {
        return $this->config['default'] ?? 'openai';
    }

    /**
     * Get configured fallback provider names.
     *
     * @return array<int, string>
     */
    public function getFallbackProviders(): array
    {
        $fallback = $this->config['fallback'] ?? null;

        if ($fallback === null) {
            return [];
        }

        if (is_string($fallback)) {
            $fallback = str_contains($fallback, ',')
                ? explode(',', $fallback)
                : [$fallback];
        }

        if (!is_array($fallback)) {
            return [];
        }

        return array_values(array_filter(array_map(function ($provider) {
            if (!is_string($provider)) {
                return null;
            }

            $provider = trim($provider);

            return $provider !== '' ? $provider : null;
        }, $fallback)));
    }

    /**
     * @deprecated Use getFallbackProviders() instead.
     */
    public function getFallbackProvider(): ?string
    {
        $providers = $this->getFallbackProviders();
        return $providers[0] ?? null;
    }

    /**
     * Get config.
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Set user ID for attribution.
     */
    public function setUserId(?int $userId): self
    {
        $this->userId = $userId;
        return $this;
    }

    /**
     * Get user ID.
     */
    public function getUserId(): ?int
    {
        return $this->userId ?? auth()->id();
    }
}

