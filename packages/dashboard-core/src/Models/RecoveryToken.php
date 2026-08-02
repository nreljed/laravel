<?php

namespace Nreljed\DashboardCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RecoveryToken extends Model
{
    protected $fillable = ['token_hash', 'used', 'expires_at'];

    protected $casts = [
        'used' => 'boolean',
        'expires_at' => 'datetime',
    ];

    private ?string $plaintextToken = null;

    public static function generate(): self
    {
        $plaintext = Str::random(64);

        $token = static::create([
            'token_hash' => Hash::make($plaintext),
            'used' => false,
            'expires_at' => now()->addHours(24),
        ]);

        $token->plaintextToken = $plaintext;

        return $token;
    }

    public function getPlaintext(): ?string
    {
        return $this->plaintextToken;
    }

    public static function findByToken(string $plaintext): ?self
    {
        return static::where('used', false)
            ->where('expires_at', '>', now())
            ->get()
            ->first(fn (self $token) => Hash::check($plaintext, $token->token_hash));
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
