<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'enabled', 'version', 'dependencies'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'dependencies' => 'array',
        ];
    }

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }
}
