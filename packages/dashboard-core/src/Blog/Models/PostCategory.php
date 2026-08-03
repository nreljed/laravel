<?php

namespace Nreljed\DashboardCore\Blog\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Nreljed\DashboardCore\Database\Factories\PostCategoryFactory;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int|null $parent_id
 * @property int $order
 * @property int $depth
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, self> $children
 * @property-read self|null $parent
 * @property-read Collection<int, Post> $posts
 * @property-read int $posts_count
 */
class PostCategory extends Model
{
    /** @use HasFactory<PostCategoryFactory> */
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'description', 'parent_id', 'order', 'depth',
        'meta_title', 'meta_description',
    ];

    protected $casts = [
        'order' => 'integer',
        'depth' => 'integer',
    ];

    protected static function newFactory(): PostCategoryFactory
    {
        return PostCategoryFactory::new();
    }

    #[\Override]
    protected static function booted(): void
    {
        static::creating(function (PostCategory $category) {
            if (! $category->slug) {
                $category->slug = Str::slug($category->name);
            }
        });

        static::saved(function (PostCategory $category) {
            $category->refreshDepth();
        });
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'category_id');
    }

    public function ancestors(): HasMany
    {
        return $this->hasMany(self::class, 'id', 'parent_id');
    }

    public function descendants(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id', 'id');
    }

    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('order')->orderBy('name');
    }

    public function refreshDepth(): void
    {
        $depth = $this->calculateDepth();

        if ($this->depth === $depth) {
            return;
        }

        static::withoutEvents(fn () => $this->update(['depth' => $depth]));

        $this->refreshChildrenDepths();
    }

    public function refreshChildrenDepths(): void
    {
        foreach ($this->children as $child) {
            $child->refreshDepth();
        }
    }

    protected function calculateDepth(): int
    {
        $depth = 0;
        $parentId = $this->parent_id;
        $visited = [$this->id];

        while ($parentId) {
            if (in_array($parentId, $visited, true)) {
                break;
            }

            $visited[] = $parentId;
            $depth++;
            $parent = static::find($parentId);
            $parentId = $parent?->parent_id;
        }

        return $depth;
    }

    public function toTree(): Collection
    {
        $categories = static::withCount('posts')->ordered()->get();
        $keyed = $categories->keyBy('id');

        $tree = new Collection;
        foreach ($categories as $category) {
            if ($category->parent_id && $keyed->has($category->parent_id)) {
                $keyed[$category->parent_id]->children->push($category);
            } else {
                $tree->push($category);
            }
        }

        return $tree;
    }

    public static function flatOptions(?int $excludeId = null): array
    {
        $query = static::ordered();

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->get()
            ->map(fn (PostCategory $cat) => [
                'id' => $cat->id,
                'name' => str_repeat('— ', $cat->depth).$cat->name,
                'depth' => $cat->depth,
            ])
            ->toArray();
    }

    public function getBreadcrumbsAttribute(): array
    {
        $breadcrumbs = [];
        $current = $this;

        while ($current) {
            array_unshift($breadcrumbs, [
                'id' => $current->id,
                'name' => $current->name,
                'slug' => $current->slug,
            ]);
            $current = $current->parent;
        }

        return $breadcrumbs;
    }

    public function getDescendantIds(): array
    {
        $ids = [];
        $walk = function (self $category) use (&$walk, &$ids) {
            foreach ($category->children as $child) {
                $ids[] = $child->id;
                $walk($child);
            }
        };

        $walk($this);

        return $ids;
    }
}
