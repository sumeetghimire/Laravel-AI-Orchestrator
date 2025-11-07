<?php

namespace Sumeetghimire\AiOrchestrator\Drivers;

use InvalidArgumentException;

class DriverFactory
{
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Create a provider instance.
     */
    public function make(string $providerName): AiProviderInterface
    {
        [$provider, $model] = $this->parseProviderName($providerName);

        if (!isset($this->config['providers'][$provider])) {
            throw new InvalidArgumentException("Provider '{$provider}' is not configured.");
        }

        $providerConfig = $this->config['providers'][$provider];
        $driver = $providerConfig['driver'] ?? $provider;
        if ($model) {
            $providerConfig['model'] = $model;
        }

        return match ($driver) {
            'openai' => new OpenAIProvider($providerConfig),
            'anthropic' => new AnthropicProvider($providerConfig),
            'gemini' => new GeminiProvider($providerConfig),
            'ollama' => new OllamaProvider($providerConfig),
            'huggingface' => new HuggingFaceProvider($providerConfig),
            'replicate' => new ReplicateProvider($providerConfig),
            default => throw new InvalidArgumentException("Driver '{$driver}' is not supported."),
        };
    }

    /**
     * Parse provider name (e.g., "openai:gpt-4" or "anthropic").
     */
    protected function parseProviderName(string $name): array
    {
        if (strpos($name, ':') !== false) {
            return explode(':', $name, 2);
        }

        return [$name, null];
    }
}

