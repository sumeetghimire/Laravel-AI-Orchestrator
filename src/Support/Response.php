<?php

namespace Sumeetghimire\AiOrchestrator\Support;

use Sumeetghimire\AiOrchestrator\AiOrchestrator;
use Sumeetghimire\AiOrchestrator\Drivers\AiProviderInterface;
use Sumeetghimire\AiOrchestrator\Models\AiLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use RuntimeException;

class Response
{
    protected AiOrchestrator $orchestrator;
    protected mixed $input;
    protected string $type;
    protected ?string $providerName = null;
    protected ?string $fallbackProvider = null;
    protected ?int $cacheTtl = null;
    protected ?array $expectedSchema = null;
    protected array $options = [];
    protected bool $isCached = false;
    protected ?array $result = null;

    public function __construct(AiOrchestrator $orchestrator, mixed $input, string $type = 'prompt', array $options = [])
    {
        $this->orchestrator = $orchestrator;
        $this->input = $input;
        $this->type = $type;
        $this->options = $options;
    }

    /**
     * Specify provider to use.
     */
    public function using(string $provider): self
    {
        $this->providerName = $provider;
        return $this;
    }

    /**
     * Set fallback provider.
     */
    public function fallback(string $provider): self
    {
        $this->fallbackProvider = $provider;
        return $this;
    }

    /**
     * Set cache TTL.
     */
    public function cache(int $ttl): self
    {
        $this->cacheTtl = $ttl;
        return $this;
    }

    /**
     * Expect structured output with schema.
     */
    public function expect(array $schema): self
    {
        $this->expectedSchema = $schema;
        return $this;
    }

    /**
     * Expect structured output with schema (alias).
     */
    public function expectSchema(array $schema): self
    {
        return $this->expect($schema);
    }

    /**
     * Set additional options.
     */
    public function withOptions(array $options): self
    {
        $this->options = array_merge($this->options, $options);
        return $this;
    }

    /**
     * Get plain text response.
     */
    public function toText(): string
    {
        $result = $this->execute();
        
        if ($this->type === 'transcribe') {
            return $result['text'] ?? '';
        }
        
        return $result['content'] ?? '';
    }

    /**
     * Get image URLs.
     */
    public function toImages(): array
    {
        $result = $this->execute();
        return $result['images'] ?? [];
    }

    /**
     * Get embeddings.
     */
    public function toEmbeddings(): array
    {
        $result = $this->execute();
        return $result['embeddings'] ?? [];
    }

    /**
     * Get audio file path or base64.
     */
    public function toAudio(): string
    {
        $result = $this->execute();
        $audio = $result['audio'] ?? '';
        
        // If it's a base64 string and no output_path was specified, save to default location
        if (!empty($audio) && !isset($this->options['output_path']) && base64_decode($audio, true) !== false) {
            $audio = $this->saveAudioToStorage($audio);
        }
        
        return $audio;
    }
    
    /**
     * Save audio to configured storage location.
     */
    protected function saveAudioToStorage(string $audioData): string
    {
        $config = $this->orchestrator->getConfig();
        $audioConfig = $config['audio'] ?? [];
        
        $disk = $audioConfig['storage_disk'] ?? 'public';
        $basePath = $audioConfig['storage_path'] ?? 'audio';
        
        // Create user-specific subfolder if enabled
        $path = $basePath;
        if ($audioConfig['user_subfolder'] ?? true) {
            $userId = $this->orchestrator->getUserId();
            $path = $basePath . '/' . ($userId ?? 'guest');
        }
        
        // Generate unique filename
        $filename = 'tts_' . time() . '_' . uniqid() . '.mp3';
        $fullPath = $path . '/' . $filename;
        
        // Decode base64 and save
        $decoded = base64_decode($audioData);
        \Illuminate\Support\Facades\Storage::disk($disk)->put($fullPath, $decoded);
        
        return $fullPath;
    }

    /**
     * Get structured response.
     */
    public function toStructured(): array
    {
        $result = $this->execute();
        $content = $result['content'] ?? '';

        if ($this->expectedSchema) {
            return $this->parseStructuredOutput($content);
        }

        // Try to parse as JSON
        $decoded = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        return ['content' => $content];
    }

