<?php

namespace Laravel\AiOrchestrator\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class ReplicateProvider implements AiProviderInterface
{
    protected array $config;
    protected Client $client;
    protected string $model;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->model = $config['model'] ?? 'meta/llama-2-7b-chat';
        $this->client = new Client([
            'base_uri' => 'https://api.replicate.com/v1/',
            'headers' => [
                'Authorization' => 'Token ' . $config['api_key'],
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    public function complete(string $prompt, array $options = []): array
    {
        return $this->chat([
            ['role' => 'user', 'content' => $prompt],
        ], $options);
    }

    public function chat(array $messages, array $options = []): array
    {
        try {
            // Convert messages to prompt format for Replicate
            $prompt = '';
            foreach ($messages as $message) {
                if ($message['role'] === 'user') {
                    $prompt .= "User: {$message['content']}\n";
                } elseif ($message['role'] === 'assistant') {
                    $prompt .= "Assistant: {$message['content']}\n";
                }
            }
            $prompt .= "Assistant: ";

            // Create prediction
            $response = $this->client->post('predictions', [
                'json' => array_merge([
                    'version' => $this->config['version'] ?? null,
                    'input' => [
                        'prompt' => $prompt,
                    ],
                ], $options),
            ]);

            $prediction = json_decode($response->getBody()->getContents(), true);
            $predictionId = $prediction['id'];

            // Poll for completion
            $maxAttempts = 60;
            $attempt = 0;
            while ($attempt < $maxAttempts) {
                sleep(1);
                $statusResponse = $this->client->get("predictions/{$predictionId}");
                $status = json_decode($statusResponse->getBody()->getContents(), true);

                if ($status['status'] === 'succeeded') {
                    $output = $status['output'] ?? '';
                    if (is_array($output)) {
                        $output = implode("\n", $output);
                    }

                    return [
                        'content' => (string) $output,
                        'input_tokens' => 0,
                        'output_tokens' => 0,
                        'total_tokens' => 0,
                        'model' => $this->model,
                    ];
                }

                if ($status['status'] === 'failed' || $status['status'] === 'canceled') {
                    throw new \RuntimeException('Replicate prediction failed: ' . ($status['error'] ?? 'Unknown error'));
                }

                $attempt++;
            }

            throw new \RuntimeException('Replicate prediction timed out');
        } catch (RequestException $e) {
            throw new \RuntimeException('Replicate API error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function streamChat(array $messages, callable $callback, array $options = []): void
    {
        // Replicate supports streaming via webhooks, but for simplicity
        // we'll just return the complete response
        $result = $this->chat($messages, $options);
        $callback($result['content']);
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function calculateCost(int $inputTokens, int $outputTokens): float
    {
        // Replicate pricing varies by model and compute time
        return 0.0; // Placeholder - actual pricing depends on model
    }

    public function getName(): string
    {
        return 'replicate';
    }
}

