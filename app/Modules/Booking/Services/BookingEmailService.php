<?php

namespace App\Modules\Booking\Services;

use App\Modules\Booking\Enums\BookingStatus;
use App\Modules\Booking\Models\Booking;
use App\Modules\Properties\Models\Property;
use App\Services\MailService;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Log;

class BookingEmailService
{
    protected static function getMailService(): MailService
    {
        return app(MailService::class);
    }

    protected static function getBookingData(int $bookingId, ?string $magicLink = null): array
    {
        $booking = Booking::findOrFail($bookingId);
        $propertyId = $booking->property_id;

        if ($magicLink === null && $booking->guest_email) {
            $rawToken = BookingGuestTokenService::generateToken($bookingId, $booking->guest_email);
            $magicLink = BookingGuestTokenService::getMagicLink($bookingId, $rawToken);
        }

        $tz = new \DateTimeZone(config('app.timezone'));
        $offset = $tz->getOffset(new \DateTime());
        $sign = $offset >= 0 ? '+' : '-';
        $hours = sprintf('%02d', (int) (abs($offset) / 3600));
        $mins = sprintf('%02d', (int) ((abs($offset) % 3600) / 60));

        $property = $propertyId ? Property::find($propertyId) : null;

        return [
            'bookingId' => $bookingId,
            'siteTimezone' => $tz->getName(),
            'siteTimezoneLabel' => $tz->getName() . " (UTC{$sign}{$hours}:{$mins})",
            'guestName' => $booking->guest_name,
            'guestEmail' => $booking->guest_email,
            'propertyTitle' => $property?->name ?? 'Property',
            'checkIn' => $booking->start_date?->toDateString(),
            'checkOut' => $booking->end_date?->toDateString(),
            'checkInTime' => $booking->check_in?->format('H:i') ?: '15:00',
            'checkOutTime' => $booking->check_out?->format('H:i') ?: '11:00',
            'guests' => $booking->guest_count,
            'total' => $booking->total_amount,
            'specialRequests' => $booking->notes,
            'bookingUrl' => $magicLink ?: url('/dashboard/booking/view/' . $bookingId),
            'siteName' => config('app.name'),
            'adminEmail' => config('mail.from.address'),
        ];
    }

    public static function sendConfirmation(int $bookingId, ?string $magicLink = null): void
    {
        $data = self::getBookingData($bookingId, $magicLink);
        if (!$data['guestEmail']) {
            Log::debug('[BookingEmailService] Confirmation skipped - no guest email', ['booking_id' => $bookingId]);
            return;
        }

        $mailService = self::getMailService();
        $sent = $mailService->send(
            $data['guestEmail'],
            sprintf('[%s] Booking Confirmed #%d — %s', $data['siteName'], $bookingId, $data['propertyTitle']),
            'booking-confirmation',
            $data
        );

        Log::debug('[BookingEmailService] Confirmation email processed', [
            'booking_id' => $bookingId,
            'to' => $data['guestEmail'],
            'success' => $sent,
            'template' => 'booking-confirmation',
        ]);

        Booking::where('id', $bookingId)->update(['email_confirmation_sent_at' => now()]);
    }

    public static function sendBookingReceived(int $bookingId, ?string $magicLink = null): void
    {
        $data = self::getBookingData($bookingId, $magicLink);
        if (!$data['guestEmail']) {
            Log::debug('[BookingEmailService] Booking received skipped - no guest email', ['booking_id' => $bookingId]);
            return;
        }

        $mailService = self::getMailService();
        $sent = $mailService->send(
            $data['guestEmail'],
            sprintf('[%s] Booking Received #%d — %s', $data['siteName'], $bookingId, $data['propertyTitle']),
            'booking-guest-received',
            $data
        );

        Log::debug('[BookingEmailService] Booking received email processed', [
            'booking_id' => $bookingId,
            'to' => $data['guestEmail'],
            'success' => $sent,
            'template' => 'booking-guest-received',
        ]);
    }

    public static function sendPreArrival(int $bookingId): void
    {
        $settings = SettingsService::getGroup('booking');
        if (empty($settings['email_enable_pre_arrival'])) {
            return;
        }

        $data = self::getBookingData($bookingId);
        if (!$data['guestEmail']) {
            return;
        }

        $mailService = self::getMailService();
        $mailService->send(
            $data['guestEmail'],
            sprintf('[%s] You\'re arriving soon — %s', $data['siteName'], $data['propertyTitle']),
            'booking-pre-arrival',
            $data
        );

        Booking::where('id', $bookingId)->update(['email_pre_arrival_sent_at' => now()]);
    }

    public static function sendCheckinInstructions(int $bookingId): void
    {
        $data = self::getBookingData($bookingId);
        if (!$data['guestEmail']) {
            return;
        }

        $mailService = self::getMailService();
        $mailService->send(
            $data['guestEmail'],
            sprintf('[%s] Welcome — check-in instructions for %s', $data['siteName'], $data['propertyTitle']),
            'booking-checkin-instructions',
            $data
        );

        Booking::where('id', $bookingId)->update(['email_checkin_instructions_sent_at' => now()]);
    }

    public static function sendCheckoutReminder(int $bookingId): void
    {
        $settings = SettingsService::getGroup('booking');
        if (empty($settings['email_enable_checkout_reminder'])) {
            return;
        }

        $data = self::getBookingData($bookingId);
        if (!$data['guestEmail']) {
            return;
        }

        $mailService = self::getMailService();
        $mailService->send(
            $data['guestEmail'],
            sprintf('[%s] Check-out tomorrow — %s', $data['siteName'], $data['propertyTitle']),
            'booking-checkout-reminder',
            $data
        );

        Booking::where('id', $bookingId)->update(['email_checkout_reminder_sent_at' => now()]);
    }

