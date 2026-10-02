---
name: base-ui-shadcn
description: "Develops UI components using Base UI and shadcn/ui with Tailwind CSS v4. Activates when creating or modifying React UI components, shadcn/ui primitives, Base UI components, Tailwind utility classes, dark mode theming, CSS variables, or when working with the design system, color tokens, or responsive layouts. Also activates for the critical dark mode text rule, the Base UI toast API, and component styling patterns in the platform / shop-module Laravel stack."
---

# Base UI + shadcn/ui Development (Laravel / nreljed/platform)

## When to Apply

Activate this skill when:

- Creating or modifying shadcn/ui components under `Components/ui/`
- Working with Base UI (`@base-ui/react`) primitives
- Using Tailwind CSS v4 utility classes
- Implementing dark mode theming
- Working with CSS custom properties and design tokens
- Debugging component styling or layout issues
- Creating new UI components following the project design system
- Using `next-themes` (`<ThemeProvider>`), or `useTheme` hooks
- Applying the `cn()` utility or `cva` variants
- Adding or reading toasts (`toast.add()` — NOT `sonner`)

---

## CRITICAL — Where To Edit

The host repo (`shop-laravel`) is a THIN SHELL. All UI components, CSS tokens
(`core.css`), and dashboard/blog/account pages live in the
`nreljed/platform` composer package; the blog kit lives in `nreljed/blog`; shop
pages live in `nreljed/shop-module`. They install into `vendor/` and map through
Vite aliases:

- `@nreljed/platform` / `@nreljed/platform/*` → `vendor/nreljed/platform/resources/js`
- `@nreljed/blog` / `@nreljed/blog/*` → `vendor/nreljed/blog/src/Resources/js`
- `@nreljed/shop-module` / `@nreljed/shop-module/*` → `vendor/nreljed/shop-module/src/Resources/js`

**NEVER edit `vendor/`.** Edit the package checkout, commit, tag, release —
the `core-update.yml` / `shop-update.yml` fan-out bumps `composer.lock` in the
app. Local dev typecheck/build reads the live `vendor/` tree, so after
`composer update` the new code is active without a dev server.

---

## CRITICAL — Dark Mode Text Rule

Every user-visible text node MUST have an explicit `text-foreground` / `text-muted-foreground` / `text-destructive` class. Without one, Tailwind v4 applies no color → browser default black → invisible on dark backgrounds.

### Why

Tailwind v4 does not set a default text color on elements. In light mode this is fine (browser default is black on white). In dark mode the background is dark, so unstyled text remains black — invisible against dark backgrounds.

### Exempt components (handle their own colors internally)

These components set their own text color in their variant definitions or base styles. Do NOT add `text-foreground` when using them:

- `Button`
- `Badge`
- `DialogTitle`
- `AlertDialogTitle`
- `DialogDescription`
- `AlertDialogDescription`
- `Label`
- `Checkbox`
- `SelectTrigger`
- `DropdownMenuItem`
- `Input`
- `Skeleton`

### NOT exempt — MUST have `text-foreground` or similar

Every one of these elements needs an explicit text color class:

- Every `<h1>`–`<h6>`
- Every `<p>`
- Every `<span>` with text content
- Every `<code>` with text content
- Every `<a>` (unless `text-muted-foreground` is intentional)
- Every `<div>` that contains visible text
- Bare text nodes in table cells or any custom cell renderer
- Every `<td>` / `TableCell`
- Custom cell renderers in data tables

---

## Toast System (MIGRATED from sonner → Base UI)

The core `Components/ui/toast.tsx` wraps `@base-ui/react/toast` via
`createToastManager()`. The core shell mounts a single `<Toaster />`. Use it
from ANY page component (platform OR shop-module):

```tsx
import { toast } from "@nreljed/platform/Components/ui/toast";

toast.add({ title: "Saved", type: "success" }); // success | info | warning | error | loading
```

**MIGRATION MAP (sonner → this API):**

