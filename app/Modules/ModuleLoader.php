<?php

namespace App\Modules;

use App\Modules\Contracts\ModuleInterface;
use Illuminate\Support\Facades\File;

class ModuleLoader
{
    protected ModuleRegistry $registry;

    public function __construct(ModuleRegistry $registry)
    {
        $this->registry = $registry;
    }

    public function load(): void
    {
        $paths = config('modules.paths', [app_path('Modules')]);

        foreach ($paths as $path) {
            $this->loadFromPath($path);
        }
    }

    protected function loadFromPath(string $path): void
    {
        if (!File::isDirectory($path)) {
            return;
        }

        $directories = File::directories($path);

        foreach ($directories as $dir) {
            $moduleFile = $dir . '/' . basename($dir) . 'Module.php';

            if (!File::exists($moduleFile)) {
                continue;
            }

            $relativePath = str_replace(
                [app_path(), base_path()],
                ['App', 'App'],
                dirname($moduleFile)
            );

            $namespace = str_replace(
                ['/', '\\'],
                ['\\', '\\'],
                'App\\Modules\\' . basename($dir)
            );

            $className = $namespace . '\\' . basename($dir) . 'Module';

            if (!class_exists($className)) {
                require_once $moduleFile;
            }

            if (!class_exists($className)) {
                continue;
            }

            $instance = app($className);

            if ($instance instanceof ModuleInterface) {
                $this->registry->register($instance);
            }
        }
    }
}
