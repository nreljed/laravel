<?php

namespace Nreljed\Platform\Ai\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Lightweight HTTP client for AI provider API calls.
 *
 * Provides a direct, framework-native (Laravel Http facade) path to call the
 * major providers for connection testing and simple one-shot prompts, without
 * depending on the full Laravel AI SDK driver stack. Response parsing is
 * normalized per provider so callers receive a consistent shape.
 */
class AiHttpClient
{
    /** API endpoint URLs for each provider. */
    private const array ENDPOINTS = [
        'openai' => 'https://api.openai.com/v1/chat/completions',
        'anthropic' => 'https://api.anthropic.com/v1/messages',
        'gemini' => 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent',
        'mistral' => 'https://api.mistral.ai/v1/chat/completions',
        'deepseek' => 'https://api.deepseek.com/v1/chat/completions',
        'xai' => 'https://api.x.ai/v1/chat/completions',
        'ollama' => 'http://localhost:11434/api/chat',
    ];

    /** Default models per provider (used when no preferred model is set). */
    private const array DEFAULT_MODELS = [
        'openai' => 'gpt-5.6-luna',
        'anthropic' => 'claude-sonnet-5',
        'gemini' => 'gemini-3.5-flash-lite',
        'mistral' => 'mistral-small-latest',
        'deepseek' => 'deepseek-chat',
        'xai' => 'grok-4.3',
        'ollama' => 'llama3.2',
    ];

    /**
     * Canonical default model for a provider. Kept in one place so direct HTTP
     * calls (the "Test" button) and SDK-backed agent generation agree.
     */
    public static function getDefaultModel(string $provider): ?string
    {
        return self::DEFAULT_MODELS[$provider] ?? null;
    }

