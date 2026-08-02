<?php

namespace Nreljed\DashboardCore\Ai\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Nreljed\DashboardCore\Models\User;

/**
 * @property int $id
 * @property int $user_id
 * @property string $provider
 * @property string $api_key_encrypted
 * @property string|null $preferred_model
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> active()
 * @method static \Illuminate\Database\Eloquent\Builder<static> forProvider(string $provider)
 */
class AiProvider extends Model
{
    protected $fillable = [
        'user_id',
        'provider',
        'api_key_encrypted',
        'preferred_model',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to only active providers.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to a specific provider slug.
     */
    public function scopeForProvider(Builder $query, string $provider): Builder
    {
        return $query->where('provider', $provider);
    }

    /**
     * Encrypt and store the raw API key.
     */
    public function setApiKeyAttribute(string $value): void
    {
        $this->attributes['api_key_encrypted'] = Crypt::encryptString($value);
    }

    /**
     * Decrypt and return the stored API key.
     */
    public function getDecryptedKey(): string
    {
        return Crypt::decryptString($this->attributes['api_key_encrypted']);
    }

    /**
     * Return a masked version of the API key for display.
     */
    public function getMaskedKey(): string
    {
        $key = $this->getDecryptedKey();

        if (strlen($key) <= 8) {
            return str_repeat('*', strlen($key));
        }

        return substr($key, 0, 4).'...'.substr($key, -4);
    }

    /**
     * Get all supported AI providers and their models.
     *
     * @return array<string, array{name: string, models: list<string>}>
     */
    public static function supportedProviders(): array
    {
        return [
            'openai' => [
                'name' => 'OpenAI',
                'models' => ['gpt-5.6-sol', 'gpt-5.6-terra', 'gpt-5.6-luna', 'gpt-5', 'o4-mini'],
            ],
            'anthropic' => [
                'name' => 'Anthropic',
                'models' => ['claude-opus-4-8', 'claude-sonnet-5', 'claude-haiku-4-5'],
            ],
            'gemini' => [
                'name' => 'Google Gemini',
                'models' => ['gemini-3.6-flash', 'gemini-3.5-flash-lite', 'gemini-2.5-pro'],
            ],
            'mistral' => [
                'name' => 'Mistral',
                'models' => ['mistral-medium-latest', 'mistral-small-latest', 'mistral-large-latest'],
            ],
            'deepseek' => [
                'name' => 'DeepSeek',
                'models' => ['deepseek-chat', 'deepseek-reasoner'],
            ],
            'xai' => [
                'name' => 'xAI (Grok)',
                'models' => ['grok-4.5', 'grok-4.3', 'grok-4.20-reasoning'],
            ],
            'ollama' => [
                'name' => 'Ollama (Local)',
                'models' => [],
            ],
        ];
    }
}
