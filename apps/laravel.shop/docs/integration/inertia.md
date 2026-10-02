# Inertia.js Integration

## Version

| Package | Version (installed) | Latest available | Status |
|---------|-------------------|-----------------|--------|
| `@inertiajs/react` | 3.4.0 | ^3.5.0 (CRM) | Outdated by 1 minor |
| `@inertiajs/vite` | **Not installed** | ^3.5.0 (CRM) | **Missing** |

---

## Current Usage Patterns

### Server-side

- `HandleInertiaRequests` middleware shares: `auth.user`, `notifications`, `flash`, `userMode`, `canSwitchMode`, `moduleDebugData`
- All controllers return `Inertia::render()` with typed props
- Validation errors returned as Inertia error bags via `$request->validate()`

### Client-side

- `useForm` for all form state (declared preference over react-hook-form)
- `router.reload({ only: ['notifications'] })` for 20s notification polling
- `router.on('navigate', callback)` for flash message listener
- Ziggy `route()` helper for all URLs (no hardcoded paths)
- `usePage().props` for accessing shared props

---

## Audit Findings

### ✅ Correct

- Session-based auth (CSRF token in meta tag, Sanctum)
- Form validation: Zod client-side + `$request->validate()` server-side
- Partial reloads used correctly for notifications
- Flash messages bridged via `HandleInertiaRequests` (not inline in controllers)
- Type-safe page props via Inertia's generic props

### ⚠️ Issues Found

| Issue | Priority | Impact | Fix |
|-------|----------|--------|-----|
| `@inertiajs/vite` plugin not installed | Medium | Missing Vite plugin for Inertia HMR optimization | `npm install @inertiajs/vite` + add to `vite.config.js` |
| Outdated `@inertiajs/react` (3.4.0 vs 3.5.0) | Low | Missing latest fixes/features | `npm install @inertiajs/react@^3.5.0` |
| `router` typed as `any` in `app.tsx` | Low | Unsafe access pattern | Use Inertia's `router` type |

### ❌ Missing from CRM-dashboard (both projects share same gaps)

- No Inertia page type definitions (no `PageProps` type)
- No shared prop types — props accessed as `unknown` / unchecked
- No `useRemember` / `usePrefetch` usage (new Inertia v3 features)
- No deferred props usage for slow data

---

## Recommended Actions

### 1. Install `@inertiajs/vite`

```
npm install @inertiajs/vite@^3.5.0
```

Then add to `vite.config.js`:

```js
import inertia from "@inertiajs/vite";

export default defineConfig({
  plugins: [
    laravel({...}),
    inertia(),        // <-- add before react()
    react(),
    tailwindcss(),
  ],
});
```

### 2. Upgrade `@inertiajs/react`

```
npm install @inertiajs/react@^3.5.0
```

### 3. Add `PageProps` type

Create `resources/js/types/index.d.ts`:

```ts
import type { User } from "@/types/user";

export type PageProps<T = {}> = T & {
  auth: {
    user: User;
  };
  flash?: Record<string, unknown>;
  notifications: Notification[];
  userMode: "host" | "guest";
  canSwitchMode: boolean;
  moduleDebugData: Record<string, unknown>;
};
```

Then use in pages: `const props = usePage<PageProps<MySpecificProps>>()`

---

## Inertia v3 Features Not Used

| Feature | Available Since | Benefit | Implementation Effort |
|---------|---------------|---------|----------------------|
| `usePrefetch` | v3.0 | Preload pages on hover for instant navigation | Low |
| `useRemember` | v3.0 | Persist form state across navigations | Low |
| `deferred` props | v3.0 | Defer slow data (e.g., stats) for faster initial render | Medium |
| `merge` option in reload | v3.0 | Merge instead of replace in partial reloads | Low |
| `preserveScroll` improvements | v3.0 | Better scroll restoration | Low |

---

## Version History

| Date | Action |
|------|--------|
| 2026-06-30 | Audit created — v3.4.0 installed, v3.5.0 available |
