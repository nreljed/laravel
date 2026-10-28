<?php

namespace Nreljed\Platform\Tests;

use Nreljed\Platform\Console\Commands\MakeResource;
use Nreljed\Platform\PlatformServiceProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Structural contract test for the `make:resource` generator.
 *
 * The platform package tests deliberately do not boot the framework (there is
 * no `base_path()` or artisan kernel here), so the generator is validated the
 * same way `platform:install` is: the command surface, the registry wiring and
 * the produced descriptor stub are pinned via reflection and source reads.
 */
final class MakeResourceContractTest extends TestCase
{
    public function test_command_is_registered_by_the_platform_service_provider(): void
    {
        $source = (string) file_get_contents((new ReflectionClass(PlatformServiceProvider::class))->getFileName());

        $this->assertStringContainsString(
            'Console\\Commands\\MakeResource::class',
            $source,
            'make:resource must be registered in PlatformServiceProvider::loadConsoleRoutes().',
        );
    }

    public function test_command_declares_the_scaffolding_options(): void
    {
        $reflection = new ReflectionClass(MakeResource::class);
        $defaults = $reflection->getDefaultProperties();
        $signature = (string) ($defaults['signature'] ?? '');

        $this->assertStringContainsString('make:resource', $signature);
        $this->assertStringContainsString('{name : The name of the resource', $signature);
        $this->assertStringContainsString('--slug', $signature);
        $this->assertStringContainsString('--model', $signature);
        $this->assertStringContainsString('--path=app/Resources', $signature);
        $this->assertStringContainsString('--namespace=App\\Resources', $signature);
    }

    public function test_default_slug_is_a_kebab_cased_plural(): void
    {
        $command = new MakeResource;
        $method = new ReflectionMethod($command, 'defaultSlug');

        $this->assertSame('products', $method->invoke($command, 'Product'));
        $this->assertSame('categories', $method->invoke($command, 'Category'));
        $this->assertSame('products', $method->invoke($command, 'Products'), 'An already-plural name must not be pluralised again.');
        $this->assertSame('tax-rates', $method->invoke($command, 'Tax Rate'));
    }

    public function test_stub_declares_a_typed_resource_definition(): void
    {
        $stub = $this->stub();

        $this->assertStringContainsString(
            'use Nreljed\\Platform\\Support\\ResourceDefinition;',
            $stub,
            'The descriptor must be built from the typed value object, not a freeform array.',
        );
        $this->assertStringContainsString('new ResourceDefinition(', $stub);
        $this->assertStringContainsString('key: \'products\'', $stub);
        $this->assertStringContainsString('label: \'Product\'', $stub);
        $this->assertStringContainsString('singular: \'Product\'', $stub);
        $this->assertStringContainsString("'searchKey' => 'name'", $stub);
    }

    public function test_stub_declares_route_names_not_closures(): void
    {
        $stub = $this->stub();

        foreach ([
            "'index' => 'dashboard.products.index'",
            "'create' => 'dashboard.products.create'",
            "'edit' => 'dashboard.products.edit'",
            "'destroy' => 'dashboard.products.destroy'",
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $stub, "Missing {$fragment} from the generated routes.");
        }

        $this->assertStringNotContainsString('fn (array', $stub, 'Descriptors are memoised config; a closure would break serialisation.');
    }

    public function test_stub_echoes_the_model_option_into_the_docblock(): void
    {
        $stub = $this->stubWithModel('Nreljed\\ShopModule\\Models\\Product');

        $this->assertStringContainsString('Persists {@see \Nreljed\ShopModule\Models\Product}', $stub);

        $this->assertStringNotContainsString(
            'Persists',
            $this->stubWithModel(null),
            'Without a --model option the docblock must not guess a model.',
        );
    }

    private function stub(): string
    {
        $command = new MakeResource;
        $method = new ReflectionMethod($command, 'buildStub');

        return (string) $method->invoke($command, 'App\\Resources', 'ProductResource', 'Product', 'products', null);
    }

    private function stubWithModel(?string $model): string
    {
        $command = new MakeResource;
        $method = new ReflectionMethod($command, 'buildStub');

        return (string) $method->invoke($command, 'App\\Resources', 'ProductResource', 'Product', 'products', $model);
    }
}
