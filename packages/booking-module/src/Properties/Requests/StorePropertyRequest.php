<?php

namespace Nreljed\BookingModule\Properties\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Nreljed\BookingModule\Properties\Models\Property;

class StorePropertyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Property::class);
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'status' => 'nullable|string|in:draft,published,archived',
            'meta' => 'nullable|array',
            'meta.description' => 'nullable|string',
            'meta.price' => 'nullable|numeric|min:0',
            'meta.surface' => 'nullable|numeric|min:0',
            'meta.bedrooms' => 'nullable|integer|min:0',
            'meta.bathrooms' => 'nullable|integer|min:0',
            'meta.garages' => 'nullable|integer|min:0',
            'meta.rooms' => 'nullable|integer|min:0',
            'meta.year' => 'nullable|string',
            'meta.floor_level' => 'nullable|integer|min:0',
            'meta.enable_address' => 'nullable|boolean',
            'meta.address' => 'nullable|string|max:255',
            'meta.locality' => 'nullable|string|max:255',
            'meta.zip' => 'nullable|string|max:20',
            'meta.latitude' => 'nullable|numeric|between:-90,90',
            'meta.longitude' => 'nullable|numeric|between:-180,180',
            'meta.google_map' => 'nullable|string',
            'meta.video' => 'nullable|url|max:500',
            'meta.enable_details' => 'nullable|boolean',
            'meta.additional_details' => 'nullable|array',
            'meta.enable_floors' => 'nullable|boolean',
            'meta.floor_plans' => 'nullable|array',
            'meta.gallery' => 'nullable|array',
            'meta.listing_id' => 'nullable|string',
            'meta.rental_period' => 'nullable|string',
            'taxonomies' => 'nullable|array',
            'taxonomies.property_type' => 'nullable|exists:property_types,id',
            'taxonomies.property_status' => 'nullable|exists:property_statuses,id',
            'taxonomies.property_city' => 'nullable|exists:cities,id',
            'taxonomies.property_features' => 'nullable|array',
            'taxonomies.property_features.*' => 'exists:property_features,id',
            'booking' => 'nullable|array',
            'booking.enabled' => 'nullable|boolean',
            'booking.settings' => 'nullable|array',
        ];
    }
}
