<?php

namespace Nreljed\Platform\Ai\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Nreljed\Platform\Ai\AiPricingCatalog;
use Nreljed\Platform\Models\User;

/**
 * @property int $id
 * @property int $user_id
 * @property string $provider
 * @property string $api_key_encrypted
 * @property string|null $preferred_model
 * @property bool $is_active
 * @property Carbon|null $last_tested_at
 * @property bool|null $last_test_ok
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
        'api_key',
        'api_key_encrypted',
        'preferred_model',
        'is_active',
        'last_tested_at',
        'last_test_ok',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_tested_at' => 'datetime',
        'last_test_ok' => 'boolean',
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
     * Get all supported AI providers and their metadata.
     *
     * Every fact the Account → Integrations UI needs beyond the DB row lives
     * here: display name, model list, canonical default model, whether a
     * stored key can be connection-tested, and capability hints for the
     * provider badges. Pricing is deliberately *not* here — that lives in
     * {@see AiPricingCatalog}.
     *
     * @return array<string, array{
     *     name: string,
     *     models: list<string>,
     *     default_model: string|null,
     *     testable: bool,
     *     capabilities: list<string>,
     * }>
     */
    public static function supportedProviders(): array
    {
        // Config-driven catalog (WS-7): a provider added to
        // config/ai-providers.php (or overridden via config('ai-providers.catalog'))
        // appears in the Account -> AI Keys UI without a code change. Fall back to
        // reading the shipped config file directly when no application container
        // is available (package unit tests, static analysis).
        try {
            if (function_exists('config')) {
                $catalog = config('ai-providers.catalog');

                if (is_array($catalog) && $catalog !== []) {
                    return $catalog;
                }
            }
        } catch (\Throwable $e) {
            // no application container available
        }

        $file = __DIR__.'/../../../config/ai-providers.php';

        if (is_file($file)) {
            $loaded = require $file;

            if (is_array($loaded) && isset($loaded['catalog']) && is_array($loaded['catalog']) && $loaded['catalog'] !== []) {
                return $loaded['catalog'];
            }
        }

        return [];
    }
}
