<?php

namespace Nreljed\BookingModule\Properties\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Nreljed\BookingModule\Booking\Services\BookingPropertyIntegrationService;
use Nreljed\BookingModule\Properties\Models\City;
use Nreljed\BookingModule\Properties\Models\Property;
use Nreljed\BookingModule\Properties\Models\PropertyFeature;
use Nreljed\BookingModule\Properties\Models\PropertyStatus;
use Nreljed\BookingModule\Properties\Models\PropertyType;
use Nreljed\BookingModule\Properties\Services\PropertyFormattingService;
use Nreljed\Platform\Http\Controllers\Controller;

class PublicPropertyController extends Controller
{
    public function __construct(
        protected PropertyFormattingService $formatter,
    ) {}

    public function index(Request $request)
    {
        $query = Property::with(['types', 'city', 'features', 'statuses', 'featuredImage', 'user', 'images'])
            ->where('status', 'published')
            ->where(function ($q) {
                $q->whereNull('published_at')->orWhere('published_at', '<=', now());
            });

        if ($search = $request->search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%");
            });
        }

        if ($typeId = $request->type_id) {
            $query->whereHas('types', fn ($q) => $q->where('property_types.id', $typeId));
        }

        if ($cityId = $request->city_id) {
            $query->where('city_id', $cityId);
        }

        if ($statusId = $request->status_id) {
            $query->whereHas('statuses', fn ($q) => $q->where('property_statuses.id', $statusId));
        }

        if ($featureIds = $request->input('feature_ids')) {
            $featureIds = is_array($featureIds) ? $featureIds : explode(',', $featureIds);
            foreach ($featureIds as $fid) {
                $query->whereHas('features', fn ($q) => $q->where('property_features.id', $fid));
            }
        }

        if ($request->filled('price_min')) {
            $query->where('price', '>=', $request->price_min);
        }

        if ($request->filled('price_max')) {
            $query->where('price', '<=', $request->price_max);
        }

        if ($request->filled('surface_min')) {
            $query->where('surface', '>=', $request->surface_min);
        }

        if ($request->filled('surface_max')) {
            $query->where('surface', '<=', $request->surface_max);
        }

        if ($request->filled('bedrooms')) {
            $query->where('metadata->bedrooms', '>=', $request->bedrooms);
        }

        if ($request->featured) {
            $query->where('featured', true);
        }

        $sortField = match ($request->sort) {
            'price' => 'price',
            'price_desc' => 'price',
            'date' => 'created_at',
            'date_asc' => 'created_at',
            'name' => 'name',
            default => 'created_at',
        };

        $sortDir = match ($request->sort) {
            'price', 'date_asc', 'name' => 'asc',
            default => 'desc',
        };

        $perPage = min((int) ($request->per_page ?? 12), 48);

        $properties = $query->orderBy($sortField, $sortDir)->paginate($perPage);

        $properties->getCollection()->transform(
            fn (Property $property) => $this->formatter->formatProperty($property)
        );

        $types = PropertyType::where('is_active', true)->orderBy('sort_order')->get();
        $cities = City::where('is_active', true)->orderBy('name')->get();
        $statuses = PropertyStatus::where('is_active', true)->orderBy('sort_order')->get();
        $features = PropertyFeature::where('is_active', true)->orderBy('group')->orderBy('sort_order')->get();

        return Inertia::render('properties/public/index', [
            'properties' => $properties,
            'filters' => $request->only([
                'search', 'type_id', 'city_id', 'status_id', 'feature_ids',
                'price_min', 'price_max', 'surface_min', 'surface_max',
                'bedrooms', 'featured', 'sort', 'per_page',
            ]),
            'types' => $types,
            'cities' => $cities,
            'statuses' => $statuses,
            'features' => $features,
            'featureGroups' => $features->groupBy('group')->map(fn ($group, $key) => [
                'group' => $key,
                'features' => $group,
            ])->values(),
        ]);
    }

    public function show(Request $request, string $slug)
    {
        $property = Property::with([
            'types', 'city', 'features', 'statuses', 'featuredImage', 'images', 'user',
        ])
            ->where('slug', $slug)
            ->where('status', 'published')
            ->firstOrFail();

        $groupedFeatures = $property->features
            ->groupBy('group')
            ->map(fn ($group, $key) => [
                'group' => $key ?: 'Other',
                'features' => $group->map(fn ($f) => [
                    'id' => $f->id,
                    'name' => $f->name,
                    'slug' => $f->slug,
                    'icon' => $f->icon,
                ]),
            ])
            ->values();

        $similar = Property::with(['types', 'city', 'featuredImage'])
            ->where('id', '!=', $property->id)
            ->where('status', 'published')
            ->where(function ($q) use ($property) {
                if ($property->city_id) {
                    $q->where('city_id', $property->city_id);
                }
                $typeIds = $property->types->pluck('id')->toArray();
                if (! empty($typeIds)) {
                    $q->orWhereHas('types', fn ($tq) => $tq->whereIn('property_types.id', $typeIds));
                }
            })
            ->inRandomOrder()
            ->take(3)
            ->get();

        $similar->transform(fn (Property $p) => $this->formatter->formatProperty($p));

        $bookingData = null;
        if (class_exists(BookingPropertyIntegrationService::class)) {
            $bookingData = BookingPropertyIntegrationService::getPropertyBookingData($property->id);
        }

        $isBookable = $bookingData && ($bookingData['enabled'] ?? false);

        return Inertia::render('properties/public/show', [
            'property' => $this->formatter->formatProperty($property),
            'groupedFeatures' => $groupedFeatures,
            'similarProperties' => $similar,
            'bookingData' => $bookingData,
            'isBookable' => $isBookable,
            'images' => $property->images->map(fn ($img) => [
                'id' => $img->id,
                'url' => $img->url,
                'thumbnail_url' => $img->thumbnail_url,
                'alt_text' => $img->alt_text,
                'is_featured' => $img->is_featured,
            ]),
        ]);
    }
}
