<?php

namespace Sumeetghimire\AiOrchestrator\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Storage;

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
            $buffer = '';
            
            while (!$body->eof()) {
                $chunk = $body->read(8192); // Read in chunks
                if ($chunk === '') {
                    break;
                }
                
                $buffer .= $chunk;
                
                // Process complete lines
                while (($pos = strpos($buffer, "\n")) !== false) {
                    $line = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 1);
                    
                    $line = trim($line);
                    if (empty($line) || $line === 'data: [DONE]') {
                        continue;
                    }

                    if (strpos($line, 'data: ') === 0) {
                        $jsonData = substr($line, 6);
                        $data = json_decode($jsonData, true);
                        if (json_last_error() === JSON_ERROR_NONE && isset($data['choices'][0]['delta']['content'])) {
                            $callback($data['choices'][0]['delta']['content']);
                        }
                    }
                }
            }
            
            // Process any remaining buffer
            if (!empty($buffer)) {
                $line = trim($buffer);
                if (!empty($line) && $line !== 'data: [DONE]' && strpos($line, 'data: ') === 0) {
                    $jsonData = substr($line, 6);
                    $data = json_decode($jsonData, true);
                    if (json_last_error() === JSON_ERROR_NONE && isset($data['choices'][0]['delta']['content'])) {
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

    public function generateImage(string $prompt, array $options = []): array
    {
        try {
            $response = $this->client->post('images/generations', [
                'json' => array_merge([
                    'prompt' => $prompt,
                    'n' => $options['n'] ?? 1,
                    'size' => $options['size'] ?? '1024x1024',
                    'response_format' => $options['response_format'] ?? 'url',
                ], $options),
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return [
                'images' => array_column($data['data'] ?? [], 'url'),
                'revised_prompt' => $data['data'][0]['revised_prompt'] ?? null,
            ];
        } catch (RequestException $e) {
            throw new \RuntimeException('OpenAI API error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function embedText(string|array $text, array $options = []): array
    {
        try {
            $input = is_array($text) ? $text : [$text];
            $model = $options['model'] ?? 'text-embedding-3-small';

            $response = $this->client->post('embeddings', [
                'json' => array_merge([
                    'model' => $model,
                    'input' => $input,
                ], $options),
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return [
                'embeddings' => array_column($data['data'] ?? [], 'embedding'),
                'usage' => $data['usage'] ?? [],
            ];
        } catch (RequestException $e) {
            throw new \RuntimeException('OpenAI API error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function transcribeAudio(string $audioPath, array $options = []): array
    {
        try {
            // Check if file exists
            if (!file_exists($audioPath)) {
                // Try with absolute path if relative path provided
                $absolutePath = realpath($audioPath);
                if (!$absolutePath || !file_exists($absolutePath)) {
                    throw new \InvalidArgumentException("Audio file not found: {$audioPath}");
                }
                $audioPath = $absolutePath;
            }

            // Verify file is readable
            if (!is_readable($audioPath)) {
                throw new \InvalidArgumentException("Audio file is not readable: {$audioPath}");
            }

            // Check file size
            $fileSize = filesize($audioPath);
            if ($fileSize === 0 || $fileSize === false) {
                throw new \InvalidArgumentException("Audio file is empty: {$audioPath}");
            }

            // Read file contents - Guzzle multipart handles streams automatically
            // We'll pass the file path directly and let Guzzle handle the stream
            $fileContents = file_get_contents($audioPath);
            if ($fileContents === false) {
                throw new \InvalidArgumentException("Could not read audio file: {$audioPath}");
            }

            // Create a temporary stream from the file contents
            $stream = fopen('php://temp', 'r+');
            fwrite($stream, $fileContents);
            rewind($stream);

            try {
                $response = $this->client->post('audio/transcriptions', [
                    'multipart' => [
                        [
                            'name' => 'file',
                            'contents' => $stream,
                            'filename' => basename($audioPath),
                        ],
                        [
                            'name' => 'model',
                            'contents' => $options['model'] ?? 'whisper-1',
                        ],
                        [
                            'name' => 'language',
                            'contents' => $options['language'] ?? '',
                        ],
                    ],
                ]);

                // Close the temporary stream
                if (is_resource($stream)) {
                    fclose($stream);
                }
            } catch (\Exception $e) {
                // Ensure stream is closed on error
                if (is_resource($stream)) {
                    fclose($stream);
                }
                throw $e;
            }

            $data = json_decode($response->getBody()->getContents(), true);

            return [
                'text' => $data['text'] ?? '',
                'language' => $data['language'] ?? null,
            ];
        } catch (RequestException $e) {
            throw new \RuntimeException('OpenAI API error: ' . $e->getMessage(), 0, $e);
        } catch (\Exception $e) {
            throw new \RuntimeException('Transcription error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function textToSpeech(string $text, array $options = []): string
    {
        try {
            $response = $this->client->post('audio/speech', [
                'json' => array_merge([
                    'model' => $options['model'] ?? 'tts-1',
                    'input' => $text,
                    'voice' => $options['voice'] ?? 'alloy',
                ], $options),
            ]);

            $audioContent = $response->getBody()->getContents();
            
            // Save audio to file if output path provided
            if (isset($options['output_path'])) {
                // Support both absolute paths and Laravel storage paths
                if (strpos($options['output_path'], storage_path()) === 0 || 
                    strpos($options['output_path'], '/') === 0) {
                    // Absolute path
                    $directory = dirname($options['output_path']);
                    if (!is_dir($directory)) {
                        mkdir($directory, 0755, true);
                    }
                    file_put_contents($options['output_path'], $audioContent);
                } else {
                    // Laravel storage path (e.g., 'audio/file.mp3')
                    $disk = $options['disk'] ?? 'public';
                    Storage::disk($disk)->put($options['output_path'], $audioContent);
                }
                return $options['output_path'];
            }

            // Return audio data as base64
            return base64_encode($audioContent);
        } catch (RequestException $e) {
            throw new \RuntimeException('OpenAI API error: ' . $e->getMessage(), 0, $e);
        }
    }
}

