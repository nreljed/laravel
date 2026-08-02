<?php

namespace Nreljed\DashboardCore\Ai\Policies;

use Nreljed\DashboardCore\Ai\Models\AiProvider;
use Nreljed\DashboardCore\Models\User;

/**
 * Authorization policy for AI provider keys.
 *
 * Ensures users can only manage their own API keys. Admin users bypass all checks via Gate::before().
 */
class AiProviderPolicy
{
    /**
     * Any authenticated user can view the AI keys page.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Users can only view their own provider keys.
     */
    public function view(User $user, AiProvider $provider): bool
    {
        return $user->id === $provider->user_id;
    }

    /**
     * Any authenticated user can create a provider key.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Users can only update their own provider keys.
     */
    public function update(User $user, AiProvider $provider): bool
    {
        return $user->id === $provider->user_id;
    }

    /**
     * Users can only delete their own provider keys.
     */
    public function delete(User $user, AiProvider $provider): bool
    {
        return $user->id === $provider->user_id;
    }
}
