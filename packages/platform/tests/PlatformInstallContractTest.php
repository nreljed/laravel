<?php

namespace Nreljed\Platform\Tests;

use Nreljed\Platform\Console\Commands\PlatformInstall;
use Nreljed\Platform\PlatformServiceProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Structural contract test for the platform:install bootstrap command.
 * Deliberately does not boot the framework (package tests run without an
 * application), so it validates the command surface and safety guarantees via
 * reflection instead.
 */
final class PlatformInstallContractTest extends TestCase
{
    public function test_command_is_registered_by_the_platform_service_provider(): void
    {
        $source = (string) file_get_contents((new ReflectionClass(PlatformServiceProvider::class))->getFileName());

        $this->assertStringContainsString(
            'Console\\Commands\\PlatformInstall::class',
            $source,
            'platform:install must be registered in PlatformServiceProvider::loadConsoleRoutes().',
        );
    }

    public function test_command_declares_migrate_and_seed_options(): void
    {
        $reflection = new ReflectionClass(PlatformInstall::class);
        $defaults = $reflection->getDefaultProperties();
        $signature = (string) ($defaults['signature'] ?? '');

        $this->assertStringContainsString('platform:install', $signature);
        $this->assertStringContainsString('--migrate', $signature);
        $this->assertStringContainsString('--seed', $signature);
    }

    public function test_seed_path_targets_the_platform_seeder_and_admin_command(): void
    {
        $source = (string) file_get_contents((new ReflectionClass(PlatformInstall::class))->getFileName());

        $this->assertStringContainsString(
            'Nreljed\\\\Platform\\\\Database\\\\Seeders\\\\RolesAndPermissionsSeeder',
            $source,
        );
        $this->assertStringContainsString('app:create-admin', $source);
    }

    public function test_failure_is_surfacable_without_destructive_actions(): void
    {
        $source = (string) file_get_contents((new ReflectionClass(PlatformInstall::class))->getFileName());

        foreach (['Command::FAILURE', 'twoColumnDetail', 'wiringChecks'] as $fragment) {
            $this->assertStringContainsString($fragment, $source);
        }

        // The command must audit state; it must never truncate or drop.
        $this->assertStringNotContainsString('migrate:fresh', $source);
        $this->assertStringNotContainsString('db:wipe', $source);
        $this->assertStringNotContainsString('schema:dump', $source);
    }
}
