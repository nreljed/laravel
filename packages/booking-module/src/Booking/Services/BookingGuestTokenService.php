<?php

namespace Nreljed\BookingModule\Booking\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BookingGuestTokenService
{
    private const TABLE = 'booking_tokens';

    private const TOKEN_LENGTH = 64;

    private const EXPIRY_DAYS = 30;

    public static function getTable(): string
    {
        return self::TABLE;
    }

    /**
     * Generate a magic link token for a guest booking.
     *
     * @return string The raw token (to be included in the magic link URL).
     */
    public static function generateToken(int $bookingId, string $guestEmail): string
    {
        $rawToken = bin2hex(random_bytes(self::TOKEN_LENGTH / 2));
        $tokenHash = hash('sha256', $rawToken);
        $expiresAt = now()->addDays(self::EXPIRY_DAYS);

        DB::table(self::TABLE)->insert([
            'booking_id' => $bookingId,
            'token_hash' => $tokenHash,
            'guest_email' => $guestEmail,
            'expires_at' => $expiresAt,
            'created_at' => now(),
        ]);

        return $rawToken;
    }

    /**
     * Validate a magic link token for a specific booking.
     */
    public static function validateToken(int $bookingId, string $rawToken): bool
    {
        $tokenHash = hash('sha256', $rawToken);

        $row = DB::table(self::TABLE)
            ->select('id', 'expires_at')
            ->where('booking_id', $bookingId)
            ->where('token_hash', $tokenHash)
            ->first();

        if (! $row) {
            return false;
        }

        return Carbon::parse($row->expires_at)->isFuture();
    }

    /**
     * Get the guest email associated with a token (for matching).
     */
    public static function getGuestEmail(int $bookingId, string $rawToken): ?string
    {
        $tokenHash = hash('sha256', $rawToken);

        $row = DB::table(self::TABLE)
            ->where('booking_id', $bookingId)
            ->where('token_hash', $tokenHash)
            ->value('guest_email');

        return $row ?: null;
    }

    /**
     * Look up the booking ID from a raw token.
     *
     * @return int|null Booking ID or null if token not found/expired.
     */
    public static function getBookingId(string $rawToken): ?int
    {
        $tokenHash = hash('sha256', $rawToken);

        $row = DB::table(self::TABLE)
            ->select('booking_id', 'expires_at')
            ->where('token_hash', $tokenHash)
            ->first();

        if (! $row || Carbon::parse($row->expires_at)->isPast()) {
            return null;
        }

        return (int) $row->booking_id;
    }

    /**
     * Build the full magic link URL for a booking.
     */
    public static function getMagicLink(int $bookingId, string $rawToken): string
    {
        return route('booking.view', ['token' => $rawToken]);
    }

    /**
     * Revoke all tokens for a booking.
     */
    public static function revokeTokens(int $bookingId): void
    {
        DB::table(self::TABLE)->where('booking_id', $bookingId)->delete();
    }

    /**
     * Purge expired tokens.
     */
    public static function purgeExpired(): int
    {
        return DB::table(self::TABLE)->where('expires_at', '<', now())->delete();
    }

    /**
     * Clean up all tokens for a user by email.
     */
    public static function deleteByEmail(string $email): void
    {
        DB::table(self::TABLE)->where('guest_email', $email)->delete();
    }

    /**
     * Clean up all tokens for a booking.
     */
    public static function deleteByBooking(int $bookingId): void
    {
        DB::table(self::TABLE)->where('booking_id', $bookingId)->delete();
    }

    /**
     * Check if table exists.
     */
    public static function tableExists(): bool
    {
        return DB::getSchemaBuilder()->hasTable(self::TABLE);
    }

    /**
     * Drop the table (used during uninstall).
     */
    public static function dropTable(): void
    {
        DB::getSchemaBuilder()->dropIfExists(self::TABLE);
    }
}
