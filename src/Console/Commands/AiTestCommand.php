<?php

namespace Sumeetghimire\AiOrchestrator\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Sumeetghimire\AiOrchestrator\Facades\Ai;

class AiTestCommand extends Command
{
    protected $signature = 'ai:test {--prompt=} {--driver=} {--fallback=}';

    protected $description = 'Run a diagnostic prompt against the configured AI provider (with optional fallback).';

    public function handle(): int
    {
        $this->newLine();
        $this->info('🔍 Laravel AI Orchestrator – Diagnostic Test');

        $defaultProvider = $this->option('driver') ?? config('ai.default');
        $fallbackProvider = $this->option('fallback') ?? config('ai.fallback');

        if (!$defaultProvider) {
            $this->error('No default provider configured. Please set AI_DRIVER in your environment.');
            return self::FAILURE;
        }

        $this->line("➡️  Using default provider: {$defaultProvider}");

        $prompt = $this->option('prompt') ?? $this->ask('Enter a test prompt');
        $prompt = trim((string) $prompt);

        if ($prompt === '') {
            $this->error('A prompt is required to run the diagnostic.');
            return self::FAILURE;
        }

        $defaultResult = $this->attemptPrompt($prompt, $defaultProvider);

        if ($defaultResult['success']) {
            $this->displaySuccess($defaultResult);
            return self::SUCCESS;
        }

        $this->newLine();
        $this->line("❌ Default provider '{$defaultProvider}' failed.");
        $this->error('Error: ' . $defaultResult['error']);

        if (!$fallbackProvider || $fallbackProvider === $defaultProvider) {
            $this->warn('No fallback provider configured.');
            $this->line('💥 Both default and fallback failed. Please check your configuration.');
            return self::FAILURE;
        }

        $this->line("\nAttempting fallback provider: {$fallbackProvider}");
        $fallbackResult = $this->attemptPrompt($prompt, $fallbackProvider);

        if ($fallbackResult['success']) {
            $this->displaySuccess($fallbackResult, true);
            return self::SUCCESS;
        }

        $this->newLine();
        $this->line('💥 Both default and fallback failed.');
        $this->error('Fallback error: ' . $fallbackResult['error']);

        return self::FAILURE;
    }

    /**
     * Attempt to execute a prompt against the specified provider.
     */
    protected function attemptPrompt(string $prompt, string $provider): array
    {
        $start = microtime(true);

        try {
            $response = Ai::prompt($prompt)
                ->using($provider)
                ->toText();

            $duration = microtime(true) - $start;

            return [
                'success' => true,
                'provider' => $provider,
                'duration' => $duration,
                'output' => trim((string) $response),
            ];
        } catch (\Throwable $throwable) {
            return [
                'success' => false,
                'provider' => $provider,
                'error' => $throwable->getMessage(),
            ];
        }
    }

    /**
     * Display a success message for a provider response.
     */
    protected function displaySuccess(array $result, bool $isFallback = false): void
    {
        $this->newLine();

        if ($isFallback) {
            $this->info('✅ Fallback provider succeeded!');
        } else {
            $this->info('✅ Default provider succeeded!');
        }

        $this->line('Provider: ' . $result['provider']);
        $this->line('Response time: ' . number_format($result['duration'], 2) . 's');

        $output = $result['output'] ?: '[Empty response]';
        $this->newLine();
        $this->line('🧠 Model output:');
        $this->line(Str::of($output)->limit(500));
        $this->newLine();
    }
}

