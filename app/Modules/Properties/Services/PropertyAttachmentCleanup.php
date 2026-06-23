<?php

namespace App\Modules\Properties\Services;

use App\Modules\Properties\Models\PropertyImage;
use Illuminate\Support\Facades\Storage;

class PropertyAttachmentCleanup
{
    public static function cleanupOrphanedAttachments(bool $dry_run = false): array
    {
        $images = PropertyImage::all();
        $disk = Storage::disk('public');

        $scanned = $images->count();
        $deleted = 0;
        $errors = [];

        foreach ($images as $image) {
            $filepath = $image->path;

            if (! $disk->exists($filepath)) {
                if (! $dry_run) {
                    try {
                        $image->delete();
                        $deleted++;
                    } catch (\Exception $e) {
                        $errors[] = "Failed to delete image record ID {$image->id}: {$e->getMessage()}";
                    }
                } else {
                    $deleted++;
                }
            }
        }

        return [
            'scanned' => $scanned,
            'deleted' => $deleted,
            'skipped' => $scanned - $deleted,
            'errors' => $errors,
        ];
    }
}
