<?php

namespace Nreljed\DashboardCore\Blog\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Nreljed\DashboardCore\Database\Factories\PostTagFactory;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int $order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Post> $posts
 */
class PostTag extends Model
{
    /** @use HasFactory<PostTagFactory> */
    use HasFactory;

    protected $fillable = ['name', 'slug', 'description', 'order'];

    protected $casts = [
        'order' => 'integer',
    ];

    protected static function newFactory(): PostTagFactory
    {
        return PostTagFactory::new();
    }

    #[\Override]
    protected static function booted(): void
    {
        static::creating(function (PostTag $tag) {
            if (! $tag->slug) {
                $tag->slug = Str::slug($tag->name);
            }
        });
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'post_tag_post');
    }
}
