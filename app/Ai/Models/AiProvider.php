<?php

namespace App\Ai\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

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
                'models' => ['gpt-4o', 'gpt-4o-mini', 'gpt-4.1', 'gpt-4.1-mini', 'o3-mini'],
            ],
            'anthropic' => [
                'name' => 'Anthropic',
                'models' => ['claude-opus-4', 'claude-sonnet-4', 'claude-haiku-4-5'],
            ],
            'gemini' => [
                'name' => 'Google Gemini',
                'models' => ['gemini-2.5-pro', 'gemini-2.5-flash', 'gemini-2.0-flash'],
            ],
            'mistral' => [
                'name' => 'Mistral',
                'models' => ['mistral-large-latest', 'mistral-medium-latest', 'mistral-small-latest'],
            ],
            'deepseek' => [
                'name' => 'DeepSeek',
                'models' => ['deepseek-chat', 'deepseek-reasoner'],
            ],
            'xai' => [
                'name' => 'xAI (Grok)',
                'models' => ['grok-3', 'grok-3-mini'],
            ],
            'ollama' => [
                'name' => 'Ollama (Local)',
                'models' => [],
            ],
        ];
    }
}
