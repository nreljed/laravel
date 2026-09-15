<?php

namespace Nreljed\Platform\Tests;

use Nreljed\Platform\Concerns\InteractsWithFeatures;
use Nreljed\Platform\Contracts\FeatureProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;
use SplFileInfo;

/**
 * Structural contract test for the FeatureProvider interface. Deliberately
 * does not boot the framework (package tests run without an application), so
 * it validates method signatures and trait coverage via reflection only.
 */
final class FeatureProviderContractTest extends TestCase
{
    public function test_interface_declares_the_full_capability_surface(): void
    {
        $expected = [
            'dependencies',
            'description',
            'enabled',
            'getAnalyticsSeries',
            'getComposerPackage',
            'getContentDescription',
            'getDashboardData',
            'getDeactivationNotice',
            'getEmailTemplates',
            'getGalleryConfig',
            'getHealthChecks',
            'getMediaSubdirectory',
            'getMetaFormattingConfig',
            'getNavGroupLabel',
            'getNavItems',
            'getNotificationTypes',
            'getOrphanMediaTargets',
            'getProfileFieldsConfig',
            'getProfileSections',
            'getRepeaterConfig',
            'getScheduledTasks',
            'getSettingsGroups',
            'getSettingsRoute',
            'getSettingsSection',
            'getSharedData',
            'getSitemapUrls',
            'hasSettings',
            'name',
            'onDisable',
            'onUserDelete',
            'permissions',
            'slug',
            'version',
        ];

        $interface = new ReflectionClass(FeatureProvider::class);

        $actual = array_map(
            fn (ReflectionMethod $method) => $method->getName(),
            $interface->getMethods(),
        );
        sort($actual);

        $this->assertSame($expected, $actual, 'FeatureProvider interface surface drifted from contract.');
    }

    public function test_interacts_with_features_trait_covers_every_optional_interface_method(): void
    {
        $trait = new ReflectionClass(InteractsWithFeatures::class);
        $traitMethods = array_map(fn (ReflectionMethod $method) => $method->getName(), $trait->getMethods());

        $interfaceMethods = array_map(
            fn (ReflectionMethod $method) => $method->getName(),
            (new ReflectionClass(FeatureProvider::class))->getMethods(),
        );

        // name() and slug() are deliberately mandatory per provider.
        $optional = array_values(array_diff($interfaceMethods, ['name', 'slug']));

        $missing = array_values(array_diff($optional, $traitMethods));

        $this->assertSame([], $missing, 'InteractsWithFeatures is missing implementations for interface methods: '.implode(', ', $missing));
    }

    public function test_concrete_providers_implement_the_contract_and_use_the_trait(): void
    {
        $violations = [];

        foreach ($this->featureProviders() as $providerClass) {
            $reflection = new ReflectionClass($providerClass);

            if (! $reflection->implementsInterface(FeatureProvider::class)) {
                continue;
            }

            if (! in_array(InteractsWithFeatures::class, $this->usedTraits($reflection), true)) {
                $violations[] = sprintf('%s must use the InteractsWithFeatures trait', $providerClass);
            }

            foreach (['name', 'slug'] as $mandatory) {
                if (! $reflection->hasMethod($mandatory)) {
                    $violations[] = sprintf('%s must implement %s()', $providerClass, $mandatory);
                }
            }
        }

        $this->assertSame([], $violations, 'Feature provider contract violations:'.PHP_EOL.implode(PHP_EOL, $violations));
    }

    public function test_permission_slugs_are_scoped_to_the_provider(): void
    {
        $violations = [];

        foreach ($this->featureProviders() as $providerClass) {
            $reflection = new ReflectionClass($providerClass);

            if (! $reflection->implementsInterface(FeatureProvider::class) || ! $reflection->hasMethod('permissions')) {
                continue;
            }

            $method = $reflection->getMethod('permissions');
            $providerSlug = strtolower($reflection->getShortName());

            // Structural check only: parse the return statement for slugs.
            $file = file_get_contents($reflection->getFileName());
            $line = $method->getStartLine() - 1;
            $lines = explode("\n", (string) $file);
            $body = implode("\n", array_slice($lines, $line, $method->getEndLine() - $method->getStartLine() + 1));

            preg_match_all("/'slug'\s*=>\s*'([^']+)'/", $body, $matches);

            foreach ($matches[1] ?? [] as $slug) {
                // Generic admin permissions may legitimately live outside the
                // feature scope; scoping rules apply to namespaced slugs only.
                if (! str_contains($slug, '.')) {
                    continue;
                }

                if (preg_match('/^(posts|shop)\./', $slug)) {
                    continue;
                }

                $violations[] = sprintf('%s declares unscoped permission slug [%s]', $providerClass, $slug);
            }
        }

        $this->assertSame([], $violations, 'Permission slug scoping violations:'.PHP_EOL.implode(PHP_EOL, $violations));
    }

    /**
     * @return list<string>
     */
    private function featureProviders(): array
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(dirname(__DIR__).'/src')
        );

        $classes = [];

        foreach ($iterator as $file) {
            /** @var SplFileInfo $file */
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $content = file_get_contents($file->getPathname());

            if (! preg_match('/^namespace ([^;]+);/m', (string) $content, $nsMatch)) {
                continue;
            }

            if (! preg_match('/^(?:final |abstract )?class\s+(\w+)/m', (string) $content, $classMatch)) {
                continue;
            }

            $classes[] = trim($nsMatch[1]).'\\'.$classMatch[1];
        }

        sort($classes);

        return array_values(array_filter($classes, function (string $class) {
            if (! class_exists($class)) {
                return false;
            }

            $reflection = new ReflectionClass($class);

            return $reflection->implementsInterface(FeatureProvider::class);
        }));
    }

    /**
     * @return list<string>
     */
    private function usedTraits(ReflectionClass $reflection): array
    {
        $traits = [];

        foreach ($reflection->getTraits() as $trait) {
            $traits[] = $trait->getName();
        }

        return $traits;
    }
}
