# AI Decision Tracing & Replay

**New in v1.3.0** — Automatically record every AI call and replay any trace with different providers, models, or settings to compare results.

## Overview

The AI Decision Tracing & Replay feature provides complete visibility into your AI operations. Every AI call is automatically recorded with full context, allowing you to:

- **Debug issues** - See exactly what was sent and received
- **Compare providers** - Replay the same input with different models
- **Track costs** - Monitor token usage and costs per trace
- **A/B test prompts** - Compare different prompt versions
- **Audit decisions** - Full record of all AI decisions for compliance

## Quick Start

### 1. Enable Tracing

Add to your `.env` file:

```env
AI_TRACING_ENABLED=true
AI_TRACING_REDACT_KEYS=api_key,password,secret,token
AI_TRACING_STORE_RAW_OUTPUT=true
```

### 2. Run Migration

```bash
php artisan migrate
```

### 3. Use AI (Automatic Tracing)

When tracing is enabled, all AI calls are automatically traced:

```php
use Sumeetghimire\AiOrchestrator\Facades\Ai;

// This call is automatically traced - no code changes needed!
$response = Ai::prompt("Explain quantum computing")
    ->using('openai')
    ->toText();
```

### 4. View Traces

Access traces via the dashboard API (when dashboard is enabled):

```
GET /ai-orchestrator/traces
```

## Configuration

Add to `config/ai.php` (or via `.env`):

```php
'tracing' => [
    'enable_tracing' => env('AI_TRACING_ENABLED', false),
    'redact_keys' => explode(',', env('AI_TRACING_REDACT_KEYS', 'api_key,password,secret,token')),
    'store_raw_output' => env('AI_TRACING_STORE_RAW_OUTPUT', true),
],
```

### Configuration Options

- **`enable_tracing`** - Enable/disable tracing (default: `false`)
- **`redact_keys`** - Array of keys to redact from input (default: `['api_key', 'password', 'secret', 'token']`)
- **`store_raw_output`** - Store full output in traces (default: `true`)

## Usage Examples

### Automatic Tracing

When enabled, all AI calls are automatically traced:

```php
$response = Ai::prompt("Write a tweet about Laravel 12")
    ->using('openai')
    ->toText();
// Trace is automatically created!
```

### Tag Traces with Prompt Identifiers

Tag traces for easier filtering and comparison:

```php
$response = Ai::prompt("Analyze user sentiment")
    ->withPromptIdentifier('sentiment_analyzer', 'v2')
    ->using('anthropic')
    ->toText();
```

### Explicit Tracing

Use the `trace()` method for explicit control:

```php
$response = Ai::trace()
    ->prompt("Generate product description")
    ->using('gemini')
    ->toText();
```

### Replay Functionality

#### Basic Replay

Replay a previous trace with the same input:

```php
$traceId = 'your-trace-uuid-here';

$response = Ai::replay($traceId)
    ->run()
    ->toText();
```

#### Replay with Overrides

Override provider, model, or options during replay:

```php
// Replay with a different provider
$response = Ai::replay($traceId)
    ->withProvider('anthropic')
    ->run()
    ->toText();

// Replay with a different model
$response = Ai::replay($traceId)
    ->withModel('gpt-4o')
    ->run()
    ->toText();

// Replay with different prompt and options
$response = Ai::replay($traceId)
    ->withPrompt('job_matcher', 'v4')
    ->withOptions(['temperature' => 0.7])
    ->run()
    ->toText();
```

#### Chaining Replay Options

```php
$response = Ai::replay($traceId)
    ->withProvider('anthropic')
    ->withModel('claude-3-opus')
    ->withPrompt('enhanced_analyzer', 'v3')
    ->withOptions(['temperature' => 0.8, 'max_tokens' => 2000])
    ->run()
    ->toText();
```

## Accessing Trace Data

### Find a Trace

```php
use Sumeetghimire\AiOrchestrator\Models\AiTrace;

$trace = AiTrace::find('trace-uuid');
echo $trace->provider; // 'openai'
echo $trace->model; // 'gpt-4o'
echo $trace->input_tokens; // 150
echo $trace->output_tokens; // 300
echo $trace->estimated_cost; // 0.0123
```

### Get Replay Traces

```php
$originalTrace = AiTrace::find('trace-uuid');
$replays = $originalTrace->replayTraces;

foreach ($replays as $replay) {
    echo "Replay: {$replay->trace_id} using {$replay->provider}\n";
}
```

### Query Traces

```php
// Get traces by user
$traces = AiTrace::where('user_id', auth()->id())
    ->orderBy('created_at', 'desc')
    ->limit(50)
    ->get();

// Get traces by provider
$traces = AiTrace::where('provider', 'openai')
    ->where('created_at', '>=', now()->subDays(7))
    ->get();

// Get traces by prompt identifier
$traces = AiTrace::where('prompt_identifier', 'sentiment_analyzer')
    ->where('prompt_version', 'v2')
    ->get();
```

## Production API Endpoints

When both dashboard and tracing are enabled, these endpoints are automatically available:

### Prerequisites

```env
AI_DASHBOARD_ENABLED=true
AI_TRACING_ENABLED=true
AI_DASHBOARD_MIDDLEWARE=auth
```

### Available Endpoints

Base URL: `http://your-app.com/ai-orchestrator`

#### 1. List All Traces

**GET** `/ai-orchestrator/traces`

Query parameters:
- `provider` - Filter by provider
- `user_id` - Filter by user ID
- `prompt_identifier` - Filter by prompt identifier
- `period` - `today`, `week`, `month`, `all` (default: `all`)
- `replays_only` - Show only replays (`true`/`false`)
- `originals_only` - Show only original traces (`true`/`false`)

