<?php

namespace Sumeetghimire\AiOrchestrator\Support;

use Sumeetghimire\AiOrchestrator\Drivers\AiProviderInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;

class TraceManager
{
    protected TraceRepository $repository;
    protected array $config;
    protected ?string $currentTraceId = null;
    protected array $traceData = [];

    public function __construct(TraceRepository $repository, array $config)
    {
        $this->repository = $repository;
        $this->config = $config;
    }

    /**
     * Check if tracing is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->config['enable_tracing'] ?? false;
    }

    /**
     * Start a new trace.
     *
     * @param array<string, mixed> $initialData
     */
    public function startTrace(array $initialData = []): string
    {
        if (!$this->isEnabled()) {
            return '';
        }

        $this->currentTraceId = (string) \Illuminate\Support\Str::uuid();
        $this->traceData = array_merge([
            'trace_id' => $this->currentTraceId,
            'retry_count' => 0,
            'tools_registered' => [],
            'tools_called' => [],
        ], $initialData);

        return $this->currentTraceId;
    }

    /**
     * Get the current trace ID.
     */
    public function getCurrentTraceId(): ?string
    {
        return $this->currentTraceId;
    }

    /**
     * Update trace data.
     *
     * @param array<string, mixed> $data
     */
    public function updateTrace(array $data): void
    {
        if (!$this->isEnabled() || !$this->currentTraceId) {
            return;
        }

        $this->traceData = array_merge($this->traceData, $data);
    }

    /**
     * Record a tool registration.
     */
    public function registerTool(string $toolName, array $toolData = []): void
    {
        if (!$this->isEnabled() || !$this->currentTraceId) {
            return;
        }

        $tools = $this->traceData['tools_registered'] ?? [];
        $tools[] = array_merge(['name' => $toolName], $toolData);
        $this->traceData['tools_registered'] = $tools;
    }

    /**
     * Record a tool call.
     */
    public function recordToolCall(string $toolName, array $callData = []): void
    {
        if (!$this->isEnabled() || !$this->currentTraceId) {
            return;
        }

        $tools = $this->traceData['tools_called'] ?? [];
        $tools[] = array_merge(['name' => $toolName], $callData);
        $this->traceData['tools_called'] = $tools;
    }

    /**
     * Increment retry count.
     */
    public function incrementRetry(): void
    {
        if (!$this->isEnabled() || !$this->currentTraceId) {
            return;
        }

        $this->traceData['retry_count'] = ($this->traceData['retry_count'] ?? 0) + 1;
    }

    /**
     * Record validation error.
     */
    public function recordValidationError(string $error): void
    {
        if (!$this->isEnabled() || !$this->currentTraceId) {
            return;
        }

        $errors = $this->traceData['validation_errors'] ?? [];
        $errors[] = $error;
        $this->traceData['validation_errors'] = $errors;
    }

    /**
     * Record schema error.
     */
    public function recordSchemaError(string $error): void
    {
        if (!$this->isEnabled() || !$this->currentTraceId) {
            return;
        }

        $errors = $this->traceData['schema_errors'] ?? [];
        $errors[] = $error;
        $this->traceData['schema_errors'] = $errors;
    }

    /**
     * Sanitize input data by redacting sensitive keys.
     *
     * @param mixed $input
     * @return mixed
     */
    public function sanitizeInput($input)
    {
        if (!$this->isEnabled()) {
            return $input;
        }

        $redactKeys = $this->config['redact_keys'] ?? ['api_key', 'password', 'secret', 'token'];

        return $this->redactSensitiveData($input, $redactKeys);
    }

    /**
     * Recursively redact sensitive data from arrays/objects.
     *
     * @param mixed $data
     * @param array<string> $keysToRedact
     * @return mixed
     */
    protected function redactSensitiveData($data, array $keysToRedact)
    {
        if (is_array($data)) {
            $sanitized = [];
            foreach ($data as $key => $value) {
                $keyLower = strtolower($key);
                $shouldRedact = false;
                
                foreach ($keysToRedact as $redactKey) {
                    if (str_contains($keyLower, strtolower($redactKey))) {
                        $shouldRedact = true;
                        break;
                    }
                }

                if ($shouldRedact) {
                    $sanitized[$key] = '[REDACTED]';
                } else {
                    $sanitized[$key] = $this->redactSensitiveData($value, $keysToRedact);
                }
            }
            return $sanitized;
        }

        if (is_object($data)) {
            $array = (array) $data;
            $sanitized = $this->redactSensitiveData($array, $keysToRedact);
            return (object) $sanitized;
        }

        return $data;
    }

    /**
     * Finalize and save the trace.
     *
     * @param AiProviderInterface $provider
     * @param mixed $input
     * @param array<string, mixed> $result
     * @param float $cost
     * @param string|null $promptIdentifier
     * @param string|null $promptVersion
     */
    public function finalizeTrace(
        AiProviderInterface $provider,
        $input,
        array $result,
        float $cost,
        ?string $promptIdentifier = null,
        ?string $promptVersion = null
    ): ?Model {
        if (!$this->isEnabled() || !$this->currentTraceId) {
            return null;
        }

        try {
            $sanitizedInput = $this->sanitizeInput($input);
            
            $finalOutput = null;
            if ($this->config['store_raw_output'] ?? true) {
                $finalOutput = $this->extractOutput($result);
            }

            $traceData = array_merge($this->traceData, [
                'provider' => $provider->getName(),
                'model' => $provider->getModel(),
                'prompt_identifier' => $promptIdentifier,
                'prompt_version' => $promptVersion,
                'sanitized_input' => $sanitizedInput,
                'input_tokens' => $result['input_tokens'] ?? $result['usage']['prompt_tokens'] ?? 0,
                'output_tokens' => $result['output_tokens'] ?? $result['usage']['completion_tokens'] ?? 0,
                'estimated_cost' => $cost,
                'final_output' => $finalOutput,
            ]);

            $trace = $this->repository->create($traceData);
            
            // Reset for next trace
            $this->currentTraceId = null;
            $this->traceData = [];

            return $trace;
        } catch (\Exception $e) {
            Log::error("Failed to save AI trace: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Extract output from result array.
     *
     * @param array<string, mixed> $result
     */
    protected function extractOutput(array $result): ?string
    {
        if (isset($result['content'])) {
            return is_string($result['content']) ? $result['content'] : json_encode($result['content']);
        }

        if (isset($result['text'])) {
            return $result['text'];
        }

        if (isset($result['images'])) {
            return json_encode($result['images']);
        }

        if (isset($result['embeddings'])) {
            return 'Embeddings: ' . count($result['embeddings']) . ' vectors';
        }

        return json_encode($result);
    }

    /**
     * Create a trace with parent reference (for replays).
     *
     * @param string $parentTraceId
     * @param array<string, mixed> $initialData
     */
    public function startReplayTrace(string $parentTraceId, array $initialData = []): string
    {
        $traceId = $this->startTrace(array_merge($initialData, [
            'parent_trace_id' => $parentTraceId,
        ]));

        return $traceId;
    }
}

