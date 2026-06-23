<?php

namespace App\Modules\Properties\Services;

class PropertyMeta
{
    public static function getFields(): array
    {
        return [
            'basic' => [
                'listing_id' => [
                    'type' => 'text',
                    'label' => 'Listing ID',
                    'description' => 'Unique identifier for the property',
                ],
                'property_price' => [
                    'type' => 'number',
                    'label' => 'Price',
                    'description' => 'Property price',
                ],
                'rental_period' => [
                    'type' => 'select',
                    'label' => 'Rental Period',
                    'description' => 'Rental period for rental properties',
                    'options' => [
                        'day' => 'Per Day',
                        'week' => 'Per Week',
                        'month' => 'Per Month',
                        'year' => 'Per Year',
                    ],
                ],
                'property_surface' => [
                    'type' => 'number',
                    'label' => 'Surface Area (m²)',
                    'description' => 'Total surface in square meters',
                ],
            ],

            'details' => [
                'property_bedrooms' => [
                    'type' => 'number',
                    'label' => 'Bedrooms',
                ],
                'property_bathrooms' => [
                    'type' => 'number',
                    'label' => 'Bathrooms',
                ],
                'property_garages' => [
                    'type' => 'number',
                    'label' => 'Garages',
                ],
                'property_rooms' => [
                    'type' => 'number',
                    'label' => 'Total Rooms',
                ],
                'property_year' => [
                    'type' => 'number',
                    'label' => 'Year Built',
                    'description' => 'Construction year',
                ],
                'property_floor_level' => [
                    'type' => 'number',
                    'label' => 'Floor Level',
                ],
            ],

            'address' => [
                'enable_address' => [
                    'type' => 'boolean',
                    'label' => 'Show Address',
                    'description' => 'Enable to display address details publicly',
                    'default' => true,
                ],
                'property_address' => [
                    'type' => 'text',
                    'label' => 'Street Address',
                    'description' => 'Street name and number',
                ],
                'property_zip' => [
                    'type' => 'text',
                    'label' => 'Postal Code / Zip',
                ],
                'property_locality' => [
                    'type' => 'text',
                    'label' => 'City / Locality',
                    'description' => 'Specific city or town name',
                ],
                'property_latitude' => [
                    'type' => 'text',
                    'label' => 'Latitude',
                ],
                'property_longitude' => [
                    'type' => 'text',
                    'label' => 'Longitude',
                ],
                'property_map_data' => [
                    'type' => 'json',
                    'label' => 'Map Data',
                    'description' => 'Stored map geometry (GeoJSON)',
                ],
            ],

            'media' => [
                'property_gallery' => [
                    'type' => 'gallery',
                    'label' => 'Gallery',
                    'description' => 'Property image gallery',
                ],
                'property_video' => [
                    'type' => 'url',
                    'label' => 'Video URL',
                    'description' => 'Link to a video tour (YouTube, Vimeo, etc.)',
                ],
            ],

            'content' => [
                'property_desc' => [
                    'type' => 'textarea',
                    'label' => 'Description',
                    'description' => 'Detailed property description',
                ],
            ],

            'amenities' => [
                'enable_details' => [
                    'type' => 'boolean',
                    'label' => 'Enable Additional Details',
                    'default' => false,
                ],
                'additional_details' => [
                    'type' => 'repeater',
                    'label' => 'Additional Details',
                    'storage' => 'json',
                    'json_key' => 'additional_details_json',
                    'enable_key' => 'enable_details',
                    'min_items' => 0,
                    'max_items' => 20,
                    'fields' => [
                        'detail_title' => ['type' => 'text', 'label' => 'Title'],
                        'detail_value' => ['type' => 'text', 'label' => 'Value'],
                    ],
                ],
            ],

            'floors' => [
                'enable_floors' => [
                    'type' => 'boolean',
                    'label' => 'Enable Floor Plans',
                    'default' => false,
                ],
                'floor_plans' => [
                    'type' => 'repeater',
                    'label' => 'Floor Plans',
                    'storage' => 'json',
                    'json_key' => 'floor_plans_json',
                    'enable_key' => 'enable_floors',
                    'min_items' => 0,
                    'max_items' => 10,
                    'fields' => [
                        'floor_name' => ['type' => 'text', 'label' => 'Floor Name'],
                        'floor_price' => ['type' => 'number', 'label' => 'Price'],
                        'floor_surface' => ['type' => 'number', 'label' => 'Surface (m²)'],
                        'floor_bedrooms' => ['type' => 'number', 'label' => 'Bedrooms'],
                        'floor_bathrooms' => ['type' => 'number', 'label' => 'Bathrooms'],
                        'floor_desc' => ['type' => 'textarea', 'label' => 'Description'],
                    ],
                ],
            ],
        ];
    }

    public static function getMetaKeys(): array
    {
        $keys = [];

        foreach (self::getFields() as $group => $fields) {
            foreach ($fields as $key => $config) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    public static function getRepeaterFields(): array
    {
        $repeaters = [];

        foreach (self::getFields() as $group => $fields) {
            foreach ($fields as $key => $config) {
                if ($config['type'] === 'repeater') {
                    $repeaters[$key] = $config;
                }
            }
        }

        return $repeaters;
    }

    public static function getFieldsByGroup(string $group): array
    {
        $fields = self::getFields();

        return $fields[$group] ?? [];
    }

    public static function getField(string $key): ?array
    {
        foreach (self::getFields() as $group => $fields) {
            if (isset($fields[$key])) {
                return $fields[$key];
            }
        }

        return null;
    }

    public static function getRepeaterJsonKey(string $fieldKey): ?string
    {
        $field = self::getField($fieldKey);

        if (! $field || $field['type'] !== 'repeater') {
            return null;
        }

        return $field['json_key'] ?? "{$fieldKey}_json";
    }

    public static function isJsonRepeater(string $fieldKey): bool
    {
        $field = self::getField($fieldKey);

        if (! $field || $field['type'] !== 'repeater') {
            return false;
        }

        return ($field['storage'] ?? 'acf') === 'json';
    }

    public static function getJsonRepeaters(): array
    {
        $repeaters = self::getRepeaterFields();
        $jsonRepeaters = [];

        foreach ($repeaters as $key => $config) {
            if (($config['storage'] ?? 'acf') === 'json') {
                $jsonRepeaters[$key] = $config;
            }
        }

        return $jsonRepeaters;
    }
}
