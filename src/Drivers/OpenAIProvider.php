<?php

namespace Sumeetghimire\AiOrchestrator\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class OpenAIProvider implements AiProviderInterface
{
    protected array $config;
    protected Client $client;
    protected string $model;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->model = $config['model'] ?? 'gpt-4o';
        $this->client = new Client([
            'base_uri' => 'https://api.openai.com/v1/',
            'headers' => [
                'Authorization' => 'Bearer ' . $config['api_key'],
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    public function complete(string $prompt, array $options = []): array
    {
        try {
            $response = $this->client->post('chat/completions', [
                'json' => array_merge([
                    'model' => $this->model,
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => $options['temperature'] ?? 0.7,
                ], $options),
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $choice = $data['choices'][0] ?? null;

            if (!$choice) {
                throw new \RuntimeException('No response from OpenAI');
            }

            return [
                'content' => $choice['message']['content'] ?? '',
                'input_tokens' => $data['usage']['prompt_tokens'] ?? 0,
                'output_tokens' => $data['usage']['completion_tokens'] ?? 0,
                'total_tokens' => $data['usage']['total_tokens'] ?? 0,
                'model' => $data['model'] ?? $this->model,
            ];
        } catch (RequestException $e) {
            throw new \RuntimeException('OpenAI API error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function chat(array $messages, array $options = []): array
    {
        try {
            $response = $this->client->post('chat/completions', [
                'json' => array_merge([
                    'model' => $this->model,
                    'messages' => $messages,
                    'temperature' => $options['temperature'] ?? 0.7,
                ], $options),
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $choice = $data['choices'][0] ?? null;

            if (!$choice) {
                throw new \RuntimeException('No response from OpenAI');
            }

            return [
                'content' => $choice['message']['content'] ?? '',
                'input_tokens' => $data['usage']['prompt_tokens'] ?? 0,
                'output_tokens' => $data['usage']['completion_tokens'] ?? 0,
                'total_tokens' => $data['usage']['total_tokens'] ?? 0,
                'model' => $data['model'] ?? $this->model,
            ];
        } catch (RequestException $e) {
            throw new \RuntimeException('OpenAI API error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function streamChat(array $messages, callable $callback, array $options = []): void
    {
        try {
            $response = $this->client->post('chat/completions', [
                'json' => array_merge([
                    'model' => $this->model,
                    'messages' => $messages,
                    'stream' => true,
                    'temperature' => $options['temperature'] ?? 0.7,
                ], $options),
                'stream' => true,
            ]);

            $body = $response->getBody();
            while (!$body->eof()) {
                $line = $body->readLine();
                if (empty($line) || $line === 'data: [DONE]') {
                    continue;
                }

                if (strpos($line, 'data: ') === 0) {
                    $data = json_decode(substr($line, 6), true);
                    if (isset($data['choices'][0]['delta']['content'])) {
                        $callback($data['choices'][0]['delta']['content']);
                    }
                }
            }
        } catch (RequestException $e) {
            throw new \RuntimeException('OpenAI API error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function calculateCost(int $inputTokens, int $outputTokens): float
    {
        // Pricing for GPT-4o (as of 2024)
        $pricing = [
            'gpt-4o' => ['input' => 0.0025, 'output' => 0.010],
            'gpt-4-turbo' => ['input' => 0.01, 'output' => 0.03],
            'gpt-4' => ['input' => 0.03, 'output' => 0.06],
            'gpt-3.5-turbo' => ['input' => 0.0005, 'output' => 0.0015],
        ];

        $modelPricing = $pricing[$this->model] ?? $pricing['gpt-3.5-turbo'];
        return ($inputTokens / 1000 * $modelPricing['input']) + ($outputTokens / 1000 * $modelPricing['output']);
    }

    public function getName(): string
    {
        return 'openai';
    }
}

