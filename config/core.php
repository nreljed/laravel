<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Load Migrations
    |--------------------------------------------------------------------------
    |
    | Disable package migrations - this product already has its own migration
    | files for the same tables.
    |
    */

    'load_migrations' => false,

    /*
    |--------------------------------------------------------------------------
    | Health Git Status Exclude
    |--------------------------------------------------------------------------
    |
    | In this monorepo the root .git tracks sibling workspace trees (apps/
    | and packages/) that are not part of this app's own source. Changes in
    | those trees must not make the system health page report "dirty".
    |
    */

    'health_git_status_exclude' => ['apps/', 'packages/'],

];
