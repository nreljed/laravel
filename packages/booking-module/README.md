# nreljed/booking-module

Booking & properties feature package for the Nreljed dashboard platform.

## Features

- **Properties** — listings CRUD, terms taxonomies (types/cities/features/statuses), gallery, public storefront pages
- **Bookings** — availability calendar, bookings pipeline, guest portal with magic-link tokens, iCal import/export, offline payment gateway, email automation

## Install

```bash
composer require nreljed/booking-module
```

Providers are auto-discovered via package discovery.

## Structure

```
src/
├── Booking/        # Bookings domain (FeatureProvider: "bookings")
└── Properties/     # Properties domain (FeatureProvider: "properties")
resources/views/    # Email templates (booking-*, property-inquiry)
```
