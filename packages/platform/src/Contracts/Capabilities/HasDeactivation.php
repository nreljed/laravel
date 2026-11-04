<?php

namespace Nreljed\Platform\Contracts\Capabilities;

/**
 * The feature has something to say on the module-management page when it is
 * disabled.
 */
interface HasDeactivation
{
    /**
     * An HTML notice displayed to admins when this feature is disabled, useful
     * for explaining what functionality is unavailable. Return null for no
     * notice.
     */
    public function getDeactivationNotice(): ?string;
}