**Example:**
```
GET /ai-orchestrator/traces?provider=openai&period=today
```

#### 2. View Specific Trace

**GET** `/ai-orchestrator/traces/{trace-id}`

Returns full trace details including input, output, errors, and metadata.

#### 3. Get Trace Statistics

**GET** `/ai-orchestrator/traces/stats`

Query parameters:
- `period` - `today`, `week`, `month`, `all`
- `user_id` - Filter by user ID

Returns aggregated statistics including total traces, cost, tokens, and provider breakdown.

#### 4. Get Replays for a Trace

**GET** `/ai-orchestrator/traces/{trace-id}/replays`

Returns all replay traces for a specific original trace.

#### 5. Execute a Replay

**POST** `/ai-orchestrator/traces/{trace-id}/replay`

Request body (all fields optional):
```json
{
  "provider": "anthropic",
  "model": "claude-3-opus",
  "prompt_identifier": "enhanced_analyzer",
  "prompt_version": "v2",
  "options": {
    "temperature": 0.7,
    "max_tokens": 2000
  }
}
```

**Response:**
```json
{
  "success": true,
  "original_trace_id": "...",
  "replay_trace_id": "...",
  "response": "The replayed response...",
  "overrides": {...}
}
```

### Security

All endpoints are protected by `AI_DASHBOARD_MIDDLEWARE`. Recommended:

```env
AI_DASHBOARD_MIDDLEWARE=auth,role:admin
```

## Trace Data Structure

Each trace contains:

- **`trace_id`** - UUID identifier
- **`parent_trace_id`** - UUID of original trace (for replays)
- **`user_id`** - User who made the request
- **`provider`** - AI provider used (openai, anthropic, etc.)
- **`model`** - Model name
- **`prompt_identifier`** - Optional prompt identifier
- **`prompt_version`** - Optional prompt version
- **`sanitized_input`** - Input with sensitive data redacted
- **`input_tokens`** - Input token count
- **`output_tokens`** - Output token count
- **`estimated_cost`** - Estimated cost
- **`retry_count`** - Number of retries
- **`validation_errors`** - Validation errors (if any)
- **`schema_errors`** - Schema validation errors (if any)
- **`final_output`** - Full output (if `store_raw_output` is enabled)
- **`metadata`** - Additional metadata (JSON)
- **`created_at`** - Timestamp

## Use Cases

### 1. Debugging

When an AI response is unexpected, view the exact trace:

```php
$trace = AiTrace::find($traceId);
// See exactly what was sent and received
var_dump($trace->sanitized_input);
var_dump($trace->final_output);
```

### 2. Cost Optimization

Compare costs across providers:

```php
// Original trace with OpenAI
$original = AiTrace::find($traceId);

// Replay with Anthropic
$replay = Ai::replay($traceId)
    ->withProvider('anthropic')
    ->run();

// Compare costs
$originalCost = $original->estimated_cost;
$replayCost = AiTrace::where('parent_trace_id', $traceId)
    ->latest()
    ->first()
    ->estimated_cost;
```

### 3. A/B Testing Prompts

Test different prompt versions:

```php
// Version 1
$v1 = Ai::prompt("Analyze sentiment")
    ->withPromptIdentifier('sentiment', 'v1')
    ->toText();

// Version 2
$v2 = Ai::prompt("Analyze sentiment: be concise")
    ->withPromptIdentifier('sentiment', 'v2')
    ->toText();

// Compare results
$v1Trace = AiTrace::where('prompt_identifier', 'sentiment')
    ->where('prompt_version', 'v1')
    ->first();

$v2Trace = AiTrace::where('prompt_identifier', 'sentiment')
    ->where('prompt_version', 'v2')
    ->first();
```

### 4. Compliance & Auditing

Maintain a complete audit trail:

```php
// Get all traces for a user
$auditTrail = AiTrace::where('user_id', $userId)
    ->orderBy('created_at', 'desc')
    ->get();

// Export for compliance
$export = $auditTrail->map(function ($trace) {
    return [
        'timestamp' => $trace->created_at,
        'provider' => $trace->provider,
        'model' => $trace->model,
        'input' => $trace->sanitized_input,
        'output' => $trace->final_output,
        'cost' => $trace->estimated_cost,
    ];
});
```

## Backward Compatibility

✅ **100% Backward Compatible**

- All existing code works unchanged
- Tracing is opt-in (disabled by default)
- Zero performance impact when disabled
- No breaking changes to existing APIs

## Performance

- **Zero impact when disabled** - Tracing checks are minimal
- **Efficient when enabled** - Single database write per trace
- **No blocking operations** - Traces saved with error handling

## Security

- **Sensitive data redaction** - Configurable keys to redact
- **Optional output storage** - Can disable storing raw output
- **User attribution** - Links traces to users (optional)
- **Middleware protection** - API endpoints protected by dashboard middleware

## Migration

Run the migration to create the `ai_traces` table:

```bash
php artisan migrate
```

The migration creates all necessary fields including indexes for performance.

## Troubleshooting

### Traces Not Being Created

1. Check `AI_TRACING_ENABLED=true` in `.env`
2. Verify migration has run: `php artisan migrate:status`
3. Check logs for errors: `storage/logs/laravel.log`

### Replay Not Working

1. Ensure trace exists: `AiTrace::find($traceId)`
2. Check trace has `sanitized_input` populated
3. Verify provider/model are valid

### High Database Usage

1. Consider disabling `store_raw_output` for large outputs
2. Implement trace cleanup/archival for old traces
3. Use database indexes (already included in migration)

## Future Enhancements

Potential future additions:
- Dashboard UI for viewing traces
- Trace comparison tools
- Automated replay testing
- Trace export/import
- Advanced querying interface

