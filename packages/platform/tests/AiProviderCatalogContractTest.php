<?php

namespace Nreljed\Platform\Tests;

use Nreljed\Platform\Ai\Controllers\AiProviderController;
use Nreljed\Platform\Ai\Models\AiProvider;
use Nreljed\Platform\Ai\Services\AiHttpClient;
use Nreljed\Platform\Http\Controllers\UserAccountController;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Contract tests for the AI provider catalog metadata that drives the
 * Account → Integrations UI (status pills, Test connection, capability
 * badges) and for the stored-key test endpoint that persists results.
 *
 * Structural checks in the style of {@see AiSpendCapContractTest} — no
 * framework boot required.
 */
final class AiProviderCatalogContractTest extends TestCase
{
    /** Providers whose stored key must be connection-testable. */
    private const array TESTABLE = ['openai', 'anthropic', 'gemini', 'mistral', 'deepseek', 'xai'];

    public function test_every_provider_declares_the_ui_metadata(): void
    {
        foreach (AiProvider::supportedProviders() as $slug => $config) {
            foreach (['name', 'models', 'default_model', 'testable', 'capabilities'] as $key) {
                $this->assertArrayHasKey(
                    $key,
                    $config,
                    "Provider {$slug} must declare '{$key}' for the Integrations UI."
                );
            }

            $this->assertIsString($config['name']);
            $this->assertIsArray($config['models']);
            $this->assertIsBool($config['testable']);
            $this->assertNotEmpty(
                $config['capabilities'],
                "Provider {$slug} must declare at least one capability."
            );
        }
    }

    public function test_only_local_provider_is_untestable(): void
    {
        $untestable = array_keys(array_filter(
            AiProvider::supportedProviders(),
            fn (array $config) => $config['testable'] === false
        ));

        $this->assertSame(
            ['ollama'],
            $untestable,
            'Only the local Ollama provider may opt out of connection testing.'
        );
    }

    public function test_default_models_match_the_http_client(): void
    {
        foreach (self::TESTABLE as $slug) {
            $default = AiProvider::supportedProviders()[$slug]['default_model'];

            $this->assertSame(
                $default,
                AiHttpClient::getDefaultModel($slug),
                "Catalog default_model for {$slug} must stay in lock-step with AiHttpClient."
            );
        }

        $this->assertSame(
            'llama3.2',
            AiHttpClient::getDefaultModel('ollama'),
            'The Ollama default model is pinned for display even though it is untestable.'
        );
    }

    public function test_testable_providers_advertise_chat_streaming_and_structured_output(): void
    {
        foreach (self::TESTABLE as $slug) {
            $capabilities = AiProvider::supportedProviders()[$slug]['capabilities'];

            foreach (['chat', 'streaming', 'structured_output'] as $capability) {
                $this->assertContains(
                    $capability,
                    $capabilities,
                    "Provider {$slug} must advertise '{$capability}'."
                );
            }
        }
    }

    public function test_stored_key_test_endpoint_is_wired(): void
    {
        $routes = (string) file_get_contents(
            dirname(__DIR__).'/src/Ai/Routes/web.php'
        );

        $this->assertStringContainsString(
            "Route::post('/ai-keys/{aiProvider}/test', [AiProviderController::class, 'testStored'])->name('test-stored')",
            $routes
        );
    }

    public function test_stored_test_authorizes_then_records_the_outcome(): void
    {
        $source = $this->sourceOf(AiProviderController::class);
        $body = $this->methodBodyOf(AiProviderController::class, 'testStored');

        $this->assertStringContainsString("\$this->authorize('update', \$aiProvider)", $body);
        $this->assertStringContainsString('last_tested_at', $body);
        $this->assertStringContainsString('last_test_ok', $body);
        $this->assertStringContainsString('getDecryptedKey()', $body);

        // Both test paths must share one runner so the SDK driver stack is
        // exercised identically before and after a key is saved.
        $this->assertStringContainsString('private function runConnectionTest(', $source);
        $this->assertSame(
            2,
            substr_count($source, '$this->runConnectionTest('),
            'test() and testStored() must both delegate to runConnectionTest().'
        );
    }

    public function test_non_testable_providers_are_rejected_with_422(): void
    {
        $body = $this->methodBodyOf(AiProviderController::class, 'testStored');

        $this->assertStringContainsString("['testable'] ?? false) === false", $body);
        $this->assertStringContainsString('], 422)', $body);
    }

    public function test_provider_payloads_ship_the_test_status_fields(): void
    {
        $controller = $this->sourceOf(AiProviderController::class);
        $account = $this->sourceOf(UserAccountController::class);

        foreach ([$controller, $account] as $source) {
            $this->assertStringContainsString("'last_tested_at'", $source);
            $this->assertStringContainsString("'last_test_ok'", $source);
        }
    }

    public function test_model_persists_test_status_columns(): void
    {
        $file = (new ReflectionClass(AiProvider::class))->getFileName();
        $source = (string) file_get_contents((string) $file);

        $this->assertStringContainsString("'last_tested_at',", $source);
        $this->assertStringContainsString("'last_test_ok',", $source);
        $this->assertStringContainsString("'last_tested_at' => 'datetime'", $source);
        $this->assertStringContainsString("'last_test_ok' => 'boolean'", $source);
    }

    private function sourceOf(string $class): string
    {
        $file = (new ReflectionClass($class))->getFileName();

        $this->assertIsString($file);

        return (string) file_get_contents($file);
    }

    private function methodBodyOf(string $class, string $method): string
    {
        $source = $this->sourceOf($class);
        $start = strpos($source, "function {$method}(");

        $this->assertNotFalse($start, "Expected {$class}::{$method}() to exist.");

        $body = substr($source, $start);
        $end = strpos($body, "\n    }");

        return $end === false ? $body : substr($body, 0, $end);
    }
}
