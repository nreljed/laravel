<?php

namespace App\Modules\Booking\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Enums\BookingStatus;
use App\Modules\Booking\Models\Booking;
use App\Modules\Booking\Services\BookingAvailabilityService;
use App\Modules\Booking\Services\BookingCacheService;
use App\Modules\Booking\Services\BookingEmailService;
use App\Modules\Booking\Services\BookingIcalService;
use App\Modules\Booking\Services\BookingPriceCalculator;
use App\Modules\Booking\Services\BookingService;
use App\Modules\Booking\Services\BookingStatusManager;
use App\Modules\Properties\Models\Property;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BookingController extends Controller
{
    public function __construct(
        protected BookingService $bookingService,
        protected BookingStatusManager $statusManager,
        protected BookingAvailabilityService $availabilityService,
        protected BookingPriceCalculator $priceCalculator,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Booking::class);

        $filters = $request->only(['status', 'property_id', 'date_from', 'date_to', 'search', 'sort', 'dir', 'per_page']);
        $bookings = $this->bookingService->search($filters);
        $stats = $this->bookingService->getStats();

        $properties = Property::select('id', 'name')->get();

        return Inertia::render('Bookings/Index', [
            'bookings' => $bookings->items(),
            'stats' => $stats,
            'properties' => $properties,
            'filters' => $filters,
        ]);
    }

    public function create()
    {
        $this->authorize('create', Booking::class);

        $properties = Property::where('status', 'published')
            ->get(['id', 'name', 'price', 'currency', 'surface', 'surface_unit', 'cleaning_fee', 'damage_deposit']);

        return Inertia::render('Bookings/Form', [
            'booking' => null,
            'properties' => $properties,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Booking::class);

        $validated = $request->validate([
            'property_id' => 'required|exists:properties,id',
            'start_date' => 'required|date|after:today',
            'end_date' => 'required|date|after:start_date',
            'guest_name' => 'nullable|string|max:255',
            'guest_email' => 'nullable|email|max:255',
            'guest_phone' => 'nullable|string|max:50',
            'adults' => 'nullable|integer|min:1',
            'children' => 'nullable|integer|min:0',
            'pets' => 'nullable|integer|min:0',
            'notes' => 'nullable|string|max:1000',
            'total_amount' => 'nullable|numeric|min:0',
            'nightly_rate' => 'nullable|numeric|min:0',
            'cleaning_fee' => 'nullable|numeric|min:0',
            'service_fee' => 'nullable|numeric|min:0',
            'damage_deposit' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'source' => 'nullable|string|max:50',
        ]);

        $validated['user_id'] = $request->user()->id;
        $validated['status'] = BookingStatus::Pending->value;
        $validated['guest_count'] = ($validated['adults'] ?? 1) + ($validated['children'] ?? 0);

        $booking = $this->bookingService->create($validated);

        return redirect()->route('dashboard.bookings.index')
            ->with('status', 'Booking created successfully.');
    }

    public function show(Booking $booking)
    {
        $this->authorize('view', $booking);

        $booking->load(['property', 'user']);

        return Inertia::render('Bookings/Show', [
            'booking' => $booking,
        ]);
    }

    public function edit(Booking $booking)
    {
        $this->authorize('update', $booking);

        $properties = Property::where('status', 'published')
            ->get(['id', 'name', 'price', 'currency', 'surface', 'surface_unit', 'cleaning_fee', 'damage_deposit']);

        return Inertia::render('Bookings/Form', [
            'booking' => $booking,
            'properties' => $properties,
        ]);
    }

    public function update(Request $request, Booking $booking)
    {
        $this->authorize('update', $booking);

        $validated = $request->validate([
            'property_id' => 'sometimes|exists:properties,id',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after:start_date',
            'status' => 'nullable|string|in:' . implode(',', BookingStatus::values()),
            'guest_name' => 'nullable|string|max:255',
            'guest_email' => 'nullable|email|max:255',
            'guest_phone' => 'nullable|string|max:50',
            'adults' => 'nullable|integer|min:1',
            'children' => 'nullable|integer|min:0',
            'pets' => 'nullable|integer|min:0',
            'notes' => 'nullable|string|max:1000',
            'total_amount' => 'nullable|numeric|min:0',
            'nightly_rate' => 'nullable|numeric|min:0',
            'cleaning_fee' => 'nullable|numeric|min:0',
            'service_fee' => 'nullable|numeric|min:0',
            'damage_deposit' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'cancellation_reason' => 'nullable|string|max:1000',
        ]);

        if (isset($validated['adults']) || isset($validated['children'])) {
            $validated['guest_count'] = ($validated['adults'] ?? $booking->adults ?? 1) + ($validated['children'] ?? $booking->children ?? 0);
        }

        $this->bookingService->update($booking, $validated);

        return redirect()->route('dashboard.bookings.index')
            ->with('status', 'Booking updated successfully.');
    }

    public function destroy(Booking $booking)
    {
        $this->authorize('delete', $booking);

        $booking->delete();

        return redirect()->route('dashboard.bookings.index')
            ->with('status', 'Booking deleted successfully.');
    }

    public function transition(Request $request, Booking $booking)
    {
        $this->authorize('transition', $booking);

        $validated = $request->validate([
            'status' => 'required|string|in:' . implode(',', BookingStatus::values()),
            'reason' => 'nullable|string|max:1000',
        ]);

        $newStatus = BookingStatus::tryFrom($validated['status']);

        if (!$newStatus) {
            return redirect()->back()->withErrors(['status' => 'Invalid status.']);
        }

        try {
            $this->statusManager->transition($booking, $newStatus, $validated['reason'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()->back()->with('status', "Booking status changed to {$newStatus->label()}.");
    }

    public function availability(Request $request, BookingAvailabilityService $availabilityService)
    {
        $this->authorize('viewAny', Booking::class);

        $validated = $request->validate([
            'property_id' => 'required|exists:properties,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'exclude_booking_id' => 'nullable|exists:bookings,id',
        ]);

        $available = $availabilityService->isAvailable(
            $validated['property_id'],
            $validated['start_date'],
            $validated['end_date'],
            $validated['exclude_booking_id'] ?? null,
        );

        return response()->json(['available' => $available]);
    }

    public function calendar(Request $request)
    {
        $this->authorize('viewAny', Booking::class);

        $year = $request->input('year', now()->year);
        $month = $request->input('month', now()->month);
        $propertyId = $request->input('property_id');

        if ($propertyId) {
            $data = $this->availabilityService->getMonthData((int) $propertyId, (int) $year, (int) $month);
        } else {
            $data = [];
        }

        $properties = Property::select('id', 'name')->get();

        return Inertia::render('Bookings/Calendar', [
            'calendar' => $data,
            'properties' => $properties,
            'year' => (int) $year,
            'month' => (int) $month,
        ]);
    }

    public function calculatePrice(Request $request)
    {
        $this->authorize('viewAny', Booking::class);

        $validated = $request->validate([
            'property_id' => 'required|exists:properties,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'adults' => 'nullable|integer|min:1',
            'children' => 'nullable|integer|min:0',
            'pets' => 'nullable|integer|min:0',
        ]);

        $property = Property::findOrFail($validated['property_id']);

        $price = $this->priceCalculator->calculateTotal(
            $property,
            $validated['start_date'],
            $validated['end_date'],
            $validated['adults'] ?? 1,
            $validated['children'] ?? 0,
            $validated['pets'] ?? 0,
        );

        return response()->json($price);
    }

    public function cancel(Request $request, Booking $booking)
    {
        $this->authorize('transition', $booking);

        $validated = $request->validate([
            'reason' => 'nullable|string|max:1000',
            'cancelled_by' => 'nullable|string|in:host,guest,system',
        ]);

        try {
            $booking = $this->statusManager->transition(
                $booking,
                BookingStatus::Cancelled,
                $validated['reason'] ?? null,
                $validated['cancelled_by'] ?? 'host',
            );

            $refundMeta = $booking->metadata['refund_percent'] ?? null;

            return redirect()->back()->with('status', 'Booking cancelled.' . ($refundMeta !== null ? " Refund: {$refundMeta}% of total." : ''));
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['status' => $e->getMessage()]);
        }
    }

    public function markDepositCollected(Booking $booking)
    {
        $this->authorize('update', $booking);

        $meta = $booking->metadata ?? [];
        $meta['deposit_status'] = 'collected';
        $meta['deposit_collected_at'] = now()->toISOString();
        $booking->update(['metadata' => $meta]);

        return redirect()->back()->with('status', 'Deposit marked as collected.');
    }

    public function markDepositRefunded(Booking $booking)
    {
        $this->authorize('update', $booking);

        $meta = $booking->metadata ?? [];
        $meta['deposit_status'] = 'refunded';
        $meta['deposit_refunded_at'] = now()->toISOString();
        $meta['deposit_released_at'] = now()->toISOString();
        $booking->update(['metadata' => $meta]);

        return redirect()->back()->with('status', 'Deposit refunded to guest.');
    }

    public function markDepositClaimed(Booking $booking)
    {
        $this->authorize('update', $booking);

        $meta = $booking->metadata ?? [];
        $meta['deposit_status'] = 'claimed';
        $meta['deposit_claimed_at'] = now()->toISOString();
        $meta['deposit_released_at'] = now()->toISOString();
        $booking->update(['metadata' => $meta]);

        return redirect()->back()->with('status', 'Deposit claimed by host.');
    }

    public function markPaid(Booking $booking)
    {
        $this->authorize('update', $booking);

        $meta = $booking->metadata ?? [];
        $meta['paid_at'] = now()->toISOString();
        $meta['payment_status'] = 'paid';
        $booking->update(['metadata' => $meta]);

        BookingCacheService::bustPropertyCache($booking->property_id);

        return redirect()->back()->with('status', 'Booking marked as paid.');
    }

    public function policyOverride(Request $request, Booking $booking)
    {
        $this->authorize('update', $booking);

        $validated = $request->validate([
            'policy' => 'required|string|in:flexible,moderate,firm,strict,limited,non_refundable,long_term',
            'full_refund_days' => 'nullable|integer|min:0',
            'partial_refund_cutoff_days' => 'nullable|integer|min:0',
            'partial_refund_percent' => 'nullable|integer|min:0|max:100',
        ]);

        $meta = $booking->metadata ?? [];
        $meta['cancellation_policy'] = $validated['policy'];

        if (isset($validated['full_refund_days'])) {
            $meta['full_refund_days'] = (int) $validated['full_refund_days'];
        }
        if (isset($validated['partial_refund_cutoff_days'])) {
            $meta['partial_refund_cutoff_days'] = (int) $validated['partial_refund_cutoff_days'];
        }
        if (isset($validated['partial_refund_percent'])) {
            $meta['partial_refund_percent'] = (int) $validated['partial_refund_percent'];
        }

        $booking->update(['metadata' => $meta]);

        return redirect()->back()->with('status', 'Cancellation policy override applied.');
    }

    public function requestReview(Booking $booking)
    {
        $this->authorize('update', $booking);

        if ($booking->status !== BookingStatus::Completed->value) {
            return redirect()->back()->withErrors(['status' => 'Only completed bookings can request reviews.']);
        }

        BookingEmailService::sendPostStay($booking->id);

        $meta = $booking->metadata ?? [];
        $meta['review_requested_at'] = now()->toISOString();
        $booking->update(['metadata' => $meta]);

        return redirect()->back()->with('status', 'Review request sent to guest.');
    }

    public function toggleArchive(Booking $booking)
    {
        $this->authorize('update', $booking);

        $booking->update([
            'archived_at' => $booking->archived_at ? null : now(),
        ]);

        return redirect()->back()->with('status', $booking->archived_at ? 'Booking archived.' : 'Booking unarchived.');
    }

    public function bulkTransition(Request $request)
    {
        $this->authorize('viewAny', Booking::class);

        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:bookings,id',
            'status' => 'required|string',
        ]);

        $ids = $validated['ids'];
        $action = $validated['status'];

        if ($action === 'delete') {
            Booking::whereIn('id', $ids)->delete();
            return redirect()->back()->with('status', 'Bookings deleted.');
        }

        if ($action === 'archive') {
            Booking::whereIn('id', $ids)->update(['archived_at' => now()]);
            return redirect()->back()->with('status', 'Bookings archived.');
        }

        if ($action === 'unarchive') {
            Booking::whereIn('id', $ids)->update(['archived_at' => null]);
            return redirect()->back()->with('status', 'Bookings unarchived.');
        }

        $newStatus = BookingStatus::tryFrom($action);
        if (!$newStatus) {
            return redirect()->back()->withErrors(['status' => 'Invalid status.']);
        }

        foreach (Booking::whereIn('id', $ids)->get() as $booking) {
            try {
                $this->statusManager->transition($booking, $newStatus);
            } catch (\InvalidArgumentException $e) {
                // Skip invalid transitions
            }
        }

        return redirect()->back()->with('status', 'Bookings updated successfully.');
    }

    public function blockDates(Request $request, BookingIcalService $icalService)
    {
        $this->authorize('create', Booking::class);

        $validated = $request->validate([
            'property_id' => 'nullable|integer|exists:properties,id',
            'start' => 'required|date',
            'end' => 'required|date|after_or_equal:start',
            'reason' => 'nullable|string|max:500',
        ]);

        if (!$validated['property_id']) {
            return response()->json(['success' => false, 'error' => 'Property is required.'], 400);
        }

        $result = $icalService->blockDates(
            (int) $validated['property_id'],
            $validated['start'],
            $validated['end'],
            $validated['reason'] ?? '',
        );

        return response()->json($result);
    }

    public function exportCsv(Request $request)
    {
        $this->authorize('viewAny', Booking::class);

        $bookings = Booking::with(['property', 'user'])->orderBy('created_at', 'desc')->get();

        $filename = 'bookings-export-' . now()->format('Y-m-d-His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($bookings) {
            $handle = fopen('php://output', 'wb');

            fputcsv($handle, [
                'ID', 'Status', 'Guest Name', 'Guest Email', 'Guest Phone',
                'Property', 'Check In', 'Check Out', 'Nights',
                'Adults', 'Children', 'Pets', 'Total Amount', 'Currency',
                'Source', 'Created At',
            ]);

            foreach ($bookings as $booking) {
                fputcsv($handle, [
                    $booking->id,
                    $booking->status,
                    $booking->guest_name,
                    $booking->guest_email,
                    $booking->guest_phone,
                    $booking->property?->name ?? 'N/A',
                    $booking->start_date,
                    $booking->end_date,
                    $booking->nights(),
                    $booking->adults ?? 0,
                    $booking->children ?? 0,
                    $booking->pets ?? 0,
                    $booking->total_amount,
                    $booking->currency ?? 'USD',
                    $booking->source ?? 'N/A',
                    $booking->created_at?->toDateTimeString(),
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
