<?php

namespace Sumeetghimire\AiOrchestrator\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
use Sumeetghimire\AiOrchestrator\Models\AiLog;
use Sumeetghimire\AiOrchestrator\Models\AiMemory;

class ModelResolver
{
    public static function log(): string
    {
        return self::resolve('log', AiLog::class);
    }

    public static function memory(): string
    {
        return self::resolve('memory', AiMemory::class);
    }

    protected static function resolve(string $key, string $default): string
    {
        $class = Config::get('ai.models.' . $key, $default);

        if (!is_string($class) || $class === '') {
            throw new InvalidArgumentException('AI model configuration [' . $key . '] must be a valid class name.');
        }

        if (!class_exists($class) || !is_subclass_of($class, Model::class)) {
            throw new InvalidArgumentException('Configured AI model [' . $class . '] for [' . $key . '] must extend [' . Model::class . '].');
        }

        return $class;
    }
}


