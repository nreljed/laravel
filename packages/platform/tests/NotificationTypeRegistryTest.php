<?php

namespace Nreljed\Platform\Tests;

use Nreljed\Platform\Services\NotificationTypeRegistry;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Structural test for the core-owned notification type tier. Package tests
 * run without a booted application, so it validates the getCoreTypes()
 * surface via reflection only — the preference-bypass behavior itself is
 * covered by the host application's integration tests.
 */
final class NotificationTypeRegistryTest extends TestCase
{
    public function test_core_types_cover_foundation_auth_security_and_account_events(): void
    {
        $expected = [
            'user.registered',
            'user.welcome',
            'password.changed',
            'password.reset',
            'email.changed',
            'account.deleted',
            'database.maintenance',
        ];

        $this->assertSame($expected, array_keys($this->coreTypes()));
    }

    public function test_security_and_account_events_are_required(): void
    {
        foreach (['password.changed', 'password.reset', 'email.changed', 'account.deleted', 'user.registered'] as $type) {
            $this->assertTrue(
                $this->coreTypes()[$type]['required'] ?? false,
                "$type must be required (not user-mutable)."
            );
        }
    }

    public function test_user_welcome_and_database_maintenance_remain_optional(): void
    {
        foreach (['user.welcome', 'database.maintenance'] as $type) {
            $this->assertFalse(
                $this->coreTypes()[$type]['required'] ?? false,
                "$type must stay user-overridable."
            );
        }
    }

    public function test_security_types_deliver_via_email_by_default(): void
    {
        foreach (['user.welcome', 'password.changed', 'password.reset', 'email.changed', 'account.deleted'] as $type) {
            $channels = $this->coreTypes()[$type]['default_channels'] ?? [];
            $this->assertContains('email', $channels, "$type must default to email delivery.");
        }
    }

    public function test_security_types_reference_registered_email_templates(): void
    {
        $expected = [
            'user.registered' => 'new-user',
            'user.welcome' => 'welcome',
            'password.changed' => 'password-changed',
            'password.reset' => 'password-reset',
            'email.changed' => 'email-changed',
            'account.deleted' => 'account-deleted',
        ];

        foreach ($expected as $type => $template) {
            $this->assertSame($template, $this->coreTypes()[$type]['email_template'] ?? null, "$type must wire to the $template template.");
        }
    }

    public function test_with_defaults_merges_broker_defaults_into_partial_definitions(): void
    {
        $merged = $this->invokeProtected('withDefaults', [
            ['label' => 'Order placed'],
            'Shop',
        ]);

        $this->assertSame([
            'group' => 'Shop',
            'default_channels' => ['in_app'],
            'default_enabled' => true,
            'required' => false,
            'severity' => 'info',
        ], array_diff_key($merged, ['label' => true]));
        $this->assertSame('Order placed', $merged['label']);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function coreTypes(): array
    {
        return $this->invokeProtected('getCoreTypes', []);
    }

    /**
     * @param  array<int, mixed>  $args
     * @return mixed
     */
    private function invokeProtected(string $method, array $args): mixed
    {
        $instance = (new ReflectionMethod(NotificationTypeRegistry::class, '__construct'))
            ->getDeclaringClass()
            ->newInstanceWithoutConstructor();

        $reflection = new ReflectionMethod(NotificationTypeRegistry::class, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($instance, $args);
    }
}