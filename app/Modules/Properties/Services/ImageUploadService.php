<?php

namespace App\Modules\Properties\Services;

use App\Modules\Properties\Models\Property;
use App\Modules\Properties\Models\PropertyImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

class ImageUploadService
{
    public function upload(UploadedFile $file, Property $property, bool $featured = false, ?string $altText = null): PropertyImage
    {
        $path = $file->store('properties/'.$property->id, 'public');

        $thumbnailPath = $this->generateThumbnail($file, $property);

        $currentCount = $property->images()->count();

        $image = PropertyImage::create([
            'property_id' => $property->id,
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
            'thumbnail_url' => $thumbnailPath ? Storage::disk('public')->url($thumbnailPath) : Storage::disk('public')->url($path),
            'alt_text' => $altText,
            'is_featured' => $featured || $currentCount === 0,
            'sort_order' => $currentCount,
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
        ]);

        if ($featured) {
            $this->setFeatured($image);
        }

        return $image;
    }

    public function delete(PropertyImage $image): bool
    {
        return (bool) $image->delete();
    }

    public function reorder(Property $property, array $orderedIds): void
    {
        foreach ($orderedIds as $index => $id) {
            PropertyImage::where('id', $id)
                ->where('property_id', $property->id)
                ->update(['sort_order' => $index]);
        }
    }

    public function setFeatured(PropertyImage $image): void
    {
        PropertyImage::where('property_id', $image->property_id)
            ->where('is_featured', true)
            ->where('id', '!=', $image->id)
            ->update(['is_featured' => false]);

        $image->update(['is_featured' => true]);
    }

    protected function generateThumbnail(UploadedFile $file, Property $property): ?string
    {
        try {
            $img = Image::decodePath($file->getRealPath());
            $img->scaleDown(400, 300);

            $thumbPath = 'properties/'.$property->id.'/thumb_'.$file->hashName();
            Storage::disk('public')->put($thumbPath, (string) $img->encode());

            return $thumbPath;
        } catch (\Exception $e) {
            return null;
        }
    }
}
