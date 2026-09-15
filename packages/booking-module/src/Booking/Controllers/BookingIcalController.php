<?php

namespace Nreljed\BookingModule\Booking\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Nreljed\BookingModule\Booking\Services\BookingIcalService;
use Nreljed\BookingModule\Properties\Models\Property;
use Nreljed\Platform\Http\Controllers\Controller;

class BookingIcalController extends Controller
{
    public function __construct(
        protected BookingIcalService $icalService,
    ) {}

    public function import(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url' => 'required|url|max:2048',
            'name' => 'nullable|string|max:255',
            'property_id' => 'required|integer|exists:properties,id',
        ]);

        $this->authorize('update', Property::findOrFail($validated['property_id']));

        $result = $this->icalService->importFeed(
            (int) $validated['property_id'],
            $validated['url'],
            $validated['name'] ?? '',
        );

        if (! $result['success']) {
            return response()->json($result, 400);
        }

        return response()->json($result);
    }

    public function feeds(Request $request): JsonResponse
    {
        $propertyId = (int) $request->input('property_id');
        if (! $propertyId) {
            return response()->json(['feeds' => []]);
        }

        $this->authorize('view', Property::findOrFail($propertyId));

        return response()->json(['feeds' => $this->icalService->getFeeds($propertyId)]);
    }

    public function sync(string $feedId, Request $request): JsonResponse
    {
        $propertyId = (int) $request->input('property_id');
        if (! $propertyId) {
            return response()->json(['success' => false, 'error' => 'Property ID required.'], 400);
        }

        $this->authorize('update', Property::findOrFail($propertyId));

        $result = $this->icalService->syncFeed($propertyId, $feedId);

        if (! $result['success']) {
            return response()->json($result, 400);
        }

        return response()->json($result);
    }

    public function destroy(string $feedId, Request $request): JsonResponse
    {
        $propertyId = (int) $request->input('property_id');
        if (! $propertyId) {
            return response()->json(['success' => false, 'error' => 'Property ID required.'], 400);
        }

        $this->authorize('update', Property::findOrFail($propertyId));

        $service = $this->icalService;
        $deleted = $service->deleteFeed($propertyId, $feedId);

        if (! $deleted) {
            return response()->json(['success' => false, 'error' => 'Feed not found.'], 404);
        }

        return response()->json(['success' => true]);
    }
}
