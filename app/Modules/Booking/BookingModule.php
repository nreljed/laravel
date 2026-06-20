<?php

namespace App\Modules\Booking;

use App\Modules\Booking\Models\Booking;
use App\Modules\Booking\Services\BookingCacheService;
use App\Modules\Booking\Services\BookingEmailService;
use App\Modules\Booking\Services\BookingGuestTokenService;
use App\Modules\Booking\Services\BookingPipelineTrace;
use App\Modules\Booking\Services\BookingRefundMailService;
use App\Modules\Contracts\ModuleInterface;
use App\Modules\Traits\HasRoutes;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

class BookingModule implements ModuleInterface
{
    use HasRoutes;

    public function name(): string
    {
        return 'Bookings';
    }

    public function slug(): string
    {
        return 'bookings';
    }

    public function description(): ?string
    {
        return 'Manage guest bookings, availability, and reservations.';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function dependencies(): array
    {
        return ['properties'];
    }

    public function routes(): void
    {
        $this->loadRoutes(__DIR__ . '/Routes/web.php', '/dashboard/bookings', 'dashboard.bookings.', 'bookings.view');
        $this->loadRoutes(__DIR__ . '/Routes/my-bookings.php', '/dashboard', 'dashboard.', 'bookings.view');
        $this->loadPublicRoutes(__DIR__ . '/Routes/guest.php', '');
    }

    public function boot(): void
    {
        Event::listen('booking.created', fn (int $bookingId) => self::onBookingCreated($bookingId));
        Event::listen('booking.status-transition', fn (int $bookingId, string $oldStatus, string $newStatus) => self::onBookingStatusTransition($bookingId, $oldStatus, $newStatus));
        Event::listen('booking.deleted', fn (int $bookingId) => self::onBookingDeleted($bookingId));
        Event::listen('booking.saved', fn (int $bookingId) => self::onBookingSaved($bookingId));
    }

    public function register(): void
    {
        //
    }

    public function getNavItems(): array
    {
        return [
            'title' => 'Bookings',
            'url' => route('dashboard.bookings.index', [], false),
            'icon' => 'Calendar',
            'permission' => 'bookings.view',
            'module_slug' => 'bookings',
            'showModeBadge' => true,
            'items' => [
                ['title' => 'All Bookings', 'url' => route('dashboard.bookings.index', [], false)],
                ['title' => 'Add Booking', 'url' => route('dashboard.bookings.create', [], false)],
                ['title' => 'Calendar', 'url' => route('dashboard.bookings.calendar', [], false)],
            ],
        ];
    }

    public function getMetaFormattingConfig(): array
    {
        return [
            'price_fields' => ['total_amount'],
            'surface_fields' => [],
        ];
    }

    public function getDashboardData(): array
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('bookings')) {
            return [];
        }

