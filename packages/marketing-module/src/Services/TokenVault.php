<?php

namespace Nreljed\MarketingModule\Services;

use Illuminate\Support\Facades\Crypt;

/**
 * Encrypts and masks social platform credentials.
 *
 * Tokens are stored encrypted rather than behind an Eloquent `encrypted`
 * cast, because the admin account list needs to show a masked token without
 * decrypting it, and because the repository's own convention (the AI
 * provider keys) is a manual encrypt/decrypt pair with an explicit mask.
 */
class TokenVault
{
    /**
     * Encrypt a token for storage. Empty values stay empty rather than
     * becoming an encrypted empty string.
     */
    public function encrypt(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Crypt::encryptString($value);
    }

    /**
     * Decrypt a stored token. A token that cannot be decrypted (a rotated
     * APP_KEY, a row written by an older schema) returns null rather than
     * throwing, so one broken row does not take down an account list.
     */
    public function decrypt(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Mask a plaintext token for display.
     */
    public function mask(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $length = strlen($value);

        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', 8).substr($value, -4);
    }

    /**
     * Mask a stored token without exposing it.
     */
    public function maskStored(?string $encrypted): string
    {
        return $this->mask($this->decrypt($encrypted));
    }
}
