<?php

namespace Nreljed\Blog\Tests;

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
 *
 * Mirrors the platform kernel's FeatureProviderContractTest.
 */
final class FeatureProviderContractTest extends TestCase
{
    public function test_interface_declares_the_full_capability_surface(): void
    {
        $expected = [
            'dataBackupSummary',
            'dependencies',
            'description',
            'enabled',
            'exportDataBackup',
            'getAdminResources',
            'getAnalyticsSeries',
            'getComposerPackage',
            'getContentDescription',
            'getDashboardData',
            'getDataBackupCapabilities',
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
            'importDataBackup',
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

        foreach ($optional as $method) {
            $this->assertContains(
                $method,
                $traitMethods,
                "InteractsWithFeatures must provide a default for interface method $method"
            );
        }
    }

    public function test_provider_implements_the_full_contract(): void
    {
        $provider = new ReflectionClass(\Nreljed\Blog\BlogServiceProvider::class);

        $this->assertTrue($provider->implementsInterface(FeatureProvider::class));

        foreach ((new ReflectionClass(FeatureProvider::class))->getMethods() as $method) {
            $this->assertTrue(
                $provider->hasMethod($method->getName()),
                'BlogServiceProvider must implement '.$method->getName().'()'
            );
        }
    }
}