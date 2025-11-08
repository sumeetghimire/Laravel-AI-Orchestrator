<?php

return [
    'default' => env('AI_DRIVER', 'openai'),
    'fallback' => env('AI_FALLBACK_DRIVER'),
    'providers' => [
        'openai' => [
            'driver' => 'openai',
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-4o'),
        ],
        'anthropic' => [
            'driver' => 'anthropic',
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model' => env('ANTHROPIC_MODEL', 'claude-3-opus-20240229'),
        ],
        'gemini' => [
            'driver' => 'gemini',
            'api_key' => env('GEMINI_API_KEY'),
            'model' => env('GEMINI_MODEL', 'gemini-1.5-pro'),
        ],
        'ollama' => [
            'driver' => 'ollama',
            'base_url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
            'model' => env('OLLAMA_MODEL', 'llama3'),
        ],
        'huggingface' => [
            'driver' => 'huggingface',
            'api_key' => env('HUGGINGFACE_API_KEY'),
            'model' => env('HUGGINGFACE_MODEL', 'meta-llama/Llama-2-7b-chat-hf'),
        ],
        'replicate' => [
            'driver' => 'replicate',
            'api_key' => env('REPLICATE_API_KEY'),
            'model' => env('REPLICATE_MODEL', 'meta/llama-2-7b-chat'),
        ],
    ],
    'cache' => [
        'enabled' => env('AI_CACHE_ENABLED', true),
        'ttl' => env('AI_CACHE_TTL', 3600),
    ],
    'logging' => [
        'enabled' => env('AI_LOGGING_ENABLED', true),
        'driver' => env('AI_LOGGING_DRIVER', 'database'), // 'database' or 'file'
    ],
    'dashboard' => [
        'enabled' => env('AI_DASHBOARD_ENABLED', false), // Disabled by default for security
        'middleware' => env('AI_DASHBOARD_MIDDLEWARE', 'web'), // Middleware to protect dashboard
        'prefix' => env('AI_DASHBOARD_PREFIX', 'ai-orchestrator'), // URL prefix
    ],
    'memory' => [
        'enabled' => env('AI_MEMORY_ENABLED', true),
        'max_messages' => env('AI_MEMORY_MAX_MESSAGES', 50),
    ],
    'audio' => [
        'storage_disk' => env('AI_AUDIO_DISK', 'public'), // Storage disk (public, local, s3, etc.)
        'storage_path' => env('AI_AUDIO_PATH', 'audio'), // Base path within storage disk
        'public_path' => env('AI_AUDIO_PUBLIC_PATH', 'storage/audio'), // Public URL path
        'user_subfolder' => env('AI_AUDIO_USER_SUBFOLDER', true), // Store in user-specific folders
        'auto_cleanup' => env('AI_AUDIO_AUTO_CLEANUP', false), // Auto cleanup old files
        'cleanup_after_days' => env('AI_AUDIO_CLEANUP_DAYS', 30), // Cleanup files older than X days
    ],
];

