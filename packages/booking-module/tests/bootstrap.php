<?php

/*
|--------------------------------------------------------------------------
| Package test bootstrap
|--------------------------------------------------------------------------
|
| The package is distributed via Composer (vcs), so the consuming
| application's vendor/autoload.php provides the framework dependencies.
| When the package is developed in the monorepo or installed standalone,
| its own autoloader is resolved instead.
|
*/

$candidates = [
    __DIR__.'/../vendor/autoload.php',           // standalone repo checkout (own vendor/)
    __DIR__.'/../../../../vendor/autoload.php', // composer vcs install (consuming app root)
    __DIR__.'/../../../vendor/autoload.php',    // monorepo (master app)
];

foreach ($candidates as $autoload) {
    if (is_file($autoload)) {
        require $autoload;

        return;
    }
}

fwrite(STDERR, "Unable to locate Composer autoloader.\n");

exit(1);
