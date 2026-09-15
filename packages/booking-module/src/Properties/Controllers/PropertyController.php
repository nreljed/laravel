<?php

namespace Nreljed\BookingModule\Properties\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Nreljed\BookingModule\Booking\Services\BookingPropertyIntegrationService;
use Nreljed\BookingModule\Properties\Models\City;
use Nreljed\BookingModule\Properties\Models\Property;
use Nreljed\BookingModule\Properties\Models\PropertyImage;
use Nreljed\BookingModule\Properties\Requests\StorePropertyRequest;
use Nreljed\BookingModule\Properties\Requests\UpdatePropertyRequest;
use Nreljed\BookingModule\Properties\Requests\UploadImageRequest;
use Nreljed\BookingModule\Properties\Services\ImageUploadService;
use Nreljed\BookingModule\Properties\Services\PropertyFormattingService;
use Nreljed\BookingModule\Properties\Services\PropertyService;
use Nreljed\BookingModule\Properties\Services\PropertyUserCountManager;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\Platform\Services\SettingsService;

class PropertyController extends Controller
{
    public function __construct(
        protected PropertyService $propertyService,
        protected PropertyFormattingService $formatter,
        protected ImageUploadService $imageService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Property::class);

        $filters = $request->only([
            'search', 'status', 'type_ids', 'category_ids', 'feature_ids', 'status_ids',
            'featured', 'price_min', 'price_max', 'sort', 'direction', 'per_page',
        ]);

        $properties = $this->propertyService->search($filters);

        $properties->getCollection()->transform(
            fn (Property $property) => $this->formatter->formatProperty($property)
        );

