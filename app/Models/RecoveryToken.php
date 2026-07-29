<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class RecoveryToken extends Model
{
    protected $fillable = ['token', 'used', 'expires_at'];

    protected $casts = [
        'used' => 'boolean',
        'expires_at' => 'datetime',
    ];

    public static function generate(): static
    {
        return static::create([
            'token' => Str::random(64),
            'used' => false,
            'expires_at' => now()->addHours(24),
        ]);
    }

    public function isValid(): bool
    {
        return ! $this->used && $this->expires_at->isFuture();
    }

    public function markUsed(): void
    {
        $this->update(['used' => true]);
    }
}
