# React

## Version

| Package        | Version | Status  |
| -------------- | ------- | ------- |
| `react`        | 19.2.7  | Current |
| `react-dom`    | 19.2.7  | Current |
| `@types/react` | 19.2.17 | Current |

## Component Patterns

### Function Declarations

Preferred over arrow functions:

```typescript
function Dashboard() {
    return <div>...</div>;
}
```

### Props Typing

```typescript
// Native elements
function Foo({ className }: React.ComponentProps<"div">) { ... }

// Radix primitives
function Bar({ ...props }: React.ComponentProps<typeof Dialog.Root>) { ... }
```

### Hooks

| Hook                   | Purpose                                                 |
| ---------------------- | ------------------------------------------------------- |
| `useAuth()`            | Current user, admin status, initials                    |
| `useNotifications()`   | Native fetch() adaptive polling (10s/30s/60s intervals) |
| `useDarkMode()`        | Theme detection                                         |
| `useFormatting()`      | Price/surface formatting                                |
| `useCurrency()`        | Currency selection                                      |
| `useTabState()`        | Persistent tab state                                    |
| `useFieldValidation()` | Field-level validation                                  |

## State Management

- **Form state**: Inertia `useForm` (not react-hook-form)
- **Local UI state**: `useState` / `useReducer`
- **Server state**: Inertia props (auto-synced on navigation)
- **Global state**: Inertia shared props via `usePage().props`

## File Structure

```
resources/js/
  Components/
    ui/           # shadcn/ui primitives (56 components)
    shared/       # Reusable app components (section, error-message, etc.)
    layout/       # Layout components (app-sidebar, ImpersonationBanner)
    navigation/   # Nav components (nav-logo, nav-main, nav-user, command-search)
    notifications/# Notification components
    frontend/     # Public-facing components
    account/      # Account page sections
    profile/      # Profile page sections
    settings/     # Settings page sections
    blog/         # Blog-specific components
    seo/          # SEO components
  hooks/          # Custom hooks
  Layouts/        # Page layouts (Authenticated, Admin, Guest, Public)
  Pages/          # Inertia page components
  lib/            # Utilities (cn, icons, schemas, notifications)
  types/          # TypeScript type definitions
```

## Conventions

- Strict TypeScript (`strict: true`, `noUnusedLocals`, `noUnusedParameters`)
- 2 spaces indentation
- `cn()` from `@/lib/utils` for class merging
- All routes via Ziggy's `route()` helper
- `asChild` / `Slot` pattern for component composition
- `data-slot` attributes on shadcn components