    /**
     * Validate structured output.
     */
    public function validate(): array
    {
        $structured = $this->toStructured();

        if ($this->expectedSchema) {
            $this->validateSchema($structured, $this->expectedSchema);
        }

        return $structured;
    }

    /**
     * Stream response.
     */
    public function stream(callable $callback): void
    {
        $provider = $this->getProvider();
        $messages = $this->prepareMessages();

        try {
            $provider->streamChat($messages, $callback, $this->options);
        } catch (\Exception $e) {
            if ($this->fallbackProvider) {
                $this->providerName = $this->fallbackProvider;
                $provider = $this->getProvider();
                $provider->streamChat($messages, $callback, $this->options);
            } else {
                throw $e;
            }
        }
    }

    /**
     * Get JSON response.
     */
    public function toJson(): JsonResponse
    {
        return response()->json($this->toStructured());
    }

    /**
     * Queue the request.
     */
    public function queue(): self
    {
        // This would dispatch a job - for now, just return self
        // In a full implementation, you'd dispatch a job here
        return $this;
    }

    /**
     * Execute the request.
     */
    protected function execute(): array
    {
        // Check cache
        if ($this->cacheTtl !== null) {
            $cacheKey = $this->getCacheKey();
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                $this->isCached = true;
                return $cached;
            }
        }

        $provider = $this->getProvider();
        $messages = $this->prepareMessages();

