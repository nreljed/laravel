<?php

namespace Nreljed\DashboardCore\Blog\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Nreljed\DashboardCore\Services\HtmlSanitizer;

/**
 * @property int $id
 * @property int $post_id
 * @property string $type
 * @property string|null $content
 * @property string|null $media_url
 * @property string|null $media_alt
 * @property array|null $settings
 * @property int $order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Post $post
 */
class ContentBlock extends Model
{
    protected $fillable = [
        'post_id', 'type', 'content', 'media_url', 'media_alt', 'settings', 'order',
    ];

    protected $casts = [
        'settings' => 'array',
        'order' => 'integer',
    ];

    protected function mediaUrl(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => HtmlSanitizer::sanitizeImageUrl($value),
        );
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
