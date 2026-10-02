# shadcn/ui Integration

## Version & Config

| Field | Value |
|-------|-------|
| Style | `base-vega` (Base UI primitive registry — NOT `new-york`/Radix) |
| Base color | `neutral` |
| CSS variables | `true` |
| Tailwind config | Empty (Tailwind v4 CSS-first, no config file) |
| Icon library | `lucide` |
| React Server Components | `false` |
| Aliases | `@/components`, `@/lib/utils`, `@/components/ui`, `@/lib`, `@/hooks` |
| Registries | `@wds`, `@diceui` (+ default shadcn registry) |

`components.json` uses `"style": "base-vega"` — the `base-` prefix selects the Base UI primitive registry, `vega` is the theme style. This is the official shadcn mechanism so `npx shadcn add/view` resolves Base UI components (from `{@base-ui/react}`) instead of Radix. Do NOT rename to a bare theme name (`nova`/`vega`) — that drops the Base UI base and fetches Radix components. Do NOT run `npx shadcn apply --preset` — it overwrites all UI components + theme CSS.

## Where Components Live

Components do NOT live in this repo (host). They are vendored into `vendor/` from the `nreljed/platform` composer package:

| Location | Contents |
|----------|----------|
| `vendor/nreljed/platform/resources/js/Components/ui/` | 67 shadcn/ui primitives (all Base UI) |
| `vendor/nreljed/platform/resources/js/Components/shared/` | App-shared components |
| `vendor/nreljed/platform/resources/js/Components/dashboard/` | KpiCard, charts, tables |
| `vendor/nreljed/shop-module/src/Resources/js/` | Shop feature pages/components |

Editing UI components = package change → PR in `nreljed/platform` → tag → release → `core-update` fan-out. NEVER edit `vendor/` directly.

## Dependency Versions

| Package | Version | Notes |
|---------|---------|-------|
| `@base-ui/react` | ^1.5.0 (installed 1.7.x) | Unstyled primitives — used by ALL 67 ui/ components |
| `shadcn` (devDep) | ^4.19.1 (installed 4.21.0) | Component generator / `npx shadcn diff` |
| `@shadcn/react` | ^0.3.0 | shadcn unstyled React kit (`message-scroller`) |
| `tailwindcss` | ^4.0.0 (installed 4.3.x) | v4 — no config file, CSS-first |
| `@tailwindcss/vite` | ^4.0.0 (installed 4.3.x) | Vite plugin (NOT PostCSS) |
| `tw-animate-css` | ^1.4.0 | Animation utilities (`animate-in`, `fade-in-0`, …) |
| `class-variance-authority` | ^0.7.1 | Variants (`cva`) |
| `clsx` + `tailwind-merge` | ^2.1.1 / ^3.6.0 | `cn()` composition |
| `lucide-react` | ^1.35.0 | Icons |
| `embla-carousel-react` | ^8.6.0 | `carousel.tsx` |
| `input-otp` | ^1.5.0 | `input-otp.tsx` |
| `sonner` | removed (^2.0.8) | Migrated to Base UI toast — see Toast below (2026-09-08) |
| `vaul` | removed (was ^1.1.2) | Superceded by `@base-ui/react/drawer` |

NO `radix-ui` / `@radix-ui/react-*` in direct deps or source imports. `@radix-ui/*` only exists transitively (cmdk). There is no `tailwindcss-animate` (legacy v3 plugin) — the codebase is on v4-native `tw-animate-css`.

## Component Inventory (67 ui/ primitives, all Base UI)

```
accordion  alert  alert-dialog  aspect-ratio  attachment  avatar  badge
breadcrumb  bubble  button-group  button  calendar  card  carousel  chart
checkbox  collapsible  combobox  command  context-menu  data-table (+ column-header,
filters, pagination, toolbar)  dialog  direction  drawer  dropdown-menu  empty  field
form  hover-card  input-group  input-otp  input  item  kbd  label  marker  menubar
message-scroller  message  native-select  navigation-menu  pagination  popover
progress  radio-group  resizable  scroll-area  select  separator  sheet  sidebar
skeleton  slider  spinner  status-badge  switch  table  tabs  textarea  toast
toggle-group  toggle  tooltip
```

