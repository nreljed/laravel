<?php

namespace Nreljed\BookingModule\Properties\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Nreljed\BookingModule\Properties\Models\City;
use Nreljed\BookingModule\Properties\Models\Property;
use Nreljed\BookingModule\Properties\Models\PropertyFeature;
use Nreljed\BookingModule\Properties\Models\PropertyStatus;
use Nreljed\BookingModule\Properties\Models\PropertyType;

class PropertyService
{
    public function create(array $data, array $typeIds = [], ?int $cityId = null, array $featureIds = [], array $statusIds = []): Property
    {
        if ($cityId) {
            $data['city_id'] = $cityId;
        }

        $property = Property::create($data);

        if ($typeIds) {
            $property->types()->sync($typeIds);
        }
        if ($featureIds) {
            $property->features()->sync($featureIds);
        }
        if ($statusIds) {
            $property->statuses()->sync($statusIds);
        }

        return $property->load(['types', 'city', 'features', 'statuses']);
    }

    public function update(Property $property, array $data, array $typeIds = [], ?int $cityId = null, array $featureIds = [], array $statusIds = []): Property
    {
        if ($cityId !== null) {
            $data['city_id'] = $cityId;
        }

        $property->update($data);

        if (array_key_exists('types', $data) || ! empty($typeIds)) {
            $property->types()->sync($typeIds);
        }
        if (array_key_exists('features', $data) || ! empty($featureIds)) {
            $property->features()->sync($featureIds);
        }
        if (! empty($statusIds)) {
            $property->statuses()->sync($statusIds);
        }

        return $property->load(['types', 'city', 'features', 'statuses', 'images', 'featuredImage']);
    }

    public function deleteWithImages(Property $property): void
    {
        $property->images->each(fn ($image) => $image->delete());
        $property->bookings()->delete();
        $property->types()->detach();
        $property->features()->detach();
        $property->statuses()->detach();
        $property->delete();
    }

    public function search(array $filters = []): LengthAwarePaginator
    {
        $query = Property::with(['types', 'city', 'features', 'statuses', 'featuredImage', 'user']);

        if (! empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['search']}%")
                    ->orWhere('city', 'like', "%{$filters['search']}%")
                    ->orWhere('address', 'like', "%{$filters['search']}%");
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['type_ids'])) {
            $query->whereHas('types', fn ($q) => $q->whereIn('property_types.id', (array) $filters['type_ids']));
        }

        if (! empty($filters['category_ids'])) {
            $query->where('city_id', $filters['category_ids']);
        }

        if (! empty($filters['feature_ids'])) {
            $query->whereHas('features', fn ($q) => $q->whereIn('property_features.id', (array) $filters['feature_ids']));
        }

        if (! empty($filters['status_ids'])) {
            $query->whereHas('statuses', fn ($q) => $q->whereIn('property_statuses.id', (array) $filters['status_ids']));
        }

        if (! empty($filters['featured'])) {
            $query->where('featured', true);
        }

        if (! empty($filters['price_min'])) {
            $query->where('price', '>=', $filters['price_min']);
        }

        if (! empty($filters['price_max'])) {
            $query->where('price', '<=', $filters['price_max']);
        }

        $sortField = $filters['sort'] ?? 'created_at';
        $sortDir = $filters['direction'] ?? 'desc';
        $query->orderBy($sortField, $sortDir);

        $perPage = $filters['per_page'] ?? 12;

        return $query->paginate($perPage);
    }

    public function getFilterOptions(): array
    {
        return [
            'types' => PropertyType::where('is_active', true)->orderBy('sort_order')->get(),
            'cities' => City::where('is_active', true)->orderBy('governorate_slug')->orderBy('sort_order')->get(),
            'features' => PropertyFeature::where('is_active', true)->orderBy('group')->orderBy('sort_order')->get(),
            'statuses' => PropertyStatus::where('is_active', true)->orderBy('sort_order')->get(),
        ];
    }
}
