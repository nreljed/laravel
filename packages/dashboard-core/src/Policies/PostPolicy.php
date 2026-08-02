<?php

namespace Nreljed\DashboardCore\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Nreljed\DashboardCore\Blog\Models\Post;
use Nreljed\DashboardCore\Models\User;

class PostPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('posts.view');
    }

    public function view(User $user, Post $post): bool
    {
        return $user->hasPermission('posts.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('posts.create');
    }

    public function update(User $user, Post $post): bool
    {
        return $user->hasPermission('posts.edit');
    }

    public function delete(User $user, Post $post): bool
    {
        return $user->hasPermission('posts.delete');
    }
}
