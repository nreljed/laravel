<?php

namespace Nreljed\MarketingModule\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nreljed\MarketingModule\Database\Factories\SocialAccountFactory;
use Nreljed\MarketingModule\Enums\SocialAccountStatus;
use Nreljed\MarketingModule\Services\TokenVault;
use Nreljed\Platform\Models\User;

/**
 * A social account a user has connected for publishing.
 *
 * The credentials on this row are encrypted at rest; nothing outside this
 * model should read the `*_encrypted` columns directly.
 */
class SocialAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'platform',
        'account_id',
        'account_name',
        'username',
        'access_token_encrypted',
        'refresh_token_encrypted',
        'scopes',
        'expires_at',
        'status',
        'metadata',
        'last_validated_at',
    ];

    protected $casts = [
        'scopes' => 'array',
        'metadata' => 'array',
        'expires_at' => 'datetime',
        'last_validated_at' => 'datetime',
        'status' => SocialAccountStatus::class,
    ];

    /**
     * Hide the encrypted columns from array/JSON serialisation so a model
     * dumped into a log or a response cannot leak a credential.
     *
     * @var list<string>
     */
    protected $hidden = [
        'access_token_encrypted',
        'refresh_token_encrypted',
    ];

    protected static function newFactory(): Factory
    {
        return SocialAccountFactory::new();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The decrypted access token.
     */
    public function accessToken(): ?string
    {
        return app(TokenVault::class)->decrypt($this->access_token_encrypted);
    }

    /**
     * The decrypted refresh token.
     */
    public function refreshToken(): ?string
    {
        return app(TokenVault::class)->decrypt($this->refresh_token_encrypted);
    }

    /**
     * A masked access token, safe to render in the account list.
     */
    public function maskedAccessToken(): string
    {
        return app(TokenVault::class)->maskStored($this->access_token_encrypted);
    }

    /**
     * The Pinterest board this account publishes to, when the platform has
     * one. Other platforms return null.
     */
    public function boardId(): ?string
    {
        $board = $this->metadata['default_board'] ?? $this->metadata['board_id'] ?? null;

        return is_string($board) && $board !== '' ? $board : null;
    }

    /**
     * Whether the stored grant has expired and needs a refresh.
     */
    public function needsRefresh(): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        return $this->expires_at->getTimestamp() <= time() + 60;
    }
}
