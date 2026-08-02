<?php

namespace Nreljed\DashboardCore\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;

class MediaService
{
    const int MAX_DIMENSION = 1920;

    const int QUALITY = 80;

    const int MAX_FILE_SIZE = 51200; // 50MB in KB

    const array IMAGE_MIMES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'image/avif', 'image/bmp', 'image/tiff',
    ];

    const array VIDEO_MIMES = [
        'video/mp4', 'video/webm', 'video/ogg', 'video/quicktime',
        'video/x-msvideo', 'video/x-matroska',
    ];

    public function getValidationRules(): array
    {
        $allMimes = array_merge(
            preg_replace('/^image\//', '', self::IMAGE_MIMES),
            preg_replace('/^video\//', '', self::VIDEO_MIMES)
        );

        return [
            'file' => 'required|file|mimetypes:'.implode(',', array_merge(self::IMAGE_MIMES, self::VIDEO_MIMES)).'|max:'.self::MAX_FILE_SIZE,
        ];
    }

    public function upload(UploadedFile $file, string $folder): array
    {
        $mime = $file->getMimeType();

        if (in_array($mime, self::VIDEO_MIMES)) {
            return $this->storeVideo($file, $folder);
        }

        return $this->processImage($file, $folder);
    }

    public function delete(string $path): void
    {
        if (! $this->isSafePath($path)) {
            return;
        }

        Storage::disk('public')->delete($path);
    }

    /**
     * Reject path traversal and absolute paths so callers can never delete
     * files outside the configured disk root.
     */
    protected function isSafePath(string $path): bool
    {
        $path = str_replace('\\', '/', trim($path));

        if ($path === '' || str_starts_with($path, '/')) {
            return false;
        }

        if (preg_match('#(^|/)\.\.(/|$)#', $path) === 1) {
            return false;
        }

        return true;
    }

    private function processImage(UploadedFile $file, string $folder): array
    {
        $manager = new ImageManager(new Driver);
        $image = $manager->decode($file->get());

        if ($image->width() > self::MAX_DIMENSION || $image->height() > self::MAX_DIMENSION) {
            $image->scaleDown(self::MAX_DIMENSION, self::MAX_DIMENSION);
        }

        $encoded = $image->encode(new WebpEncoder(quality: self::QUALITY));

        $filename = Str::random(40).'.webp';
        $path = $folder.'/'.$filename;

        Storage::disk('public')->put($path, (string) $encoded);

        return [
            'url' => Storage::url($path),
            'path' => $path,
        ];
    }

    private function storeVideo(UploadedFile $file, string $folder): array
    {
        $filename = Str::random(40).'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs($folder, $filename, 'public');

        return [
            'url' => Storage::url($path),
            'path' => $path,
        ];
    }
}
