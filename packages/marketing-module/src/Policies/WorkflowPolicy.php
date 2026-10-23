<?php

namespace Nreljed\MarketingModule\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Nreljed\MarketingModule\Models\Workflow;
use Nreljed\Platform\Models\User;

/**
 * Workflows act on the whole audience, so the policy is permission-only.
 */
class WorkflowPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('marketing.workflows.manage');
    }

    public function view(User $user, Workflow $workflow): bool
    {
        return $user->hasPermission('marketing.workflows.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('marketing.workflows.manage');
    }

    public function update(User $user, Workflow $workflow): bool
    {
        return $user->hasPermission('marketing.workflows.manage');
    }

    public function delete(User $user, Workflow $workflow): bool
    {
        return $user->hasPermission('marketing.workflows.manage');
    }
}