    /**
     * Test a provider connection with a minimal prompt.
     *
     * @return array{success: bool, message: string, response?: string, usage?: array{input_tokens: int, output_tokens: int}}
     */
    public function test(string $provider, string $apiKey, ?string $model = null): array
    {
        try {
            $response = $this->request(
                provider: $provider,
                apiKey: $apiKey,
                model: $model,
                prompt: 'You are a test assistant. Respond with exactly one word: ok',
                maxTokens: 10,
            );

            if ($response['success']) {
                return [
                    'success' => true,
                    'message' => 'Connection verified successfully.',
                    'response' => substr((string) ($response['text'] ?? ''), 0, 100),
                    'usage' => $response['usage'] ?? null,
                ];
            }

            return [
                'success' => false,
                'message' => $response['error'] ?? 'Connection failed. Please check your API key.',
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => 'Connection failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Send a one-shot prompt and return a normalized result.
     *
     * @return array{success: bool, text?: string, error?: string, usage?: array{input_tokens: int, output_tokens: int}, model?: string}
     */
    public function send(
        string $provider,
        string $apiKey,
        string $prompt,
        ?string $model = null,
        ?string $systemPrompt = null,
        int $maxTokens = 2048,
    ): array {
        return $this->request(
            provider: $provider,
            apiKey: $apiKey,
            model: $model,
            prompt: $prompt,
            systemPrompt: $systemPrompt,
            maxTokens: $maxTokens,
        );
    }

    /**
     * Build and dispatch the provider request.
     *
     * @return array{success: bool, text?: string, error?: string, usage?: array{input_tokens: int, output_tokens: int}, model?: string}
     */
    private function request(
        string $provider,
        string $apiKey,
        ?string $model,
        string $prompt,
        ?string $systemPrompt = null,
        int $maxTokens = 2048,
    ): array {
        if (! isset(self::ENDPOINTS[$provider])) {
            return ['success' => false, 'error' => "Unsupported provider: {$provider}"];
        }

        $model ??= self::DEFAULT_MODELS[$provider] ?? null;
        [$headers, $body] = $this->buildPayload($provider, $apiKey, $model, $prompt, $systemPrompt, $maxTokens);
        $url = $this->buildUrl($provider, $model);

        try {
            $response = Http::timeout(60)
                ->withHeaders($headers)
                ->post($url, $body);
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }

        if ($response->failed()) {
            return [
                'success' => false,
                'error' => $this->extractError($response->json(), $response->status()),
            ];
        }

        $parsed = $this->parseResponse($provider, $response->json());

        return $parsed;
    }

    /** @return array{0: array<string, string>, 1: array<string, mixed>} */
    private function buildPayload(
        string $provider,
        string $apiKey,
        ?string $model,
        string $prompt,
        ?string $systemPrompt,
        int $maxTokens,
    ): array {
        $headers = ['Content-Type' => 'application/json'];
        $body = [];

        switch ($provider) {
            case 'openai':
            case 'mistral':
            case 'deepseek':
            case 'xai':
                $headers['Authorization'] = 'Bearer '.$apiKey;
                $messages = [];
                if ($systemPrompt) {
                    $messages[] = ['role' => 'system', 'content' => $systemPrompt];
                }
                $messages[] = ['role' => 'user', 'content' => $prompt];
                $body = ['model' => $model, 'messages' => $messages, 'max_tokens' => $maxTokens];
                break;

            case 'anthropic':
                $headers['x-api-key'] = $apiKey;
                $headers['anthropic-version'] = '2023-06-01';
                $body = [
                    'model' => $model,
                    'max_tokens' => $maxTokens,
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                ];
                if ($systemPrompt) {
                    $body['system'] = $systemPrompt;
                }
                break;

            case 'gemini':
                $headers['x-goog-api-key'] = $apiKey;
                $body = [
                    'contents' => [['parts' => [['text' => $prompt]]]],
                    'generationConfig' => ['maxOutputTokens' => $maxTokens],
                ];
                break;

            case 'ollama':
                $messages = [];
                if ($systemPrompt) {
                    $messages[] = ['role' => 'system', 'content' => $systemPrompt];
                }
                $messages[] = ['role' => 'user', 'content' => $prompt];
                $body = ['model' => $model, 'messages' => $messages, 'stream' => false];
                break;
        }

        return [$headers, $body];
    }

    private function buildUrl(string $provider, ?string $model): string
    {
        $url = self::ENDPOINTS[$provider];

        if ($provider === 'gemini') {
            $url = sprintf($url, $model ?? 'gemini-2.0-flash');
        }

        return $url;
    }

    /**
     * @param  array<string, mixed>|null  $data
     * @return array{success: bool, text?: string, error?: string, usage?: array{input_tokens: int, output_tokens: int}, model?: string}
     */
    private function parseResponse(string $provider, ?array $data): array
    {
        if (! is_array($data)) {
            return ['success' => false, 'error' => 'Empty response from provider'];
        }

        switch ($provider) {
            case 'anthropic':
                return [
                    'success' => true,
                    'text' => (string) ($data['content'][0]['text'] ?? ''),
                    'usage' => [
                        'input_tokens' => (int) ($data['usage']['input_tokens'] ?? 0),
                        'output_tokens' => (int) ($data['usage']['output_tokens'] ?? 0),
                    ],
                    'model' => $data['model'] ?? null,
                ];

            case 'gemini':
                return [
                    'success' => true,
                    'text' => (string) ($data['candidates'][0]['content']['parts'][0]['text'] ?? ''),
                    'usage' => [
                        'input_tokens' => (int) ($data['usageMetadata']['promptTokenCount'] ?? 0),
                        'output_tokens' => (int) ($data['usageMetadata']['candidatesTokenCount'] ?? 0),
                    ],
                    'model' => $data['modelVersion'] ?? null,
                ];

            case 'ollama':
                return [
                    'success' => true,
                    'text' => (string) ($data['message']['content'] ?? ''),
                    'usage' => [
                        'input_tokens' => (int) ($data['prompt_eval_count'] ?? 0),
                        'output_tokens' => (int) ($data['eval_count'] ?? 0),
                    ],
                    'model' => $data['model'] ?? null,
                ];

            default:
                return [
                    'success' => true,
                    'text' => (string) ($data['choices'][0]['message']['content'] ?? ''),
                    'usage' => [
                        'input_tokens' => (int) ($data['usage']['prompt_tokens'] ?? 0),
                        'output_tokens' => (int) ($data['usage']['completion_tokens'] ?? 0),
                    ],
                    'model' => $data['model'] ?? null,
                ];
        }
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    private function extractError(?array $data, int $statusCode): string
    {
        if (isset($data['error']['message'])) {
            return (string) $data['error']['message'];
        }

        if (isset($data['error']['type'])) {
            return 'Provider error: '.(string) $data['error']['type'];
        }

        return (string) ($data['message'] ?? "Provider returned HTTP {$statusCode}");
    }
}
