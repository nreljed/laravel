# SettingsService

`app/Services/SettingsService.php`

DB-driven application configuration via the `App\Models\Setting` model. All settings are cached under key `app_settings` per group.

## Methods

### `get(string $key, mixed $default = null)`

Get a single setting by key. Delegates to `Setting::get()`.

### `set(string $key, mixed $value)`

Set a single setting value. Clears the settings cache.

### `setMany(array $settings)`

Set multiple settings at once (key-value pairs). Clears cache.

### `getGroup(string $group)`

Get all settings for a named group. Cached for 3600 seconds.

### `getAllGrouped()`

Get all settings organized by group. Cached for 3600 seconds.

### `getFormattingDefaults()`

Returns formatting configuration with these defaults:

| Key | Default | Description |
|-----|---------|-------------|
| `default_currency` | `USD` | Currency code |
| `currency_position` | `before` | Symbol position (`before`/`after`) |
| `price_empty_text` | `""` | Text when no price set |
| `surface_unit` | `sq_ft` | Area measurement unit |
| `thousands_separator` | `,` | Thousands separator |
| `decimal_separator` | `.` | Decimal separator |
| `date_format` | `Y-m-d` | Date display format |
| `time_format` | `H:i` | Time display format |
| `start_of_week` | `0` | Sunday (0=Sun through 6=Sat) |
| `timezone` | `UTC` | Application timezone |

### `formatPrice(float $value, array $options = [])`

Format a price value using `FormattingService`.

### `formatSurface(float $value, array $options = [])`

Format a surface value using `FormattingService`.

### `clearCache()`

Clears all setting cache groups:

`general`, `registration`, `notifications`, `security`, `email`, `maintenance`, `localization`, `formatting`, `properties`

## Setting Groups

Settings are stored in the database grouped by prefix. Each group typically corresponds to a settings tab in the admin panel.
