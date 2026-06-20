<?php

namespace App\Modules\Booking\Services;

use App\Modules\Booking\Http\Controllers\BookingGlobalSettingsController;
use App\Modules\Properties\Models\Property;
use App\Modules\Properties\Services\PropertyFormattingService;
use App\Services\SettingsService;

class BookingWidgetRenderer
{
    /**
     * @param array<string, mixed> $atts
     */
    public static function render(array $atts = []): string
    {
        $propertyId = !empty($atts['property_id']) ? (int) $atts['property_id'] : 0;
        if (!$propertyId) {
            return '<p class="crm-booking-error">' . __('Property not found.') . '</p>';
        }

        $showPrice = !empty($atts['show_price']);
        $buttonText = !empty($atts['button_text']) ? $atts['button_text'] : 'Book Now';
        $mode = !empty($atts['mode']) ? $atts['mode'] : 'full';

        $property = Property::find($propertyId);
        if (!$property) {
            return '<p class="crm-booking-error">' . __('Property not found.') . '</p>';
        }

        $meta = $property->metadata ?? [];
        $bookingEnabled = !empty($meta['booking_enabled']);
        if (!$bookingEnabled) {
            return '<p class="crm-booking-error">' . __('Booking is not available for this property.') . '</p>';
        }

        $formattingService = app(PropertyFormattingService::class);
        $formattingSettings = $formattingService->getFormattingOptions();

        $weekStartsOn = (int) SettingsService::get('start_of_week', 1);

        $globalSettings = SettingsService::getGroup('booking');
        $bookingSettings = [
            'enable_nonrefundable_discount' => !empty($globalSettings['enable_nonrefundable_discount']),
            'nonrefundable_discount_percent' => (int) ($globalSettings['nonrefundable_discount_percent'] ?? 10),
            'calendar_max_range' => (int) ($globalSettings['calendar_max_range'] ?? 12),
        ];

        $containerId = 'crm-booking-widget-' . $propertyId . '-' . random_int(1000, 9999);

        return sprintf(
            '<div id="%s" data-crm-booking-widget data-property-id="%s" data-show-price="%s" data-button-text="%s" data-mode="%s" data-formatting-settings="%s" data-booking-settings="%s" data-week-starts-on="%s" class="crm-booking-widget-container">
  <p class="crm-booking-loading">%s</p>
</div>',
            htmlspecialchars($containerId),
            htmlspecialchars((string) $propertyId),
            $showPrice ? 'true' : 'false',
            htmlspecialchars($buttonText),
            htmlspecialchars($mode),
            htmlspecialchars(json_encode($formattingSettings)),
            htmlspecialchars(json_encode($bookingSettings)),
            htmlspecialchars((string) $weekStartsOn),
            __('Loading booking widget...')
        );
    }
}
