<?php

namespace Nreljed\DashboardCore\Notifications;

/**
 * Critical alert broadcast to all admins after a database maintenance run.
 */
class DatabaseMaintenanceNotification extends AppNotification
{
    public function __construct(
        string $type,
        string $title,
        string $message,
        string $severity = 'critical',
        array $data = [],
    ) {
        parent::__construct($type, array_merge([
            'title' => $title,
            'message' => $message,
            'severity' => $severity,
            'module' => 'core',
            'link' => '',
        ], $data));
    }
}
