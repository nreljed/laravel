<?php

namespace App\Modules\Booking\Controllers;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Modules\Booking\Enums\BookingStatus;
use App\Modules\Booking\Models\Booking;
use App\Modules\Booking\Services\BookingAvailabilityService;
use App\Modules\Booking\Services\BookingCacheService;
use App\Modules\Booking\Services\BookingGuestTokenService;
use App\Modules\Booking\Services\BookingPipelineTrace;
use App\Modules\Booking\Services\BookingPriceCalculator;
use App\Modules\Properties\Models\Property;
use App\Services\AuditService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BookingDemoController extends Controller
{
    public function __construct(
        protected BookingAvailabilityService $availabilityService,
        protected BookingCacheService $cacheService,
        protected BookingGuestTokenService $guestTokenService,
        protected BookingPriceCalculator $priceCalculator,
    ) {}

    public function demo(): mixed
    {
        $userId = auth()->id();

        $properties = Property::where('user_id', $userId)
            ->whereIn('status', ['published', 'draft', 'pending'])
            ->orderBy('name', 'asc')
            ->get();

        $bookableProperties = [];
        foreach ($properties as $property) {
            $metadata = $property->metadata ?? [];
            $bookable = ($metadata['booking_enabled'] ?? false) === true;
            $settings = $this->getPropertyBookingData($property);

            $thumbnailUrl = $property->featuredImage?->url;
            if (! $thumbnailUrl) {
                $images = $property->images ?? [];
                if (! empty($images)) {
                    $first = is_array($images) ? $images[0] : $images->first();
                    $thumbnailUrl = $first['url'] ?? $first->url ?? null;
                }
            }

            $city = $property->city ?? '';

            $availability = null;
            if ($bookable) {
                $this->cacheService->bustPropertyCache($property->id);
                $availability = [];
                $today = new \DateTime;
                for ($i = 0; $i < 4; $i++) {
                    $month = $today->format('Y-m');
                    $dates = $this->availabilityService->getMonthData($property->id, (int) $today->format('Y'), (int) $today->format('m'));
                    if (! empty($dates['days'])) {
                        foreach ($dates['days'] as $dateStr => $info) {
                            $availability[$dateStr] = $info;
                        }
                    }
                    $today->modify('+1 month');
                }
            }

            $bookingsList = [];
            if ($bookable) {
                $bookings = Booking::where('property_id', $property->id)
                    ->whereIn('status', BookingStatus::values())
                    ->limit(50)
                    ->get(['id', 'status', 'start_date', 'end_date', 'guest_count', 'guest_name', 'created_at']);

                foreach ($bookings as $b) {
                    $bookingsList[] = [
                        'id' => $b->id,
                        'status' => $b->status,
                        'check_in' => $b->start_date,
                        'check_out' => $b->end_date,
                        'guests' => $b->guest_count,
                        'guest_name' => $b->guest_name,
                        'created_at' => $b->created_at->toDateTimeString(),
                    ];
                }
            }

            $bookableProperties[] = [
                'id' => $property->id,
                'title' => $property->name,
                'price' => $property->price,
                'rental_period' => $metadata['rental_period'] ?? '',
                'thumbnail_url' => $thumbnailUrl ?: null,
                'city' => $city,
                'status_label' => $property->status,
                'post_status' => $property->status,
                'bookable' => $bookable,
                'booking' => $bookable ? $settings : null,
                'availability' => $availability,
                'bookings' => $bookingsList,
            ];
        }

        return Inertia::render(
            'Modules/Bookings/BookingDemo',
            [
                'properties' => $bookableProperties,
                'page' => ['title' => 'Booking Demo'],
            ]
        );
    }

    public function cleanupAll(): JsonResponse
    {
        if (! auth()->user()->can('manage_options')) {
            return response()->json(['error' => 'Admin access required'], 403);
        }

        $count = 0;
        $batchSize = 100;
        $propertyIds = [];

        Booking::chunk($batchSize, function ($bookings) use (&$count, &$propertyIds) {
            foreach ($bookings as $booking) {
                $propertyIds[$booking->property_id] = true;
                $booking->delete();
                $count++;
            }
        });

        foreach (array_keys($propertyIds) as $pid) {
            $this->cacheService->bustPropertyCache($pid);
        }

        return response()->json([
            'success' => true,
            'deleted' => $count,
            'message' => "Deleted {$count} booking(s)",
        ]);
    }

    public function seed(): mixed
    {
        $property = Property::where('status', 'published')
            ->where('metadata->booking_enabled', '1')
            ->first();

        if (! $property) {
            return redirect()->route('bookings.demo')->withErrors(['seed' => 'No bookable property found. Enable booking on a property first.']);
        }

        if ($property->user_id !== auth()->id()) {
            return redirect()->route('bookings.demo')->withErrors(['auth' => 'Unauthorized']);
        }

        $today = now()->addDay();
        $checkIn = $today->toDateString();
        $checkOut = $today->addDays(3)->toDateString();

        $price = $this->priceCalculator->calculateTotal($property, $checkIn, $checkOut, 2);

        $userId = auth()->id();

        $booking = Booking::create([
            'property_id' => $property->id,
            'user_id' => $userId,
            'start_date' => $checkIn,
            'end_date' => $checkOut,
            'status' => BookingStatus::Pending->value,
            'guest_name' => 'Test Guest',
            'guest_email' => 'test@example.com',
            'guest_phone' => '555-0100',
            'adults' => 2,
            'pets' => 0,
            'nightly_rate' => $price['nightly_rate'],
            'cleaning_fee' => $price['cleaning_fee'],
            'service_fee' => $price['service_fee'],
            'total_amount' => $price['total_amount'],
            'check_in' => $checkIn.' 15:00:00',
            'check_out' => $checkOut.' 11:00:00',
            'guest_count' => 2,
            'metadata' => [
                'cancellation_policy' => 'flexible',
                'damage_deposit_charged' => $price['damage_deposit'] ?? 0,
                'price_breakdown' => json_encode($price),
            ],
        ]);

        $this->guestTokenService->generateToken($booking->id, 'test@example.com');

        AuditService::log(AuditAction::BookingCreate, auth()->id(), [
            'booking_id' => $booking->id,
            'property_id' => $property->id,
            'guest_email' => 'test@example.com',
            'source' => 'demo_seed',
        ]);

        return redirect()->to('/dashboard/manage-bookings/calendar?month='.now()->addDay()->format('Y-m'))->with(
            'status',
            "Seeded booking #{$booking->id}: {$checkIn} \u{2192} {$checkOut} at {$property->name}"
        );
    }

    public function diagnose(Request $request): JsonResponse
    {
        if (! auth()->user()->can('manage_options')) {
            return response()->json(['error' => 'Admin only'], 403);
        }

        $month = $request->input('month', now()->format('Y-m'));
        $startDate = Carbon::parse($month)->startOfMonth()->toDateString();
        $endDate = Carbon::parse($month)->endOfMonth()->toDateString();
        $bookingId = (int) $request->input('id', 0);

        $booking = $bookingId ? Booking::find($bookingId) : null;

        $calendarQuery = Booking::whereIn('status', BookingStatus::values())
            ->where('start_date', '<=', $endDate)
            ->where('end_date', '>=', $startDate)
            ->orderBy('id', 'desc')
            ->limit(500)
            ->get(['id', 'status', 'start_date', 'end_date']);

        $allQuery = Booking::whereIn('status', BookingStatus::values())
            ->orderBy('id', 'desc')
            ->limit(500)
            ->get(['id', 'status']);

        $simpleQuery = Booking::whereIn('status', BookingStatus::values())
            ->where('start_date', '<=', $endDate)
            ->where('end_date', '>=', $startDate)
            ->orderBy('id', 'desc')
            ->limit(500)
            ->get(['id', 'status', 'start_date', 'end_date']);

        return response()->json([
            'month' => $month,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'checked_booking' => $booking ? [
                'id' => $booking->id,
                'status' => $booking->status,
                'start_date' => $booking->start_date,
                'end_date' => $booking->end_date,
                'metadata' => $booking->metadata,
            ] : null,
            'calendar_query_found' => $calendarQuery->count(),
            'calendar_query_ids' => $calendarQuery->pluck('id')->toArray(),
            'simple_query_found' => $simpleQuery->count(),
            'simple_query_ids' => $simpleQuery->pluck('id')->toArray(),
            'all_query_found' => $allQuery->count(),
            'all_query_ids' => $allQuery->pluck('id')->toArray(),
            'all_query_first_status' => $allQuery->first()?->status,
        ], JSON_PRETTY_PRINT);
    }

    public function generateMagicLink(int $id): JsonResponse
    {
        $booking = Booking::find($id);
        if (! $booking) {
            return response()->json(['error' => 'Booking not found'], 404);
        }

        $guestEmail = $booking->guest_email;
        if (! $guestEmail) {
            return response()->json(['error' => 'No guest email on booking'], 400);
        }

        $this->guestTokenService->revokeTokens($id);
        $rawToken = $this->guestTokenService->generateToken($id, $guestEmail);
        $magicLink = $this->guestTokenService->getMagicLink($id, $rawToken);

        return response()->json([
            'success' => true,
            'magic_link' => $magicLink,
            'raw_token' => $rawToken,
            'booking_id' => $id,
            'guest_email' => $guestEmail,
            'expires_in_days' => 30,
            'token_table_exists' => true,
        ]);
    }

    public function tokenStatus(int $id): JsonResponse
    {
        $booking = Booking::find($id);
        if (! $booking) {
            return response()->json(['error' => 'Booking not found'], 404);
        }

        $tokens = $this->guestTokenService->getTokens($id);

        return response()->json([
            'success' => true,
            'booking_id' => $id,
            'table_exists' => true,
            'total_tokens' => count($tokens),
            'tokens' => $tokens,
        ]);
    }

    public function emailStatus(int $id): JsonResponse
    {
        $booking = Booking::find($id);
        if (! $booking) {
            return response()->json(['error' => 'Booking not found'], 404);
        }

        $emailFields = [
            'email_confirmation_sent' => 'Confirmation',
            'email_pre_arrival_sent' => 'Pre-arrival',
            'email_checkin_instructions_sent' => 'Check-in Instructions',
            'email_checkout_reminder_sent' => 'Check-out Reminder',
            'email_post_stay_sent' => 'Post-stay',
            'email_cancellation_sent' => 'Cancellation',
        ];

        $metadata = $booking->metadata ?? [];
        $emails = [];
        foreach ($emailFields as $key => $label) {
            $sentValue = $metadata[$key] ?? null;
            $emails[] = [
                'key' => $key,
                'label' => $label,
                'sent' => ! empty($sentValue),
                'sent_at' => $sentValue ?: null,
            ];
        }

        $trace = BookingPipelineTrace::get($id);

        return response()->json([
            'success' => true,
            'booking_id' => $id,
            'emails' => $emails,
            'trace' => $trace,
        ]);
    }

    public function pipelineTrace(int $id): JsonResponse
    {
        $booking = Booking::find($id);
        if (! $booking) {
            return response()->json(['error' => 'Booking not found'], 404);
        }

        $trace = BookingPipelineTrace::get($id);

        return response()->json([
            'success' => true,
            'booking_id' => $id,
            'trace' => $trace,
        ]);
    }

    public function verifyIntegration(int $id): JsonResponse
    {
        $booking = Booking::with('property')->find($id);
        if (! $booking) {
            return response()->json(['error' => 'Booking not found'], 404);
        }

        $property = $booking->property;
        $metadata = $booking->metadata ?? [];

        $guestEmail = $booking->guest_email;
        $checkIn = $booking->start_date;
        $checkOut = $booking->end_date;
        $totalPrice = $booking->total_amount;
        $breakdown = $metadata['price_breakdown'] ?? null;

        $checks = [
            [
                'key' => 'booking_cpt',
                'label' => 'Booking record created correctly',
                'passed' => true,
                'detail' => 'Model: Booking, ID: '.$booking->id,
            ],
            [
                'key' => 'property_ref',
                'label' => 'Booking references a valid property',
                'passed' => $property && $property->exists,
                'detail' => $property ? $property->name.' (ID: '.$booking->property_id.')' : 'No property found (ID: '.$booking->property_id.')',
            ],
            [
                'key' => 'guest_email',
                'label' => 'Guest email is stored and valid',
                'passed' => ! empty($guestEmail) && filter_var($guestEmail, FILTER_VALIDATE_EMAIL) !== false,
                'detail' => $guestEmail ?: 'Missing',
            ],
            [
                'key' => 'date_range',
                'label' => 'Check-in / Check-out dates are valid',
                'passed' => ! empty($checkIn) && ! empty($checkOut) && strtotime($checkOut) > strtotime($checkIn),
                'detail' => $checkIn.' → '.$checkOut,
            ],
            [
                'key' => 'price_breakdown',
                'label' => 'Price breakdown stored (JSON meta)',
                'passed' => ! empty($breakdown),
                'detail' => $breakdown ? 'Total: '.number_format((float) $totalPrice, 2) : 'Missing',
            ],
            [
                'key' => 'price_total_matches',
                'label' => 'Total price matches breakdown total',
                'passed' => false,
                'detail' => '',
            ],
            [
                'key' => 'host_assigned',
                'label' => 'Booking has host assigned',
                'passed' => $booking->user_id > 0,
                'detail' => $booking->user_id > 0 ? 'User ID: '.$booking->user_id : 'No host assigned',
            ],
            [
                'key' => 'guest_name',
                'label' => 'Guest name stored',
                'passed' => ! empty($booking->guest_name),
                'detail' => $booking->guest_name ?: 'Missing',
            ],
            [
                'key' => 'guests_count',
                'label' => 'Guest count is set',
                'passed' => ($booking->guest_count ?? 0) > 0,
                'detail' => (string) ($booking->guest_count ?? 0),
            ],
            [
                'key' => 'cancellation_policy',
                'label' => 'Cancellation policy assigned',
                'passed' => ! empty($metadata['cancellation_policy']),
                'detail' => $metadata['cancellation_policy'] ?? 'Missing',
            ],
        ];

        if (! empty($breakdown)) {
            $decoded = is_string($breakdown) ? json_decode($breakdown, true) : $breakdown;
            if (isset($decoded['total_amount']) && (float) $totalPrice > 0) {
                $checks[5]['passed'] = abs((float) $decoded['total_amount'] - (float) $totalPrice) < 0.01;
                $checks[5]['detail'] = 'Meta: '.number_format((float) $totalPrice, 2).' | Breakdown: '.number_format((float) $decoded['total_amount'], 2);
            } else {
                $checks[5]['detail'] = 'Breakdown missing total or meta total is 0';
            }
        }

        $allPassed = true;
        $total = count($checks);
        $passed = 0;
        foreach ($checks as $c) {
            if ($c['passed']) {
                $passed++;
            } else {
                $allPassed = false;
            }
        }

        return response()->json([
            'success' => true,
            'booking_id' => $id,
            'summary' => $passed.' / '.$total.' checks passed',
            'all_passed' => $allPassed,
            'checks' => $checks,
        ]);
    }

    public function demoResult(int $id): mixed
    {
        $booking = Booking::with('property')->find($id);
        if (! $booking) {
            return redirect()->route('bookings.demo')->withErrors(['not_found' => 'Booking not found']);
        }

        $user = auth()->user();
        $isAdmin = $user->can('manage_options');

        if (! $isAdmin) {
            $hostId = $booking->user_id;
            $guestEmail = $booking->guest_email;
            if ($hostId !== $user->id && $guestEmail !== $user->email) {
                return redirect()->route('bookings.demo')->withErrors(['auth' => 'Unauthorized']);
            }
        }

        $property = $booking->property;
        $metadata = $booking->metadata ?? [];

        $propertyData = null;
        if ($property) {
            $thumbnailUrl = $property->featuredImage?->url;
            if (! $thumbnailUrl) {
                $images = $property->images ?? [];
                if (! empty($images)) {
                    $first = is_array($images) ? $images[0] : $images->first();
                    $thumbnailUrl = $first['url'] ?? $first->url ?? null;
                }
            }

            $propertyData = [
                'id' => $property->id,
                'title' => $property->name,
                'price' => $property->price,
                'thumbnail_url' => $thumbnailUrl ?: null,
                'city' => $property->city ?? '',
                'booking_settings' => $this->getPropertyBookingData($property),
            ];
        }

        $guestEmail = $booking->guest_email;
        $rawToken = null;
        $magicLink = null;
        if ($guestEmail) {
            $this->guestTokenService->revokeTokens($id);
            $rawToken = $this->guestTokenService->generateToken($id, $guestEmail);
            $magicLink = $this->guestTokenService->getMagicLink($id, $rawToken);
        }

        $emailFields = [
            'email_confirmation_sent' => 'Confirmation',
            'email_pre_arrival_sent' => 'Pre-arrival',
            'email_checkin_instructions_sent' => 'Check-in Instructions',
            'email_checkout_reminder_sent' => 'Check-out Reminder',
            'email_post_stay_sent' => 'Post-stay',
            'email_cancellation_sent' => 'Cancellation',
        ];
        $emailStatus = [];
        foreach ($emailFields as $key => $label) {
            $sentValue = $metadata[$key] ?? null;
            $emailStatus[] = [
                'key' => $key,
                'label' => $label,
                'sent' => ! empty($sentValue),
                'sent_at' => $sentValue ?: null,
            ];
        }

        return Inertia::render(
            'Modules/Bookings/BookingDemoResult',
            [
                'booking' => [
                    'id' => $booking->id,
                    'title' => $booking->guest_name ? 'Booking: '.$booking->guest_name : 'Booking #'.$booking->id,
                    'status' => $booking->status->value,
                    'status_label' => $booking->status->label(),
                    'status_badge_class' => $this->getStatusBadgeClass($booking->status),
                    'property_id' => $booking->property_id,
                    'property_title' => $property->name ?? '',
                    'guest_name' => $booking->guest_name,
                    'guest_email' => $booking->guest_email,
                    'guest_phone' => $booking->guest_phone,
                    'check_in' => $booking->start_date,
                    'check_out' => $booking->end_date,
                    'nights' => $booking->nights(),
                    'guests' => $booking->guest_count,
                    'has_pets' => ($booking->pets ?? 0) > 0,
                    'has_unit' => ! empty($metadata['unit_id']),
                    'unit_name' => $metadata['unit_name'] ?? '',
                    'price_breakdown' => isset($metadata['price_breakdown']) ? json_decode($metadata['price_breakdown'], true) : null,
                    'special_requests' => $metadata['special_requests'] ?? '',
                    'created_at' => $booking->created_at->toDateTimeString(),
                    'magic_link' => $magicLink,
                    'raw_token' => $rawToken,
                    'token_table_exists' => true,
                    'email_status' => $emailStatus,
                    'allowed_transitions' => $this->getAllowedTransitions($booking->status),
                ],
                'property' => $propertyData,
                'admin_url' => '/dashboard/manage-bookings/'.$booking->id,
                'page' => ['title' => 'Demo Result - Booking #'.$booking->id],
            ]
        );
    }

    protected function getStatusBadgeClass(BookingStatus $status): string
    {
        return match ($status) {
            BookingStatus::Pending => 'bg-yellow-100 text-yellow-800',
            BookingStatus::Confirmed => 'bg-blue-100 text-blue-800',
            BookingStatus::CheckedIn => 'bg-green-100 text-green-800',
            BookingStatus::CheckedOut => 'bg-gray-100 text-gray-800',
            BookingStatus::Cancelled => 'bg-red-100 text-red-800',
            BookingStatus::NoShow => 'bg-red-100 text-red-800',
            BookingStatus::Completed => 'bg-gray-100 text-gray-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    protected function getAllowedTransitions(BookingStatus $status): array
    {
        $result = [];
        foreach (BookingStatus::cases() as $target) {
            if ($status->canTransitionTo($target)) {
                $result[] = [
                    'value' => $target->value,
                    'label' => $target->label(),
                ];
            }
        }

        return $result;
    }

    protected function getPropertyBookingData(Property $property): array
    {
        $metadata = $property->metadata ?? [];
        $settings = [];
        foreach (['nightly_rate', 'cleaning_fee', 'service_fee', 'min_nights', 'max_nights', 'cancellation_policy', 'check_in_time', 'check_out_time', 'currency'] as $key) {
            $settings[$key] = $metadata[$key] ?? null;
        }

        return $settings;
    }
}
