<?php

namespace Laravel\AiOrchestrator\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class GeminiProvider implements AiProviderInterface
{
    protected array $config;
    protected Client $client;
    protected string $model;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->model = $config['model'] ?? 'gemini-1.5-pro';
        $this->client = new Client([
            'base_uri' => 'https://generativelanguage.googleapis.com/v1beta/',
            'headers' => [
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
            // Convert messages format for Gemini
            $geminiMessages = [];
            foreach ($messages as $message) {
                if ($message['role'] !== 'system') {
                    $geminiMessages[] = [
                        'role' => $message['role'] === 'assistant' ? 'model' : 'user',
                        'parts' => [['text' => $message['content']]],
                    ];
                }
            }

            $response = $this->client->post("models/{$this->model}:generateContent", [
                'query' => ['key' => $this->config['api_key']],
                'json' => array_merge([
                    'contents' => $geminiMessages,
                ], $options),
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $candidate = $data['candidates'][0] ?? null;

            if (!$candidate) {
                throw new \RuntimeException('No response from Gemini');
            }

            $content = $candidate['content']['parts'][0]['text'] ?? '';
            $usageMetadata = $data['usageMetadata'] ?? [];

            return [
                'content' => $content,
                'input_tokens' => $usageMetadata['promptTokenCount'] ?? 0,
                'output_tokens' => $usageMetadata['candidatesTokenCount'] ?? 0,
                'total_tokens' => $usageMetadata['totalTokenCount'] ?? 0,
                'model' => $this->model,
            ];
        } catch (RequestException $e) {
            throw new \RuntimeException('Gemini API error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function streamChat(array $messages, callable $callback, array $options = []): void
    {
        try {
            $geminiMessages = [];
            foreach ($messages as $message) {
                if ($message['role'] !== 'system') {
                    $geminiMessages[] = [
                        'role' => $message['role'] === 'assistant' ? 'model' : 'user',
                        'parts' => [['text' => $message['content']]],
                    ];
                }
            }

            $response = $this->client->post("models/{$this->model}:streamGenerateContent", [
                'query' => ['key' => $this->config['api_key']],
                'json' => array_merge([
                    'contents' => $geminiMessages,
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
                if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                    $callback($data['candidates'][0]['content']['parts'][0]['text']);
                }
            }
        } catch (RequestException $e) {
            throw new \RuntimeException('Gemini API error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function calculateCost(int $inputTokens, int $outputTokens): float
    {
        // Pricing for Gemini models (as of 2024)
        $pricing = [
            'gemini-1.5-pro' => ['input' => 0.00125, 'output' => 0.005],
            'gemini-1.5-flash' => ['input' => 0.000075, 'output' => 0.0003],
        ];

        $modelPricing = $pricing[$this->model] ?? $pricing['gemini-1.5-pro'];
        return ($inputTokens / 1000 * $modelPricing['input']) + ($outputTokens / 1000 * $modelPricing['output']);
    }

    public function getName(): string
    {
        return 'gemini';
    }
}

