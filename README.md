<div align="center">
  <img src="https://raw.githubusercontent.com/sumeetghimire/Laravel-AI-Orchestrator/main/logo.png" alt="Laravel AI Orchestrator" width="200">
  
  # Laravel AI Orchestrator
  
  > A unified, driver-based AI orchestration layer for Laravel — supporting OpenAI, Anthropic, Gemini, Ollama, HuggingFace, and more.
</div>

## Overview

**Laravel AI Orchestrator** lets you connect, manage, and switch between multiple AI models with a single elegant API.  
It handles provider differences, caching, cost tracking, fallback logic, and structured output — so you can focus on building intelligent Laravel apps faster.

### Highlights

- Plug & play support for OpenAI, Anthropic, Gemini, Ollama, HuggingFace, Replicate  
- Self-hosted/local model support — run AI models on your own infrastructure  
- Fallback & chaining — automatically retry or switch models  
- Smart caching to reduce token usage & cost  
- Token + cost tracking per user & provider  
- Streaming support for chat responses  
- Multi-modal support — images, audio, embeddings  
- Unified API for chat, completion, embeddings, image generation, and audio  
- Structured Output (JSON / typed responses)  
- Extendable driver system — add your own AI provider  
- User attribution + quota tracking  
- Zero-cost local models — perfect for development and privacy-sensitive applications  
- Built-in logging, events, and monitoring hooks  

---

## Installation

```bash
composer require sumeetghimire/laravel-ai-orchestrator
```

Then publish config:

```bash
php artisan vendor:publish --tag="ai-config"
```

Publish migrations:

```bash
php artisan vendor:publish --tag="ai-migrations"
php artisan migrate
```

---

## Configuration (`config/ai.php`)

The package comes with a default configuration file. You can customize it by publishing the config file.

```php
return [
    'default' => env('AI_DRIVER', 'openai'),
    'providers' => [
        'openai' => [
            'driver' => 'openai',
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-4o'),
        ],
        // ... other providers
    ],
    'cache' => [
        'enabled' => env('AI_CACHE_ENABLED', true),
        'ttl' => env('AI_CACHE_TTL', 3600),
    ],
    'logging' => [
        'enabled' => env('AI_LOGGING_ENABLED', true),
        'driver' => env('AI_LOGGING_DRIVER', 'database'),
    ],
];
```

Don't forget to add your API keys to your `.env` file:

```env
# Cloud providers
AI_DRIVER=openai
OPENAI_API_KEY=your-api-key-here
ANTHROPIC_API_KEY=your-api-key-here
GEMINI_API_KEY=your-api-key-here

# Self-hosted (Ollama)
OLLAMA_BASE_URL=http://localhost:11434
OLLAMA_MODEL=llama3
```

---

## Basic Usage

### Prompt Completion

```php
use Sumeetghimire\AiOrchestrator\Facades\Ai;

$response = Ai::prompt("Write a tweet about Laravel 12")->toText();
echo $response; 
```

### Chat Conversation

```php
$response = Ai::chat([
    ['role' => 'system', 'content' => 'You are a coding assistant.'],
    ['role' => 'user', 'content' => 'Write a Fibonacci function in Python.'],
])->using('anthropic')->toText();
```

### Fallback Chain

```php
// Try cloud first, fallback to local
$response = Ai::prompt("Explain quantum computing")
    ->using('openai:gpt-4o')
    ->fallback('ollama:llama3')
    ->toText();

// Or local first, cloud fallback
$response = Ai::prompt("Quick response")
    ->using('ollama:llama3')
    ->fallback('openai:gpt-4o')
    ->toText();
```

---

## Structured Output (Typed Responses)

### Concept

Get **structured JSON or typed data** instead of free-form text. Useful for extraction, automation, or chaining.

### Example: Schema Output

```php
$article = Ai::prompt("Summarize this blog post", [
    'input' => $blogText,
])->expect([
    'title' => 'string',
    'summary' => 'string',
    'keywords' => 'array',
])->toStructured();
```

**Output:**
```php
[
  'title' => 'Laravel Orchestrator: Unified AI Layer',
  'summary' => 'A system for managing multiple AI models...',
  'keywords' => ['laravel', 'ai', 'openai', 'orchestrator']
]
```

### JSON Enforcement Mode

```php
$response = Ai::prompt("Extract contact info from this text")
    ->using('openai:gpt-4o')
    ->expectSchema([
        'name' => 'string',
        'email' => 'string',
        'phone' => 'string',
    ])
    ->toStructured();
```

The orchestrator automatically ensures the model returns valid JSON, retries on errors, and validates schema.

### Schema Validation Example

```php
$validated = Ai::prompt("Generate product data")
    ->expectSchema([
        'name' => 'required|string',
        'price' => 'required|numeric|min:0',
        'stock' => 'integer',
    ])
    ->validate();
```

Invalid JSON? The orchestrator retries with a correction prompt automatically.

