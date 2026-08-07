<?php

namespace Nreljed\DashboardCore\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Nreljed\DashboardCore\Models\Setting;

class EmailSettingsService
{
    private const string CACHE_KEY = 'email_settings';

    /**
     * Default email settings.
     * All empty — must be configured via the admin UI.
     */
    public static function defaults(): array
    {
        return [
            'mailer' => 'smtp',
            'smtp_host' => '',
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
            'smtp_auth' => true,
            'auto_tls' => true,
            'smtp_allow_self_signed' => false,
            'smtp_ehlo_domain' => '',
            'smtp_username' => '',
            'smtp_password' => '',
            'from_email' => '',
            'from_name' => '',
            'reply_to_email' => '',
            'return_path' => '',
            'use_from_as_return_path' => false,
            'force_from_email' => true,
            'force_from_name' => true,
        ];
    }

    /**
     * Get all email settings, merging stored values over defaults.
     * Caches the stored (encrypted) row for 5 minutes; decryption happens on
     * every read so the decrypted secret never touches the cache store.
     */
    public function getOptions(): array
    {
        $merged = Cache::remember(self::CACHE_KEY, 300, fn () => $this->readStoredOptions());

        if (! empty($merged['smtp_password']) && $this->isEncrypted($merged['smtp_password'])) {
            try {
                $merged['smtp_password'] = Crypt::decryptString($merged['smtp_password']);
            } catch (\Throwable) {
                $merged['smtp_password'] = '';
            }
        }

        return $merged;
    }

    /**
     * Read stored options straight from the database.
     *
     * @return array<string, mixed>
     */
    protected function readStoredOptions(): array
    {
        $settings = Setting::where('key', 'email_settings')->first();
        $stored = $settings ? (array) json_decode($settings->value, true) : [];

        return array_merge(self::defaults(), $stored);
    }

    /**
     * Get a single option by key.
     */
    public function getOption(string $key, mixed $default = null): mixed
    {
        $options = $this->getOptions();

        return $options[$key] ?? $default;
    }

    /**
     * Persist email settings: sanitize, encrypt password, merge, and store.
     */
    public function updateFromRequest(array $data): array
    {
        $current = $this->getOptions();
        $sanitized = $this->sanitize($data, $current);
        $merged = array_merge($current, $sanitized);

        if (! empty($merged['smtp_password']) && ! $this->isEncrypted($merged['smtp_password'])) {
            $merged['smtp_password'] = Crypt::encryptString($merged['smtp_password']);
        }

        Setting::updateOrCreate(
            ['key' => 'email_settings'],
            ['value' => json_encode($merged)]
        );

        Cache::forget(self::CACHE_KEY);

        return $this->getOptions();
    }

    /**
     * Expose settings to frontend with password masked.
     */
    public function forFrontend(): array
    {
        $options = $this->getOptions();
        $options['smtp_password'] = $options['smtp_password'] ? '********' : '';
        $options['has_encrypted_password'] = true;

        return $options;
    }

    /**
     * Sanitize input data against allowed values and ranges.
     */
    protected function sanitize(array $data, array $current): array
    {
        $sanitized = [];

        if (isset($data['mailer'])) {
            $sanitized['mailer'] = in_array($data['mailer'], ['smtp', 'sendmail', 'mail', 'log'])
                ? $data['mailer'] : 'smtp';
        }
        if (isset($data['smtp_host'])) {
            $sanitized['smtp_host'] = trim($data['smtp_host']);
        }
        if (isset($data['smtp_port'])) {
            $sanitized['smtp_port'] = max(1, min(65535, (int) $data['smtp_port']));
        }
        if (isset($data['smtp_encryption'])) {
            $sanitized['smtp_encryption'] = in_array($data['smtp_encryption'], ['tls', 'ssl', 'none'])
                ? $data['smtp_encryption'] : 'tls';
        }
        if (isset($data['smtp_auth'])) {
            $sanitized['smtp_auth'] = filter_var($data['smtp_auth'], FILTER_VALIDATE_BOOLEAN);
        }
        if (isset($data['auto_tls'])) {
            $sanitized['auto_tls'] = filter_var($data['auto_tls'], FILTER_VALIDATE_BOOLEAN);
        }
        if (isset($data['smtp_allow_self_signed'])) {
            $sanitized['smtp_allow_self_signed'] = filter_var($data['smtp_allow_self_signed'], FILTER_VALIDATE_BOOLEAN);
        }
        if (isset($data['smtp_ehlo_domain'])) {
            $sanitized['smtp_ehlo_domain'] = trim($data['smtp_ehlo_domain']);
        }
        if (isset($data['smtp_username'])) {
            $sanitized['smtp_username'] = trim($data['smtp_username']);
        }
        if (isset($data['smtp_password']) && $data['smtp_password'] !== '' && $data['smtp_password'] !== '********') {
            $sanitized['smtp_password'] = $data['smtp_password'];
        }
        if (isset($data['from_email'])) {
            $sanitized['from_email'] = trim($data['from_email']);
        }
        if (isset($data['from_name'])) {
            $sanitized['from_name'] = trim($data['from_name']);
        }
        if (isset($data['reply_to_email'])) {
            $sanitized['reply_to_email'] = trim($data['reply_to_email']);
        }
        if (isset($data['return_path'])) {
            $sanitized['return_path'] = trim($data['return_path']);
        }
        if (isset($data['use_from_as_return_path'])) {
            $sanitized['use_from_as_return_path'] = filter_var($data['use_from_as_return_path'], FILTER_VALIDATE_BOOLEAN);
        }
        if (isset($data['force_from_email'])) {
            $sanitized['force_from_email'] = filter_var($data['force_from_email'], FILTER_VALIDATE_BOOLEAN);
        }
        if (isset($data['force_from_name'])) {
            $sanitized['force_from_name'] = filter_var($data['force_from_name'], FILTER_VALIDATE_BOOLEAN);
        }

        return $sanitized;
    }

    /**
     * Check if a password value is already encrypted.
     */
    protected function isEncrypted(string $value): bool
    {
        if (! str_starts_with($value, 'eyJ')) {
            return false;
        }

        try {
            Crypt::decryptString($value);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
