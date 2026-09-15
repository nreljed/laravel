<?php

namespace Nreljed\Platform\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $folder
 * @property string|null $parent_type
 * @property int|null $parent_id
 * @property string $path
 * @property string $url
 * @property string $filename
 * @property string|null $title
 * @property string $mime_type
 * @property int $size
 * @property int|null $width
 * @property int|null $height
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
class Media extends Model
{
    protected $table = 'media';

    protected $fillable = [
        'user_id', 'folder', 'parent_type', 'parent_id', 'path', 'url', 'filename', 'title',
        'mime_type', 'size', 'width', 'height',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): MorphTo
    {
        return $this->morphTo();
    }
}