| Old (sonner — REMOVED)        | New (Base UI)                                  |
| ----------------------------- | ---------------------------------------------- |
| `toast.success("msg")`        | `toast.add({ title: "msg", type: "success" })` |
| `toast.error("msg")`          | `toast.add({ title: "msg", type: "error" })`   |
| `import { toast } from "sonner"` | `import { toast } from "@nreljed/platform/Components/ui/toast"` |

**Do NOT** `import { toast } from "sonner"` — sonner was REMOVED (2026-09-08)
and there is NO sonner `<Toaster />` mounted (sonner toasts are silent
no-ops). Optional `description` field is supported:
`toast.add({ title, description, type })`.

---

## Pre-creation Checklist

Before creating any new page or component, verify:

1. Every `<h1>`–`<h6>` → `text-foreground` (unless used as `text-muted-foreground` intentionally)
2. Every `<span>`, `<div>`, `<p>` that has text content → `text-foreground`
3. Every custom table cell renderer → `text-foreground` on the text-bearing element
4. Every `<td>` / `TableCell` → `text-foreground`
5. Every `<code>` with text → `text-muted-foreground` or `text-foreground`

---

## CSS Variable Tokens & Design System

### Token source of truth

`vendor/nreljed/platform/resources/css/core.css` — imported into the
host `resources/css/app.css` AFTER `@import "tailwindcss"` + `@source` globs:

```css
@import "tailwindcss" source(none);
@source "../";
@source "../../vendor/nreljed/shop-module/src/Resources/js/";
@source "../../vendor/nreljed/platform/resources/js/";
@import "tw-animate-css";
@import "../../vendor/nreljed/platform/resources/css/core.css";
```

All values use `oklch()`. `@theme inline` maps `--color-*` → Tailwind utilities.

### Core color tokens

| CSS Variable         | Tailwind class               | Usage                               |
| -------------------- | ---------------------------- | ----------------------------------- |
| `--foreground`       | `text-foreground`            | Primary text color                  |
| `--muted-foreground` | `text-muted-foreground`      | Secondary/descriptive text          |
| `--destructive`      | `text-destructive`           | Error/destructive text and actions  |
| `--primary`          | `bg-primary`, `text-primary` | Primary actions, active states      |
| `--secondary`        | `bg-secondary`               | Secondary surfaces                  |
| `--accent`           | `bg-accent`, `text-accent`   | Accent highlights                   |
| `--muted`            | `bg-muted`                   | Muted backgrounds, subtle surfaces  |
| `--popover`          | `bg-popover`                 | Popover/dropdown/dialog backgrounds |
| `--card`             | `bg-card`                    | Card backgrounds                    |
| `--border`           | `border-border`              | Default border color                |
| `--input`            | `border-input`               | Input field borders                 |
| `--ring`             | `ring-ring`                  | Focus ring color                    |

### Status color tokens

Use CSS variable-based tokens, NOT hardcoded Tailwind colors:

| Token            | Tailwind usage                             | State                        |
| ---------------- | ------------------------------------------ | ---------------------------- |
| `status-success` | `bg-status-success/10 text-status-success` | Active, approved, positive   |
| `status-error`   | `bg-status-error/10 text-status-error`     | Error, rejected, destructive |
| `status-info`    | `bg-status-info/10 text-status-info`       | Informational, in-progress   |
| `warning`        | `bg-warning/10 text-warning`               | Pending, caution             |

NEVER use hardcoded `text-green-*`, `text-red-*`, `text-amber-*`, `text-blue-*` for status colors. There is a `status-badge.tsx` component for this.

### Radius tokens

`@theme inline` maps: `--radius-sm` = `calc(var(--radius) * 0.6)`, `--radius-md` = `* 0.8`, `--radius-lg` = `var(--radius)` (0.625rem), `--radius-xl` = `* 1.4`, up to `* 4`.

### NO overlay token

Unlike Project-WP, this app does NOT define `--overlay`. Modal overlays use `bg-black/10` directly. If you add a new overlay, keep the `bg-black/N` convention OR add the token to `core.css` (package change) — don't mix.

---

## Base UI (`@base-ui/react`)

### Overview

Base UI provides unstyled, accessible primitives. ALL shadcn/ui components use Base UI. There is a `radix-ui` package in neither host deps nor source imports — `@radix-ui/*` only appears transitively (via `cmdk`). Do not introduce Radix imports.

