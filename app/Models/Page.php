<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    protected $fillable = ['slug', 'title', 'content', 'is_published'];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }
}
