<?php

namespace App\Console\Commands;

use App\TestPosts\Models\TestPost;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

#[Signature('app:cleanup-orphan-media {--dry-run : List orphan files without deleting them}')]
#[Description('Scan storage and remove media files not referenced by any model.')]
class CleanupOrphanMedia extends Command
{
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $this->info('Scanning test-posts storage...');

        $deleted = $this->cleanOrphans('test-posts', TestPost::class, $dryRun);

        $this->newLine();
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

        $files = $disk->files($folder);
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

        if ($orphanCount === 0) {
            $this->line("  No orphans found in {$folder}/");
        }

        return $orphanCount;
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
