<?php

namespace App\Blog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentBlock extends Model
{
    protected $fillable = [
        'post_id', 'type', 'content', 'media_url', 'media_alt', 'settings', 'order',
    ];

    protected $casts = [
        'settings' => 'array',
        'order' => 'integer',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
