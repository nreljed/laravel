# Inertia.js

## Version

| Package                     | Version | Status  |
| --------------------------- | ------- | ------- |
| `@inertiajs/react`          | 3.6.1   | Current |
| `@inertiajs/vite`           | 3.6.1   | Current |
| `inertiajs/inertia-laravel` | ^3.1    | Current |

## How It Works

Inertia bridges Laravel controllers and React components. Controllers return `Inertia::render()` which sends a page object as JSON. The React client-side adapter renders the component.

```
Browser → Laravel Route → Controller → Inertia::render('Page', $props)
                                         ↓
                                    JSON page object
                                         ↓
React Client → Resolves page component → Renders with props
```

## Server Side

### Middleware

`HandleInertiaRequests` shares global props with every page:

```php
'auth' => ['user' => fn () => $this->buildAuthUser($request->user())],
'latestNotifications' => fn () => ...,
'featureNavItems' => fn () => FeatureRegistry::getAllNavItems(),
'userCapabilities' => fn () => $this->getUserCapabilities($request->user()),
```

### Rendering

```php
return Inertia::render('Dashboard/Index', [
    'stats' => $stats,
    'recentActivity' => $activity,
]);
```

### Deferred Props

Heavy data can be deferred to a separate request:

```php
return Inertia::render('Users/Index', [
    'users' => User::all(),
    'permissions' => Inertia::defer(fn () => Permission::all()),
]);
```

## Client Side

### Page Resolution

`app.tsx` uses `import.meta.glob` to auto-discover pages:

```typescript
const pages = import.meta.glob("./Pages/**/*.tsx");
// + Blog pages from @blog/Pages/**/*.tsx
```

### Forms

All forms use Inertia's `useForm`:

```typescript
import { useForm } from "@inertiajs/react";

const { data, setData, post, processing, errors } = useForm({
  name: "",
  email: "",
});

function submit() {
  post(route("profile.update"));
}
```

### Navigation

```typescript
import { router, Link } from "@inertiajs/react";

// Programmatic
router.visit('/dashboard');
router.reload({ only: ['notifications'] });

// Declarative
<Link href={route('dashboard')}>Dashboard</Link>
```

### Flash Messages

`app.tsx` listens for navigate events and shows toasts via the Base UI toast manager:

```typescript
import { toast } from "@/Components/ui/toast";

router.on("navigate", () => {
  const flash = page.props?.flash;
  if (flash.status) toast.add({ title: String(flash.status), type: "success" });
  if (flash.error) toast.add({ title: String(flash.error), type: "error" });
});
```

## Conventions

- All routes via Ziggy's `route()` helper — no hardcoded URLs
- `useForm` for all form state (not react-hook-form)
- `usePage().props` for accessing shared props
- Partial reloads for polling (`router.reload({ only: [...] })`)
