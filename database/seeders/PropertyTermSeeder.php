<?php

namespace Database\Seeders;

use App\Modules\Properties\Models\PropertyCategory;
use App\Modules\Properties\Models\PropertyFeature;
use App\Modules\Properties\Models\PropertyType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PropertyTermSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Rent', 'slug' => 'rent', 'description' => 'Properties available for rent', 'sort_order' => 1],
            ['name' => 'Buy', 'slug' => 'buy', 'description' => 'Properties available for purchase', 'sort_order' => 2],
            ['name' => 'Short Stay', 'slug' => 'short-stay', 'description' => 'Short-term rental properties', 'sort_order' => 3],
            ['name' => 'Commercial', 'slug' => 'commercial', 'description' => 'Commercial property listings', 'sort_order' => 4],
        ];

        foreach ($types as $type) {
            PropertyType::firstOrCreate(['slug' => $type['slug']], $type);
        }

        $categories = [
            ['name' => 'Apartment', 'slug' => 'apartment', 'sort_order' => 1, 'children' => [
                ['name' => 'Studio', 'slug' => 'studio', 'sort_order' => 1],
                ['name' => '1 Bedroom', 'slug' => '1-bedroom', 'sort_order' => 2],
                ['name' => '2 Bedroom', 'slug' => '2-bedroom', 'sort_order' => 3],
                ['name' => 'Penthouse', 'slug' => 'penthouse', 'sort_order' => 4],
            ]],
            ['name' => 'House', 'slug' => 'house', 'sort_order' => 2, 'children' => [
                ['name' => 'Villa', 'slug' => 'villa', 'sort_order' => 1],
                ['name' => 'Townhouse', 'slug' => 'townhouse', 'sort_order' => 2],
                ['name' => 'Bungalow', 'slug' => 'bungalow', 'sort_order' => 3],
            ]],
            ['name' => 'Commercial', 'slug' => 'commercial-property', 'sort_order' => 3, 'children' => [
                ['name' => 'Office', 'slug' => 'office', 'sort_order' => 1],
                ['name' => 'Retail', 'slug' => 'retail', 'sort_order' => 2],
                ['name' => 'Warehouse', 'slug' => 'warehouse', 'sort_order' => 3],
            ]],
            ['name' => 'Land', 'slug' => 'land', 'sort_order' => 4],
        ];

        foreach ($categories as $catData) {
            $children = $catData['children'] ?? [];
            unset($catData['children']);
            $parent = PropertyCategory::firstOrCreate(['slug' => $catData['slug']], $catData);
            foreach ($children as $child) {
                PropertyCategory::firstOrCreate(
                    ['slug' => $child['slug']],
                    array_merge($child, ['parent_id' => $parent->id])
                );
            }
        }

        $groups = [
            'Interior' => [
                ['name' => 'Air Conditioning', 'icon' => 'Snowflake'],
                ['name' => 'Heating', 'icon' => 'Thermometer'],
                ['name' => 'Washer', 'icon' => 'WashingMachine'],
                ['name' => 'Dryer', 'icon' => 'Dryer'],
                ['name' => 'Dishwasher', 'icon' => 'Utensils'],
                ['name' => 'Refrigerator', 'icon' => 'Refrigerator'],
                ['name' => 'Oven', 'icon' => 'CookingPot'],
                ['name' => 'Microwave', 'icon' => 'Microwave'],
                ['name' => 'Furnished', 'icon' => 'Sofa'],
            ],
            'Exterior' => [
                ['name' => 'Swimming Pool', 'icon' => 'Pool'],
                ['name' => 'Garden', 'icon' => 'Flower2'],
                ['name' => 'Terrace', 'icon' => 'Sun'],
                ['name' => 'Balcony', 'icon' => 'Building2'],
                ['name' => 'Garage', 'icon' => 'Car'],
                ['name' => 'Parking', 'icon' => 'ParkingCircle'],
                ['name' => 'Rooftop', 'icon' => 'ArrowUpToLine'],
            ],
            'Utilities' => [
                ['name' => 'WiFi', 'icon' => 'Wifi'],
                ['name' => 'Water', 'icon' => 'Droplets'],
                ['name' => 'Electricity', 'icon' => 'Zap'],
                ['name' => 'Gas', 'icon' => 'Flame'],
                ['name' => 'Solar Panels', 'icon' => 'SunMedium'],
            ],
            'Security' => [
                ['name' => 'Security System', 'icon' => 'Shield'],
                ['name' => 'CCTV', 'icon' => 'Camera'],
                ['name' => 'Doorman', 'icon' => 'UserCheck'],
                ['name' => 'Gated Community', 'icon' => 'Fence'],
            ],
        ];

        $sortOrder = 0;
        foreach ($groups as $group => $features) {
            foreach ($features as $feature) {
                PropertyFeature::firstOrCreate(
                    ['slug' => Str::slug($feature['name'])],
                    [
                        'name' => $feature['name'],
                        'icon' => $feature['icon'],
                        'group' => $group,
                        'sort_order' => $sortOrder++,
                    ]
                );
            }
        }
    }
}
