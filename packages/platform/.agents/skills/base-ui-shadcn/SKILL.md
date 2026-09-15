---
name: base-ui-shadcn
description: "Develops UI components using Base UI and shadcn/ui with Tailwind CSS v4. Activates when creating or modifying React UI components in platform, shadcn/ui primitives, Base UI components, Tailwind utility classes, dark mode theming, CSS variables, the `cn` package, or when working with the design system, color tokens, or responsive layouts. Also activates for the critical dark mode text rule and component styling patterns."
---

# Base UI + shadcn/ui Development (platform)

## When to Apply

Activate this skill when:

- Creating or modifying shadcn/ui components under `resources/js/Components/ui/`
- Working with Base UI (`@base-ui/react`) primitives
- Using Tailwind CSS v4 utility classes
- Implementing dark mode theming
- Working with CSS custom properties and design tokens
- Debugging component styling or layout issues
- Working with the `cn` package or `cva` variants
- Composing primitives via the `render` prop

---

## Core Rules (in priority order)

1. **Composition = `render` prop, NEVER `asChild`.** Base UI primitives (and all shadcn
   wrappers in this repo) accept a `render` prop. `asChild` was removed from every trigger
   (trigger, dialog, alert-dialog, dropdown-menu, popover, sheet) and from buttons in
   platform — consumers were all migrated. Writing new `asChild` code is a breaking
   error. `<X asChild>` fails typecheck because the prop no longer exists.
2. **Toasts = `toast.add({ title, type })`, NEVER `sonner`.** The sonner package was removed
   from all consumers. Use `import { toast } from "@/Components/ui/toast"`. Types:
   `"success" | "error" | "info" | "warning"`. The app shell renders `<Toaster />`.
3. **`cn` from the `cn` package**, not clsx/tailwind-merge. `@/lib/utils` re-exports it:
   `export { cn } from "cn"`. Consumers install `cn` as a direct dependency (it replaces
   `clsx` + `tailwind-merge` — do not add those back).
4. **Base UI primitives only** — never `@radix-ui/react-*`. The components.json style is
   `base-vega` (Base UI primitive registry). Status colors use `status-*` tokens, never
   hardcoded `text-green-*`/`text-red-*`.
5. **Dark Mode Text Rule** (see below) — enforce on every change.

---

## CRITICAL — Dark Mode Text Rule

Every user-visible text node MUST have an explicit `text-foreground` / `text-muted-foreground` / `text-destructive` class. Without one, Tailwind v4 applies no color → browser default black → invisible on dark backgrounds.

### Why

Tailwind v4 does not set a default text color on elements. In light mode this is fine (browser default is black on white). In dark mode the background is dark, so unstyled text remains black — invisible against dark backgrounds.

### Exempt components (handle their own colors internally)

These components set their own text color in their variant definitions or base styles. You do NOT need to add `text-foreground` when using them:

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

- Every `<h1>`–`<h6>`
- Every `<p>`
- Every `<span>` with text content
- Every `<code>` with text content (use `text-muted-foreground` or `text-foreground`)
- Every `<a>` (unless `text-muted-foreground` is intentional)
- Every `<div>` that contains visible text
- Bare text nodes in table cells or any custom cell renderer
- Every `<td>` / `TableCell` + custom cell renderers in data tables

---

## Design Tokens & CSS

Consuming apps import `resources/css/core.css` and may override any subset of CSS variables
under `@layer base`. Core's `core.css` is the shared source (never hand-edit for one-off app
tweaks — apps override tokens instead). All values use `oklch()`.

| CSS Variable         | Tailwind class            | Usage                              |
| -------------------- | ------------------------- | ---------------------------------- |
| `--foreground`       | `text-foreground`         | Primary text color                 |
| `--muted-foreground` | `text-muted-foreground`   | Secondary/descriptive text         |
| `--destructive`      | `text-destructive`        | Error/destructive text and actions |
| `--primary`          | `bg-primary`, `text-primary` | Primary actions, active states   |
| `--secondary`        | `bg-secondary`            | Secondary surfaces                 |
| `--accent`           | `bg-accent`, `text-accent` | Accent highlights                 |
| `--muted`            | `bg-muted`                | Muted backgrounds, subtle surfaces |
| `--popover`          | `bg-popover`              | Popover/dropdown/dialog backgrounds|
| `--card`             | `bg-card`                 | Card backgrounds                   |
| `--border`           | `border-border`           | Default border color               |
| `--input`            | `border-input`            | Input field borders                |
| `--ring`             | `ring-ring`               | Focus ring color                   |
| `--overlay`          | `bg-overlay`              | Overlay/backdrop color             |

Status tokens: `status-success`, `status-error`, `status-info`, `warning` — used as
`bg-status-success/10 text-status-success` etc. Accent tokens: `accent-purple`,
`accent-rose`, `accent-cyan`, `accent-pink`, `featured` — always `bg-X/10 text-X`.
Radius derives from `--radius` via `calc(var(--radius) * N)` scales.

Use `bg-overlay/N` (opacity modifier), NOT `bg-black/N`. Tailwind v4 handles the overlay
token via `@theme inline` in the app's `app.css`.

## Component Location

- Generic/reusable primitives → `resources/js/Components/ui/`
- App-specific shared → `resources/js/Components/shared/` (or a dir under `Components/`)
- Module pages → inside the module's `Resources/js/Pages/...`
- Everything imports via `@/Components/...` (capital C — Windows FS workaround; lowecase
  `@/components/...` breaks).

---

## Base UI (`@base-ui/react`) — the only primitive source