## Key Patterns

### Base UI primitives (NOT Radix)

```tsx
import { Dialog as DialogPrimitive } from "@base-ui/react/dialog";
import { Select as SelectPrimitive } from "@base-ui/react/select";
```

- Follow shadcn/ui v4 anatomy: `data-slot="component-name"` on every primitive root, `cn()` for class composition, `cva` for variants.
- Base UI state attributes are `data-open`/`data-closed` (NOT Radix `data-state`).
- Composition uses the `render` prop (NOT `asChild`/`Slot`):

```tsx
<DialogPrimitive.Close render={<Button variant="ghost" size="icon-sm" />}>
  <XIcon />
</DialogPrimitive.Close>
```

- Floating components (`dialog`, `dropdown-menu`, `popover`, `select`, `tooltip`, `sheet`, `drawer`) use `<Primitive.Positioner side=… sideOffset={4}>` + `<Primitive.Popup>` and animate with `data-open:*` / `data-closed:*` + `slide-in-from-*` / `fade-*`.

### Toast (Base UI, single source)

`Components/ui/toast.tsx` wraps `@base-ui/react/toast` via `createToastManager()`. The core shell mounts `<Toaster />` once. Usage anywhere (including shop-module pages):

```tsx
import { toast } from "@/Components/ui/toast";

toast.add({ title: "Saved", type: "success" });   // success | info | warning | error | loading
```

This is a BREAKING CHANGE from the old sonner API: `toast.success("msg")` → `toast.add({ title: "msg", type: "success" })`, `toast.error("msg")` → `toast.add({ title: "msg", type: "error" })`. The `sonner` dependency was REMOVED with the shop-module migration (v0.1.10) — do not `import { toast } from "sonner"`.

### Design tokens

- Token source of truth: `vendor/nreljed/platform/resources/css/core.css` — imported into `resources/css/app.css` AFTER `@import "tailwindcss"`. Per-app overrides live in the host `app.css` (`--warning` etc.).
- `@theme inline` maps `--color-*` → Tailwind utilities. Includes `--warning`, `--status-{success,error,info}` + foreground tokens.
- Radius: multiplicative scale via `--radius` (`calc(var(--radius) * 0.6)` etc.).
- No `--overlay` token in this app (unlike Project-WP). Overlays use `bg-black/10`.
- Dark mode: `@custom-variant dark (&:is(.dark *))`, class-based on `<html>`, toggled by `next-themes` `<ThemeProvider>`.

### CRITICAL — Dark Mode Text Rule

Tailwind v4 sets no default text color. Every user-visible text node needs an explicit `text-foreground` / `text-muted-foreground` / `text-destructive`, otherwise it renders black-on-dark in dark mode. Exempt components that set their own color: `Button`, `Badge`, `Label`, `DialogTitle`/`Description`, `AlertDialogTitle`/`Description`, `Checkbox`, `SelectTrigger`, `DropdownMenuItem`, `Input`, `Skeleton`. NOT exempt: headings, `<p>`, `<span>`, `<code>`, `<a>`, bare text in table cells / custom cell renderers, `<td>`/`TableCell`.

### Focus rings

Use `ring-[3px] ring-ring/50` (explicit px) — `ring-3` numeric shorthand is not reliable cross-version.

## Dev Workflow

- Install: `npm ci --legacy-peer-deps` (required — repo has a pre-existing eslint 10 / eslint-plugin-react peer conflict that CI tolerates).
- Build: `npm run typecheck` → `npm run build`. `public/hot` must not exist.
- Tailwind content: `@source` globs in `app.css` cover host + both vendored packages. When a package adds JS, its `@source` glob must exist.

## Version History

| Date | Action |
|------|--------|
| 2026-06-30 | Initial audit created |
| 2026-09-08 | Base UI migration — `base-vega` style, `@base-ui/react` everywhere, removed `radix-ui`/`vaul`/`sonner`, `shadcn` CLI added, all toasts on Base UI `toast.add()` |