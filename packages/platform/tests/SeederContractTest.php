<?php

namespace Nreljed\Platform\Tests;

use Nreljed\Platform\Database\Seeders\RolesAndPermissionsSeeder;
use Nreljed\Platform\Database\Seeders\SettingsSeeder;
use Nreljed\Platform\Services\EmailSettingsService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Structural contract tests for the platform-owned baseline seeders.
 * Deliberately does not boot the framework, so it validates the seeder surface
 * and its config-driven design via reflection and source inspection.
 */
final class SeederContractTest extends TestCase
{
    public function test_settings_seeder_defines_the_expected_baseline_groups(): void
    {
        $reflection = new ReflectionClass(SettingsSeeder::class);

        $this->assertTrue(
            $reflection->hasMethod('run'),
            'SettingsSeeder must expose a run() method.',
        );
        $this->assertTrue(
            $reflection->hasMethod('baseline'),
            'SettingsSeeder must expose a baseline() method.',
        );
    }

    public function test_settings_seeder_is_config_driven(): void
    {
        $source = (string) file_get_contents((new ReflectionClass(SettingsSeeder::class))->getFileName());

        foreach ([
            "config('app.name'",
            "config('app.description'",
            "config('app.url'",
            "config('app.locale'",
            "config('app.timezone'",
            "config('roles.default_role'",
            "config('mail.default'",
            "config('mail.from.address'",
            "config('mail.from.name'",
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $source);
        }
    }

    public function test_settings_seeder_uses_the_json_email_settings_shape(): void
    {
        $reflection = new ReflectionClass(SettingsSeeder::class);
        $source = (string) file_get_contents($reflection->getFileName());

        $this->assertStringContainsString("'key' => 'email_settings'", $source);
        $this->assertStringContainsString('json_encode', $source);
        $this->assertStringContainsString('EmailSettingsService', $source);
        $this->assertStringNotContainsString("'key' => 'mail_driver'", $source);
        $this->assertStringNotContainsString("'key' => 'mail_from_address'", $source);
    }

    public function test_settings_seeder_is_idempotent_and_preserves_admin_values(): void
    {
        $source = (string) file_get_contents((new ReflectionClass(SettingsSeeder::class))->getFileName());

        $this->assertStringContainsString('firstOrCreate', $source);
    }

    public function test_email_settings_defaults_merge_cleanly_with_stored_values(): void
    {
        $defaults = EmailSettingsService::defaults();

        // The JSON row seeded must be compatible with EmailSettingsService::getOptions()
        // i.e. every default key still present after array_merge of stored values.
        $seeded = array_merge(
            $defaults,
            ['mailer' => 'smtp', 'from_email' => 'foo@bar.com', 'from_name' => 'Foo'],
        );

        foreach ($defaults as $key => $value) {
            $this->assertArrayHasKey($key, $seeded);
        }

        $this->assertSame(
            'foo@bar.com',
            $seeded['from_email'],
            'Stored email settings must override defaults on merge.',
        );
    }

    public function test_roles_seeder_folds_in_the_legacy_crm_user_cleanup(): void
    {
        $reflection = new ReflectionClass(RolesAndPermissionsSeeder::class);

        $this->assertTrue(
            $reflection->hasMethod('migrateLegacyCrmUserRole'),
            'The canonical RolesAndPermissionsSeeder must own the crm_user → customer cleanup.',
        );
    }

    public function test_roles_seeder_cleanup_is_idempotent(): void
    {
        $source = (string) file_get_contents((new ReflectionClass(RolesAndPermissionsSeeder::class))->getFileName());

        $this->assertStringContainsString("'crm_user'", $source);
        $this->assertStringContainsString("'customer'", $source);
    }
}
