<?php

namespace App\Blog\Models;

use App\Models\User;
use App\Services\HtmlSanitizer;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property string $slug
 * @property string|null $content
 * @property string|null $excerpt
 * @property string|null $featured_image
 * @property string $status
 * @property Carbon|null $published_at
 * @property array|null $metadata
 * @property int|null $category_id
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property string|null $og_image
 * @property string|null $focus_keyword
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read self|null $category
 * @property-read Collection<int, PostTag> $tags
 * @property-read Collection<int, ContentBlock> $contentBlocks
 */
class Post extends Model
{
    protected $table = 'posts';

    protected $fillable = [
        'user_id', 'title', 'slug', 'content', 'excerpt', 'featured_image',
        'status', 'published_at', 'category_id', 'metadata',
        'meta_title', 'meta_description', 'og_image', 'focus_keyword',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected function content(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => HtmlSanitizer::sanitize($value),
        );
    }

    protected function excerpt(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => HtmlSanitizer::sanitizeText($value),
        );
    }

    #[\Override]
    protected static function booted(): void
    {
        static::creating(function (Post $post) {
            if (! $post->slug) {
                $post->slug = Str::slug($post->title).'-'.Str::random(6);
            }
        });

        static::deleting(function (Post $post) {
            $paths = [];

            if ($post->featured_image) {
                $paths[] = self::urlToPath($post->featured_image);
            }

            if ($post->og_image) {
                $paths[] = self::urlToPath($post->og_image);
            }

            foreach ($post->contentBlocks as $block) {
                if ($block->media_url) {
                    $paths[] = self::urlToPath($block->media_url);
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

        $path = $url;

        if (str_contains(str_replace('\\', '/', $path), '../')) {
            return null;
        }

        return $path;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PostCategory::class, 'category_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(PostTag::class, 'post_tag_post');
    }

    public function contentBlocks(): HasMany
    {
        return $this->hasMany(ContentBlock::class)->orderBy('order');
    }
}
