# shadcn/ui Integration

## Version & Config

| Field | Value |
|-------|-------|
| Style | `new-york` |
| Base color | `neutral` |
| CSS variables | `true` |
| Tailwind config | Empty (Tailwind v4 native) |
| Icon library | `lucide` |
| React Server Components | `false` |
| Aliases | `@/components`, `@/lib/utils`, `@/components/ui`, `@/lib`, `@/hooks` |

Components registry: Standard shadcn/ui registry (no third-party registries like CRM-dashboard's `@wds`, `@diceui`, `@shadcn-map`).

---

## Dependency Versions

| Package | Laravel | CRM-dashboard | Status |
|---------|---------|---------------|--------|
| `radix-ui` | ^1.6.0 | ^1.6.0 | Same |
| `class-variance-authority` | ^0.7.1 | ^0.7.1 | Same |
| `clsx` | ^2.1.1 | ^2.1.1 | Same |
| `tailwind-merge` | ^3.6.0 | ^3.6.0 | Same |
| `lucide-react` | ^1.20.0 | ^1.21.0 | Slightly behind |
| `tailwindcss-animate` | ^1.0.7 | Not used | **CRM uses `tw-animate-css` v1.4.0** |
| `tw-animate-css` | Not used | ^1.4.0 | **Missing in Laravel** |
| `tailwindcss` | ^4.0.0 | ^4.3.1 | Behind |
| `@tailwindcss/vite` | ^4.0.0 | ^4.3.1 | Behind |
| `cmdk` | ^1.1.1 | ^1.1.1 | Same |
| `input-otp` | ^1.4.2 | ^1.4.2 | Same |
| `sonner` | ^2.0.7 | ^2.0.7 | Same |
| `vaul` | ^1.1.2 | ^1.1.2 | Same |
| `embla-carousel-react` | ^8.6.0 | ^8.6.0 | Same |
| `react-day-picker` | ^10.0.1 | ^10.0.1 | Same |
| `@tanstack/react-table` | ^8.21.3 | ^8.21.3 | Same |
| `@base-ui/react` | ^1.5.0 | ^1.6.0 | Behind (combobox only) |

---

## Component Inventory

### Standard shadcn/ui Components (37)

```
accordion   alert       alert-dialog   aspect-ratio
avatar      badge       breadcrumb     button
calendar    card        carousel       checkbox
collapsible command     context-menu   dialog
drawer      dropdown-menu form         hover-card
input       input-otp   label          menubar
navigation-menu  pagination  popover   progress
radio-group scroll-area  select        separator
sheet       sidebar     skeleton       slider
switch      table       tabs           textarea
toggle      toggle-group  tooltip
```

### Custom/Extended Components (43)

```
avatar-uploader       button-group         combobox
country-select       cropper-dialog       current-password-input
data-table           data-table-column-header
data-table-filters   data-table-pagination
data-table-toolbar   datetime-picker      empty
featured-image-selector   field           file-upload
gallery-sortable-image   gallery-uploader
input-group          map                  map-debug-panel
mobile-detail-dialog number-input        password-input
phone-input          place-autocomplete   publish-section
repeater             settings-accordion   sonner
spinner              taxonomy-selector    terms-badge
terms-selector       url-input            url-video
```

---

## Audit Findings

### ✅ Correct — Already on Latest shadcn/ui v4 Patterns

| Pattern | Status |
|---------|--------|
| `data-slot` attributes on all primitives | ✅ All components |
| `radix-ui` monorepo (Feb 2026 change) | ✅ All except `button-group.tsx` (now fixed) |
| OKLCH color space (not HSL) | ✅ |
| Tailwind v4 (`@import "tailwindcss"`) | ✅ |
| React 19 types (no `forwardRef`) | ✅ All components |
| `@theme inline` CSS variable mapping | ✅ |
| `new-york` style | ✅ |
| `neutral` base color | ✅ |
| Sonner for toasts (no legacy `toast.tsx`) | ✅ |
| CSS variables complete (sidebar + chart tokens) | ✅ |

### 🐛 Issues Found & Fixed

| File | Issue | Fix Applied |
|------|-------|-------------|
| `button-group.tsx` | Used `@radix-ui/react-slot` (old scoped import) instead of `radix-ui` monorepo | ✅ Changed to `import { Slot } from "radix-ui"` + `Slot.Root` |

### ⚠️ Remaining Issues

| Issue | Priority | Impact | Fix |
|-------|----------|--------|-----|
| `tailwindcss-animate` vs `tw-animate-css` | Medium | `tw-animate-css` is the Tailwind v4-native animation plugin; `tailwindcss-animate` is the legacy v3 port | Migrate to `tw-animate-css` |
| `@base-ui/react` outdated (1.5.0 vs 1.6.0) | Low | Used only in `combobox.tsx` | `npm install @base-ui/react@^1.6.0` |
| `lucide-react` outdated (1.20.0 vs 1.21.0) | Low | Icon version drift | `npm install lucide-react@^1.21.0` |
| `tailwindcss` outdated (4.0.0 vs 4.3.1) | Low | Missing Tailwind v4.1+ features | `npm install tailwindcss@^4.3.1` |

---

## CSS Variables Audit

### Present (both :root and .dark)

All standard shadcn/neutral tokens present: `background`, `foreground`, `card`, `popover`, `primary`, `secondary`, `muted`, `accent`, `destructive`, `border`, `input`, `ring`, `chart-1` through `chart-5`, `sidebar` through `sidebar-ring`.

### Missing vs CRM-dashboard

| Token | CRM-dashboard | Laravel | Priority |
|-------|---------------|---------|----------|
| `--warning` (oklch) | ✅ | ❌ | Low |
| `--status-available` / `--status-booked` / etc. | ✅ | ❌ | Low (booking-specific) |
| `--featured` | ✅ | ❌ | Low |
| `--password-fair` / `--password-strong` | ✅ | ❌ | Low (password input styling) |
| `--sidebar-width` / `--sidebar-width-icon` | In CSS variables | In sidebar.tsx constants | Low |

---

## Recommended Actions

### 1. Migrate from `tailwindcss-animate` to `tw-animate-css`

`tw-animate-css` is the Tailwind v4-native animation plugin. `tailwindcss-animate` is a legacy port.

```bash
npm uninstall tailwindcss-animate
npm install tw-animate-css@^1.4.0
```

Then update `resources/css/app.css`:

```css
@plugin "tw-animate-css";
/* Remove: @plugin "tailwindcss-animate"; */
```

Typecheck + build to verify.

### 2. Upgrade dependencies

```bash
npm install tailwindcss@^4.3.1 @tailwindcss/vite@^4.3.1 lucide-react@^1.21.0 @base-ui/react@^1.6.0
```

### 3. Add third-party registries (optional)

CRM-dashboard uses `@wds`, `@diceui`, `@shadcn-map` registries in `components.json`. These are optional but could be useful if you want:

- `@wds`: Additional shadcn components from Webscope's design system
- `@diceui`: Collection of extended UI primitives
- `@shadcn-map`: Ready-made Leaflet map shadcn components

### 4. Use `@import "tailwindcss" source(none)` with `@source` directives

CRM-dashboard uses a more explicit Tailwind v4 content source config:

```css
@import "tailwindcss" source(none);
@source "../resources/js/";
@source "../../app/Modules/Properties/Resources/js/";
@source "../../app/Modules/Booking/Resources/js/";
@source "../../app/Modules/Shop/Resources/js/";
@source "../../app/Modules/Posts/Resources/js/";
```

This is more maintainable than relying on the default source resolution.

---

## Version History

| Date | Action |
|------|--------|
| 2026-06-30 | Audit created — button-group.tsx import fixed |
