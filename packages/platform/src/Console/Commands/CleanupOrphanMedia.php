<?php

namespace Nreljed\Platform\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Nreljed\Platform\Services\FeatureRegistry;

#[Signature('core:cleanup-orphan-media {--dry-run : List orphan files without deleting them}')]
#[Description('Scan storage and remove media files not referenced by any model.')]
class CleanupOrphanMedia extends Command
{
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $configTargets = config('media.orphan_media', []);

        // Merge feature-declared targets so modules can register their own
        // storage folders without touching the host config.
        $targets = array_merge($configTargets, app(FeatureRegistry::class)->getAllOrphanMediaTargets());

        $deleted = 0;

        foreach ($targets as $folder => $modelClass) {
            $this->info("Scanning {$folder}/ storage...");
            $deleted += $this->cleanOrphans((string) $folder, $modelClass, $dryRun);
            $this->newLine();
        }

        $this->info($dryRun
            ? "Would delete {$deleted} orphan file(s)."
            : "Deleted {$deleted} orphan file(s).");

        return self::SUCCESS;
    }

    /**
     * Scan a storage folder and remove files not referenced by the given model.
     *
     * @param  class-string<Model>  $modelClass
     * @return int Number of files deleted (or would be deleted in dry-run).
     */
    private function cleanOrphans(string $folder, string $modelClass, bool $dryRun): int
    {
        $disk = Storage::disk('public');
        $referencedPaths = $this->getAllReferencedPaths($modelClass);

        // allFiles() recurses YYYY/m month subfolders used by organizeByMonth
        // upload profiles (posts/2026/09/...) — files() only scans the top level.
        $files = $disk->allFiles($folder);
        $orphanCount = 0;

        foreach ($files as $file) {
            if (! in_array($file, $referencedPaths, true)) {
                if ($dryRun) {
                    $this->line("  [dry-run] Would delete: {$file}");
                } else {
                    $disk->delete($file);
                    $this->line("  Deleted: {$file}");
                }
                $orphanCount++;
            }
        }

        if (! $dryRun) {
            $this->pruneEmptyDirectories($disk, $folder);
        }

        if ($orphanCount === 0) {
            $this->line("  No orphans found in {$folder}/");
        }

        return $orphanCount;
    }

    /**
     * Remove directories left empty after the sweep (deepest first), so
     * nested YYYY/m month folders do not accumulate.
     */
    private function pruneEmptyDirectories(FilesystemAdapter $disk, string $folder): void
    {
        $directories = $disk->allDirectories($folder);
        rsort($directories);

        foreach ($directories as $directory) {
            if ($disk->files($directory) === [] && $disk->directories($directory) === []) {
                $disk->deleteDirectory($directory);
            }
        }
    }

    /**
     * Collect all storage paths referenced by all rows of the given model.
     *
     * @param  class-string<Model>  $modelClass
     * @return list<string>
     */
    private function getAllReferencedPaths(string $modelClass): array
    {
        $paths = [];

        $modelClass::query()->each(function ($model) use (&$paths) {
            if (method_exists($model, 'getReferencedPaths')) {
                $paths = array_merge($paths, $model->getReferencedPaths());
            }
        });

        return array_values(array_unique($paths));
    }
}
