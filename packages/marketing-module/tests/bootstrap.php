<?php

use Composer\Autoload\ClassLoader;

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
        $loader = require $autoload;

        // A package installed as a dependency of an application loses its
        // own autoload-dev rules, so the test namespace has to be mapped by
        // hand. In a standalone checkout Composer already did it.
        $prefixes = $loader instanceof ClassLoader ? $loader->getPrefixesPsr4() : [];

        if (! isset($prefixes['Nreljed\\MarketingModule\\Tests\\'])) {
            spl_autoload_register(static function (string $class): void {
                $prefix = 'Nreljed\\MarketingModule\\Tests\\';

                if (! str_starts_with($class, $prefix)) {
                    return;
                }

                $relative = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix)));
                $file = __DIR__.DIRECTORY_SEPARATOR.$relative.'.php';

                if (is_file($file)) {
                    require $file;
                }
            });
        }

        return;
    }
}

fwrite(STDERR, "Unable to locate Composer autoloader.\n");

exit(1);
