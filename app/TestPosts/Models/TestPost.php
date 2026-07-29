<?php

namespace App\TestPosts\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TestPost extends Model
{
    protected $table = 'test_posts';

    protected $fillable = [
        'user_id',
        'title',
        'slug',
        'content',
        'excerpt',
        'featured_image',
        'gallery_images',
        'status',
        'published_at',
        'metadata',
        'latitude',
        'longitude',
        'address',
        'map_zoom',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'metadata' => 'array',
            'gallery_images' => 'array',
            'address' => 'array',
            'latitude' => 'float',
            'longitude' => 'float',
            'map_zoom' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (TestPost $testPost) {
            if (! $testPost->slug) {
                $testPost->slug = Str::slug($testPost->title).'-'.Str::random(6);
            }
        });

        static::updating(function (TestPost $testPost) {
            $oldGallery = $testPost->getOriginal('gallery_images');
            $newGallery = $testPost->gallery_images;

            if (is_string($oldGallery)) {
                $oldGallery = json_decode($oldGallery, true) ?? [];
            }
            if (is_string($newGallery)) {
                $newGallery = json_decode($newGallery, true) ?? [];
            }

            $oldGallery = $oldGallery ?? [];
            $newGallery = $newGallery ?? [];

            $oldUrls = array_map(fn (array $img) => $img['url'] ?? '', $oldGallery);
            $newUrls = array_map(fn (array $img) => $img['url'] ?? '', $newGallery);

            $removedUrls = array_diff($oldUrls, $newUrls);

            foreach ($removedUrls as $url) {
                $path = self::urlToPath($url);
                if ($path) {
                    Storage::disk('public')->delete($path);
                }
            }

            $oldFeatured = $testPost->getOriginal('featured_image');
            $newFeatured = $testPost->featured_image;

            if ($oldFeatured && $oldFeatured !== $newFeatured) {
                $path = self::urlToPath($oldFeatured);
                if ($path) {
                    Storage::disk('public')->delete($path);
                }
            }
        });

        static::deleting(function (TestPost $testPost) {
            $paths = [];

            if ($testPost->featured_image) {
                $paths[] = self::urlToPath($testPost->featured_image);
            }

            if ($testPost->gallery_images) {
                foreach ($testPost->gallery_images as $image) {
                    $url = $image['url'] ?? null;
                    if ($url) {
                        $paths[] = self::urlToPath($url);
                    }
                }
            }

            foreach (array_unique(array_filter($paths)) as $path) {
                Storage::disk('public')->delete($path);
            }
        });
    }

    protected static function urlToPath(?string $url): ?string
    {
        if (empty($url)) {
            return null;
        }

        $prefix = '/storage/';

        if (str_starts_with($url, $prefix)) {
            return substr($url, strlen($prefix));
        }

        if (str_starts_with($url, 'http')) {
            $parsed = parse_url($url, PHP_URL_PATH);
            if ($parsed && str_starts_with($parsed, $prefix)) {
                return substr($parsed, strlen($prefix));
            }

            return null;
        }

        if (! str_contains($url, '/')) {
            return null;
        }

        return $url;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all storage paths referenced by this model.
     *
     * @return array<string>
     */
    public function getReferencedPaths(): array
    {
        $paths = [];

        if ($this->featured_image) {
            $path = self::urlToPath($this->featured_image);
            if ($path) {
                $paths[] = $path;
            }
        }

        if ($this->gallery_images) {
            foreach ($this->gallery_images as $image) {
                $url = $image['url'] ?? null;
                if ($url) {
                    $path = self::urlToPath($url);
                    if ($path) {
                        $paths[] = $path;
                    }
                }
            }
        }

        return array_values(array_unique(array_filter($paths)));
    }
}
