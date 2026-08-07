<?php

namespace Nreljed\DashboardCore\Services;

class NotificationTypeRegistry
{
    protected ?array $types = null;

    public function __construct(protected FeatureRegistry $registry) {}

    public function all(): array
    {
        if ($this->types === null) {
            $this->types = array_merge(
                config('core.notification_types', []),
                $this->registry->getAllNotificationTypes(),
            );
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
}
