# Frontend Patterns

## useForm (Inertia)

All form state uses Inertia's `useForm` — not react-hook-form:

```typescript
const { data, setData, post, processing, errors } = useForm({
    name: '',
    email: '',
    // ...
});

// Submit
post(route('dashboard.bookings.store'), {
    onSuccess: () => toast.success('Created!'),
    onError: (errors) => console.error(errors),
});
```

Validation errors come from server-side `$request->validate()`. Optional Zod validation on client.

## useAuth

```typescript
import { useAuth } from '@/hooks/use-auth';

const { user, isAdmin, getUserInitials } = useAuth();
```

- `user` — current user object from Inertia shared props
- `isAdmin` — shortcut for `user?.isAdmin`
- `getUserInitials(name)` — avatar fallback initials

## Ziggy routes()

All URLs use Ziggy's `route()` helper — no hardcoded paths:

```typescript
import { route } from 'ziggy-js';

// Basic
route('dashboard.bookings.index')

// With params
route('dashboard.bookings.show', booking.id)

// Relative URL (for nav items, prevents XHR cross-origin issues)
route('dashboard.bookings.index', [], false)
```

## Toast (Base UI)

```typescript
import { toast } from "@/Components/ui/toast";

toast.add({ title: 'Booking created!', type: 'success' });
toast.add({ title: 'Something went wrong.', type: 'error' });
toast.add({ title: 'Info message', description: 'Detail', type: 'info' });
```

Flash messages from `usePage().props.flash` are wrapped in page components and displayed via Base UI toast.

## Theme (next-themes)

```typescript
import { useTheme } from 'next-themes';

const { theme, setTheme } = useTheme();
// theme: 'light' | 'dark' | 'system'
```

`<ThemeProvider>` wraps the app. ThemeSwitcher component in nav-user dropdown.

## Module Page Access from Frontend

```typescript
const pageRecords = usePage<Record<string, unknown>>().props;
const globalSettings = pageRecords.booking_global_settings as Record<string, unknown>;
```

## Component Imports

Use `@/Components/...` alias (NOT `@/components/...`):

```typescript
import { Button } from '@/Components/ui/button';
import { Card } from '@/Components/ui/card';
```

## cn() Utility

```typescript
import { cn } from '@/lib/utils';

cn('base-class', condition && 'conditional-class', variantClass)
```

## router.reload for Polling

```typescript
import { router } from '@inertiajs/react';

// Notification polling (20s)
router.reload({ only: ['notifications'] });
```
