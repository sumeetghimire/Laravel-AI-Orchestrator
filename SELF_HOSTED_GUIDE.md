# Self-Hosted & Local Model Guide

This guide covers setting up and using self-hosted/local AI models with Laravel AI Orchestrator.

## Table of Contents

- [Why Self-Hosted Models?](#why-self-hosted-models)
- [Ollama Setup](#ollama-setup)
- [Configuration](#configuration)
- [Usage Examples](#usage-examples)
- [Deployment Options](#deployment-options)
- [Custom Self-Hosted Providers](#custom-self-hosted-providers)
- [Troubleshooting](#troubleshooting)

## Why Self-Hosted Models?

### Benefits

1. **Zero API Costs** — No per-request fees or token charges
2. **Data Privacy** — Keep sensitive data on your infrastructure
3. **Offline Capability** — Work without internet connectivity
4. **No Rate Limits** — Unlimited requests without API throttling
5. **Custom Models** — Use fine-tuned or specialized models
6. **Development** — Perfect for testing without API costs

### When to Use

- **Development & Testing** — Test AI features without API costs
- **Privacy-Sensitive Applications** — Healthcare, finance, legal
- **High Volume** — Process thousands of requests without cost
- **Offline Applications** — Edge devices, air-gapped networks
- **Custom Models** — Fine-tuned models for specific domains

## Ollama Setup

[Ollama](https://ollama.ai) is the recommended solution for running local LLMs. It's easy to install and provides a simple API.

### Installation

#### macOS

```bash
# Using Homebrew
brew install ollama

# Or download from https://ollama.ai/download
```

#### Linux

```bash
# Install script
curl -fsSL https://ollama.ai/install.sh | sh

# Or using package manager
# Ubuntu/Debian
curl -fsSL https://ollama.ai/install.sh | sh
```

#### Windows

Download from [ollama.ai/download](https://ollama.ai/download) and run the installer.

### Starting the Server

```bash
# Start Ollama server
ollama serve

# The server will run on http://localhost:11434
```

### Pulling Models

```bash
# Popular models
ollama pull llama3              # Meta's Llama 3 (8B)
ollama pull llama3:70b         # Llama 3 70B (larger, better quality)
ollama pull mistral            # Mistral 7B
ollama pull codellama          # Code-focused Llama
ollama pull neural-chat        # Neural Chat model
ollama pull starling-lm        # Starling LM

# List available models
ollama list

# See all available models
# Visit https://ollama.ai/library
```

### Model Recommendations

| Model | Size | Use Case | Quality |
|-------|------|----------|---------|
| `llama3:8b` | ~4.7GB | General purpose, development | ⭐⭐⭐⭐ |
| `llama3:70b` | ~40GB | High-quality responses | ⭐⭐⭐⭐⭐ |
| `mistral:7b` | ~4.1GB | Fast, efficient | ⭐⭐⭐⭐ |
| `codellama` | ~3.8GB | Code generation, debugging | ⭐⭐⭐⭐⭐ |
| `neural-chat` | ~4.1GB | Conversational AI | ⭐⭐⭐⭐ |

## Configuration

### Environment Variables

Add to your `.env` file:

```env
# Use Ollama as default
AI_DRIVER=ollama

# Local Ollama server
OLLAMA_BASE_URL=http://localhost:11434
OLLAMA_MODEL=llama3

# Or remote Ollama server
# OLLAMA_BASE_URL=http://your-server:11434
# OLLAMA_MODEL=llama3:70b
```

### Config File

The `config/ai.php` file already includes Ollama configuration:

```php
'ollama' => [
    'driver' => 'ollama',
    'base_url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
    'model' => env('OLLAMA_MODEL', 'llama3'),
],
```

## Usage Examples

### Basic Usage

```php
use Sumeetghimire\AiOrchestrator\Facades\Ai;

// Use Ollama as default
$response = Ai::prompt("Explain Laravel's service container")
    ->toText();

// Explicit provider
$response = Ai::prompt("Write a Python function")
    ->using('ollama')
    ->toText();

// Specify model inline
$response = Ai::prompt("Generate SQL query")
    ->using('ollama:codellama')
    ->toText();
```

### Chat Conversations

```php
$response = Ai::chat([
    ['role' => 'system', 'content' => 'You are a helpful coding assistant.'],
    ['role' => 'user', 'content' => 'Write a Fibonacci function in PHP.'],
])->using('ollama:codellama')
  ->toText();
```

### Embeddings

```php
$embeddings = Ai::embed("Laravel is a PHP framework")
    ->using('ollama')
    ->toEmbeddings();
```

### Hybrid Setup (Cloud + Local)

Use cloud for production, local for development:

```php
// Production: Cloud with local fallback
$response = Ai::prompt("Complex analysis")
    ->using('openai')
    ->fallback('ollama:llama3')
    ->toText();

// Development: Local only
$response = Ai::prompt("Test prompt")
    ->using('ollama')
    ->toText();
```

### Cost-Saving Strategy

Use local models for development/testing, cloud for production:

```php
// app/helpers.php or in your service
function aiResponse($prompt) {
    if (app()->environment('local')) {
        return Ai::prompt($prompt)
            ->using('ollama')
            ->toText();
    }
    
    return Ai::prompt($prompt)
        ->using('openai')
        ->fallback('ollama')
        ->toText();
}
```

## Deployment Options

### Option 1: Same Server

Run Ollama on the same machine as Laravel:

```bash
# Install Ollama
curl -fsSL https://ollama.ai/install.sh | sh

# Start as service
systemctl enable ollama
systemctl start ollama

# Pull models
ollama pull llama3
```

### Option 2: Dedicated GPU Server

Run Ollama on a separate GPU server for better performance:

```env
# In Laravel .env
OLLAMA_BASE_URL=http://gpu-server:11434
```

### Option 3: Docker

```yaml
# docker-compose.yml
services:
  ollama:
    image: ollama/ollama:latest
    ports:
      - "11434:11434"
    volumes:
      - ollama-data:/root/.ollama
    # Optional: GPU support
    deploy:
      resources:
        reservations:
          devices:
            - driver: nvidia
              count: 1
              capabilities: [gpu]

volumes:
  ollama-data:
```

### Option 4: Kubernetes

```yaml
# ollama-deployment.yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: ollama
spec:
  replicas: 1
  selector:
    matchLabels:
      app: ollama
  template:
    metadata:
      labels:
        app: ollama
    spec:
      containers:
      - name: ollama
        image: ollama/ollama:latest
        ports:
        - containerPort: 11434
        resources:
          requests:
            nvidia.com/gpu: 1
```

## Custom Self-Hosted Providers

You can create a custom provider for any self-hosted model API.

### Example: Custom Provider

```php
<?php

namespace App\Providers\Ai;

use Sumeetghimire\AiOrchestrator\Drivers\AiProviderInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class CustomLocalProvider implements AiProviderInterface
{
    protected Client $client;
    protected string $model;
    
    public function __construct(array $config)
    {
        $this->model = $config['model'] ?? 'default';
        $this->client = new Client([
            'base_uri' => $config['base_url'] ?? 'http://localhost:8080',
            'timeout' => 300,
        ]);
    }
    
    public function complete(string $prompt, array $options = []): array
    {
        try {
            $response = $this->client->post('/v1/completions', [
                'json' => [
                    'model' => $this->model,
                    'prompt' => $prompt,
                    'temperature' => $options['temperature'] ?? 0.7,
                ],
            ]);
            
            $data = json_decode($response->getBody()->getContents(), true);
            
            return [
                'content' => $data['text'] ?? '',
                'input_tokens' => $data['usage']['prompt_tokens'] ?? 0,
                'output_tokens' => $data['usage']['completion_tokens'] ?? 0,
                'total_tokens' => $data['usage']['total_tokens'] ?? 0,
                'model' => $this->model,
            ];
        } catch (RequestException $e) {
            throw new \RuntimeException('Custom provider error: ' . $e->getMessage(), 0, $e);
        }
    }
    
    // Implement other interface methods...
    public function chat(array $messages, array $options = []): array { /* ... */ }
    public function streamChat(array $messages, callable $callback, array $options = []): void { /* ... */ }
    public function generateImage(string $prompt, array $options = []): array { /* ... */ }
    public function embedText(string|array $text, array $options = []): array { /* ... */ }
    public function transcribeAudio(string $audioPath, array $options = []): array { /* ... */ }
    public function textToSpeech(string $text, array $options = []): string { /* ... */ }
    public function getModel(): string { return $this->model; }
    public function calculateCost(int $inputTokens, int $outputTokens): float { return 0.0; }
    public function getName(): string { return 'custom-local'; }
}
```

### Register Custom Provider

Update `src/Drivers/DriverFactory.php`:

```php
return match ($driver) {
    'openai' => new OpenAIProvider($providerConfig),
    'ollama' => new OllamaProvider($providerConfig),
    'custom-local' => new CustomLocalProvider($providerConfig),
    // ...
};
```

Then add to `config/ai.php`:

```php
'providers' => [
    'custom-local' => [
        'driver' => 'custom-local',
        'base_url' => env('CUSTOM_MODEL_URL', 'http://localhost:8080'),
        'model' => env('CUSTOM_MODEL', 'my-model'),
    ],
],
```

## Troubleshooting

### Connection Issues

**Error: "Connection refused"**

- Ensure Ollama server is running: `ollama serve`
- Check `OLLAMA_BASE_URL` in `.env`
- Verify firewall allows port 11434

**Error: "Model not found"**

- Pull the model: `ollama pull llama3`
- Check available models: `ollama list`
- Verify `OLLAMA_MODEL` in `.env`

### Performance Issues

**Slow responses:**

- Use smaller models (e.g., `llama3:8b` instead of `llama3:70b`)
- Enable GPU support in Ollama
- Run Ollama on a dedicated server
- Increase timeout in `OllamaProvider.php`

**Out of memory:**

- Use smaller models
- Increase server RAM
- Use cloud providers for large models

### Production Tips

1. **Use systemd service** for automatic restarts
2. **Monitor resource usage** (CPU, RAM, GPU)
3. **Set up health checks** for Ollama server
4. **Implement fallback** to cloud providers
5. **Cache responses** for common queries

## Resources

- [Ollama Documentation](https://github.com/ollama/ollama)
- [Ollama Model Library](https://ollama.ai/library)
- [Laravel AI Orchestrator GitHub](https://github.com/sumeetghimire/Laravel-AI-Orchestrator)

## Support

For issues or questions:
- Open an issue on GitHub
- Check the main README.md
- Review Ollama documentation

