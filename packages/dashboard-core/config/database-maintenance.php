<?php

/*
|--------------------------------------------------------------------------
| Database Maintenance
|--------------------------------------------------------------------------
|
| Controls whether the destructive database reset/reseed UI is available.
| Defaults to enabled everywhere except production. Production is locked down
| to the token-protected /deploy/reset endpoint (triggered by the
| database-reset GitHub Action); set DB_MAINTENANCE_ENABLED=true only if you
| explicitly want the UI available there too.
|
*/

return [
    'enabled' => env('DB_MAINTENANCE_ENABLED', ! app()->environment('production')),
];
