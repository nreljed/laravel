<?php

namespace Nreljed\Platform\Contracts\Capabilities;

/**
 * The feature sends notifications and/or owns email templates.
 */
interface HasNotifications
{
    /**
     * Database notification types this feature dispatches, so the preference UI
     * can list them.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getNotificationTypes(): array;

    /**
     * Email templates this feature exposes to the template editor.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getEmailTemplates(): array;
}
