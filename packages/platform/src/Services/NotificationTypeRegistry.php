<?php

namespace Nreljed\Platform\Services;

/**
 * Aggregates every notification type in the system from two ownership tiers:
 *
 *  1. core types — owned by this package (auth/security/account/system),
 *     defined in getCoreTypes(); modules can never override them.
 *  2. feature types — contributed by each feature provider via
 *     getNotificationTypes(); merged with safe defaults so a module only
 *     declares what differs from the norm.
 *
 * A type may declare: label, description, group, severity, default_channels,
 * default_enabled, required (bypasses user preferences and delivers only via
 * default_channels), and optional email_template.
 */
class NotificationTypeRegistry
{
    protected ?array $types = null;

    public function __construct(protected FeatureRegistry $registry) {}

    public function all(): array
    {
        if ($this->types === null) {
            $types = $this->getCoreTypes();

            foreach ($this->registry->allProviders() as $provider) {
                foreach ($provider->getNotificationTypes() as $key => $type) {
                    // Core types take precedence: feature providers add their
                    // own types but never touch core-owned ones.
                    if (isset($types[$key])) {
                        continue;
                    }

                    if (! is_array($type)) {
                        continue;
                    }

                    $types[$key] = $this->withDefaults(
                        $type,
                        $provider->name() ?: $provider->slug(),
                    );
                }
            }

            $this->types = $types;
        }

        return $this->types;
    }

    public function get(string $key): ?array
    {
        return $this->all()[$key] ?? null;
    }

    public function getByGroup(string $group): array
    {
        return array_filter($this->all(), fn ($type) => ($type['group'] ?? '') === $group);
    }

    public function groups(): array
    {
        $groups = [];
        foreach ($this->all() as $key => $type) {
            $group = $type['group'] ?? 'Other';
            if (! isset($groups[$group])) {
                $groups[$group] = [];
            }
            $groups[$group][$key] = $type;
        }

        return $groups;
    }

    /**
     * Merge SDK defaults into a feature-provided type definition so a module
     * only has to declare the parameters it actually cares about:
     *
     *   ['label' => ..., 'description' => ..., 'email_template' => ...]
     *
     * becomes a fully-resolved configuration with a name-spaced group, in-app
     * delivery, enabled by default and user-overridable.
     *
     * @param  array<string, mixed>  $type
     * @return array<string, mixed>
     */
    protected function withDefaults(array $type, string $group): array
    {
        return array_merge([
            'group' => $group,
            'default_channels' => ['in_app'],
            'default_enabled' => true,
            'required' => false,
            'severity' => 'info',
        ], $type);
    }

    /**
     * Core-owned notification types. Foundation, auth and account-security
     * notifications belong to this package; feature providers add their own
     * types via getNotificationTypes() and can never alter these.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function getCoreTypes(): array
    {
        return [
            'user.registered' => [
                'label' => 'New User Registration',
                'description' => 'When a new user registers on the site.',
                'group' => 'Account',
                'severity' => 'info',
                'default_channels' => ['in_app', 'email'],
                'default_enabled' => true,
                'required' => true,
                'email_template' => 'new-user',
            ],
            'user.welcome' => [
                'label' => 'Welcome Email',
                'description' => 'Account-created welcome email sent to new users.',
                'group' => 'Account',
                'severity' => 'info',
                'default_channels' => ['email'],
                'default_enabled' => true,
                'required' => false,
                'email_template' => 'welcome',
            ],
            'password.changed' => [
                'label' => 'Password Changed',
                'description' => 'When your password is changed.',
                'group' => 'Account',
                'severity' => 'warning',
                'default_channels' => ['email'],
                'default_enabled' => true,
                'required' => true,
                'email_template' => 'password-changed',
            ],
            'password.reset' => [
                'label' => 'Password Reset',
                'description' => 'When you reset your password.',
                'group' => 'Account',
                'severity' => 'warning',
                'default_channels' => ['email'],
                'default_enabled' => true,
                'required' => true,
                'email_template' => 'password-reset',
            ],
            'email.changed' => [
                'label' => 'Email Changed',
                'description' => 'When your email address is changed.',
                'group' => 'Account',
                'severity' => 'warning',
                'default_channels' => ['email'],
                'default_enabled' => true,
                'required' => true,
                'email_template' => 'email-changed',
            ],
            'account.deleted' => [
                'label' => 'Account Deleted',
                'description' => 'When your account is deleted.',
                'group' => 'Account',
                'severity' => 'critical',
                'default_channels' => ['email'],
                'default_enabled' => true,
                'required' => true,
                'email_template' => 'account-deleted',
            ],
            'database.maintenance' => [
                'label' => 'Database maintenance',
                'description' => 'Reset/reseed runs performed by an admin or the deploy endpoint.',
                'group' => 'System',
                'severity' => 'critical',
                'default_channels' => ['in_app'],
                'default_enabled' => true,
                'required' => false,
            ],
        ];
    }
}
