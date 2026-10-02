# Formatting & Dashboard Services

## FormattingService

Centralized formatting for prices and surface areas. Registered as a **singleton** in `AppServiceProvider` — injectable via constructor DI.

### Registration

```php
// AppServiceProvider::register()
$this->app->singleton(FormattingService::class);
```

### Usage (DI)

```php
class SomeService
{
    public function __construct(
        protected FormattingService $formattingService,
    ) {}

    public function format(): string
    {
        return $this->formattingService->formatPrice(1234.56);
    }
}
```

### Legacy bridge

`SettingsService` still uses static calls and bridges via `app(FormattingService::class)` — awaiting migration to injectable.

### Methods

```php
$service->formatPrice(float|int|string $value, array $options = []): string
// Returns "$1,234.56" (options: currency, position, thousands_sep, decimal_sep, empty_text)

$service->formatSurface(float|int|string $value, array $options = []): string
// Returns "1,234 ft²" (options: unit, thousands_sep, decimal_sep)

$service->formatNumber(float|int|string $number, string $thousandsSep = ',', string $decimalSep = '.'): string
// Returns "1,234.56"

$service->getCurrencySymbol(string $currencyCode): string
$service->getSurfaceUnit(string $unitCode): string
$service->getCurrencyOptions(): array
$service->getSurfaceUnitOptions(): array
$service->getCurrencyPositionOptions(): array
$service->getThousandsSeparatorOptions(): array
$service->getDecimalSeparatorOptions(): array
$service->getDateFormatOptions(): array
$service->getTimeFormatOptions(): array
$service->getDaysOfWeek(): array
```

### Module Integration

Each module specifies which of its fields are prices or surfaces via `getMetaFormattingConfig()`:

```php
public function getMetaFormattingConfig(): array
{
    return [
        'price_fields' => ['price'],
        'surface_fields' => ['surface'],
    ];
}
```

Used by `formatRepeaterData()` to automatically append `_formatted` fields:

```php
$service->formatRepeaterData(array $data, string $moduleSlug, array $globalOptions = []): array
```

### Callers (updated to injectable)

| Caller | Injection Method |
|--------|-----------------|
| `ModuleController` | Constructor DI |
| `PropertyFormattingService` | Constructor DI |
| `SettingsService` | `app(FormattingService::class)` bridge |
| `BookingService` | `app(FormattingService::class)` bridge |
| `BookingCurrencyService` | `app(FormattingService::class)` bridge |

## DashboardService

Cached aggregation for dashboard stats. Cache is busted when module data changes.

### Methods

```php
$service->getStats(): array
// Returns stats from all enabled modules aggregated
// Each module's getDashboardData() supplies its own stats

$service->clearCache(): void
// Busters all dashboard stats cache
```

### Per-Module Stats

| Module | Stats |
|--------|-------|
| Properties | `total`, `published`, `draft`, `totalValue` |
| Booking | `total`, `pending`, `confirmed` |
| Posts | `total`, `published`, `draft` |
| Shop | `total_products`, `published_products`, `total_orders`, `pending_orders`, `low_stock_count` |