        return Inertia::render('properties/index', [
            'properties' => $properties->items(),
            'filters' => $filters,
            'filterOptions' => $this->propertyService->getFilterOptions(),
            'stats' => [
                'total' => Property::count(),
                'published' => Property::where('status', 'published')->count(),
                'draft' => Property::where('status', 'draft')->count(),
                'totalValue' => Property::where('status', 'published')->sum('price'),
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Property::class);

        $settings = SettingsService::getAllGrouped();
        $propertiesSettings = $settings['properties'] ?? [];
        $filterOptions = $this->propertyService->getFilterOptions();

        return Inertia::render('properties/form', [
            'property' => null,
            'property_types' => $filterOptions['types'] ?? [],
            'property_statuses' => $filterOptions['statuses'] ?? [],
            'cities' => $filterOptions['cities'] ?? [],
            'property_features' => $filterOptions['features'] ?? [],
            'settings' => $propertiesSettings,
            'nonce' => csrf_token(),
            'booking_active' => BookingPropertyIntegrationService::isModuleActive(),
            'booking' => [
                'enabled' => false,
                'settings' => BookingPropertyIntegrationService::getFieldDefaults(),
            ],
            'default_status' => $propertiesSettings['default_status'] ?? 'draft',
        ]);
    }

    public function store(StorePropertyRequest $request): RedirectResponse
    {
        $this->authorize('create', Property::class);

        $data = $this->transformFormData($request);
        $data['user_id'] = $request->user()->id;

        $property = $this->propertyService->create(
            $data,
            $request->input('taxonomies.property_type') ? [$request->input('taxonomies.property_type')] : [],
            $request->input('taxonomies.property_city') ? (int) $request->input('taxonomies.property_city') : null,
            $request->input('taxonomies.property_features', []),
            $request->input('taxonomies.property_status') ? [$request->input('taxonomies.property_status')] : [],
        );

        if ($request->input('booking')) {
            BookingPropertyIntegrationService::savePropertyBookingData(
                $property->id,
                $request->input('booking'),
            );
        }

        app(PropertyUserCountManager::class)->updateCount($request->user()->id);

        return redirect()->route('dashboard.properties.index')
            ->with('status', 'Property created successfully.');
    }

    public function edit(Property $property): Response
    {
        $this->authorize('update', $property);

        $property->load(['types', 'city', 'features', 'statuses', 'images', 'featuredImage']);
        $settings = SettingsService::getAllGrouped();
        $propertiesSettings = $settings['properties'] ?? [];
        $filterOptions = $this->propertyService->getFilterOptions();

        $meta = $property->metadata ?? [];
        if (empty($meta['latitude']) && $property->latitude) {
            $meta['latitude'] = $property->latitude;
        }
        if (empty($meta['longitude']) && $property->longitude) {
            $meta['longitude'] = $property->longitude;
        }
        $meta['gallery'] = $property->images->map(fn ($img) => [
            'id' => $img->id,
            'attachment_id' => $img->id,
            'url' => $img->url,
            'url_raw' => $img->url,
            'name' => $img->alt_text ?: $img->filename,
            'featured' => $img->is_featured,
        ])->toArray();

        $bookingData = null;
        if (BookingPropertyIntegrationService::isModuleActive()) {
            $bookingData = BookingPropertyIntegrationService::getPropertyBookingData($property->id);
        }

        $publishDate = null;
        if ($property->status === 'future' && $property->published_at) {
            $publishDate = $property->published_at->format('Y-m-d\TH:i:s');
        }

        return Inertia::render('properties/form', [
            'property' => array_merge(
                $this->formatter->formatProperty($property),
                ['publish_date' => $publishDate],
            ),
            'property_types' => $filterOptions['types'] ?? [],
            'property_statuses' => $filterOptions['statuses'] ?? [],
            'cities' => $filterOptions['cities'] ?? [],
            'property_features' => $filterOptions['features'] ?? [],
            'meta' => $meta,
            'selected_type' => $property->types->first()?->id,
            'selected_city' => $property->city_id,
            'selected_status' => $property->statuses->first()?->id,
            'selected_features' => $property->features->pluck('id')->toArray(),
            'settings' => $propertiesSettings,
            'nonce' => csrf_token(),
            'booking' => $bookingData,
            'booking_active' => BookingPropertyIntegrationService::isModuleActive(),
            'default_status' => $propertiesSettings['default_status'] ?? 'draft',
        ]);
    }

    public function update(UpdatePropertyRequest $request, Property $property): RedirectResponse
    {
        $this->authorize('update', $property);

        $data = $this->transformFormData($request);

        $this->propertyService->update(
            $property,
            $data,
            $request->input('taxonomies.property_type') ? [$request->input('taxonomies.property_type')] : [],
            $request->input('taxonomies.property_city') ? (int) $request->input('taxonomies.property_city') : null,
            $request->input('taxonomies.property_features', []),
            $request->input('taxonomies.property_status') ? [$request->input('taxonomies.property_status')] : [],
        );

        if ($request->input('booking')) {
            BookingPropertyIntegrationService::savePropertyBookingData(
                $property->id,
                $request->input('booking'),
            );
        }

        return redirect()->route('dashboard.properties.index')
            ->with('status', 'Property updated successfully.');
    }

    protected function transformFormData(Request $request): array
    {
        $meta = $request->input('meta', []);
        $fmtDefaults = SettingsService::getFormattingDefaults();

        $metadata = [
            'listing_id' => $meta['listing_id'] ?? '',
            'bedrooms' => (int) ($meta['bedrooms'] ?? 0),
            'bathrooms' => (int) ($meta['bathrooms'] ?? 0),
            'garages' => (int) ($meta['garages'] ?? 0),
            'rooms' => $meta['rooms'] ?? 0,
            'year' => $meta['year'] ?? '',
            'floor_level' => $meta['floor_level'] ?? 0,
            'rental_period' => $meta['rental_period'] ?? 'month',
            'enable_address' => ! empty($meta['enable_address']),
            'google_map' => $meta['google_map'] ?? '',
            'enable_details' => ! empty($meta['enable_details']),
            'enable_floors' => ! empty($meta['enable_floors']),
            'additional_details_json' => $meta['additional_details'] ?? [],
            'floor_plans_json' => $meta['floor_plans'] ?? [],
            'latitude' => ($meta['latitude'] ?? '') !== '' ? $meta['latitude'] : null,
            'longitude' => ($meta['longitude'] ?? '') !== '' ? $meta['longitude'] : null,
            'map_data' => ! empty($meta['map_data']) ? $meta['map_data'] : '',
        ];

        $status = $request->input('status', 'draft');
        $publishDate = $request->input('publish_date');
        $publishedAt = null;

        if ($status === 'publish') {
            $status = 'published';
            $publishedAt = now();
        } elseif ($status === 'future') {
            $publishedAt = $publishDate ? Carbon::parse($publishDate) : now();
        } elseif ($status === 'draft') {
            $publishedAt = null;
        }

        if ($status === 'published' && ! $publishedAt) {
            $publishedAt = now();
        }

        $data = [
            'name' => $request->input('title'),
            'code' => $request->input('code') ?: null,
            'status' => $status,
            'published_at' => $publishedAt,
            'description' => $meta['description'] ?? null,
            'price' => ($meta['price'] ?? '') !== '' ? $meta['price'] : null,
            'surface' => ($meta['surface'] ?? '') !== '' ? $meta['surface'] : null,
            'currency' => $fmtDefaults['default_currency'] ?? 'USD',
            'surface_unit' => $fmtDefaults['surface_unit'] ?? 'sq_m',
            'address' => $meta['address'] ?? null,
            'city' => $meta['locality'] ?? null,
            'zip' => $meta['zip'] ?? null,
            'country' => $fmtDefaults['default_country'] ?? 'TN',
            'latitude' => ($meta['latitude'] ?? '') !== '' ? $meta['latitude'] : null,
            'longitude' => ($meta['longitude'] ?? '') !== '' ? $meta['longitude'] : null,
            'video_url' => $meta['video'] ?? null,
            'metadata' => $metadata,
        ];

        $cityId = $request->input('taxonomies.property_city');
        if ($cityId) {
            $city = City::find($cityId);
            if ($city) {
                $data['state'] = $city->governorate;
            }
        }

        return $data;
    }

    public function destroy(Property $property): RedirectResponse
    {
        $this->authorize('delete', $property);

        $this->propertyService->deleteWithImages($property);

        app(PropertyUserCountManager::class)->updateCount($property->user_id);

        return redirect()->route('dashboard.properties.index')
            ->with('status', 'Property deleted successfully.');
    }

    public function uploadImage(UploadImageRequest $request, Property $property): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $property);

