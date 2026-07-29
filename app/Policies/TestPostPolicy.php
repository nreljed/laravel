<?php

namespace App\Policies;

use App\Models\User;
use App\TestPosts\Models\TestPost;
use Illuminate\Auth\Access\HandlesAuthorization;

class TestPostPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('test-posts.view');
    }

    public function view(User $user, TestPost $testPost): bool
    {
        return $user->hasPermission('test-posts.view') || $user->id === $testPost->user_id || $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('test-posts.create');
    }

    public function update(User $user, TestPost $testPost): bool
    {
        return $user->id === $testPost->user_id || $user->isAdmin();
    }

    public function delete(User $user, TestPost $testPost): bool
    {
        return $user->id === $testPost->user_id || $user->isAdmin();
    }
}
