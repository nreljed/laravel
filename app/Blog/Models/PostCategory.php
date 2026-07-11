<?php

namespace App\Blog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PostCategory extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'parent_id', 'order'];

    protected $casts = ['order' => 'integer'];

    protected static function booted(): void
    {
        static::creating(function (PostCategory $category) {
            if (! $category->slug) {
                $category->slug = Str::slug($category->name);
            }
        });
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function posts()
    {
        return $this->hasMany(Post::class, 'category_id');
    }
}
