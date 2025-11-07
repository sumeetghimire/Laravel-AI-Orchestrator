<?php

namespace Sumeetghimire\AiOrchestrator\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class AnthropicProvider implements AiProviderInterface
{
    protected array $config;
    protected Client $client;
    protected string $model;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->model = $config['model'] ?? 'claude-3-opus-20240229';
        $this->client = new Client([
            'base_uri' => 'https://api.anthropic.com/v1/',
            'headers' => [
                'x-api-key' => $config['api_key'],
                'anthropic-version' => '2023-06-01',
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
            $systemMessage = '';
            $anthropicMessages = [];

            foreach ($messages as $message) {
                if ($message['role'] === 'system') {
                    $systemMessage = $message['content'];
                } else {
                    $anthropicMessages[] = [
                        'role' => $message['role'] === 'assistant' ? 'assistant' : 'user',
                        'content' => $message['content'],
                    ];
                }
            }

            $payload = array_merge([
                'model' => $this->model,
                'messages' => $anthropicMessages,
                'max_tokens' => $options['max_tokens'] ?? 4096,
            ], $options);

            if ($systemMessage) {
                $payload['system'] = $systemMessage;
            }

            $response = $this->client->post('messages', [
                'json' => $payload,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return [
                'content' => $data['content'][0]['text'] ?? '',
                'input_tokens' => $data['usage']['input_tokens'] ?? 0,
                'output_tokens' => $data['usage']['output_tokens'] ?? 0,
                'total_tokens' => ($data['usage']['input_tokens'] ?? 0) + ($data['usage']['output_tokens'] ?? 0),
                'model' => $data['model'] ?? $this->model,
            ];
        } catch (RequestException $e) {
            throw new \RuntimeException('Anthropic API error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function streamChat(array $messages, callable $callback, array $options = []): void
    {
        try {
            $systemMessage = '';
            $anthropicMessages = [];

            foreach ($messages as $message) {
                if ($message['role'] === 'system') {
                    $systemMessage = $message['content'];
                } else {
                    $anthropicMessages[] = [
                        'role' => $message['role'] === 'assistant' ? 'assistant' : 'user',
                        'content' => $message['content'],
                    ];
                }
            }

            $payload = array_merge([
                'model' => $this->model,
                'messages' => $anthropicMessages,
                'max_tokens' => $options['max_tokens'] ?? 4096,
                'stream' => true,
            ], $options);

            if ($systemMessage) {
                $payload['system'] = $systemMessage;
            }

            $response = $this->client->post('messages', [
                'json' => $payload,
                'stream' => true,
            ]);

            $body = $response->getBody();
            while (!$body->eof()) {
                $line = $body->readLine();
                if (empty($line) || !str_starts_with($line, 'data: ')) {
                    continue;
                }

                $data = json_decode(substr($line, 6), true);
                if (isset($data['type']) && $data['type'] === 'content_block_delta') {
                    if (isset($data['delta']['text'])) {
                        $callback($data['delta']['text']);
                    }
                }
            }
        } catch (RequestException $e) {
            throw new \RuntimeException('Anthropic API error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function calculateCost(int $inputTokens, int $outputTokens): float
    {
        $pricing = [
            'claude-3-opus-20240229' => ['input' => 0.015, 'output' => 0.075],
            'claude-3-sonnet-20240229' => ['input' => 0.003, 'output' => 0.015],
            'claude-3-haiku-20240307' => ['input' => 0.00025, 'output' => 0.00125],
        ];

        $modelPricing = $pricing[$this->model] ?? $pricing['claude-3-sonnet-20240229'];
        return ($inputTokens / 1000 * $modelPricing['input']) + ($outputTokens / 1000 * $modelPricing['output']);
    }

    public function getName(): string
    {
        return 'anthropic';
    }

    public function generateImage(string $prompt, array $options = []): array
    {
        throw new \RuntimeException('Image generation not supported by Anthropic. Use OpenAI or other providers.');
    }

    public function embedText(string|array $text, array $options = []): array
    {
        throw new \RuntimeException('Embeddings not directly supported by Anthropic API. Use OpenAI or other providers.');
    }

    public function transcribeAudio(string $audioPath, array $options = []): array
    {
        throw new \RuntimeException('Audio transcription not supported by Anthropic. Use OpenAI Whisper.');
    }

    public function textToSpeech(string $text, array $options = []): string
    {
        throw new \RuntimeException('Text-to-speech not supported by Anthropic. Use OpenAI TTS or other providers.');
    }
}

