<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RepeaterData extends Model
{
    protected $table = 'repeater_data';

    protected $fillable = [
        'model_type',
        'model_id',
        'field_key',
        'module',
        'data',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }
}