### Package

```json
"@base-ui/react": "^1.5.0"
```

### `components.json` style

`"style": "base-vega"` — the `base-` prefix selects the Base UI primitive registry; `vega` is the theme style. `npx shadcn add/view` therefore resolves Base UI components. Do NOT rename to a bare theme name; do NOT run `npx shadcn apply --preset`.

### Import pattern

Base UI primitives are imported from subpath exports:

```tsx
import { Dialog as DialogPrimitive } from "@base-ui/react/dialog";
import { AlertDialog as AlertDialogPrimitive } from "@base-ui/react/alert-dialog";
import { Select as SelectPrimitive } from "@base-ui/react/select";
import { Checkbox as CheckboxPrimitive } from "@base-ui/react/checkbox";
import { Menu as MenuPrimitive } from "@base-ui/react/menu";
import { Input as InputPrimitive } from "@base-ui/react/input";
import { Button as ButtonPrimitive } from "@base-ui/react/button";
```

### Component anatomy

```tsx
function MyComponent({ className, ...props }: PrimitiveType.Props) {
  return (
    <PrimitiveType data-slot="my-component" className={cn("base-styles", className)} {...props} />
  );
}
```

### Data attributes (Base UI pattern)

| State | Attribute | Note |
| ----- | --------- | ---- |
| open/closed | `data-open` / `data-closed` | NOT `data-state` |
| checked | `data-checked` | checkbox/radio |
| selected | `data-selected` | menu list item, select item |
| orientation | `data-horizontal` / `data-vertical` | custom variant (tabs, slider, separator) |

Animations:

```tsx
<DialogPrimitive.Popup
  className="data-open:animate-in data-open:fade-in-0 data-open:zoom-in-95 data-open:slide-in-from-bottom-2 data-closed:animate-out data-closed:fade-out-0 data-closed:zoom-out-95"
/>
```

### `render` prop pattern

Base UI uses `render` (NOT `asChild` / Slot):

```tsx
<DialogPrimitive.Close render={<Button variant="ghost" size="icon-sm" />}>
  <XIcon />
</DialogPrimitive.Close>
```

### Positioning

Select and DropdownMenu use `Positioner` for floating placement:

```tsx
<SelectPrimitive.Positioner side="bottom" sideOffset={4} align="center" alignItemWithTrigger>
  <SelectPrimitive.Popup>{children}</SelectPrimitive.Popup>
</SelectPrimitive.Positioner>
```

### Focus rings

Use `ring-[3px] ring-ring/50` on focus-visible — not `ring-3` (numeric shorthand is not reliable cross-version).

---

## Tailwind v4 Specifics

### Vite plugin (NOT PostCSS)

```js
// vite.config.js
import tailwindcss from "@tailwindcss/vite";
plugins: [tailwindcss()];
```

### No `tailwind.config.js`

All configuration lives in CSS. `@theme inline` maps CSS vars → utilities.

### Dark mode variant

```css
@custom-variant dark (&:is(.dark *));
```

Class-based. `.dark` is toggled on `<html>` by `next-themes` `<ThemeProvider>`.

### Source scanning

Tailwind source globs live in the HOST `app.css` (`@source`), covering host JS plus both vendored packages. When a new JS dir is added in platform or shop-module, ensure the app's `@source` glob includes it.

### Animation library

`@import "tw-animate-css"` provides `animate-in`, `animate-out`, `fade-in-0`, `zoom-in-95`, etc.

---

## Common Patterns

### Namespace import for React

```tsx
import * as React from "react";
// NOT: import React from "react";
```

### Toasts (NOT sonner)

```tsx
import { toast } from "@nreljed/platform/Components/ui/toast";
toast.add({ title: "Created", type: "success" });
```

### Button variant usage

```tsx
<Button variant="default">Primary</Button>
<Button variant="outline">Outline</Button>
<Button variant="destructive">Delete</Button>
<Button variant="ghost">Ghost</Button>
<Button variant="secondary">Secondary</Button>
<Button variant="link">Link</Button>
```

### Dialog pattern

