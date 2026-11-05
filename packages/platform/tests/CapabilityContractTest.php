<?php

namespace Nreljed\Platform\Tests;

use Nreljed\Platform\Concerns\InteractsWithFeatures;
use Nreljed\Platform\Contracts\Feature;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;
use SplFileInfo;

/**
 * Structural guard for the capability split.
 *
 * v0.3.0 splits the old 37-method FeatureProvider into a minimal Feature
 * interface plus opt-in capability interfaces. The composite must keep covering
 * every capability so pre-0.3 modules continue to satisfy the contract, and the
 * umbrella trait must keep providing a default for every optional method so
 * those modules do not have to change.
 *
 * No framework boot: the package test suite runs without an application.
 */
final class CapabilityContractTest extends TestCase
{
    public function test_the_minimal_feature_contract_is_identity_and_lifecycle_only(): void
    {
        $expected = [
            'dependencies',
            'description',
            'enabled',
            'getComposerPackage',
            'name',
            'onDisable',
            'onUserDelete',
            'slug',
            'version',
        ];

        $actual = $this->methodNames(new ReflectionClass(Feature::class));

        $this->assertSame(
            $expected,
            $actual,
            'Feature is the minimal contract: adding a method here forces every module to implement it.'
        );
    }

    public function test_the_umbrella_trait_covers_every_capability_method(): void
    {
        $traitMethods = $this->methodNames(new ReflectionClass(InteractsWithFeatures::class));

        // name() and slug() are deliberately mandatory on every feature.
        $required = ['name', 'slug'];

        $missing = [];

        foreach ($this->capabilityInterfaces() as $interface) {
            foreach ($interface->getMethods() as $method) {
                if (! in_array($method->getName(), $traitMethods, true)) {
                    $missing[] = $interface->getShortName().'::'.$method->getName().'()';
                }
            }
        }

        $this->assertSame(
            [],
            $missing,
            'InteractsWithFeatures is the zero-change migration path; it must default every optional method: '.implode(', ', $missing)
        );

        foreach ($required as $mandatory) {
            $this->assertNotContains(
                $mandatory,
                $traitMethods,
                "{$mandatory}() must stay mandatory — a feature without an identity must fail to load."
            );
        }
    }

    public function test_every_capability_interface_declares_at_least_one_method(): void
    {
        $empty = [];

        foreach ($this->capabilityInterfaces() as $interface) {
            if ($interface->getMethods() === []) {
                $empty[] = $interface->getShortName();
            }
        }

        $this->assertSame([], $empty, 'An empty marker interface would be a capability nobody can implement: '.implode(', ', $empty));
    }

    public function test_capability_interfaces_are_grouped_by_concern_and_do_not_overlap(): void
    {
        $owners = [];

        foreach ($this->capabilityInterfaces() as $interface) {
            foreach ($interface->getMethods() as $method) {
                $name = $method->getName();
                $owners[$name][] = $interface->getShortName();
            }
        }

        $overlaps = array_filter($owners, fn (array $interfaces) => count($interfaces) > 1);

        $this->assertSame(
            [],
            array_keys($overlaps),
            'A method declared by two capabilities makes "implement one concern" ambiguous: '.implode(', ', array_keys($overlaps))
        );
    }

    /**
     * Every Has* interface shipped in Contracts/Capabilities.
     *
     * @return list<ReflectionClass<object>>
     */
    private function capabilityInterfaces(): array
    {
        $directory = dirname(__DIR__).'/src/Contracts/Capabilities';

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

        $interfaces = [];

        foreach ($iterator as $file) {
            /** @var SplFileInfo $file */
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $class = 'Nreljed\\Platform\\Contracts\\Capabilities\\'.$file->getBasename('.php');

            $this->assertTrue(class_exists($class) || interface_exists($class), "{$class} could not be autoloaded.");

            $reflection = new ReflectionClass($class);

            $this->assertTrue($reflection->isInterface(), "{$class} must be an interface.");

            $interfaces[] = $reflection;
        }

        $this->assertNotEmpty($interfaces, 'No capability interfaces were discovered.');

        usort($interfaces, fn (ReflectionClass $a, ReflectionClass $b) => strcmp($a->getShortName(), $b->getShortName()));

        return $interfaces;
    }

    /**
     * @param  ReflectionClass<object>  $reflection
     * @return list<string>
     */
    private function methodNames(ReflectionClass $reflection): array
    {
        $names = array_map(
            fn (ReflectionMethod $method) => $method->getName(),
            $reflection->getMethods(),
        );

        sort($names);

        return $names;
    }
}
