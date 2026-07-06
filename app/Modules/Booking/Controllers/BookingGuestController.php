<?php

namespace App\Modules\Booking\Controllers;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\BookableRegistry;
use App\Modules\Booking\Enums\BookingStatus;
use App\Modules\Booking\Models\Booking;
use App\Modules\Booking\Services\BookingAvailabilityService;
use App\Modules\Booking\Services\BookingCacheService;
use App\Modules\Booking\Services\BookingGuestTokenService;
use App\Modules\Booking\Services\BookingPriceCalculator;
use App\Modules\Booking\Services\BookingStatusManager;
use App\Modules\Booking\Services\HostProfileService;
use App\Services\AuditService;
use App\Services\MailService;
use App\Services\SettingsService;
use App\Traits\PasswordValidationTrait;
use App\Traits\UserDataTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class BookingGuestController extends Controller
{
    use PasswordValidationTrait;
    use UserDataTrait;

    public function __construct(
        protected BookingAvailabilityService $availabilityService,
        protected BookingCacheService $cacheService,
        protected BookingGuestTokenService $guestTokenService,
        protected BookingPriceCalculator $priceCalculator,
        protected BookingStatusManager $statusManager,
        protected MailService $mailService,
        protected HostProfileService $hostProfile,
    ) {}

    private function acquireLock(int $bookingId, int $ttl = 10): bool
    {
        $lockKey = 'crm_booking_action_lock_'.$bookingId;
        if (Cache::get($lockKey)) {
            return false;
        }
        Cache::put($lockKey, true, $ttl);

        return true;
    }

    private function releaseLock(int $bookingId): void
    {
        Cache::forget('crm_booking_action_lock_'.$bookingId);
    }

    public function view(Request $request): Response|RedirectResponse
    {
        $rawToken = $request->input('token', '');

        if (empty($rawToken)) {
            return redirect()->to('/');
        }

        $bookingId = $this->guestTokenService->getBookingId($rawToken);
        if (! $bookingId) {
            return Inertia::render('Modules/Bookings/GuestView', [
                'error' => [
                    'title' => 'Link Expired or Invalid',
                    'message' => 'This booking link has expired or is invalid. Please check your email for the most recent link or contact the host.',
                ],
                'booking' => null,
            ]);
        }

        $booking = Booking::find($bookingId);
        if (! $booking) {
            return Inertia::render('Modules/Bookings/GuestView', [
                'error' => [
                    'title' => 'Booking Not Found',
                    'message' => 'This booking could not be found. It may have been deleted.',
                ],
                'booking' => null,
            ]);
        }

        $propertyId = $booking->property_id;
        $bookingData = $this->buildBookingDataArray($booking, $propertyId);

        $guestEmail = $booking->guest_email;
        $needsLogin = $guestEmail && User::where('email', $guestEmail)->exists();

        return Inertia::render('Modules/Bookings/GuestView', [
            'booking' => $bookingData,
            'token' => $rawToken,
            'error' => null,
            'needs_login' => $needsLogin,
        ]);
    }

    public function requestCancellation(int $bookingId, Request $request): JsonResponse
    {
        if (! $this->acquireLock($bookingId)) {
            return response()->json(['error' => 'A request is already being processed for this booking. Please wait.'], 429);
        }

        $rawToken = $request->input('token', '');

        if (empty($rawToken) || ! $this->guestTokenService->validateToken($bookingId, $rawToken)) {
            $this->releaseLock($bookingId);

            return response()->json(['error' => 'Invalid or expired link.'], 403);
        }

        $booking = Booking::find($bookingId);
        if (! $booking) {
            $this->releaseLock($bookingId);

            return response()->json(['error' => 'Booking not found.'], 404);
        }

        $settings = SettingsService::getGroup('booking');
        $allowedStatuses = $settings['allow_guest_cancellation_statuses'] ?? [BookingStatus::Pending->value, BookingStatus::Confirmed->value];
        if (! in_array($booking->status->value, $allowedStatuses, true)) {
            return response()->json(['error' => 'Cancellation is not allowed for bookings in this status.'], 400);
        }

        $guestEmail = $booking->guest_email;
        if (! $guestEmail) {
            return response()->json(['error' => 'Guest email not found.'], 400);
        }

        $cancelToken = bin2hex(random_bytes(32));
        Cache::put(
            'crm_booking_cancel_token_'.$cancelToken,
            ['booking_id' => $bookingId, 'guest_email' => $guestEmail],
            1800
        );

        $cancelUrl = route('booking.confirm-cancel', ['bookingId' => $bookingId, 'cancel_token' => $cancelToken]);

        $property = app(BookableRegistry::class)->resolve('properties', $booking->property_id);

        $this->mailService->send(
            $guestEmail,
            'Confirm your cancellation request',
            'booking-cancel-request',
            [
                'guestName' => $booking->guest_name,
                'propertyTitle' => $property?->name ?? 'Property',
                'bookingId' => $bookingId,
                'checkIn' => $booking->start_date,
                'checkOut' => $booking->end_date,
                'cancelUrl' => $cancelUrl,
                'expiryMinutes' => 30,
            ]
        );

        Cache::put('crm_booking_cancel_req_'.$bookingId, true, 1800);

        $this->releaseLock($bookingId);

        return response()->json([
            'success' => true,
            'message' => 'Check your email for the cancellation confirmation link.',
        ]);
    }

    public function requestModification(int $bookingId, Request $request): JsonResponse
    {
        if (! $this->acquireLock($bookingId)) {
            return response()->json(['error' => 'A request is already being processed for this booking. Please wait.'], 429);
        }

        $rawToken = $request->input('token', '');

        if (empty($rawToken) || ! $this->guestTokenService->validateToken($bookingId, $rawToken)) {
            $this->releaseLock($bookingId);

            return response()->json(['error' => 'Invalid or expired link.'], 403);
        }

        $result = $this->processModification($bookingId, $request);
        if (isset($result['error'])) {
            $this->releaseLock($bookingId);

            return response()->json(['error' => $result['error']], $result['status']);
        }

        $this->releaseLock($bookingId);

        $redirectUrl = route('booking.view', ['token' => $rawToken]);

        return response()->json([
            'success' => true,
            'message' => 'Booking updated. Your new dates: '.$result['check_in'].' to '.$result['check_out'].'.',
            'redirect_url' => $redirectUrl,
        ]);
    }

    public function loggedInModify(int $bookingId, Request $request): JsonResponse
    {
        if (! $this->acquireLock($bookingId)) {
            return response()->json(['error' => 'A request is already being processed for this booking. Please wait.'], 429);
        }

        if (! Auth::check()) {
            $this->releaseLock($bookingId);

            return response()->json(['error' => 'Authentication required.'], 401);
        }

        $user = Auth::user();
        $userId = $user->id;

        $booking = Booking::find($bookingId);
        if (! $booking) {
            $this->releaseLock($bookingId);

            return response()->json(['error' => 'Booking not found.'], 404);
        }

        $guestEmail = $booking->guest_email;
        $bookingUserId = $booking->user_id;
        if ($guestEmail !== $user->email && $bookingUserId !== $userId) {
            $this->releaseLock($bookingId);

            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $result = $this->processModification($bookingId, $request);
        if (isset($result['error'])) {
            $this->releaseLock($bookingId);

            return response()->json(['error' => $result['error']], $result['status']);
        }

        $this->releaseLock($bookingId);

        $redirectUrl = route('dashboard.my-bookings.show', $bookingId);

        return response()->json([
            'success' => true,
            'message' => 'Booking updated. Your new dates: '.$result['check_in'].' to '.$result['check_out'].'.',
            'redirect_url' => $redirectUrl,
        ]);
    }

    public function loggedInCancel(int $bookingId, Request $request): JsonResponse
    {
        if (! $this->acquireLock($bookingId)) {
            return response()->json(['error' => 'A request is already being processed for this booking. Please wait.'], 429);
        }

        if (! Auth::check()) {
            $this->releaseLock($bookingId);

            return response()->json(['error' => 'Authentication required.'], 401);
        }

        $user = Auth::user();
        $userId = $user->id;

        $booking = Booking::find($bookingId);
        if (! $booking) {
            $this->releaseLock($bookingId);

            return response()->json(['error' => 'Booking not found.'], 404);
        }

        $settings = SettingsService::getGroup('booking');
        $allowedStatuses = $settings['allow_guest_cancellation_statuses'] ?? [BookingStatus::Pending->value, BookingStatus::Confirmed->value];
        if (! in_array($booking->status->value, $allowedStatuses, true)) {
            return response()->json(['error' => 'Cancellation is not allowed for bookings in this status.'], 400);
        }

        $guestEmail = $booking->guest_email;
        $bookingUserId = $booking->user_id;
        if ($guestEmail !== $user->email && $bookingUserId !== $userId) {
            $this->releaseLock($bookingId);

            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $newStatus = BookingStatus::Cancelled;
        $currentStatus = $booking->status;
        if (! $currentStatus->canTransitionTo($newStatus)) {
            $this->releaseLock($bookingId);

            return response()->json(['error' => 'Cannot cancel this booking in its current status.'], 400);
        }

        try {
            $this->statusManager->transition($booking, $newStatus, 'guest');
        } catch (\Exception $e) {
            $this->releaseLock($bookingId);

            return response()->json(['error' => $e->getMessage()], 400);
        }

        AuditService::log(AuditAction::BookingCancel, auth()->id(), [
            'booking_id' => $bookingId,
            'cancelled_by' => 'guest',
            'property_id' => $booking->property_id,
        ]);

        $this->releaseLock($bookingId);

        return response()->json([
            'success' => true,
            'message' => 'Booking cancelled successfully.',
        ]);
    }

    private function processModification(int $bookingId, Request $request): array
    {
        $booking = Booking::find($bookingId);
        if (! $booking) {
            return ['error' => 'Booking not found.', 'status' => 404];
        }

        $currentStatus = $booking->status;
        $settings = SettingsService::getGroup('booking');
        $allowedModifyStatuses = $settings['allow_guest_modification_statuses'] ?? [BookingStatus::Pending->value, BookingStatus::Confirmed->value, BookingStatus::CheckedIn->value];
        if (! in_array($currentStatus->value, $allowedModifyStatuses, true)) {
            $label = $currentStatus->label();

            return ['error' => 'Modification is not allowed for bookings with status "'.$label.'".', 'status' => 400];
        }

        $validated = $request->validate([
            'check_in_date' => 'required|date',
            'check_out_date' => 'required|date|after:check_in_date',
            'guests' => 'required|integer|min:1',
        ]);

        $checkIn = $validated['check_in_date'];
        $checkOut = $validated['check_out_date'];
        $guests = (int) $validated['guests'];

        $propertyId = $booking->property_id;
        if (! $propertyId) {
            return ['error' => 'Property not found for this booking.', 'status' => 400];
        }

        $metadata = $booking->metadata ?? [];
        $unitId = $metadata['unit_id'] ?? null;

        if (! $this->availabilityService->isAvailable($propertyId, $checkIn, $checkOut, $bookingId)) {
            return ['error' => 'Those dates are not available. Please choose different dates.', 'status' => 409];
        }

        $hasPets = ($booking->pets ?? 0) > 0;
        $property = app(BookableRegistry::class)->resolve('properties', $propertyId);
        if (! $property) {
            return ['error' => 'Property not found.', 'status' => 404];
        }

        $price = $this->priceCalculator->calculateTotal(
            $property, $checkIn, $checkOut, $guests, 0, $hasPets ? 1 : 0
        );

        $nights = now()->parse($checkIn)->diffInDays(now()->parse($checkOut));

        $oldCheckIn = $booking->start_date;
        $oldCheckOut = $booking->end_date;
        $oldGuests = $booking->guest_count;

        $booking->update([
            'start_date' => $checkIn,
            'end_date' => $checkOut,
            'guest_count' => $guests,
            'nightly_rate' => $price['nightly_rate'],
            'cleaning_fee' => $price['cleaning_fee'],
            'service_fee' => $price['service_fee'],
            'total_amount' => $price['total_amount'],
        ]);

        $meta = $booking->metadata ?? [];
        unset($meta['has_modification_request'], $meta['modification_request']);
        $booking->update(['metadata' => $meta]);

        $this->cacheService->bustPropertyCache($propertyId);

        AuditService::log(AuditAction::BookingUpdate, 0, [
            'booking_id' => $bookingId,
            'old_check_in' => $oldCheckIn,
            'old_check_out' => $oldCheckOut,
            'old_guests' => $oldGuests,
            'new_check_in' => $checkIn,
            'new_check_out' => $checkOut,
            'new_guests' => $guests,
            'new_total' => $price['total_amount'],
            'modified_by' => 'guest',
        ]);

        $hostId = $booking->user_id;
        if ($hostId) {
            $globalSettings = SettingsService::getGroup('booking');
            if (! empty($globalSettings['email_enable_modification_request'])) {
                $guestName = $booking->guest_name;
            }
        }

        return [
            'check_in' => $checkIn,
            'check_out' => $checkOut,
        ];
    }

    public function guestCheckIn(int $bookingId, Request $request): JsonResponse
    {
        if (! $this->acquireLock($bookingId)) {
            return response()->json(['error' => 'A request is already being processed for this booking. Please wait.'], 429);
        }

        $rawToken = $request->input('token', '');
        if (empty($rawToken) || ! $this->guestTokenService->validateToken($bookingId, $rawToken)) {
            $this->releaseLock($bookingId);

            return response()->json(['error' => 'Invalid or expired link.'], 403);
        }

        $booking = Booking::find($bookingId);
        if (! $booking) {
            $this->releaseLock($bookingId);

            return response()->json(['error' => 'Booking not found.'], 404);
        }

        $currentStatus = $booking->status;
        if (! $currentStatus->canTransitionTo(BookingStatus::CheckedIn)) {
            $this->releaseLock($bookingId);

            return response()->json(['error' => 'Check-in is not allowed for bookings in this status.'], 400);
        }

        try {
            $this->statusManager->transition($booking, BookingStatus::CheckedIn, 'guest check-in');
        } catch (\Exception $e) {
            $this->releaseLock($bookingId);

            return response()->json(['error' => $e->getMessage()], 400);
        }

        AuditService::log(AuditAction::BookingUpdate, 0, [
            'booking_id' => $bookingId,
            'action' => 'check_in',
            'performed_by' => 'guest',
            'property_id' => $booking->property_id,
        ]);

        $this->releaseLock($bookingId);

        return response()->json([
            'success' => true,
            'message' => 'Check-in successful. Welcome!',
        ]);
    }

    public function guestCheckOut(int $bookingId, Request $request): JsonResponse
    {
        if (! $this->acquireLock($bookingId)) {
            return response()->json(['error' => 'A request is already being processed for this booking. Please wait.'], 429);
        }

        $rawToken = $request->input('token', '');
        if (empty($rawToken) || ! $this->guestTokenService->validateToken($bookingId, $rawToken)) {
            $this->releaseLock($bookingId);

            return response()->json(['error' => 'Invalid or expired link.'], 403);
        }

        $booking = Booking::find($bookingId);
        if (! $booking) {
            $this->releaseLock($bookingId);

            return response()->json(['error' => 'Booking not found.'], 404);
        }

        $currentStatus = $booking->status;
        if (! $currentStatus->canTransitionTo(BookingStatus::CheckedOut)) {
            $this->releaseLock($bookingId);

            return response()->json(['error' => 'Check-out is not allowed for bookings in this status.'], 400);
        }

        try {
            $this->statusManager->transition($booking, BookingStatus::CheckedOut, 'guest check-out');
        } catch (\Exception $e) {
            $this->releaseLock($bookingId);

            return response()->json(['error' => $e->getMessage()], 400);
        }

        AuditService::log(AuditAction::BookingUpdate, 0, [
            'booking_id' => $bookingId,
            'action' => 'check_out',
            'performed_by' => 'guest',
            'property_id' => $booking->property_id,
        ]);

        $this->releaseLock($bookingId);

        return response()->json([
            'success' => true,
            'message' => 'Check-out successful. Thank you for staying!',
        ]);
    }

    public function loggedInCheckIn(int $bookingId, Request $request): JsonResponse
    {
        if (! $this->acquireLock($bookingId)) {
            return response()->json(['error' => 'A request is already being processed for this booking. Please wait.'], 429);
        }

        if (! Auth::check()) {
            $this->releaseLock($bookingId);

            return response()->json(['error' => 'Authentication required.'], 401);
        }

        $user = Auth::user();
        $userId = $user->id;

        $booking = Booking::find($bookingId);
        if (! $booking) {
            $this->releaseLock($bookingId);

            return response()->json(['error' => 'Booking not found.'], 404);
        }

        $guestEmail = $booking->guest_email;
        $bookingUserId = $booking->user_id;
        if ($guestEmail !== $user->email && $bookingUserId !== $userId) {
            $this->releaseLock($bookingId);

            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $currentStatus = $booking->status;
        if (! $currentStatus->canTransitionTo(BookingStatus::CheckedIn)) {
            $this->releaseLock($bookingId);

            return response()->json(['error' => 'Check-in is not allowed for bookings in this status.'], 400);
        }

        try {
            $this->statusManager->transition($booking, BookingStatus::CheckedIn, 'guest check-in');
        } catch (\Exception $e) {
            $this->releaseLock($bookingId);

            return response()->json(['error' => $e->getMessage()], 400);
        }

        AuditService::log(AuditAction::BookingUpdate, auth()->id(), [
            'booking_id' => $bookingId,
            'action' => 'check_in',
            'performed_by' => 'guest',
            'property_id' => $booking->property_id,
        ]);

        $this->releaseLock($bookingId);

        return response()->json([
            'success' => true,
            'message' => 'Check-in successful. Welcome!',
        ]);
    }

    public function loggedInCheckOut(int $bookingId, Request $request): JsonResponse
    {
        if (! $this->acquireLock($bookingId)) {
            return response()->json(['error' => 'A request is already being processed for this booking. Please wait.'], 429);
        }

        if (! Auth::check()) {
            $this->releaseLock($bookingId);

            return response()->json(['error' => 'Authentication required.'], 401);
        }

        $user = Auth::user();
        $userId = $user->id;

        $booking = Booking::find($bookingId);
        if (! $booking) {
            $this->releaseLock($bookingId);

            return response()->json(['error' => 'Booking not found.'], 404);
        }

        $guestEmail = $booking->guest_email;
        $bookingUserId = $booking->user_id;
        if ($guestEmail !== $user->email && $bookingUserId !== $userId) {
            $this->releaseLock($bookingId);

            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $currentStatus = $booking->status;
        if (! $currentStatus->canTransitionTo(BookingStatus::CheckedOut)) {
            $this->releaseLock($bookingId);

            return response()->json(['error' => 'Check-out is not allowed for bookings in this status.'], 400);
        }

        try {
            $this->statusManager->transition($booking, BookingStatus::CheckedOut, 'guest check-out');
        } catch (\Exception $e) {
            $this->releaseLock($bookingId);

            return response()->json(['error' => $e->getMessage()], 400);
        }

        AuditService::log(AuditAction::BookingUpdate, auth()->id(), [
            'booking_id' => $bookingId,
            'action' => 'check_out',
            'performed_by' => 'guest',
            'property_id' => $booking->property_id,
        ]);

        $this->releaseLock($bookingId);

        return response()->json([
            'success' => true,
            'message' => 'Check-out successful. Thank you for staying!',
        ]);
    }

    public function confirmCancellation(int $bookingId, Request $request): Response|RedirectResponse
    {
        if (! $this->acquireLock($bookingId)) {
            return Inertia::render('Modules/Bookings/GuestView', [
                'error' => [
                    'title' => 'Please Wait',
                    'message' => 'A request is already being processed for this booking.',
                ],
                'booking' => null,
                'token' => '',
            ]);
        }

        $cancelToken = $request->input('cancel_token', '');

        if (empty($cancelToken)) {
            $this->releaseLock($bookingId);

            return redirect()->to('/');
        }

        $tokenData = Cache::get('crm_booking_cancel_token_'.$cancelToken);
        if (! $tokenData || ! is_array($tokenData) || (int) $tokenData['booking_id'] !== $bookingId) {
            $this->releaseLock($bookingId);

            return Inertia::render('Modules/Bookings/GuestView', [
                'error' => [
                    'title' => 'Cancellation Link Expired',
                    'message' => 'This cancellation confirmation link has expired. Please go back and request cancellation again.',
                ],
                'booking' => null,
                'token' => '',
            ]);
        }

        $booking = Booking::find($bookingId);
        if (! $booking) {
            $this->releaseLock($bookingId);

            return Inertia::render('Modules/Bookings/GuestView', [
                'error' => [
                    'title' => 'Booking Not Found',
                    'message' => 'This booking could not be found.',
                ],
                'booking' => null,
                'token' => '',
            ]);
        }

        $newStatus = BookingStatus::Cancelled;
        try {
            $this->statusManager->transition($booking, $newStatus, 'guest');
        } catch (\Exception $e) {
            $this->releaseLock($bookingId);

            return Inertia::render('Modules/Bookings/GuestView', [
                'error' => [
                    'title' => 'Cancellation Failed',
                    'message' => $e->getMessage(),
                ],
                'booking' => null,
                'token' => '',
            ]);
        }

        AuditService::log(AuditAction::BookingCancel, 0, [
            'booking_id' => $bookingId,
            'cancelled_by' => 'guest',
            'property_id' => $booking->property_id,
        ]);

        Cache::forget('crm_booking_cancel_token_'.$cancelToken);
        Cache::forget('crm_booking_cancel_req_'.$bookingId);

        $this->guestTokenService->revokeTokens($bookingId);

        $this->releaseLock($bookingId);

        $metadata = $booking->metadata ?? [];
        $property = app(BookableRegistry::class)->resolve('properties', $booking->property_id);

        return Inertia::render('Modules/Bookings/GuestView', [
            'booking' => [
                'id' => $bookingId,
                'status' => BookingStatus::Cancelled->value,
                'property_title' => $property?->name ?? '',
                'check_in_date' => $booking->start_date,
                'check_out_date' => $booking->end_date,
                'guest_name' => $booking->guest_name,
                'refund_amount' => (float) ($metadata['refund_amount'] ?? 0),
                'cancelled_at' => now()->toDateTimeString(),
            ],
            'cancellation_confirmed' => true,
            'token' => '',
            'error' => null,
        ]);
    }

    public function registerFromBooking(Request $request): Response|RedirectResponse
    {
        $bookingId = (int) $request->input('booking_id', 0);
        $rawToken = $request->input('token', '');
        $password = $request->input('password', '');

        if (! $bookingId || empty($rawToken)) {
            return Inertia::render('Modules/Bookings/GuestView', [
                'error' => [
                    'title' => 'Invalid Link',
                    'message' => 'This link is invalid. Please check your email for the correct link.',
                ],
                'booking' => null,
                'token' => '',
            ]);
        }

        if (! $this->guestTokenService->validateToken($bookingId, $rawToken)) {
            return Inertia::render('Modules/Bookings/GuestView', [
                'error' => [
                    'title' => 'Link Expired',
                    'message' => 'This booking link has expired. Please request a new link.',
                ],
                'booking' => null,
                'token' => '',
            ]);
        }

        $booking = Booking::find($bookingId);
        if (! $booking) {
            return Inertia::render('Modules/Bookings/GuestView', [
                'error' => [
                    'title' => 'Booking Not Found',
                    'message' => 'This booking could not be found.',
                ],
                'booking' => null,
                'token' => '',
            ]);
        }

        $guestEmail = $booking->guest_email;
        $guestName = $booking->guest_name;
        $guestPhone = $booking->guest_phone;

        if (! $guestEmail) {
            return Inertia::render('Modules/Bookings/GuestView', [
                'error' => [
                    'title' => 'Registration Failed',
                    'message' => 'No guest email found for this booking.',
                ],
                'booking' => null,
                'token' => '',
            ]);
        }

        $displayName = $request->input('display_name', $guestName ?: '');

        $existingUser = User::where('email', $guestEmail)->first();
        if ($existingUser) {
            $bookingData = $this->buildBookingDataArray($booking, $booking->property_id);

            return Inertia::render('Modules/Bookings/GuestView', [
                'booking' => $bookingData,
                'token' => $rawToken,
                'needs_login' => true,
                'error' => null,
            ]);
        }

        $validated = $request->validate([
            'password' => ['required', 'string', Password::defaults()],
            'display_name' => 'nullable|string|max:255',
        ]);

        $user = User::create([
            'name' => $displayName ?: explode('@', $guestEmail)[0],
            'email' => $guestEmail,
            'password' => bcrypt($validated['password']),
            'display_name' => $displayName,
        ]);

        if ($guestPhone) {
            $user->phone = $guestPhone;
            $user->save();
        }

        $booking->update(['user_id' => $user->id]);

        $this->guestTokenService->revokeTokens($bookingId);

        $property = app(BookableRegistry::class)->resolve('properties', $booking->property_id);

        AuditService::log(AuditAction::BookingGuestRegister, $user->id, [
            'booking_id' => $bookingId,
            'guest_email' => $guestEmail,
        ]);

        Auth::login($user, true);

        Inertia::flash('status', 'Account created! You are now logged in.');

        return redirect()->to('/dashboard', 303);
    }

    private function buildBookingDataArray(Booking $booking, int $propertyId): array
    {
        $metadata = $booking->metadata ?? [];
        $property = app(BookableRegistry::class)->resolve('properties', $propertyId);

        $bookingData = [
            'id' => $booking->id,
            'status' => $booking->status,
            'status_badge_class' => $this->getStatusBadgeClass($booking->status),
            'user_id' => $booking->user_id,
            'guest_name' => $booking->guest_name,
            'guest_email' => $booking->guest_email,
            'guest_phone' => $booking->guest_phone,
            'property_id' => $propertyId,
            'property_title' => $property?->name ?? '',
            'property_thumbnail' => $property?->featuredImage?->url,
            'property_url' => $property ? route('dashboard.properties.edit', $property->id) : null,
            'check_in_date' => $booking->start_date,
            'check_out_date' => $booking->end_date,
            'check_in_time' => $metadata['check_in_time'] ?? '15:00',
            'check_out_time' => $metadata['check_out_time'] ?? '11:00',
            'timezone' => config('app.timezone', 'UTC'),
            'guests' => $booking->guest_count,
            'has_pets' => ($booking->pets ?? 0) > 0,
            'special_requests' => $metadata['special_requests'] ?? '',
            'total_price' => (float) ($booking->total_amount ?? 0),
            'nightly_rate' => (float) ($booking->nightly_rate ?? 0),
            'cleaning_fee' => (float) ($booking->cleaning_fee ?? 0),
            'service_fee' => (float) ($booking->service_fee ?? 0),
            'cancellation_policy' => $metadata['cancellation_policy'] ?? 'flexible',
            'created_at' => $booking->created_at?->toDateTimeString(),
            'unit_id' => $metadata['unit_id'] ?? null,
            'unit_name' => $metadata['unit_name'] ?? null,
            'refund_amount' => (float) ($metadata['refund_amount'] ?? 0),
            'cancelled_at' => $metadata['cancelled_at'] ?? null,
            'cancellation_requested' => Cache::get('crm_booking_cancel_req_'.$booking->id) !== null,
            'modification_requested' => ($metadata['has_modification_request'] ?? '') === '1',
        ];

        $hostId = $booking->user_id;
        if ($hostId) {
            $hostProfile = $this->hostProfile->resolveAll($hostId);
            $bookingData['host_name'] = User::find($hostId)?->display_name ?? User::find($hostId)?->name ?? '';
            $bookingData = array_merge($bookingData, $hostProfile);
        } else {
            $bookingData['host_name'] = '';
            $bookingData['host_phone'] = '';
            $bookingData['host_phone_numbers'] = [];
            $bookingData['host_email'] = '';
            $bookingData['host_avatar'] = '';
            $bookingData['host_bio'] = '';
            $bookingData['host_social_media'] = [];
        }

        if ($propertyId) {
            $checkInDate = $bookingData['check_in_date'];
            $checkOutDate = $bookingData['check_out_date'];
            $guestCount = $bookingData['guests'];
            $hasPets = $bookingData['has_pets'];
            $unitId = $bookingData['unit_id'] ?: null;

            try {
                $pricingService = new BookingPriceCalculator;
                $pricing = $pricingService->calculateTotal(
                    $property, $checkInDate, $checkOutDate, $guestCount, 0, $hasPets ? 1 : 0,
                );
                $bookingData['pricing'] = $pricing;
            } catch (\Throwable $e) {
                $bookingData['pricing'] = null;
            }
        } else {
            $bookingData['pricing'] = null;
        }

        $bookingData['refund_preview'] = $this->computeRefundPreview($booking, $propertyId);

        return $bookingData;
    }

    private function computeRefundPreview(Booking $booking, int $propertyId): array
    {
        try {
            $metadata = $booking->metadata ?? [];
            $policy = $metadata['cancellation_policy'] ?? 'flexible';
            $checkIn = $booking->start_date;
            $total = (float) ($booking->total_amount ?? 0);
            $bookedAt = $booking->created_at?->toDateTimeString() ?? $booking->created_at;

            $propertyMeta = [];
            if ($propertyId) {
                $prop = app(BookableRegistry::class)->resolve('properties', $propertyId);
                $propertyMeta = $prop->getMeta() ?? [];
            }

            $globalSettings = SettingsService::getGroup('booking');

            $nights = $booking->nights();

            return $this->calculateRefund(
                $policy,
                $checkIn ?: now()->toDateString(),
                null,
                $total,
                [
                    'full_refund_days' => (int) ($propertyMeta['cancellation_full_refund_days'] ?? 0),
                    'partial_refund_cutoff_days' => (int) ($propertyMeta['cancellation_partial_refund_cutoff_days'] ?? 0),
                    'partial_refund_percent' => (int) ($propertyMeta['cancellation_partial_refund_percent'] ?? 0),
                    'total_nights' => $nights,
                    'long_term_min_nights' => (int) ($propertyMeta['long_term_min_nights'] ?? 0),
                    'long_term_nonrefundable_nights' => (int) ($propertyMeta['long_term_nonrefundable_nights'] ?? 0),
                    'booked_at' => $bookedAt,
                    'grace_period_hours' => (int) ($globalSettings['grace_period_hours'] ?? 24),
                    'grace_period_advance_days' => (int) ($globalSettings['grace_period_advance_days'] ?? 7),
                ]
            );
        } catch (\Throwable $e) {
            return [
                'refund_percent' => 0,
                'refund_amount' => 0.0,
                'policy_applied' => 'error',
                'reason' => __('Unable to calculate refund preview'),
            ];
        }
    }

    private function calculateRefund(string $policy, string $checkIn, $checkInTime, float $total, array $params): array
    {
        $now = now();
        $checkInDate = now()->parse($checkIn);
        $daysUntilCheckIn = $now->startOfDay()->diffInDays($checkInDate->startOfDay(), false);

        if ($daysUntilCheckIn < 0) {
            return [
                'refund_percent' => 0,
                'refund_amount' => 0.0,
                'policy_applied' => $policy,
                'reason' => __('Check-in date has passed'),
            ];
        }

        if ($policy === 'flexible') {
            if ($daysUntilCheckIn >= 1) {
                return [
                    'refund_percent' => 100,
                    'refund_amount' => $total,
                    'policy_applied' => 'flexible',
                ];
            }
        } elseif ($policy === 'moderate') {
            if ($daysUntilCheckIn >= 5) {
                return [
                    'refund_percent' => 100,
                    'refund_amount' => $total,
                    'policy_applied' => 'moderate',
                ];
            }
        } elseif ($policy === 'firm') {
            if ($daysUntilCheckIn >= 30) {
                return [
                    'refund_percent' => 100,
                    'refund_amount' => $total,
                    'policy_applied' => 'firm',
                ];
            }
        }

        $fullRefundDays = $params['full_refund_days'] ?? 5;
        $partialCutoffDays = $params['partial_refund_cutoff_days'] ?? 1;
        $partialPercent = $params['partial_refund_percent'] ?? 50;

        if ($daysUntilCheckIn >= $fullRefundDays) {
            return [
                'refund_percent' => 100,
                'refund_amount' => $total,
                'policy_applied' => $policy,
            ];
        }

        if ($daysUntilCheckIn >= $partialCutoffDays) {
            $amount = round($total * $partialPercent / 100, 2);

            return [
                'refund_percent' => $partialPercent,
                'refund_amount' => $amount,
                'policy_applied' => $policy,
            ];
        }

        return [
            'refund_percent' => 0,
            'refund_amount' => 0.0,
            'policy_applied' => $policy,
        ];
    }

    private function getStatusBadgeClass(BookingStatus $status): string
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

    public function sendMagicLink(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'booking_id' => 'required|integer|exists:bookings,id',
        ]);

        $email = $validated['email'];
        $bookingId = (int) $validated['booking_id'];

        $booking = Booking::find($bookingId);
        if (! $booking) {
            return response()->json(['error' => 'Booking not found.'], 404);
        }

        $storedEmail = $booking->guest_email;
        if (strtolower($storedEmail ?? '') !== strtolower($email)) {
            return response()->json(['error' => 'Email does not match this booking.'], 403);
        }

        $rateKey = 'crm_booking_resend_link_'.$bookingId;
        $lastSent = (int) Cache::get($rateKey, 0);
        if ($lastSent && (time() - $lastSent) < 300) {
            return response()->json(['error' => 'Please wait 5 minutes before requesting another link.'], 429);
        }

        $rawToken = $this->guestTokenService->generateToken($bookingId, $email);
        $magicLink = $this->guestTokenService->getMagicLink($bookingId, $rawToken);

        $property = app(BookableRegistry::class)->resolve('properties', $booking->property_id);

        $this->mailService->send(
            $email,
            'Your Booking Link',
            'booking-guest-received',
            [
                'guestName' => $booking->guest_name,
                'propertyTitle' => $property?->name ?? 'Property',
                'bookingId' => $bookingId,
                'checkIn' => $booking->start_date,
                'checkOut' => $booking->end_date,
                'magicLink' => $magicLink,
                'expiresInDays' => 30,
            ]
        );

        Cache::put($rateKey, time(), 300);

        return response()->json([
            'success' => true,
            'message' => 'Booking link sent to your email.',
        ]);
    }

    public function inlineLogin(Request $request): RedirectResponse
    {
        $bookingId = (int) $request->input('booking_id', 0);
        $rawToken = $request->input('token', '');
        $password = $request->input('password', '');

        if (! $bookingId || empty($rawToken)) {
            return redirect()->to('/');
        }

        if (! $this->guestTokenService->validateToken($bookingId, $rawToken)) {
            $redirect = '/dashboard/booking/view?token='.urlencode($rawToken);
            Inertia::flash('error', [
                'title' => 'Link Expired',
                'message' => 'This booking link has expired. Please request a new link.',
            ]);

            return redirect()->to($redirect);
        }

        $booking = Booking::find($bookingId);
        if (! $booking || ! $booking->guest_email) {
            return redirect()->to('/');
        }

        $email = $booking->guest_email;

        if (! Auth::attempt(['email' => $email, 'password' => $password])) {
            $redirect = '/dashboard/booking/view?token='.urlencode($rawToken);
            Inertia::flash('error', [
                'title' => 'Incorrect Password',
                'message' => 'The password you entered is incorrect. Please try again.',
            ]);

            return redirect()->to($redirect);
        }

        AuditService::log(AuditAction::Login, Auth::id());

        return redirect()->to('/dashboard/my-bookings/'.$bookingId, 303);
    }

    public function show(int $bookingId): Response|RedirectResponse
    {
        if (! Auth::check()) {
            return redirect()->route('dashboard.my-bookings.index');
        }

        $user = Auth::user();
        $userId = $user->id;

        $booking = Booking::with('property')->find($bookingId);
        if (! $booking) {
            return redirect()->route('dashboard.my-bookings.index');
        }

        $guestEmail = $booking->guest_email;
        $bookingUserId = $booking->user_id;
        if ($guestEmail !== $user->email && $bookingUserId !== $userId) {
            return redirect()->route('dashboard.my-bookings.index');
        }

        $property = $booking->property;
        $propertyId = $booking->property_id;
        $hostId = $booking->user_id;
        $metadata = $booking->metadata ?? [];

        $bookingData = [
            'id' => $booking->id,
            'status' => $booking->status,
            'status_label' => $booking->status->label(),
            'status_badge_class' => $this->getStatusBadgeClass($booking->status),
            'property_id' => $propertyId,
            'property_title' => $property?->name ?? '',
            'property_thumbnail' => $property?->featuredImage?->url,
            'property_url' => $property ? route('dashboard.properties.edit', $property->id) : null,
            'guest_name' => $booking->guest_name,
            'guest_email' => $booking->guest_email,
            'guest_phone' => $booking->guest_phone,
            'check_in_date' => $booking->start_date,
            'check_out_date' => $booking->end_date,
            'check_in_time' => $metadata['check_in_time'] ?? '15:00',
            'check_out_time' => $metadata['check_out_time'] ?? '11:00',
            'timezone' => config('app.timezone', 'UTC'),
            'guests' => (int) ($booking->guest_count ?? 0),
            'has_pets' => ($booking->pets ?? 0) > 0,
            'special_requests' => $metadata['special_requests'] ?? '',
            'total_price' => (float) ($booking->total_amount ?? 0),
            'nightly_rate' => (float) ($booking->nightly_rate ?? 0),
            'cleaning_fee' => (float) ($booking->cleaning_fee ?? 0),
            'service_fee' => (float) ($booking->service_fee ?? 0),
            'cancellation_policy' => $metadata['cancellation_policy'] ?? 'flexible',
            'created_at' => $booking->created_at?->toDateTimeString(),
            'unit_id' => $metadata['unit_id'] ?? null,
            'unit_name' => $metadata['unit_name'] ?? null,
            'refund_amount' => (float) ($metadata['refund_amount'] ?? 0),
            'refund_percent' => isset($metadata['refund_percent']) ? (float) $metadata['refund_percent'] : null,
            'refund_policy_applied' => $metadata['refund_policy_applied'] ?? null,
            'host_name' => $hostId ? User::find($hostId)?->display_name ?? User::find($hostId)?->name : '',
        ] + $this->hostProfile->resolveAll($hostId);

        if ($propertyId) {
            try {
                $priceCalculator = new BookingPriceCalculator;
                $pricing = $priceCalculator->calculateTotal(
                    $property,
                    $bookingData['check_in_date'],
                    $bookingData['check_out_date'],
                    $bookingData['guests'],
                    0,
                    $bookingData['has_pets'] ? 1 : 0,
                );
                $bookingData['pricing'] = $pricing;
            } catch (\Throwable $e) {
                $bookingData['pricing'] = null;
            }
        } else {
            $bookingData['pricing'] = null;
        }

        $bookingData['refund_preview'] = $this->computeRefundPreview($booking, $propertyId);

        return Inertia::render('Modules/Bookings/MyBookingDetail', [
            'booking' => $bookingData,
        ]);
    }

    public function myBookings(Request $request): Response
    {
        $user = Auth::user();
        $userEmail = $user->email;

        $query = Booking::with('property')
            ->where(function ($q) use ($user, $userEmail) {
                $q->where('user_id', $user->id)
                    ->orWhere('guest_email', $userEmail);
            })
            ->orderBy('created_at', 'desc');

        $status = $request->input('status');
        if ($status && $status !== 'all' && BookingStatus::tryFrom($status)) {
            $query->where('status', $status);
        }

        $bookings = $query->get()->map(function ($booking) {
            $metadata = $booking->metadata ?? [];
            $featuredImage = $booking->property?->featuredImage;

            return [
                'id' => $booking->id,
                'status' => $booking->status,
                'status_label' => $booking->status->label(),
                'property_title' => $booking->property?->name ?? '',
                'property_thumbnail' => $featuredImage?->url,
                'property_url' => $booking->property ? route('dashboard.properties.edit', $booking->property->id) : null,
                'check_in' => $booking->start_date?->toDateString(),
                'check_out' => $booking->end_date?->toDateString(),
                'nights' => $booking->nights(),
                'guests' => $booking->guest_count,
                'total' => (float) ($booking->total_amount ?? 0),
                'refund_percent' => (string) ($metadata['refund_percent'] ?? ''),
                'refund_amount' => (string) ($metadata['refund_amount'] ?? ''),
                'refund_policy_applied' => (string) ($metadata['refund_policy_applied'] ?? ''),
            ];
        });

        return Inertia::render('Modules/Bookings/MyBookings', [
            'bookings' => $bookings,
            'filters' => ['status' => $request->input('status', 'all')],
        ]);
    }

    public function toggleMode(Request $request): RedirectResponse
    {
        $mode = $request->input('mode');
        if (! in_array($mode, ['host', 'guest'], true)) {
            return back()->withErrors(['error' => 'Invalid mode.']);
        }
        $user = Auth::user();
        if ($user) {
            $user->update(['metadata' => array_merge($user->metadata ?? [], ['crm_active_mode' => $mode])]);
        }

        return back();
    }
}