        try {
            $result = match ($this->type) {
                'chat' => $provider->chat($messages, $this->options),
                'image' => $provider->generateImage($messages, $this->options),
                'embedding' => $provider->embedText($messages, $this->options),
                'transcribe' => $provider->transcribeAudio($messages, $this->options),
                'speech' => ['audio' => $provider->textToSpeech($messages, $this->options)],
                default => $provider->complete($messages, $this->options),
            };

            // Calculate cost (only for text operations with tokens)
            $cost = 0.0;
            if ($this->type === 'prompt' || $this->type === 'chat') {
                $cost = $provider->calculateCost(
                    $result['input_tokens'] ?? 0,
                    $result['output_tokens'] ?? 0
                );
            } elseif ($this->type === 'embedding') {
                // Embedding cost calculation (simplified)
                $cost = ($result['usage']['total_tokens'] ?? 0) / 1000000 * 0.0001; // Approximate
            } elseif ($this->type === 'image') {
                // Image generation cost (DALL-E pricing)
                $cost = 0.040; // Approximate per image
            } elseif ($this->type === 'transcribe' || $this->type === 'speech') {
                // Audio cost (Whisper/TTS pricing)
                $cost = 0.006; // Approximate per minute
            }

            // Log the request
            $this->logRequest($provider, $messages, $result, $cost);

            // Store in cache
            if ($this->cacheTtl !== null) {
                Cache::put($this->getCacheKey(), $result, $this->cacheTtl);
            }

            $this->result = $result;
            return $result;
        } catch (\Exception $e) {
            // Try fallback if available
            if ($this->fallbackProvider && $this->providerName !== $this->fallbackProvider) {
                Log::warning("AI request failed, trying fallback: " . $e->getMessage());
                $this->providerName = $this->fallbackProvider;
                return $this->execute();
            }

            throw $e;
        }
    }

    /**
     * Get provider instance.
     */
    protected function getProvider(): AiProviderInterface
    {
        $providerName = $this->providerName ?? $this->orchestrator->getDefaultProvider();
        return $this->orchestrator->getProvider($providerName);
    }

    /**
     * Prepare messages for the provider.
     */
    protected function prepareMessages(): array|string
    {
        if ($this->type === 'chat') {
            return $this->input;
        }

        if ($this->type === 'image' || $this->type === 'embedding' || $this->type === 'transcribe' || $this->type === 'speech') {
            return $this->input;
        }

        // For prompt type with expected schema, add JSON format instruction
        if ($this->expectedSchema) {
            $schemaDescription = $this->formatSchemaDescription($this->expectedSchema);
            $this->input .= "\n\nPlease respond with valid JSON matching this schema: " . json_encode($this->expectedSchema);
        }

        return $this->input;
    }

    /**
     * Format schema description.
     */
    protected function formatSchemaDescription(array $schema): string
    {
        $description = [];
        foreach ($schema as $key => $type) {
            $description[] = "{$key}: {$type}";
        }
        return implode(', ', $description);
    }

    /**
     * Parse structured output.
     */
    protected function parseStructuredOutput(string $content): array
    {
        // Try to extract JSON from content
        $json = $this->extractJson($content);

        if ($json === null) {
            // If no valid JSON found and we have a schema, retry with correction
            if ($this->expectedSchema) {
                return $this->retryWithCorrection();
            }
            return ['content' => $content];
        }

        // Validate against schema
        if ($this->expectedSchema) {
            $this->validateSchema($json, $this->expectedSchema);
        }

        return $json;
    }

    /**
     * Extract JSON from content.
     */
    protected function extractJson(string $content): ?array
    {
        // Try to find JSON in the content
        if (preg_match('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s', $content, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        // Try direct JSON decode
        $decoded = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        return null;
    }

    /**
     * Retry with correction prompt.
     */
    protected function retryWithCorrection(): array
    {
        $provider = $this->getProvider();
        $schemaJson = json_encode($this->expectedSchema);
        
        $correctionPrompt = "The previous response was not valid JSON. Please provide a valid JSON response matching this exact schema: {$schemaJson}\n\nOriginal prompt: {$this->input}";

        try {
            $result = $provider->complete($correctionPrompt, $this->options);
            $json = $this->extractJson($result['content']);
            
            if ($json !== null) {
                $this->validateSchema($json, $this->expectedSchema);
                return $json;
            }
        } catch (\Exception $e) {
            Log::error("Failed to get corrected JSON response: " . $e->getMessage());
        }

        throw new RuntimeException("Could not get valid structured output from AI provider");
    }

    /**
     * Validate schema.
     */
    protected function validateSchema(array $data, array $schema): void
    {
        foreach ($schema as $key => $type) {
            if (strpos($type, 'required') !== false || isset($data[$key])) {
                if (!isset($data[$key])) {
                    throw new InvalidArgumentException("Missing required field: {$key}");
                }

                $actualType = gettype($data[$key]);
                $expectedType = str_replace('required|', '', $type);

                if ($expectedType === 'string' && $actualType !== 'string') {
                    throw new InvalidArgumentException("Field '{$key}' must be a string, got {$actualType}");
                }
                if ($expectedType === 'array' && $actualType !== 'array') {
                    throw new InvalidArgumentException("Field '{$key}' must be an array, got {$actualType}");
                }
                if ($expectedType === 'numeric' && !is_numeric($data[$key])) {
                    throw new InvalidArgumentException("Field '{$key}' must be numeric, got {$actualType}");
                }
                if ($expectedType === 'integer' && !is_int($data[$key])) {
                    throw new InvalidArgumentException("Field '{$key}' must be an integer, got {$actualType}");
                }
            }
        }
    }

    /**
     * Get cache key.
     */
    protected function getCacheKey(): string
    {
        $provider = $this->providerName ?? $this->orchestrator->getDefaultProvider();
        $inputHash = md5(serialize($this->input) . serialize($this->options));
        return "ai:{$provider}:{$inputHash}";
    }

    /**
     * Log the request.
     */
    protected function logRequest(AiProviderInterface $provider, mixed $input, array $result, float $cost): void
    {
        try {
            $startTime = microtime(true);
            $duration = microtime(true) - $startTime;

            $responseContent = match ($this->type) {
                'image' => json_encode($result['images'] ?? []),
                'embedding' => 'Embeddings generated (' . count($result['embeddings'] ?? []) . ' vectors)',
                'transcribe' => $result['text'] ?? '',
                'speech' => 'Audio generated',
                default => $result['content'] ?? '',
            };

            AiLog::create([
                'user_id' => $this->orchestrator->getUserId(),
                'provider' => $provider->getName(),
                'model' => $provider->getModel(),
                'prompt' => is_array($input) ? json_encode($input) : (is_string($input) ? $input : json_encode($input)),
                'response' => $responseContent,
                'tokens' => $result['total_tokens'] ?? ($result['usage']['total_tokens'] ?? 0),
                'cost' => $cost,
                'cached' => $this->isCached,
                'duration' => $duration,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to log AI request: " . $e->getMessage());
        }
    }
}

