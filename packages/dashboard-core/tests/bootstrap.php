<?php

/*
|--------------------------------------------------------------------------
| Package test bootstrap
|--------------------------------------------------------------------------
|
| The package is developed inside the monorepo, so the consuming
| application's vendor/autoload.php provides the framework dependencies.
| When the package is installed standalone, its own vendor/autoload.php is
| used instead.
|
*/

$candidates = [
    __DIR__.'/../../../vendor/autoload.php',  // monorepo (master app)
    __DIR__.'/../../vendor/autoload.php',     // standalone package install
];

foreach ($candidates as $autoload) {
    if (is_file($autoload)) {
        require $autoload;

        return;
    }
}

fwrite(STDERR, "Unable to locate Composer autoloader.\n");

exit(1);
