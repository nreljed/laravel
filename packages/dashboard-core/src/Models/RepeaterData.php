<?php

namespace Nreljed\DashboardCore\Models;

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

    #[\Override]
    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }
}
