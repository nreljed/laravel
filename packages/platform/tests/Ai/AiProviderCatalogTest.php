<?php

namespace Nreljed\Platform\Tests\Ai;

use Nreljed\Platform\Ai\Models\AiProvider;
use PHPUnit\Framework\TestCase;

/**
 * Contract tests for the config-driven AI provider catalog (WS-7).
 *
 * Asserts that AiProvider::supportedProviders() agrees with the shipped
 * config/ai-providers.php catalog, so adding a provider to the config is
 * enough to surface it in the Account -> AI Keys UI.
 */
final class AiProviderCatalogTest extends TestCase
{
    public function test_supported_providers_match_the_shipped_config(): void
    {
        $file = dirname(__DIR__, 2).'/config/ai-providers.php';
        $this->assertFileExists($file);

        $loaded = require $file;
        $this->assertIsArray($loaded);
        $this->assertArrayHasKey('catalog', $loaded);

        $this->assertSame(
            $loaded['catalog'],
            AiProvider::supportedProviders(),
            'AiProvider::supportedProviders() must agree with config/ai-providers.php.'
        );
    }

    public function test_catalog_is_non_empty_and_every_entry_has_required_keys(): void
    {
        $catalog = AiProvider::supportedProviders();

        $this->assertNotEmpty($catalog);

        foreach ($catalog as $slug => $config) {
            foreach (['name', 'models', 'default_model', 'testable', 'capabilities'] as $key) {
                $this->assertArrayHasKey($key, $config, "Provider {$slug} must declare '{$key}'.");
            }
        }
    }
}
