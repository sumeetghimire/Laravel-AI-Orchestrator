# 🚀 Laravel AI Orchestrator

> A unified, driver-based AI orchestration layer for Laravel — supporting OpenAI, Anthropic, Gemini, Ollama, HuggingFace, and more.

## 🧠 Overview

**Laravel AI Orchestrator** lets you connect, manage, and switch between multiple AI models with a single elegant API.  
It handles provider differences, caching, cost tracking, fallback logic, and structured output — so you can focus on building intelligent Laravel apps faster.

### ✨ Highlights

- 🧩 Plug & play support for OpenAI, Anthropic, Gemini, Ollama, HuggingFace, Replicate  
- 🔁 Fallback & chaining — automatically retry or switch models  
- 💾 Smart caching to reduce token usage & cost  
- 📊 Token + cost tracking per user & provider  
- ⚡ Streaming support for chat responses  
- 🧠 Unified API for chat, completion, embedding, and image generation  
- 🧱 Structured Output (JSON / typed responses)  
- 🔌 Extendable driver system — add your own AI provider  
- 🧍 User attribution + quota tracking  
- 🔒 Optional local/offline model support  
- 📜 Built-in logging, events, and monitoring hooks  

---

## 📦 Installation

```bash
composer require laravel/ai-orchestrator
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

## ⚙️ Configuration (`config/ai.php`)

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
AI_DRIVER=openai
OPENAI_API_KEY=your-api-key-here
ANTHROPIC_API_KEY=your-api-key-here
GEMINI_API_KEY=your-api-key-here
```

---

## 🧩 Basic Usage

### 🔹 Prompt Completion

```php
use Laravel\AiOrchestrator\Facades\Ai;

$response = Ai::prompt("Write a tweet about Laravel 12")->toText();
echo $response; 
```

### 🔹 Chat Conversation

```php
$response = Ai::chat([
    ['role' => 'system', 'content' => 'You are a coding assistant.'],
    ['role' => 'user', 'content' => 'Write a Fibonacci function in Python.'],
])->using('anthropic')->toText();
```

### 🔹 Fallback Chain

```php
$response = Ai::prompt("Explain quantum computing")
    ->using('ollama:llama3')
    ->fallback('openai:gpt-4o')
    ->toText();
```

---

## 🧱 Structured Output (Typed Responses)

### 🧠 Concept

Get **structured JSON or typed data** instead of free-form text. Useful for extraction, automation, or chaining.

### 🔹 Example: Schema Output

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

### 🔹 JSON Enforcement Mode

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

### 🔹 Schema Validation Example

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

## 💾 Caching

```php
$response = Ai::prompt("Summarize Laravel's request lifecycle")
    ->cache(3600)
    ->toText();
```

---

## 🔁 Streaming Responses

```php
Ai::prompt("Generate step-by-step Laravel CI/CD guide")
    ->stream(function ($chunk) {
        echo $chunk;
    });
```

---

## 📊 Token & Cost Tracking

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

## 🧱 Database Schema

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

## 🧰 Integration Examples

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

## 🔌 Creating Custom Providers

You can create your own AI provider by implementing the `AiProviderInterface`:

```php
use Laravel\AiOrchestrator\Drivers\AiProviderInterface;

class CustomProvider implements AiProviderInterface
{
    // Implement required methods
}
```

---

## 📊 Dashboard

The package includes a built-in dashboard for monitoring AI usage, costs, and performance.

### ⚠️ Security Note

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

- 📊 Usage statistics (cost, tokens, requests)
- 📈 Provider breakdown
- 📋 Request logs
- 🔍 Filtering by period (today/week/month/all)
- 👤 User-specific analytics

See `DASHBOARD_SECURITY.md` for detailed security configuration.

---

## 🧠 Future Roadmap

- [ ] Auto model selector (route task to best model)  
- [ ] UI dashboard for usage + cost analytics  
- [ ] Native Laravel Nova & Filament integration  
- [ ] Plugin system for tools (browser, filesystem)  
- [ ] Context-aware memory management  
- [ ] Typed DTO generation from structured output  

---

## 🤝 Contributing

Pull requests are welcome!  
If you want to add a new provider, extend the `AiProvider` interface and submit a PR.

---

## 📜 License

MIT © 2025 — Laravel AI Orchestrator Team  
Made for developers who believe AI should be **beautifully integrated**.

