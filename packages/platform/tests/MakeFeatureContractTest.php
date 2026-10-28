<?php

namespace Nreljed\Platform\Tests;

use Nreljed\Platform\Console\Commands\MakeFeature;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Structural contract test for the `make:feature` generator.
 *
 * The generator has to register the scaffolded provider where the
 * FeatureRegistry actually looks for it: the `providers` array of
 * config/features.php. config/core.php carries engine gates and has no
 * features list, so writing there produced a provider nobody discovered.
 *
 * @coversNothing
 */
final class MakeFeatureContractTest extends TestCase
{
    private string $configPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configPath = (string) tempnam(sys_get_temp_dir(), 'features-').'.php';

        file_put_contents($this->configPath, <<<'PHP'
        <?php

        use Nreljed\Platform\Ai\AiServiceProvider;

        return [

            'providers' => [
                AiServiceProvider::class,
            ],

            'enabled' => [
                'ai' => true,
            ],

        ];
        PHP);
    }

    protected function tearDown(): void
    {
        if (is_file($this->configPath)) {
            unlink($this->configPath);
        }

        parent::tearDown();
    }

    public function test_provider_is_inserted_into_the_features_providers_array(): void
    {
        $this->assertTrue($this->insert('App\\FeatureProviders\\ForumServiceProvider'));

        $config = (string) file_get_contents($this->configPath);

        $this->assertStringContainsString('App\\FeatureProviders\\ForumServiceProvider::class,', $config);
        $this->assertStringContainsString('AiServiceProvider::class,', $config, 'existing providers must survive');
        $this->assertStringContainsString("'enabled' => [", $config, 'the enabled defaults must not be touched');
    }

    public function test_inserted_provider_sits_inside_the_providers_array(): void
    {
        $this->insert('App\\FeatureProviders\\ForumServiceProvider');

        $config = (string) file_get_contents($this->configPath);

        $providers = $this->arrayBlock($config, 'providers');

        $this->assertStringContainsString('App\\FeatureProviders\\ForumServiceProvider::class,', $providers);
        $this->assertStringNotContainsString('App\\FeatureProviders\\ForumServiceProvider::class,', $this->arrayBlock($config, 'enabled'));
    }

    public function test_registration_is_idempotent(): void
    {
        $this->insert('App\\FeatureProviders\\ForumServiceProvider');
        $this->insert('App\\FeatureProviders\\ForumServiceProvider');

        $config = (string) file_get_contents($this->configPath);

        $this->assertSame(
            1,
            substr_count($config, 'App\\FeatureProviders\\ForumServiceProvider::class,'),
            'a provider must never be registered twice',
        );
    }

    public function test_an_empty_providers_array_is_expanded(): void
    {
        file_put_contents($this->configPath, <<<'PHP'
        <?php

        return [

            'providers' => [],

        ];
        PHP);

        $this->assertTrue($this->insert('App\\FeatureProviders\\ForumServiceProvider'));

        $config = (string) file_get_contents($this->configPath);

        $this->assertStringContainsString('App\\FeatureProviders\\ForumServiceProvider::class,', $config);
        $this->assertStringNotContainsString("'providers' => [],", $config);
    }

    public function test_a_config_without_a_providers_array_is_reported_as_not_registered(): void
    {
        file_put_contents($this->configPath, <<<'PHP'
        <?php

        return [

            'enabled' => [
                'ai' => true,
            ],

        ];
        PHP);

        $this->assertFalse($this->insert('App\\FeatureProviders\\ForumServiceProvider'));
    }

    public function test_the_command_targets_the_features_config(): void
    {
        $source = (string) file_get_contents((new ReflectionMethod(MakeFeature::class, 'registerInConfig'))->getFileName());

        $this->assertStringContainsString("config_path('features.php')", $source);
        $this->assertStringNotContainsString("config_path('core.php')", $source);
    }

    private function insert(string $fqcn): bool
    {
        $command = new MakeFeature;
        $method = new ReflectionMethod($command, 'insertProvider');

        return (bool) $method->invoke($command, $this->configPath, $fqcn);
    }

    private function arrayBlock(string $config, string $key): string
    {
        $start = strpos($config, "'".$key."' => [");

        $this->assertNotFalse($start, "[{$key}] must exist in the config");

        $depth = 0;
        $length = strlen($config);

        for ($index = $start; $index < $length; $index++) {
            if ($config[$index] === '[') {
                $depth++;
            }

            if ($config[$index] === ']') {
                $depth--;

                if ($depth === 0) {
                    return substr($config, $start, $index - $start);
                }
            }
        }

        return '';
    }
}
