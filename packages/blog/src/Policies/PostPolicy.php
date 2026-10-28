<?php

namespace Nreljed\Blog\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Nreljed\Platform\Models\User;
use Nreljed\Blog\Models\Post;

class PostPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('posts.view');
    }

    public function view(User $user, Post $post): bool
    {
        return $user->hasPermission('posts.view')
            && ($this->canManagePosts($user) || $user->id === $post->user_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('posts.create');
    }

    public function update(User $user, Post $post): bool
    {
        return $user->hasPermission('posts.edit')
            && ($this->canManagePosts($user) || $user->id === $post->user_id);
    }

    public function delete(User $user, Post $post): bool
    {
        return $user->hasPermission('posts.delete')
            && ($this->canManagePosts($user) || $user->id === $post->user_id);
    }

    protected function canManagePosts(User $user): bool
    {
        return $user->hasPermission('posts.manage');
    }
}
