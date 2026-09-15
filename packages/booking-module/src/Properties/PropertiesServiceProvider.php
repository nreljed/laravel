<?php

namespace Nreljed\BookingModule\Properties;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Nreljed\BookingModule\Properties\Models\Property;
use Nreljed\BookingModule\Properties\Policies\PropertyPolicy;
use Nreljed\Platform\Concerns\InteractsWithFeatures;
use Nreljed\Platform\Contracts\FeatureProvider;

class PropertiesServiceProvider extends ServiceProvider implements FeatureProvider
{
    use InteractsWithFeatures;

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        if ($this->enabled()) {
            $this->loadRoutes();
        }

        Gate::policy(Property::class, PropertyPolicy::class);
    }

    protected function loadRoutes(): void
    {
        Route::middleware(['web', 'auth', 'verified', 'permission:properties.view'])
            ->prefix('/dashboard/properties')
            ->name('dashboard.properties.')
            ->group(fn () => require __DIR__.'/Routes/web.php');

        Route::middleware(['web'])
            ->group(fn () => require __DIR__.'/Routes/public.php');
    }

    // ─── FeatureProvider required methods ───

    public function name(): string
    {
        return 'Properties';
    }

    public function slug(): string
    {
        return 'properties';
    }

    public function description(): ?string
    {
        return 'Create and manage property listings, types, cities, features, and statuses.';
    }

    public function getComposerPackage(): ?string
    {
        return 'nreljed/booking-module';
    }

    public function getSettingsGroups(): array
    {
        return ['properties'];
    }

    // ─── Helpers ───

    public function getNavGroupLabel(): ?string
    {
        return 'Manage Property';
    }

    public function getNavItems(): array
    {
        return [
            [
                'title' => 'Properties',
                'groupLabel' => 'Manage Property',
                'url' => route('dashboard.properties.dashboard', [], false),
                'icon' => 'Building2',
                'permission' => 'properties.view',
                'module_slug' => 'properties',
                'showModeBadge' => false,
                'items' => [
                    ['title' => 'Dashboard', 'url' => route('dashboard.properties.dashboard', [], false)],
                    ['title' => 'All Properties', 'url' => route('dashboard.properties.index', [], false)],
                    ['title' => 'Add Property', 'url' => route('dashboard.properties.create', [], false)],
                    ['title' => 'Types', 'url' => route('dashboard.properties.terms.types.index', [], false)],
                    ['title' => 'Cities', 'url' => route('dashboard.properties.terms.cities.index', [], false)],
                    ['title' => 'Features', 'url' => route('dashboard.properties.terms.features.index', [], false)],
                    ['title' => 'Statuses', 'url' => route('dashboard.properties.terms.statuses.index', [], false)],
                ],
            ],
        ];
    }

    public function getMetaFormattingConfig(): array
    {
        return [
            'price_fields' => ['price'],
            'surface_fields' => ['surface'],
        ];
    }

    public function getDashboardData(): array
    {
        if (! Schema::hasTable('properties')) {
            return [];
        }

        return [
            'label' => 'Properties',
            'icon' => 'Building2',
            'total' => Property::count(),
            'published' => Property::where('status', 'published')->count(),
            'draft' => Property::where('status', 'draft')->count(),
            'totalValue' => Property::where('status', 'published')->sum('price'),
        ];
    }

    public function getSharedData(): array
    {
        return [];
    }

    public function getSettingsRoute(): ?string
    {
        return null;
    }

    public function permissions(): array
    {
        return [
            ['slug' => 'properties.view', 'name' => 'View Properties', 'description' => 'View property listings.'],
            ['slug' => 'properties.create', 'name' => 'Create Properties', 'description' => 'Create new property listings.'],
            ['slug' => 'properties.edit', 'name' => 'Edit Properties', 'description' => 'Edit existing property listings.'],
            ['slug' => 'properties.delete', 'name' => 'Delete Properties', 'description' => 'Delete property listings.'],
        ];
    }

    public function hasSettings(): bool
    {
        return false;
    }

    public function getRepeaterConfig(): array
    {
        return [
            'additional_details' => [
                'label' => 'Additional Details',
                'json_key' => 'additional_details_json',
                'enable_key' => 'enable_additional_details',
                'min_items' => 0,
                'max_items' => 20,
                'fields' => [
                    'detail_title' => ['type' => 'text', 'label' => 'Title', 'required' => true],
                    'detail_value' => ['type' => 'text', 'label' => 'Value', 'required' => true],
                ],
            ],
            'floor_plans' => [
                'label' => 'Floor Plans',
                'json_key' => 'floor_plans_json',
                'enable_key' => 'enable_floor_plans',
                'min_items' => 0,
                'max_items' => 10,
                'fields' => [
                    'floor_name' => ['type' => 'text', 'label' => 'Floor Name', 'required' => true],
                    'floor_price' => ['type' => 'number', 'label' => 'Price'],
                    'floor_surface' => ['type' => 'number', 'label' => 'Surface (m²)'],
                    'floor_bedrooms' => ['type' => 'number', 'label' => 'Bedrooms'],
                    'floor_bathrooms' => ['type' => 'number', 'label' => 'Bathrooms'],
                    'floor_desc' => ['type' => 'textarea', 'label' => 'Description'],
                ],
            ],
        ];
    }

    public function getGalleryConfig(): array
    {
        return [
            'gallery' => [
                'label' => 'Property Gallery',
                'json_key' => 'property_gallery',
                'thumbnail_key' => 'property_thumbnail_url',
                'max_images' => 20,
                'upload_action' => 'dashboard.properties.upload-image',
                'delete_action' => 'dashboard.properties.delete-image',
                'upload_folder' => 'properties',
                'hide_in_media_library' => true,
                'create_attachment' => true,
            ],
        ];
    }

    public function getProfileFieldsConfig(): array
    {
        return [
            'phone_numbers' => [
                'type' => 'repeater',
                'label' => 'Phone Numbers',
                'description' => 'Manage your contact phone numbers.',
                'json_key' => 'phone_numbers',
                'max_items' => 10,
                'fields' => [
                    'label' => [
                        'type' => 'select',
                        'label' => 'Type',
                        'presetMode' => true,
                        'presetOtherValue' => 'Other',
                        'customPlaceholder' => 'Enter custom label',
                        'options' => [
                            ['label' => 'Mobile', 'value' => 'Mobile'],
                            ['label' => 'Work', 'value' => 'Work'],
                            ['label' => 'Home', 'value' => 'Home'],
                            ['label' => 'WhatsApp', 'value' => 'WhatsApp'],
                        ],
                    ],
                    'phone' => [
                        'type' => 'phone',
                        'label' => 'Phone Number',
                        'placeholder' => '+1 (555) 000-0000',
                    ],
                ],
            ],
            'social_media' => [
                'type' => 'repeater',
                'label' => 'Social Media Links',
                'description' => 'Manage your social media profiles.',
                'json_key' => 'social_media',
                'max_items' => 20,
                'fields' => [
                    'social_title' => [
                        'type' => 'select',
                        'label' => 'Platform',
                        'presetMode' => true,
                        'presetOtherValue' => 'Other',
                        'customPlaceholder' => 'Enter custom platform',
                        'options' => [
                            ['label' => 'Facebook', 'value' => 'Facebook'],
                            ['label' => 'Instagram', 'value' => 'Instagram'],
                            ['label' => 'LinkedIn', 'value' => 'LinkedIn'],
                            ['label' => 'X (Twitter)', 'value' => 'X'],
                            ['label' => 'TikTok', 'value' => 'TikTok'],
                            ['label' => 'YouTube', 'value' => 'YouTube'],
                        ],
                    ],
                    'social_value' => [
                        'type' => 'url',
                        'label' => 'Profile URL',
                        'placeholder' => 'https://',
                    ],
                ],
            ],
        ];
    }

    public function getEmailTemplates(): array
    {
        return [];
    }

    public function getNotificationTypes(): array
    {
        return [
            'property.created' => [
                'label' => 'New Property',
                'description' => 'When a new property is created.',
                'default_channels' => ['in_app'],
                'default_enabled' => true,
                'group' => 'Properties',
            ],
            'property.updated' => [
                'label' => 'Property Updated',
                'description' => 'When a property is updated.',
                'default_channels' => ['in_app'],
                'default_enabled' => true,
                'group' => 'Properties',
            ],
            'property.inquiry' => [
                'label' => 'Property Inquiry',
                'description' => 'When someone inquires about a property.',
                'default_channels' => ['in_app', 'email'],
                'default_enabled' => true,
                'email_template' => 'property-inquiry',
                'group' => 'Properties',
            ],
        ];
    }

    public function onUserDelete(int $userId): void
    {
        //
    }
}