Base UI provides unstyled, accessible primitives. Every shadcn component here wraps a Base
UI primitive, adds styling via `cn()`, and sets `data-slot="component-name"`.

### Import pattern

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

### Data attributes

Base UI uses `data-open`/`data-closed` (NOT `data-state`) and `data-checked`:

```tsx
<DialogPrimitive.Popup className="data-open:animate-in data-open:fade-in-0 data-closed:animate-out data-closed:fade-out-0" />
<CheckboxPrimitive.Root className="data-checked:bg-primary" />
```

### `render` prop pattern (THE composition mechanism)

```tsx
// Close button inside a dialog
<DialogPrimitive.Close render={<Button variant="ghost" size="icon-sm" />}>
  <XIcon />
</DialogPrimitive.Close>

// Trigger as a button
<DialogTrigger render={<Button />}>Open dialog</DialogTrigger>

// Trigger as a navigation link (child props move into render)
<Button render={<Link href={route("blog.index")} />} variant="outline">
  View posts
</Button>
```

Rules:
- The `render` element holds ONLY the child element's props (href, preserveState, onClick,
  its own className). Do NOT put its children there.
- The visible children (text, icons) become children of the OUTER component.
- Keep variant/size/className on the outer component.
- `render` goes first among JSX attributes.

### Positioning

Select and DropdownMenu use `Positioner`:

```tsx
<SelectPrimitive.Positioner side="bottom" sideOffset={4} align="center">
  <SelectPrimitive.Popup>{children}</SelectPrimitive.Popup>
</SelectPrimitive.Positioner>
```

---

## `cn` package

`@/lib/utils.ts` re-exports `cn` from the `cn` npm package (zero-dep drop-in for
clsx + tailwind-merge):

```ts
export { cn } from "cn";
```

Consumers install it as a direct dependency; the core `vite.dedupe.js` includes `cn` in the
canonical dedupe list. Never import from `clsx` or `tailwind-merge` directly.

`cva` (class-variance-authority) is still used for variant components — only clsx and
tailwind-merge were replaced by `cn`.

---

## Common Patterns

### Dialog

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

### AlertDialog

```tsx
<AlertDialog>
  <AlertDialogTrigger render={<Button variant="destructive" />}>Delete</AlertDialogTrigger>
  <AlertDialogContent>
    <AlertDialogHeader>
      <AlertDialogTitle>Confirm deletion</AlertDialogTitle>
      <AlertDialogDescription>This cannot be undone.</AlertDialogDescription>
    </AlertDialogHeader>
    <AlertDialogFooter>
      <AlertDialogCancel>Cancel</AlertDialogCancel>
      <AlertDialogAction variant="destructive">Delete</AlertDialogAction>
    </AlertDialogFooter>
  </AlertDialogContent>
</AlertDialog>
```

### DropdownMenu

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

### DataTable

New API (since core v0.1.36): `defaultPageSize` (renamed from `pageSize`),
`pageSizeOptions` (default `[10,20,50,100]`), `loading` (skeleton rows), `toolbarRight`.
The select column always renders; bulk actions bar appears when rows are selected and
`onBulkAction` is provided (`bulkActions` optional — falls back to status-derived actions).

---

## Anti-patterns

- `asChild` on any shadcn/Base UI component — removed; use `render`
- `sonner` toasts — removed; use `toast.add({ title, type })`
- `@radix-ui/react-*` imports — use `@base-ui/react` subpath exports
- Hardcoded status colors (`text-green-500`, `bg-red-100`) — use `status-*` tokens
- `bg-black/50` overlays — use `bg-overlay/50`
- Un-styled text on dark surfaces (missing `text-foreground`/`text-muted-foreground`)
- `data-state` for Base UI animations — use `data-open`/`data-closed`
- `import React from "react"` — use `import * as React from "react"` (or named imports)
- `process.env.NODE_ENV` in frontend — use `import.meta.env.DEV`/`.PROD`
- `clsx`/`tailwind-merge` imports — they were replaced by the `cn` package

---

## Dependency Versions (core)

| Package            | Version    | Notes                                     |
| ------------------ | ---------- | ----------------------------------------- |
| `@base-ui/react`   | `^1.6.0`   | Unstyled primitives (NOT Radix)           |
| `cn`               | `^0.2.6`   | Zero-dep cn replacement (no clsx/tw-merge)|
| `tailwindcss`      | `^4.x`     | v4 — CSS-first, no JS config              |
| `@shadcn/react`    | `^0.3.0`   | shadcn React package (components)         |
| `shadcn`           | `^4.x`     | CLI devDep (components.json base-vega)    |
| `tw-animate-css`   | `^1.4.0`   | Animation utilities                       |
| `class-variance-authority` | `^0.7.1` | `cva` variant management         |
| `lucide-react`     | `^1.44.0`  | Icons                                    |
| `@tanstack/react-table` | `^9.2.3` | DataTable (features API)                 |

## Key Files

| File                                            | Purpose                                  |
| ----------------------------------------------- | ---------------------------------------- |
| `resources/js/Components/ui/`                   | All shadcn/ui base components (Base UI)  |
| `resources/js/lib/utils.ts`                     | `export { cn } from "cn"`, format helpers |
| `resources/js/Components/ui/toast.tsx`          | `toast.add({ title, type })` manager     |
| `resources/js/Components/ui/data-table.tsx`     | Unified DataTable (defaultPageSize etc.) |
| `resources/css/core.css`                        | Shared design tokens — apps override     |
| `components.json`                               | `style: "base-vega"` (Base UI registry)  |
| `vite.dedupe.js`                                | Canonical dedupe list (includes `cn`)    |