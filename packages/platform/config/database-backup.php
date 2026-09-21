<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Database Backups
    |--------------------------------------------------------------------------
    |
    | Controls the automatic `database:backup` schedule. Backups are gzipped
    | dump files stored under the configured directory (default <storage>/app/
    | backups) and pruned to the retention count after each run.
    | (The old `/deploy/backup` HTTP endpoint was removed — the CLI command is
    | the supported path.)
    |
    */

    'enabled' => env('DB_BACKUP_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Backup Directory
    |--------------------------------------------------------------------------
    |
    | Directory dump files are written to. Relative paths resolve against the
    | application storage path; absolute paths are used as-is.
    |
    */

    'dir' => env('DB_BACKUP_DIR', 'app/backups'),

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | How many gzipped dumps to keep after each run. Older files are pruned
    | newest-first so the backup directory never grows beyond this bound.
    |
    */

    'keep' => (int) env('DB_BACKUP_KEEP', 7),

    /*
    |--------------------------------------------------------------------------
    | Schedule
    |--------------------------------------------------------------------------
    |
    | Time of day (server timezone) the `database:backup` command runs at.
    | Picked to avoid clashing with the deploy window and peak traffic.
    |
    */

    'schedule' => env('DB_BACKUP_SCHEDULE', '03:17'),

    /*
    |--------------------------------------------------------------------------
    | mysqldump Binary
    |--------------------------------------------------------------------------
    |
    | Path to the mysqldump binary. Shared hosts sometimes keep it off the
    | default PATH or under a versioned name (e.g. /usr/local/mysql5.7/bin/
    | mysqldump) — override via DB_BACKUP_MYSQLDUMP. When the binary is not
    | available, the service falls back to a pure PDO dump.
    |
    */

    'mysqldump' => env('DB_BACKUP_MYSQLDUMP', 'mysqldump'),

];
