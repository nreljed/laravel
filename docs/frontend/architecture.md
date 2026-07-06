# Frontend Architecture

## Tech Stack

| Technology | Version | Purpose |
|-----------|---------|---------|
| React | 19.2 | UI framework |
| Inertia.js | 3.4 | Server-driven routing |
| TypeScript | 6 | Type safety |
| Vite | 8 | Build tool |
| Tailwind CSS | 4 | Styling |
| shadcn/ui | new-york, neutral base | UI component library |
| Radix UI | latest | Headless primitives |
| Lucide React | latest | Icons |
| Ziggy | latest | Laravel route helper in JS |

## Build Pipeline

```
Vite 8
  ├── vite.config.ts
  ├── tsconfig.json → tsc --noEmit (typecheck)
  ├── tailwind.config.ts
  └── resources/js/app.tsx (entry point)
        ├── Creates Inertia app
        ├── Registers Ziggy route helper
        ├── Sets up ThemeProvider (next-themes)
        └── Auto-discovers pages via import.meta.glob
              ├── resources/js/Pages/**/*.tsx
              └── app/Modules/*/Resources/js/Pages/**/*.tsx
```

## Module Page Discovery

Module pages are lazy-loaded via Vite glob imports in `app.tsx`:

```typescript
const pages = import.meta.glob([
    '../resources/js/Pages/**/*.tsx',
    '../app/Modules/*/Resources/js/Pages/**/*.tsx',
]);
```

This automatically picks up any page added to any module's `Resources/js/Pages/` directory without configuration.

## TypeScript Conventions

- `strict: true` in tsconfig
- `forceConsistentCasingInFileNames: false` (Windows case-insensitive FS)
- Path alias `@/` → `resources/js/`
- All components use explicit TypeScript interfaces for props
- No `any` unless strictly necessary

## Module Page Naming

The Inertia page path maps from the file path to the component name:

```
app/Modules/Booking/Resources/js/Pages/Index.tsx     →  Bookings/Index
app/Modules/Booking/Resources/js/Pages/GuestView.tsx  →  Bookings/GuestView
resources/js/Pages/Dashboard/Index.tsx                 →  Dashboard/Index
```

Module pages use the `{ModuleName}/{PageName}` convention.

## Build Commands

```bash
npm run dev              # Vite dev server with HMR
npm run build            # tsc --noEmit + vite build (production)
npm run typecheck        # tsc --noEmit (CI check)
```
