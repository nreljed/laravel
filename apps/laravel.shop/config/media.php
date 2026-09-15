<?php

use Nreljed\Blog\Models\Post;
use Nreljed\Platform\Models\User;

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
        'avatars' => User::class,
    ],

];
