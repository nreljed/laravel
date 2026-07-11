<?php

namespace App\Policies;

use App\Blog\Models\Post;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PostPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('posts.view');
    }

    public function view(User $user, Post $post): bool
    {
        return $user->id === $post->user_id || $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('posts.create');
    }

    public function update(User $user, Post $post): bool
    {
        return $user->id === $post->user_id || $user->isAdmin();
    }

    public function delete(User $user, Post $post): bool
    {
        return $user->id === $post->user_id || $user->isAdmin();
    }
}
