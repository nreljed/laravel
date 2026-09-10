<?php

use App\TestPosts\Models\TestPost;
use Nreljed\DashboardCore\Models\User;
use Nreljed\DashboardCore\Posts\Models\Post;

return [

    /*
    |--------------------------------------------------------------------------
    | Orphan Media Targets
    |--------------------------------------------------------------------------
    |
    | Folder => model class pairs scanned by the core:cleanup-orphan-media
    | command. Each model class must expose a getReferencedPaths() method that
    | returns the storage paths it references.
    |
    */

    'orphan_media' => [
        'posts' => Post::class,
        'test-posts' => TestPost::class,
        'avatars' => User::class,
    ],

];