```tsx
<Dialog>
  <DialogTrigger render={<Button />}>Open</DialogTrigger>
  <DialogContent>
    <DialogHeader>
      <DialogTitle>Title</DialogTitle>
      <DialogDescription>Description</DialogDescription>
    </DialogHeader>
    {/* content */}
    <DialogFooter>
      <DialogClose render={<Button variant="outline" />}>Cancel</DialogClose>
      <Button>Confirm</Button>
    </DialogFooter>
  </DialogContent>
</Dialog>
```

### Select pattern

```tsx
<Select defaultValue="us">
  <SelectTrigger>
    <SelectValue />
  </SelectTrigger>
  <SelectContent>
    <SelectItem value="us">United States</SelectItem>
    <SelectItem value="uk">United Kingdom</SelectItem>
  </SelectContent>
</Select>
```

### DropdownMenu pattern

```tsx
<DropdownMenu>
  <DropdownMenuTrigger render={<Button variant="ghost" size="icon-sm" />}>
    <MoreHorizontalIcon />
  </DropdownMenuTrigger>
  <DropdownMenuContent>
    <DropdownMenuItem>Edit</DropdownMenuItem>
    <DropdownMenuSeparator />
    <DropdownMenuItem variant="destructive">Delete</DropdownMenuItem>
  </DropdownMenuContent>
</DropdownMenu>
```

### Table with dark mode text

```tsx
<Table>
  <TableHeader>
    <TableRow>
      <TableHead>Name</TableHead> {/* TableHead has text-foreground built in */}
    </TableRow>
  </TableHeader>
  <TableBody>
    <TableRow>
      <TableCell>
        <span className="text-foreground">Value</span> {/* MUST have text-foreground */}
      </TableCell>
    </TableRow>
  </TableBody>
</Table>
```

---

## Anti-patterns

### DO NOT

- `import { toast } from "sonner"` — use `toast.add({ title, type })` from `@nreljed/platform/Components/ui/toast`
- Use hardcoded colors (`text-green-500`, `bg-red-100`) for status — use `status-*` tokens
- Skip `text-foreground` on `<span>`, `<p>`, `<div>`, `<h1>`–`<h6>`, `<td>` because "it works in light mode"
- Import from `@radix-ui/react-*` or `radix-ui` for new components — use `@base-ui/react`
- Use `import React from "react"` — use `import * as React from "react"`
- Use `data-state` for Base UI animations — Base UI uses `data-open` / `data-closed`
- Use `ring-3` — use `ring-[3px]`
- Add `text-foreground` to exempt components (Button, Badge, Input, etc.) — they set their own colors
- Edit `vendor/` — edit the package checkouts and release

### DO

- Always use `cn()` for className composition
- Set `data-slot="component-name"` on every shadcn/ui component root
- Use `render` prop for Base UI composition (not `asChild`)
- Use `cva` for multi-variant components
- Check both light and dark mode when adding text to any component
- Use `text-muted-foreground` for secondary/descriptive text
- Use `bg-muted/50` for subtle hover/selected states on table rows
- Add toasts via `toast.add({ title, type })`

---

## Dependency Versions

| Package                    | Version    | Notes                             |
| -------------------------- | ---------- | --------------------------------- |
| `tailwindcss`              | `^4.0.0`   | v4 — no config file, CSS-first    |
| `@tailwindcss/vite`        | `^4.0.0`   | Vite plugin (NOT PostCSS)         |
| `@base-ui/react`           | `^1.5.0`   | Unstyled primitives (NOT Radix)   |
| `shadcn` (devDep)          | `^4.19.1`  | Component generator / diff        |
| `tw-animate-css`           | `^1.4.0`   | Animation utilities               |
| `class-variance-authority` | `^0.7.1`   | Variants (`cva`)                  |
| `clsx` / `tailwind-merge`  | `^2.1.1` / `^3.6.0` | `cn()` composition        |
| `lucide-react`             | `^1.35.0`  | Icons                             |
| `embla-carousel-react`     | `^8.6.0`   | Required by `carousel.tsx`        |
| `input-otp`                | `^1.5.0`   | Required by `input-otp.tsx`       |