---

## Caching

```php
$response = Ai::prompt("Summarize Laravel's request lifecycle")
    ->cache(3600)
    ->toText();
```

---

## Streaming Responses

```php
Ai::prompt("Generate step-by-step Laravel CI/CD guide")
    ->stream(function ($chunk) {
        echo $chunk;
    });
```

---

## Multi-Modal Support

### Image Generation

```php
$images = Ai::image("A futuristic cityscape at sunset")
    ->using('openai')
    ->toImages();

// Returns array of image URLs
foreach ($images as $imageUrl) {
    echo "<img src='{$imageUrl}'>";
}
```

### Embeddings (Vector Search)

```php
// Generate embeddings for semantic search
$embeddings = Ai::embed("Laravel is a PHP framework")
    ->using('openai')
    ->toEmbeddings();

// Or multiple texts at once
$embeddings = Ai::embed([
    "Laravel framework",
    "PHP development",
    "Web application"
])->toEmbeddings();
```

### Audio Transcription (Speech-to-Text)

```php
$transcription = Ai::transcribe(storage_path('audio/recording.mp3'))
    ->using('openai')
    ->toText();

echo $transcription; // "The transcribed text..."
```

### Text-to-Speech

```php
// Save to file
$audioPath = Ai::speak("Hello, this is a test")
    ->using('openai')
    ->withOptions(['output_path' => storage_path('audio/output.mp3')])
    ->toAudio();

// Or get as base64
$audioBase64 = Ai::speak("Hello world")
    ->using('openai')
    ->toAudio();
```

---

## Self-Hosted & Local Model Support

**Laravel AI Orchestrator** fully supports self-hosted and local AI models, giving you complete control over your AI infrastructure, privacy, and costs.

### Why Use Self-Hosted Models?

- **Zero API costs** — Run models locally without per-request fees
- **Data privacy** — Keep sensitive data on your infrastructure
- **Offline capability** — Work without internet connectivity
- **Custom models** — Use fine-tuned or specialized models
- **Development flexibility** — Test without API rate limits

### Ollama (Recommended for Local Models)

