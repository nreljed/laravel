<?php

namespace Nreljed\BookingModule\Properties\Services;

use Illuminate\Support\Collection;
use Nreljed\BookingModule\Properties\Models\Property;
use Nreljed\Platform\Services\FormattingService;
use Nreljed\Platform\Services\SettingsService;

class PropertyFormattingService
{
    public function __construct(
        protected FormattingService $formattingService,
    ) {}

    public function formatPrice(Property $property, array $overrides = []): string
    {
        $options = array_merge($property->getFormattingOptions(), $overrides);

        return $this->formattingService->formatPrice($property->price, $options);
    }

    public function formatSurface(Property $property, array $overrides = []): string
    {
        $options = array_merge($property->getFormattingOptions(), $overrides);

        return $this->formattingService->formatSurface($property->surface, $options);
    }

    public function formatNumber(float|int|string $value, array $overrides = []): string
    {
        $options = array_merge(SettingsService::getFormattingOptions(), $overrides);

        return $this->formattingService->formatNumber(
            $value,
            $options['thousands_sep'] ?? ',',
            $options['decimal_sep'] ?? '.',
        );
    }

    public function formatProperty(Property $property, array $overrides = []): array
    {
        $data = $property->toArray();
        $options = array_merge($property->getFormattingOptions(), $overrides);

        $data['price_formatted'] = $property->price
            ? $this->formattingService->formatPrice($property->price, $options)
            : '';

        $data['surface_formatted'] = $property->surface
            ? $this->formattingService->formatSurface($property->surface, $options)
            : '';

        return $data;
    }

    public function formatCollection(Collection $properties, array $overrides = []): array
    {
        return $properties->map(fn (Property $property) => $this->formatProperty($property, $overrides))->all();
    }

    public function formatRepeaterRows(array $rows, array $overrides = []): array
    {
        return $this->formattingService->formatRepeaterData($rows, 'properties', $overrides);
    }
}
