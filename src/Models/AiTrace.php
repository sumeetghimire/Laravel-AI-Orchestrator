<?php

namespace Sumeetghimire\AiOrchestrator\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiTrace extends Model
{
    protected $table = 'ai_traces';

    protected $primaryKey = 'trace_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'trace_id',
        'parent_trace_id',
        'user_id',
        'provider',
        'model',
        'prompt_identifier',
        'prompt_version',
        'sanitized_input',
        'tools_registered',
        'tools_called',
        'retry_count',
        'validation_errors',
        'schema_errors',
        'input_tokens',
        'output_tokens',
        'estimated_cost',
        'final_output',
        'metadata',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'sanitized_input' => 'array',
        'tools_registered' => 'array',
        'tools_called' => 'array',
        'retry_count' => 'integer',
        'validation_errors' => 'array',
        'schema_errors' => 'array',
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'estimated_cost' => 'decimal:4',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user that owns the trace.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', 'App\\Models\\User'), 'user_id');
    }

    /**
     * Get the parent trace (if this is a replay).
     */
    public function parentTrace(): BelongsTo
    {
        return $this->belongsTo(AiTrace::class, 'parent_trace_id', 'trace_id');
    }

    /**
     * Get all replay traces for this trace.
     */
    public function replayTraces(): HasMany
    {
        return $this->hasMany(AiTrace::class, 'parent_trace_id', 'trace_id');
    }
}

