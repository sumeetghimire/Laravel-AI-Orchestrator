<?php

namespace Sumeetghimire\AiOrchestrator\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class OllamaProvider implements AiProviderInterface
{
    protected array $config;
    protected Client $client;
    protected string $model;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->model = $config['model'] ?? 'llama3';
        $baseUrl = $config['base_url'] ?? 'http://localhost:11434';
        
        $this->client = new Client([
            'base_uri' => $baseUrl,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'timeout' => 300, // Longer timeout for local models
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
            $response = $this->client->post('api/chat', [
                'json' => array_merge([
                    'model' => $this->model,
                    'messages' => $messages,
                    'stream' => false,
                ], $options),
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return [
                'content' => $data['message']['content'] ?? '',
                'input_tokens' => 0, // Ollama doesn't always provide token counts
                'output_tokens' => 0,
                'total_tokens' => 0,
                'model' => $data['model'] ?? $this->model,
            ];
        } catch (RequestException $e) {
            throw new \RuntimeException('Ollama API error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function streamChat(array $messages, callable $callback, array $options = []): void
    {
        try {
            $response = $this->client->post('api/chat', [
                'json' => array_merge([
                    'model' => $this->model,
                    'messages' => $messages,
                    'stream' => true,
                ], $options),
                'stream' => true,
            ]);

            $body = $response->getBody();
            while (!$body->eof()) {
                $line = $body->readLine();
                if (empty($line)) {
                    continue;
                }

                $data = json_decode($line, true);
                if (isset($data['message']['content'])) {
                    $callback($data['message']['content']);
                }
            }
        } catch (RequestException $e) {
            throw new \RuntimeException('Ollama API error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function calculateCost(int $inputTokens, int $outputTokens): float
    {
        // Ollama is free (local models)
        return 0.0;
    }

    public function getName(): string
    {
        return 'ollama';
    }
}

