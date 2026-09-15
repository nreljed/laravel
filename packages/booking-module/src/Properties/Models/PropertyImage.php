<?php

namespace Nreljed\BookingModule\Properties\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PropertyImage extends Model
{
    protected $table = 'property_images';

    protected $fillable = [
        'property_id', 'path', 'url', 'thumbnail_url', 'alt_text',
        'caption', 'is_featured', 'sort_order', 'mime_type', 'file_size',
    ];

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
            'file_size' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (self $image) {
            Storage::disk('public')->delete([$image->path, $image->thumbnail_url]);
        });
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
