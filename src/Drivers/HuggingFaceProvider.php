<?php

namespace Sumeetghimire\AiOrchestrator\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class HuggingFaceProvider implements AiProviderInterface
{
    protected array $config;
    protected Client $client;
    protected string $model;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->model = $config['model'] ?? 'meta-llama/Llama-2-7b-chat-hf';
        $this->client = new Client([
            'base_uri' => 'https://api-inference.huggingface.co/',
            'headers' => [
                'Authorization' => 'Bearer ' . $config['api_key'],
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    public function complete(string $prompt, array $options = []): array
    {
        try {
            $response = $this->client->post("models/{$this->model}", [
                'json' => array_merge([
                    'inputs' => $prompt,
                ], $options),
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            // HuggingFace returns different formats depending on the model
            $content = '';
            if (isset($data[0]['generated_text'])) {
                $content = $data[0]['generated_text'];
            } elseif (isset($data['generated_text'])) {
                $content = $data['generated_text'];
            } elseif (is_string($data)) {
                $content = $data;
            }

            return [
                'content' => $content,
                'input_tokens' => 0, // HuggingFace doesn't provide token counts
                'output_tokens' => 0,
                'total_tokens' => 0,
                'model' => $this->model,
            ];
        } catch (RequestException $e) {
            throw new \RuntimeException('HuggingFace API error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function chat(array $messages, array $options = []): array
    {
        // Convert messages to prompt format
        $prompt = '';
        foreach ($messages as $message) {
            $role = $message['role'];
            $content = $message['content'];
            $prompt .= ucfirst($role) . ": {$content}\n";
        }
        $prompt .= "Assistant: ";

        return $this->complete($prompt, $options);
    }

    public function streamChat(array $messages, callable $callback, array $options = []): void
    {
        // HuggingFace doesn't support streaming in the same way
        // For now, we'll just return the complete response
        $result = $this->chat($messages, $options);
        $callback($result['content']);
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function calculateCost(int $inputTokens, int $outputTokens): float
    {
        // HuggingFace pricing varies by model and endpoint
        // For inference API, it's typically pay-per-use
        return 0.0; // Placeholder - actual pricing depends on model
    }

    public function getName(): string
    {
        return 'huggingface';
    }
}