    public static function sendPostStay(int $bookingId): void
    {
        $settings = SettingsService::getGroup('booking');
        if (empty($settings['email_enable_post_stay'])) {
            return;
        }

        $data = self::getBookingData($bookingId);
        if (!$data['guestEmail']) {
            return;
        }

        $mailService = self::getMailService();
        $mailService->send(
            $data['guestEmail'],
            sprintf('[%s] How was your stay at %s?', $data['siteName'], $data['propertyTitle']),
            'booking-post-stay',
            $data
        );

        Booking::where('id', $bookingId)->update(['email_post_stay_sent_at' => now()]);
    }

    public static function sendCancellation(int $bookingId): void
    {
        $data = self::getBookingData($bookingId);
        if (!$data['guestEmail']) {
            Log::debug('[BookingEmailService] Cancellation skipped - no guest email', ['booking_id' => $bookingId]);
            return;
        }

        $booking = Booking::find($bookingId);
        $refundAmount = $booking?->metadata['refund_amount'] ?? null;
        $refundPolicy = $booking?->metadata['refund_policy_applied'] ?? null;

        if ($refundAmount && $refundPolicy) {
            $data['refundInfo'] = sprintf(
                '$%s refunded (%s policy)',
                number_format((float) $refundAmount, 2),
                str_replace('_', ' ', $refundPolicy)
            );
        }

        $mailService = self::getMailService();
        $sent = $mailService->send(
            $data['guestEmail'],
            sprintf('[%s] Booking Cancelled #%d — %s', $data['siteName'], $bookingId, $data['propertyTitle']),
            'booking-cancellation',
            $data
        );

        Log::debug('[BookingEmailService] Cancellation email processed', [
            'booking_id' => $bookingId,
            'to' => $data['guestEmail'],
            'success' => $sent,
            'template' => 'booking-cancellation',
        ]);

        Booking::where('id', $bookingId)->update(['email_cancellation_sent_at' => now()]);
    }

    public static function processScheduledEmails(): array
    {
        $sent = [
            'pre_arrival' => 0,
            'checkin_instructions' => 0,
            'checkout_reminder' => 0,
            'post_stay' => 0,
        ];

        $settings = SettingsService::getGroup('booking');

        if (!empty($settings['email_enable_pre_arrival'])) {
            $preArrivalDays = (int) ($settings['email_pre_arrival_days'] ?? 3);
            $targetDate = now()->addDays($preArrivalDays)->toDateString();

            $candidates = Booking::where('status', BookingStatus::Confirmed->value)
                ->where('start_date', $targetDate)
                ->whereNull('email_pre_arrival_sent_at')
                ->limit(100)
                ->pluck('id');

            foreach ($candidates as $bookingId) {
                self::sendPreArrival($bookingId);
                $sent['pre_arrival']++;
            }
        }

        $today = now()->toDateString();

        $candidates = Booking::where('status', BookingStatus::Confirmed->value)
            ->where('start_date', $today)
            ->whereNull('email_checkin_instructions_sent_at')
            ->limit(100)
            ->pluck('id');

        foreach ($candidates as $bookingId) {
            self::sendCheckinInstructions($bookingId);
            $sent['checkin_instructions']++;
        }

        if (!empty($settings['email_enable_checkout_reminder'])) {
            $tomorrow = now()->addDay()->toDateString();

            $candidates = Booking::where('status', BookingStatus::CheckedIn->value)
                ->where('end_date', $tomorrow)
                ->whereNull('email_checkout_reminder_sent_at')
                ->limit(100)
                ->pluck('id');

            foreach ($candidates as $bookingId) {
                self::sendCheckoutReminder($bookingId);
                $sent['checkout_reminder']++;
            }
        }

        if (!empty($settings['email_enable_post_stay'])) {
            $postStayDays = (int) ($settings['email_post_stay_delay_days'] ?? 1);
            $targetDate = now()->subDays($postStayDays)->toDateString();

            $candidates = Booking::where('status', BookingStatus::CheckedOut->value)
                ->where('end_date', $targetDate)
                ->whereNull('email_post_stay_sent_at')
                ->limit(100)
                ->pluck('id');

            foreach ($candidates as $bookingId) {
                self::sendPostStay($bookingId);
                $sent['post_stay']++;
            }
        }

        SettingsService::set('booking_email_cron_last_run', now()->toDateTimeString());

        return $sent;
    }

    public static function handleStatusTransition(int $bookingId, string $oldStatus, string $newStatus): void
    {
        Log::debug('[BookingEmailService] handleStatusTransition entered', [
            'booking_id' => $bookingId,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
        ]);

        if ($newStatus === BookingStatus::Confirmed->value && $oldStatus !== BookingStatus::Confirmed->value) {
            self::sendConfirmation($bookingId);
        }

        if ($newStatus === BookingStatus::CheckedIn->value && $oldStatus !== BookingStatus::CheckedIn->value) {
            self::sendCheckinInstructions($bookingId);
        }

        if ($newStatus === BookingStatus::Cancelled->value && $oldStatus !== BookingStatus::Cancelled->value) {
            self::sendCancellation($bookingId);
        }
    }

    /** @deprecated Use handleStatusTransition instead */
    public static function sendStatusChangeEmail(int $bookingId, string $oldStatus, string $newStatus): void
    {
        self::handleStatusTransition($bookingId, $oldStatus, $newStatus);
    }

    /** @deprecated Use sendConfirmation instead */
    public static function sendNewBookingNotification(int $bookingId): void
    {
        self::sendConfirmation($bookingId);
    }
}
