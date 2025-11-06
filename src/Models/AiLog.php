<?php

namespace Sumeetghimire\AiOrchestrator\Models;

use Illuminate\Database\Eloquent\Model;

class AiLog extends Model
{
    protected $table = 'ai_logs';

    protected $fillable = [
        'user_id',
        'provider',
        'model',
        'prompt',
        'response',
        'tokens',
        'cost',
        'cached',
        'duration',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'tokens' => 'integer',
        'cost' => 'decimal:4',
        'cached' => 'boolean',
        'duration' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user that owns the log.
     */
    public function user()
    {
        return $this->belongsTo(config('auth.providers.users.model', 'App\\Models\\User'), 'user_id');
    }
}

