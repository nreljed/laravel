# Properties Module — Frontend

## Pages

All pages in `Resources/js/Pages/`:

| Page | Route | Purpose |
|------|-------|---------|
| `Index.tsx` | `/dashboard/properties` | Property list (DataTable) |
| `Form.tsx` | `/dashboard/properties/create`, `/dashboard/properties/{property}/edit` | Create/edit property form |
| `Types.tsx` | `/dashboard/properties/types` | Property types CRUD |
| `Cities.tsx` | `/dashboard/properties/cities` | Cities CRUD |
| `Features.tsx` | `/dashboard/properties/features` | Features CRUD |
| `Statuses.tsx` | `/dashboard/properties/statuses` | Property statuses CRUD |

## Property Form

The `Form.tsx` component is the core — a multi-section form for creating/editing properties:

| Section | Description |
|---------|-------------|
| Basic Info | Name, description, status, type, city |
| Pricing | Price per night, cleaning fee, deposit |
| Surface Area | Surface size with unit selection |
| Location | Address, coordinates (map picker) |
| Gallery | Image upload with drag-and-drop reorder |
| Features | Feature group selection (Exterior, Interior, Security, Special) |
| Terms | Property terms and conditions |

## Booking Configuration

Booking-specific settings (pricing, rules, availability, units) are managed via the **BookingSection** component embedded inside the Property Form:

```
Properties → Edit Property → BookingSection (5 tabs) → Save into properties.metadata
```

See [Booking Architecture](../booking/architecture.md) for details.

## Term Pages

Each term management page provides:

- DataTable with search and pagination
- Inline create/edit via dialog
- Image URL input with thumbnail preview (Types, Cities)
- Delete with confirmation
- Reorder (Statuses)

## Image Upload

Properties have a gallery with:
- Drag-and-drop upload
- Featured image selection
- Drag-and-drop reorder
- Per-image delete
- Config: max 20 images, `properties` upload path, hidden from media library
