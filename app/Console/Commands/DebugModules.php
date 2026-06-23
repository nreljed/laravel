<?php

namespace App\Console\Commands;

use App\Modules\ModuleRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class DebugModules extends Command
{
    protected $signature = 'modules:debug {slug? : Filter by module slug}';

    protected $description = 'Debug module registration, nav items, and route resolution';

    public function handle(ModuleRegistry $registry): int
    {
        $filter = $this->argument('slug');

        $this->info('=== MODULE REGISTRY DEBUG ===');
        $this->newLine();

        if (! Schema::hasTable('modules')) {
            $this->warn('Modules table does not exist. Run migrations first.');

            return Command::FAILURE;
        }

        $allModules = $registry->all();
        $bootedSlugs = $registry->getBooted()->keys()->toArray();

        if ($allModules->isEmpty()) {
            $this->error('No modules registered in the registry.');

            return Command::FAILURE;
        }

        foreach ($allModules as $slug => $module) {
            if ($filter && $slug !== $filter) {
                continue;
            }

            $enabled = $registry->isEnabled($slug);
            $booted = in_array($slug, $bootedSlugs);

            $status = ($enabled ? 'ENABLED' : 'DISABLED').' | '.($booted ? 'BOOTED' : 'NOT BOOTED');
            $this->line("  <fg=cyan>{$module->name()}</> ({$slug})  —  {$status}");

            $deps = $module->dependencies();
            if (! empty($deps)) {
                $this->line('    Dependencies: '.implode(', ', $deps));
            }

            $this->line('    Description: '.($module->description() ?? '-'));

            $nav = $module->getNavItems();
            if (empty($nav)) {
                $this->line('    Nav items: <fg=yellow>none</>');
            } else {
                $this->line('    Nav:');
                $this->renderNavItem($nav, 2);
            }

            $frontendPages = $module->getFrontendPages();
            if (! empty($frontendPages)) {
                $this->line('    Frontend pages:');
                foreach ($frontendPages as $page) {
                    $exists = Route::has($page['name']);
                    $marker = $exists ? '<fg=green>✓</>' : '<fg=red>✗</>';
                    $this->line("      {$marker} {$page['name']}  →  {$page['component']}  ({$page['path']})");
                }
            }

            $this->newLine();
        }

        $this->info('=== END ===');

        return Command::SUCCESS;
    }

    protected function renderNavItem(array $item, int $depth): void
    {
        $indent = str_repeat('  ', $depth);
        $title = $item['title'] ?? '?';

        $routeExists = false;
        $url = $item['url'] ?? null;
        if ($url) {
            $routeExists = true;
        }

        $marker = $routeExists ? '<fg=green>✓</>' : '<fg=yellow>~</>';
        $this->line("{$indent}{$marker} {$title}");

        if ($url) {
            $this->line("{$indent}  URL: {$url}");
        }

        if (! empty($item['items'])) {
            foreach ($item['items'] as $subItem) {
                $subUrl = $subItem['url'] ?? null;
                $subMarker = $subUrl ? '<fg=green>✓</>' : '<fg=red>✗</>';
                $this->line("{$indent}  {$subMarker} {$subItem['title']}");

                if ($subUrl) {
                    try {
                        $this->line("{$indent}    URL: {$subUrl}");
                    } catch (\Exception $e) {
                        $this->line("{$indent}    URL: <fg=red>ERROR: {$e->getMessage()}</>");
                    }
                }
            }
        }
    }
}
