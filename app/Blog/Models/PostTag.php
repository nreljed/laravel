<?php

namespace App\Blog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PostTag extends Model
{
    protected $fillable = ['name', 'slug'];

    protected static function booted(): void
    {
        static::creating(function (PostTag $tag) {
            if (! $tag->slug) {
                $tag->slug = Str::slug($tag->name);
            }
        });
    }

    public function posts()
    {
        return $this->belongsToMany(Post::class, 'post_tag_post');
    }
}
