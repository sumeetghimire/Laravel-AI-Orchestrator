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
        $fallbackProviders = $this->resolveFallbackProviders(
            $this->option('fallback') ?? config('ai.fallback'),
            $defaultProvider
        );

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

        foreach ($defaultResult['hints'] as $hint) {
            $this->warn('Hint: ' . $hint);
        }

        if (empty($fallbackProviders)) {
            $this->warn('No fallback providers configured.');
            $this->line('💥 All attempts failed. Please check your configuration.');
            return self::FAILURE;
        }

        $this->line("\nAttempting fallback providers in order: " . implode(', ', $fallbackProviders));

        foreach ($fallbackProviders as $fallbackProvider) {
            $fallbackResult = $this->attemptPrompt($prompt, $fallbackProvider);

            if ($fallbackResult['success']) {
                $this->displaySuccess($fallbackResult, true);
                return self::SUCCESS;
            }

            $this->newLine();
            $this->line("❌ Fallback provider '{$fallbackProvider}' failed.");
            $this->error('Error: ' . $fallbackResult['error']);

            foreach ($fallbackResult['hints'] as $hint) {
                $this->warn('Hint: ' . $hint);
            }
        }

        $this->newLine();
        $this->line('💥 All fallback providers failed.');

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
            $error = $this->normalizeError($throwable, $provider);

            return [
                'success' => false,
                'provider' => $provider,
                'error' => $error['message'],
                'hints' => $error['hints'],
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

    /**
     * Provide human-friendly hints for common API errors.
     */
    protected function normalizeError(\Throwable $throwable, string $provider): array
    {
        $message = $throwable->getMessage();
        $lower = Str::of($message)->lower();
        $hints = [];

        if ($lower->contains('unauthorized') || $lower->contains('401')) {
            $hints[] = "Verify the API key for '{$provider}' (environment variable and config).";
        }

        if ($lower->contains('not_found') || $lower->contains('model:') || $lower->contains('unknown model')) {
            $hints[] = "Double-check the configured model for '{$provider}'. Use a model your account can access.";
        }

        if ($lower->contains('rate limit') || $lower->contains('too many requests') || $lower->contains('429')) {
            $hints[] = 'You are hitting rate limits. Pause briefly or reduce request frequency.';
        }

        if ($lower->contains('timeout') || $lower->contains('timed out')) {
            $hints[] = 'The request timed out. Check network connectivity or try again.';
        }

        return [
            'message' => $message,
            'hints' => $hints,
        ];
    }

    /**
     * Normalise fallback providers.
     *
     * @param mixed $fallback
     * @return array<int, string>
     */
    protected function resolveFallbackProviders(mixed $fallback, ?string $default): array
    {
        if ($fallback === null) {
            return [];
        }

        if (is_string($fallback)) {
            $fallback = str_contains($fallback, ',')
                ? explode(',', $fallback)
                : [$fallback];
        }

        if (!is_array($fallback)) {
            return [];
        }

        $providers = [];
        foreach ($fallback as $provider) {
            if (!is_string($provider)) {
                continue;
            }

            $provider = trim($provider);
            if ($provider === '') {
                continue;
            }

            if ($default !== null && $provider === $default) {
                continue;
            }

            if (!in_array($provider, $providers, true)) {
                $providers[] = $provider;
            }
        }

        return $providers;
    }
}