        return [
            'label' => 'Bookings',
            'icon' => 'Calendar',
            'total' => Booking::count(),
            'pending' => Booking::where('status', 'pending')->count(),
            'confirmed' => Booking::where('status', 'confirmed')->count(),
        ];
    }

    public function getSharedData(): array
    {
        return [];
    }

    public function getSettingsRoute(): ?string
    {
        return route('dashboard.admin.settings', [], false);
    }

    public function getFrontendPages(): array
    {
        return [
            ['path' => '/dashboard/bookings', 'component' => 'Bookings/Index', 'name' => 'dashboard.bookings.index'],
            ['path' => '/dashboard/bookings/create', 'component' => 'Bookings/Form', 'name' => 'dashboard.bookings.create'],
            ['path' => '/dashboard/bookings/{booking}/edit', 'component' => 'Bookings/Form', 'name' => 'dashboard.bookings.edit'],
            ['path' => '/dashboard/bookings/{booking}', 'component' => 'Bookings/Show', 'name' => 'dashboard.bookings.show'],
            ['path' => '/dashboard/bookings/calendar', 'component' => 'Bookings/Calendar', 'name' => 'dashboard.bookings.calendar'],
        ];
    }

    public function migrationPaths(): array
    {
        return [__DIR__ . '/database/migrations'];
    }

    public function permissions(): array
    {
        return [
            ['slug' => 'bookings.view', 'name' => 'View Bookings', 'description' => 'View booking records.'],
            ['slug' => 'bookings.create', 'name' => 'Create Bookings', 'description' => 'Create new bookings.'],
            ['slug' => 'bookings.edit', 'name' => 'Edit Bookings', 'description' => 'Edit existing bookings.'],
            ['slug' => 'bookings.delete', 'name' => 'Delete Bookings', 'description' => 'Delete booking records.'],
        ];
    }

    public function hasSettings(): bool
    {
        return true;
    }

    public function getRepeaterConfig(): array
    {
        return [
            'house_rules' => [
                'label' => 'House Rules',
                'json_key' => 'house_rules_json',
                'enable_key' => 'enable_house_rules',
                'min_items' => 0,
                'max_items' => 20,
                'fields' => [
                    'rule_title' => ['type' => 'text', 'label' => 'Rule Title', 'required' => true],
                    'rule_description' => ['type' => 'textarea', 'label' => 'Description'],
                    'rule_type' => ['type' => 'select', 'label' => 'Type', 'options' => [
                        ['label' => 'Noise', 'value' => 'noise'],
                        ['label' => 'Smoking', 'value' => 'smoking'],
                        ['label' => 'Parties', 'value' => 'parties'],
                        ['label' => 'Visitors', 'value' => 'visitors'],
                        ['label' => 'Cleaning', 'value' => 'cleaning'],
                        ['label' => 'Parking', 'value' => 'parking'],
                        ['label' => 'Damage', 'value' => 'damage'],
                        ['label' => 'Other', 'value' => 'other'],
                    ]],
                    'rule_enforced' => ['type' => 'boolean', 'label' => 'Enforced'],
                ],
            ],
            'seasonal_rates' => [
                'label' => 'Seasonal Rates',
                'json_key' => 'seasonal_rates_json',
                'enable_key' => 'enable_seasonal_rates',
                'min_items' => 0,
                'max_items' => 50,
                'fields' => [
                    'label' => ['type' => 'text', 'label' => 'Label', 'required' => true],
                    'start_date' => ['type' => 'date', 'label' => 'Start Date'],
                    'end_date' => ['type' => 'date', 'label' => 'End Date'],
                    'nightly_rate' => ['type' => 'number', 'label' => 'Nightly Rate'],
                ],
            ],
        ];
    }

    public function getGalleryConfig(): array
    {
        return [
            'damage_photos' => [
                'label' => 'Damage Photos',
                'json_key' => 'booking_damage_photos',
                'thumbnail_key' => 'booking_damage_thumbnail',
                'max_images' => 20,
                'upload_action' => 'dashboard.bookings.upload-damage',
                'delete_action' => 'dashboard.bookings.delete-damage',
                'upload_folder' => 'bookings/damage',
                'hide_in_media_library' => true,
                'create_attachment' => true,
            ],
        ];
    }

    public function getProfileFieldsConfig(): array
    {
        return [];
    }

    public function getEmailTemplates(): array
    {
        return [
            'booking-confirmation' => [
                'label' => 'Booking Confirmation',
                'sample_data' => ['guestName' => 'John Doe', 'propertyTitle' => 'Beach Villa', 'bookingId' => 'BK-1001', 'checkIn' => '2026-07-01', 'checkOut' => '2026-07-05', 'guests' => '2', 'bookingUrl' => 'https://example.com/booking/1001'],
                'group' => 'Booking',
            ],
            'booking-cancellation' => [
                'label' => 'Booking Cancellation',
                'sample_data' => ['guestName' => 'John Doe', 'propertyTitle' => 'Beach Villa', 'bookingId' => 'BK-1001', 'checkIn' => '2026-07-01', 'checkOut' => '2026-07-05', 'refundInfo' => 'Full refund issued — $1,200'],
                'group' => 'Booking',
            ],
            'booking-status-change' => [
                'label' => 'Booking Status Change',
                'sample_data' => ['guestName' => 'John Doe', 'propertyTitle' => 'Beach Villa', 'bookingId' => 'BK-1001', 'oldStatus' => 'pending', 'newStatus' => 'confirmed', 'checkIn' => '2026-07-01', 'checkOut' => '2026-07-05', 'bookingUrl' => 'https://example.com/booking/1001'],
                'group' => 'Booking',
            ],
            'booking-new-notification' => [
                'label' => 'New Booking Notification (Admin)',
                'sample_data' => ['guestName' => 'John Doe', 'guestEmail' => 'john@example.com', 'propertyTitle' => 'Beach Villa', 'bookingId' => 'BK-1001', 'bookingUrl' => 'https://example.com/booking/1001'],
                'group' => 'Booking',
            ],
            'booking-pre-arrival' => [
                'label' => 'Pre-Arrival Instructions',
                'sample_data' => ['guestName' => 'John Doe', 'propertyTitle' => 'Beach Villa', 'bookingId' => 'BK-1001', 'checkIn' => '2026-07-01', 'checkInTime' => '15:00', 'checkOut' => '2026-07-05', 'checkOutTime' => '11:00', 'guests' => '2', 'address' => '123 Beach Rd', 'bookingUrl' => 'https://example.com/booking/1001'],
                'group' => 'Booking',
            ],
            'booking-post-stay' => [
                'label' => 'Post-Stay Follow-up',
                'sample_data' => ['guestName' => 'John Doe', 'propertyTitle' => 'Beach Villa', 'bookingId' => 'BK-1001', 'checkIn' => '2026-07-01', 'checkOut' => '2026-07-05', 'bookingUrl' => 'https://example.com/review/1001'],
                'group' => 'Booking',
            ],
            'booking-cancel-request' => [
                'label' => 'Cancel Request (Guest Confirmation)',
                'sample_data' => ['guestName' => 'John Doe', 'propertyTitle' => 'Beach Villa', 'bookingId' => 'BK-1001', 'checkIn' => '2026-07-01', 'checkOut' => '2026-07-05', 'cancelUrl' => 'https://example.com/cancel/abc123', 'expiryMinutes' => '30'],
                'group' => 'Booking',
            ],
            'booking-checkin-instructions' => [
                'label' => 'Check-In Instructions',
                'sample_data' => ['guestName' => 'John Doe', 'propertyTitle' => 'Beach Villa', 'bookingId' => 'BK-1001', 'checkInTime' => '15:00', 'checkOutTime' => '11:00', 'checkOut' => '2026-07-05', 'checkInInstructions' => 'Please use the keypad at the main entrance. Code: 1234.', 'bookingUrl' => 'https://example.com/booking/1001'],
                'group' => 'Booking',
            ],
            'booking-guest-received' => [
                'label' => 'Booking Received (Guest)',
                'sample_data' => ['guestName' => 'John Doe', 'propertyTitle' => 'Beach Villa', 'bookingId' => 'BK-1001', 'checkIn' => '2026-07-01', 'checkInTime' => '15:00', 'checkOut' => '2026-07-05', 'checkOutTime' => '11:00', 'guests' => '3', 'magicLink' => 'https://example.com/manage/abc123', 'expiresInDays' => '30'],
                'group' => 'Booking',
            ],
            'booking-checkout-reminder' => [
                'label' => 'Check-Out Reminder',
                'sample_data' => ['guestName' => 'John Doe', 'propertyTitle' => 'Beach Villa', 'bookingId' => 'BK-1001', 'checkOut' => '2026-07-05', 'checkOutTime' => '11:00', 'checkOutInstructions' => 'Please leave keys on the kitchen counter.', 'bookingUrl' => 'https://example.com/booking/1001'],
                'group' => 'Booking',
            ],
            'booking-refund' => [
                'label' => 'Refund Summary',
                'sample_data' => ['guestName' => 'John Doe', 'propertyTitle' => 'Beach Villa', 'bookingId' => 'BK-1001', 'checkIn' => '2026-07-01', 'checkOut' => '2026-07-05', 'policyApplied' => 'Flexible (48h)', 'refundPercent' => '100', 'refundAmount' => '$1,200'],
                'group' => 'Booking',
            ],
        ];
    }

    public function getNotificationTypes(): array
    {
        return [
            'booking.created' => [
                'label' => 'New Booking',
                'description' => 'When a guest makes a new booking.',
                'default_channels' => ['in_app', 'email'],
                'default_enabled' => true,
                'email_template' => 'booking-new-notification',
                'group' => 'Booking',
            ],
            'booking.cancelled' => [
                'label' => 'Booking Cancelled',
                'description' => 'When a booking is cancelled.',
                'default_channels' => ['in_app', 'email'],
                'default_enabled' => true,
                'email_template' => 'booking-cancellation',
                'group' => 'Booking',
            ],
            'booking.confirmed' => [
                'label' => 'Booking Confirmed',
                'description' => 'When a booking is confirmed.',
                'default_channels' => ['in_app', 'email'],
                'default_enabled' => true,
                'email_template' => 'booking-confirmation',
                'group' => 'Booking',
            ],
            'booking.modified' => [
                'label' => 'Booking Modified',
                'description' => 'When a booking is modified.',
                'default_channels' => ['in_app', 'email'],
                'default_enabled' => true,
                'email_template' => 'booking-status-change',
                'group' => 'Booking',
            ],
        ];
    }

    public static function onBookingCreated(int $bookingId): void
    {
        try {
            $booking = Booking::find($bookingId);
            if (!$booking || !$booking->guest_email) {
                return;
            }

            BookingPipelineTrace::add($bookingId, 'created.handler_entered', 'BookingModule::onBookingCreated entered', [
                'guest_email' => $booking->guest_email,
            ]);

            $rawToken = BookingGuestTokenService::generateToken($bookingId, $booking->guest_email);
            $magicLink = BookingGuestTokenService::getMagicLink($bookingId, $rawToken);

            BookingPipelineTrace::add($bookingId, 'created.token_generated', 'Magic link token generated');

            BookingEmailService::sendConfirmation($bookingId, $magicLink);

            BookingPipelineTrace::add($bookingId, 'created.confirmation_sent', 'Guest confirmation email sent');
        } catch (\Throwable $e) {
            BookingPipelineTrace::add($bookingId, 'created.error', $e->getMessage());
            Log::error("BookingModule::onBookingCreated failed: {$e->getMessage()}", [
                'booking_id' => $bookingId,
                'exception' => $e,
            ]);
        }
    }

    public static function onBookingStatusTransition(int $bookingId, string $oldStatus, string $newStatus): void
    {
        try {
            $booking = Booking::find($bookingId);
            if (!$booking) {
                return;
            }

            BookingPipelineTrace::add($bookingId, 'transition.handler_entered', "Status transition: {$oldStatus} -> {$newStatus}");

            if ($newStatus === 'cancelled') {
                BookingEmailService::sendCancellation($bookingId);
                BookingPipelineTrace::add($bookingId, 'transition.cancellation_sent', 'Cancellation email sent to guest');
            }

            if ($newStatus === 'checked_out') {
                $refund = $booking->metadata['refund'] ?? null;
                if ($refund) {
                    BookingRefundMailService::sendRefundNotification($bookingId, $refund);
                    BookingPipelineTrace::add($bookingId, 'transition.refund_notified', 'Refund notification sent');
                }
            }

            BookingEmailService::handleStatusTransition($bookingId, $oldStatus, $newStatus);
        } catch (\Throwable $e) {
            BookingPipelineTrace::add($bookingId, 'transition.error', $e->getMessage());
            Log::error("BookingModule::onBookingStatusTransition failed: {$e->getMessage()}", [
                'booking_id' => $bookingId,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'exception' => $e,
            ]);
        }
    }

    public static function onBookingDeleted(int $bookingId): void
    {
        try {
            $booking = Booking::withTrashed()->find($bookingId);
            if (!$booking) {
                return;
            }

            BookingPipelineTrace::add($bookingId, 'deleted.handler_entered', 'BookingModule::onBookingDeleted entered', [
                'property_id' => $booking->property_id,
            ]);

            BookingCacheService::bustPropertyCache($booking->property_id);

            BookingGuestTokenService::revokeTokens($bookingId);

            BookingPipelineTrace::add($bookingId, 'deleted.completed', 'Cache busted and tokens revoked');
            BookingPipelineTrace::clear($bookingId);
        } catch (\Throwable $e) {
            Log::error("BookingModule::onBookingDeleted failed: {$e->getMessage()}", [
                'booking_id' => $bookingId,
                'exception' => $e,
            ]);
        }
    }

    public static function onBookingSaved(int $bookingId): void
    {
        try {
            $booking = Booking::find($bookingId);
            if (!$booking) {
                return;
            }

            BookingCacheService::bustPropertyCache($booking->property_id);
        } catch (\Throwable $e) {
            Log::error("BookingModule::onBookingSaved failed: {$e->getMessage()}", [
                'booking_id' => $bookingId,
                'exception' => $e,
            ]);
        }
    }

    public function onUserDelete(int $userId): void
    {
        Booking::where('user_id', $userId)->each(function (Booking $booking) {
            $booking->delete();
        });
    }
}
