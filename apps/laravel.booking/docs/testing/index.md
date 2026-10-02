# Testing Infrastructure

## PHPUnit

Tests use standard PHPUnit with Laravel's `TestCase` base class.

### Running Tests

```bash
php artisan test              # Run all tests
php artisan test --filter=Booking  # Run specific test file
php artisan test --stop-on-failure  # Stop on first failure
```

### Test Database

Tests use SQLite in-memory database. `phpunit.xml` configures:

```xml
<server name="DB_CONNECTION" value="sqlite"/>
<server name="DB_DATABASE" value=":memory:"/>
```

## BootsModules Trait

Many tests depend on modules being loaded. Use the `BootsModules` trait:

```php
use Tests\BootsModules;

class BookingControllerTest extends TestCase
{
    use BootsModules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootModules();
    }
}
```

`bootModules()`:
1. Loads module classes via `ModuleLoader`
2. Seeds module records (`ModuleSeeder`) and roles/permissions (`RolesAndPermissionsSeeder`)
3. Boots enabled modules and registers routes
4. Calls `RouteCollection::refreshNameLookups()` — required for `route()` helper
5. Creates any missing permissions and syncs to admin role

## Test Architecture

```
tests/
├── Feature/
│   ├── Modules/
│   │   ├── Properties/
│   │   │   ├── PropertyControllerTest.php
│   │   │   ├── PropertyTypeTest.php
│   │   │   ├── CityTest.php
│   │   │   └── FeatureTest.php
│   │   ├── Booking/
│   │   │   ├── BookingControllerTest.php
│   │   │   ├── BookingActionServiceTest.php
│   │   │   ├── BookingGuestControllerTest.php
│   │   │   └── ...
│   │   ├── Shop/
│   │   │   └── ...
│   │   └── Posts/
│   │       └── ...
│   ├── Auth/
│   ├── Admin/
│   └── ...
├── Unit/
│   └── ...
├── BootsModules.php
└── CreatesApplication.php
```

## Testing Patterns

### Controller Tests

```php
public function test_can_view_booking_index()
{
    $user = User::factory()->create();
    $user->assignRole('admin');
    $this->actingAs($user);

    $response = $this->get(route('dashboard.bookings.index'));
    $response->assertStatus(200);
}
```

### Service Tests

```php
public function test_transition_valid_status()
{
    $this->bootModules();
    $booking = Booking::factory()->create(['status' => 'confirmed']);
    $manager = app(BookingStatusManager::class);

    $manager->transition($booking, BookingStatus::CheckedIn, 'test');

    $this->assertEquals('checked_in', $booking->fresh()->status);
    $this->assertNotNull($booking->fresh()->checked_in_at);
}
```

## Current Test Count

- **119 tests**, **261 assertions**
- All passing
- Test duration: ~8-10 seconds