        $image = $this->imageService->upload(
            $request->file('file'),
            $property,
            $request->boolean('is_featured'),
            $request->input('alt_text'),
        );

        if ($request->expectsJson()) {
            return response()->json([
                'url' => $image->url,
                'path' => $image->path,
                'attachment_id' => $image->id,
            ]);
        }

        return back()->with('status', 'Image uploaded successfully.');
    }

    public function deleteImage(Property $property, PropertyImage $image): RedirectResponse
    {
        $this->authorize('update', $property);

        abort_if($image->property_id !== $property->id, 404);

        $this->imageService->delete($image);

        return back()->with('status', 'Image deleted successfully.');
    }

    public function reorderImages(Request $request, Property $property): RedirectResponse
    {
        $this->authorize('update', $property);

        $request->validate(['ordered_ids' => 'required|array', 'ordered_ids.*' => 'integer|exists:property_images,id']);

        $this->imageService->reorder($property, $request->input('ordered_ids'));

        return back()->with('status', 'Images reordered successfully.');
    }

    public function setFeaturedImage(Property $property, PropertyImage $image): RedirectResponse
    {
        $this->authorize('update', $property);

        abort_if($image->property_id !== $property->id, 404);

        $this->imageService->setFeatured($image);

        return back()->with('status', 'Featured image updated.');
    }

    public function updateStatus(Request $request, Property $property): RedirectResponse
    {
        $this->authorize('update', $property);

        $validated = $request->validate([
            'status' => 'required|string|in:draft,published,future,archived',
            'publish_date' => 'nullable|string',
        ]);

        $update = ['status' => $validated['status']];

        if ($validated['status'] === 'published') {
            if (! $property->published_at) {
                $update['published_at'] = now();
            }
        } elseif ($validated['status'] === 'future') {
            $update['published_at'] = $validated['publish_date']
                ? Carbon::parse($validated['publish_date'])
                : null;
        } elseif ($validated['status'] === 'draft') {
            $update['published_at'] = null;
        }

        $property->update($update);

        app(PropertyUserCountManager::class)->updateCount($property->user_id);

        $messages = [
            'published' => 'Property published successfully.',
            'future' => 'Property scheduled for publication.',
            'draft' => 'Property moved to draft.',
            'archived' => 'Property archived.',
        ];

        return redirect()->back()->with('status', $messages[$validated['status']] ?? 'Status updated.');
    }

    public function bulkAction(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', Property::class);

        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:properties,id',
            'action' => 'required|string|in:delete,draft,publish',
        ]);

        $ids = $validated['ids'];
        $action = $validated['action'];
        $user = $request->user();
        $processed = 0;

        $properties = Property::whereIn('id', $ids)->get();

        foreach ($properties as $property) {
            if ($action === 'delete' && $user->cannot('delete', $property)) {
                continue;
            }
            if ($action !== 'delete' && $user->cannot('update', $property)) {
                continue;
            }

            match ($action) {
                'delete' => $this->propertyService->deleteWithImages($property),
                'draft' => $property->update(['status' => 'draft', 'published_at' => null]),
                'publish' => $property->update(['status' => 'published', 'published_at' => $property->published_at ?? now()]),
            };

            $processed++;
        }

        $actionLabels = [
            'delete' => 'deleted',
            'draft' => 'saved as draft',
            'publish' => 'published',
        ];

        return redirect()->back()->with(
            'status',
            "{$processed} property(s) {$actionLabels[$action]} successfully.",
        );
    }

    public function dashboard(): Response
    {
        $this->authorize('viewAny', Property::class);

        return Inertia::render('properties/dashboard', [
            'stats' => [
                'total' => Property::count(),
                'published' => Property::where('status', 'published')->count(),
                'draft' => Property::where('status', 'draft')->count(),
                'totalValue' => (float) Property::where('status', 'published')->sum('price'),
            ],
            'recentProperties' => Property::latest()->take(5)->get(['id', 'name', 'status', 'price', 'created_at']),
        ]);
    }
}
