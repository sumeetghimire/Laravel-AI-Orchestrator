<?php

namespace Sumeetghimire\AiOrchestrator\Support;

use Sumeetghimire\AiOrchestrator\AiOrchestrator;
use Sumeetghimire\AiOrchestrator\Support\Response;
use Illuminate\Database\Eloquent\Model;

class ReplayResponse
{
    protected AiOrchestrator $orchestrator;
    protected Model $trace;
    protected TraceManager $traceManager;
    protected ?string $overrideProvider = null;
    protected ?string $overrideModel = null;
    protected ?string $overridePromptIdentifier = null;
    protected ?string $overridePromptVersion = null;
    protected array $overrideOptions = [];

    public function __construct(
        AiOrchestrator $orchestrator,
        Model $trace,
        TraceManager $traceManager
    ) {
        $this->orchestrator = $orchestrator;
        $this->trace = $trace;
        $this->traceManager = $traceManager;
    }

    /**
     * Override the provider for replay.
     */
    public function withProvider(string $provider): self
    {
        $this->overrideProvider = $provider;
        return $this;
    }

    /**
     * Override the model for replay.
     */
    public function withModel(string $model): self
    {
        $this->overrideModel = $model;
        return $this;
    }

    /**
     * Override the prompt identifier for replay.
     */
    public function withPrompt(string $promptIdentifier, ?string $version = null): self
    {
        $this->overridePromptIdentifier = $promptIdentifier;
        $this->overridePromptVersion = $version;
        return $this;
    }

    /**
     * Override options (e.g., temperature).
     */
    public function withOptions(array $options): self
    {
        $this->overrideOptions = array_merge($this->overrideOptions, $options);
        return $this;
    }

    /**
     * Execute the replay and return a Response instance.
     */
    public function run(): Response
    {
        // Start a new trace linked to the original
        $traceId = $this->traceManager->startReplayTrace($this->trace->trace_id, [
            'user_id' => $this->trace->user_id,
        ]);

        // Get the original input
        $originalInput = $this->trace->sanitized_input;

        // Determine input type (prompt string or chat messages)
        $input = $this->extractInput($originalInput);
        $type = $this->determineInputType($originalInput);

        // Create response based on type
        if ($type === 'chat' && is_array($input)) {
            $response = $this->orchestrator->chat($input);
        } else {
            $response = $this->orchestrator->prompt(is_string($input) ? $input : json_encode($input));
        }

        // Apply overrides
        $providerToUse = $this->overrideProvider ?? $this->trace->provider;
        
        // Handle model override by appending to provider name (e.g., "openai:gpt-4")
        if ($this->overrideModel) {
            $providerToUse = $providerToUse . ':' . $this->overrideModel;
        }
        
        $response->using($providerToUse);

        if (!empty($this->overrideOptions)) {
            $response->withOptions($this->overrideOptions);
        }

        // Store override metadata for trace
        $this->traceManager->updateTrace([
            'provider' => $this->overrideProvider ?? $this->trace->provider,
            'model' => $this->overrideModel ?? $this->trace->model,
            'prompt_identifier' => $this->overridePromptIdentifier ?? $this->trace->prompt_identifier,
            'prompt_version' => $this->overridePromptVersion ?? $this->trace->prompt_version,
            'metadata' => [
                'replay_of' => $this->trace->trace_id,
                'overrides' => [
                    'provider' => $this->overrideProvider,
                    'model' => $this->overrideModel,
                    'prompt_identifier' => $this->overridePromptIdentifier,
                    'prompt_version' => $this->overridePromptVersion,
                    'options' => $this->overrideOptions,
                ],
            ],
        ]);

        return $response;
    }

    /**
     * Extract input from trace data.
     *
     * @param mixed $originalInput
     * @return mixed
     */
    protected function extractInput($originalInput)
    {
        if (is_array($originalInput)) {
            // Check if it's a chat messages array
            if (isset($originalInput[0]) && is_array($originalInput[0]) && isset($originalInput[0]['role'])) {
                return $originalInput;
            }
            
            // Check if there's a 'content' or 'prompt' key
            if (isset($originalInput['content'])) {
                return $originalInput['content'];
            }
            
            if (isset($originalInput['prompt'])) {
                return $originalInput['prompt'];
            }
            
            // Return as-is for other array structures
            return $originalInput;
        }

        if (is_string($originalInput)) {
            return $originalInput;
        }

        // Fallback: try to convert to string
        return json_encode($originalInput);
    }

    /**
     * Determine the input type from trace data.
     *
     * @param mixed $originalInput
     */
    protected function determineInputType($originalInput): string
    {
        if (is_array($originalInput)) {
            // Check if it's a chat messages array
            if (isset($originalInput[0]) && is_array($originalInput[0]) && isset($originalInput[0]['role'])) {
                return 'chat';
            }
        }

        return 'prompt';
    }
}

