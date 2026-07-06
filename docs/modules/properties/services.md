# Properties Module — Services

| Service | Purpose | Key Methods |
|---------|---------|-------------|
| `PropertyService` | Property CRUD operations with image handling | `store()`, `update()`, `deleteWithImages()`, `uploadImages()` |
| `PropertyFilterService` | Property list filtering and search | `applyFilters(query, filters)` |
| `PricingService` | Pricing calculation for properties | `calculatePrice(property, checkIn, checkOut, guests)` |

## PropertyService

Handles the business logic for property CRUD:

```php
PropertyService::store($request, $user): Property
```

- Validates and creates property record
- Handles image upload and association
- Sets default metadata

```php
PropertyService::update($property, $request): Property
```

- Updates property fields
- Handles metadata changes
- Updates images as needed

```php
PropertyService::deleteWithImages($property): void
```

- Deletes property and associated images from storage
- Cascade deletes related bookings
- Removes image records