[Ollama](https://ollama.ai) is the easiest way to run large language models locally. It provides a simple API server that runs on your machine or server.

#### Installation

```bash
# Install Ollama (macOS/Linux)
curl -fsSL https://ollama.ai/install.sh | sh

# Or download from https://ollama.ai/download
```

#### Setup

1. **Start Ollama server:**
   ```bash
   ollama serve
   ```

2. **Pull a model:**
   ```bash
   ollama pull llama3
   ollama pull mistral
   ollama pull codellama
   ```

3. **Configure in Laravel:**

   Add to your `.env`:
   ```env
   AI_DRIVER=ollama
   OLLAMA_BASE_URL=http://localhost:11434
   OLLAMA_MODEL=llama3
   ```

   Or use remote Ollama server:
   ```env
   OLLAMA_BASE_URL=http://your-server:11434
   ```

#### Usage

```php
// Use Ollama as default
$response = Ai::prompt("Explain Laravel's service container")
    ->using('ollama')
    ->toText();

// Or specify model inline
$response = Ai::prompt("Write Python code")
    ->using('ollama:codellama')
    ->toText();

// Use local model as fallback
$response = Ai::prompt("Complex task")
    ->using('openai')           // Try cloud first
    ->fallback('ollama:llama3')  // Fallback to local if cloud fails
    ->toText();
```

#### Supported Ollama Models

- `llama3` / `llama3:8b` / `llama3:70b`
- `mistral` / `mistral:7b`
- `codellama` / `codellama:13b`
- `neural-chat` / `starling-lm`
- And [100+ more models](https://ollama.ai/library)

### Custom Self-Hosted Models

You can create a custom provider for any self-hosted model that exposes an API:

```php
use Sumeetghimire\AiOrchestrator\Drivers\AiProviderInterface;

class CustomSelfHostedProvider implements AiProviderInterface
{
    protected $baseUrl;
    
    public function __construct(array $config)
    {
        $this->baseUrl = $config['base_url'] ?? 'http://localhost:8080';
        // Initialize your HTTP client
    }
    
    // Implement interface methods...
}
```

Then register it in `config/ai.php`:

```php
'providers' => [
    'custom-local' => [
        'driver' => 'custom-self-hosted',
        'base_url' => env('CUSTOM_MODEL_URL', 'http://localhost:8080'),
        'model' => env('CUSTOM_MODEL', 'my-custom-model'),
    ],
],
```

### Hybrid Setup (Cloud + Local)

Perfect for production: use cloud models for heavy tasks, local models for development and fallbacks.

```php
// Production: Use cloud with local fallback
$response = Ai::prompt("User query")
    ->using('openai')
    ->fallback('ollama:llama3')
    ->toText();

// Development: Use local only
$response = Ai::prompt("Development test")
    ->using('ollama')
    ->toText();
```

### Cost Comparison

| Provider | Cost per Request | Notes |
|----------|-----------------|-------|
| **Ollama (Local)** | **$0.00** | Free, runs on your hardware |
| OpenAI GPT-4 | ~$0.03-0.10 | Pay per token |
| Anthropic Claude | ~$0.015-0.075 | Pay per token |
| Gemini | ~$0.00125-0.005 | Pay per token |

**Local models are free but require hardware.** Great for development, testing, and privacy-sensitive applications.

### Deployment Options

1. **Same Server** — Run Ollama on the same machine as Laravel
2. **Dedicated Server** — Run Ollama on a separate GPU server
3. **Docker Container** — Deploy Ollama in Docker for easy scaling
4. **Kubernetes** — Orchestrate multiple local model instances

### Example: Docker Setup

```dockerfile
# Dockerfile for Ollama server
FROM ollama/ollama:latest

# Expose Ollama API
EXPOSE 11434
```

```yaml
# docker-compose.yml
services:
  ollama:
    image: ollama/ollama
    ports:
      - "11434:11434"
    volumes:
      - ollama-data:/root/.ollama
```

---

## Token & Cost Tracking

```php
$total = Ai::usage()
    ->provider('openai')
    ->today()
    ->sum('cost');
```

Or per user:

```php
$totalTokens = Ai::usage()
    ->user(auth()->id())
    ->sum('tokens');
```

---

## Database Schema

**Table:** `ai_logs`

| Column | Type | Description |
|---------|------|-------------|
| `id` | bigint | Primary key |
| `user_id` | int | Optional |
| `provider` | string | Provider name |
| `model` | string | Model used |
| `prompt` | text | Prompt content |
| `response` | longtext | Response content |
| `tokens` | int | Token count |
| `cost` | decimal | Estimated cost |
| `cached` | boolean | Cached result flag |
| `duration` | float | Time taken |
| `created_at` | timestamp | — |

---

## Integration Examples

```php
// Blade / Controller
$response = Ai::prompt("Suggest SEO titles for: $post->title")->toText();

// Jobs / Queues
Ai::prompt("Analyze user logs")->queue()->dispatchLater();

// API Endpoint
Route::post('/ai', fn(Request $req) =>
    Ai::prompt($req->input('prompt'))->json()
);
```

---

## Creating Custom Providers

You can create your own AI provider by implementing the `AiProviderInterface`:

```php
use Sumeetghimire\AiOrchestrator\Drivers\AiProviderInterface;

class CustomProvider implements AiProviderInterface
{
    // Implement required methods
}
```

---

## Dashboard

The package includes a built-in dashboard for monitoring AI usage, costs, and performance.

### Security Note

**The dashboard is disabled by default for security.** You must explicitly enable it.

### Enable Dashboard

```env
AI_DASHBOARD_ENABLED=true
AI_DASHBOARD_MIDDLEWARE=auth  # Protect with authentication
```

### Access Dashboard

Once enabled:
```
http://your-app.com/ai-orchestrator/dashboard
```

### Secure the Dashboard

**Always protect the dashboard with middleware:**

```env
# Require authentication
AI_DASHBOARD_MIDDLEWARE=auth

# Require admin role
AI_DASHBOARD_MIDDLEWARE=auth,role:admin

# Custom middleware
AI_DASHBOARD_MIDDLEWARE=auth,admin.check
```

### Dashboard Features

- Usage statistics (cost, tokens, requests)
- Provider breakdown
- Request logs
- Filtering by period (today/week/month/all)
- User-specific analytics

See `DASHBOARD_SECURITY.md` for detailed security configuration.

---

## Self-Hosted Models

Laravel AI Orchestrator supports self-hosted and local AI models for zero-cost, privacy-focused AI operations.

**Quick Start:**
```bash
# Install Ollama
curl -fsSL https://ollama.ai/install.sh | sh

# Pull a model
ollama pull llama3

# Configure in Laravel
# Set OLLAMA_BASE_URL=http://localhost:11434 in .env
```

**Usage:**
```php
$response = Ai::prompt("Explain Laravel")
    ->using('ollama:llama3')
    ->toText();
```

See **[SELF_HOSTED_GUIDE.md](SELF_HOSTED_GUIDE.md)** for complete setup instructions, deployment options, and troubleshooting.

---

## Future Roadmap

- [ ] Auto model selector (route task to best model)  
- [ ] UI dashboard for usage + cost analytics  
- [ ] Native Laravel Nova & Filament integration  
- [ ] Plugin system for tools (browser, filesystem)  
- [ ] Context-aware memory management  
- [ ] Typed DTO generation from structured output  

---

## Contributing

Pull requests are welcome!  
If you want to add a new provider, extend the `AiProvider` interface and submit a PR.

---

## License

MIT © 2025 — Laravel AI Orchestrator Team  
Made for developers who believe AI should be **beautifully integrated**.

