# shadcn/ui

## Config

| Field           | Value                      |
| --------------- | -------------------------- |
| Style           | `new-york`                 |
| Base color      | `neutral`                  |
| CSS variables   | `true`                     |
| Tailwind config | Empty (Tailwind v4 native) |
| Icon library    | `lucide`                   |
| RSC             | `false`                    |

## Component Inventory (56)

### Layout

`accordion`, `aspect-ratio`, `card`, `collapsible`, `resizable`, `separator`, `sidebar`

### Navigation

`breadcrumb`, `command`, `context-menu`, `dropdown-menu`, `menubar`, `navigation-menu`, `pagination`, `tabs`

### Forms

`button`, `button-group`, `checkbox`, `combobox`, `field`, `input`, `input-group`, `input-otp`, `label`, `radio-group`, `select`, `slider`, `switch`, `textarea`, `toggle`, `toggle-group`

### Data Display

`badge`, `data-table`, `data-table-column-header`, `data-table-filters`, `data-table-pagination`, `data-table-toolbar`, `table`

### Feedback

`alert`, `alert-dialog`, `dialog`, `drawer`, `empty`, `hover-card`, `popover`, `progress`, `skeleton`, `spinner`

### Media

`avatar`, `calendar`, `carousel`, `scroll-area`, `sheet`

### Overlays

`sonner`, `tooltip`

## Custom Components

Extended from shadcn patterns:

| Component | File             | Notes                               |
| --------- | ---------------- | ----------------------------------- |
| `field`   | `ui/field.tsx`   | Custom CVA variants for form fields |
| `empty`   | `ui/empty.tsx`   | Empty state display                 |
| `spinner` | `ui/spinner.tsx` | Loading spinner                     |

## Dependencies

| Package                    | Purpose             |
| -------------------------- | ------------------- |
| `radix-ui`                 | Headless primitives |
| `class-variance-authority` | Component variants  |
| `clsx`                     | Conditional classes |
| `tailwind-merge`           | Class deduplication |
| `tw-animate-css`           | Animation utilities |
| `cmdk`                     | Command palette     |
| `vaul`                     | Drawer component    |
| `sonner`                   | Toast notifications |
| `embla-carousel-react`     | Carousel            |
| `react-day-picker`         | Calendar            |
| `input-otp`                | OTP input           |

## Adding Components

```bash
npx shadcn@latest add button card dialog
npx shadcn@latest add --all
```

## Conventions

- All components use `cn()` from `@/lib/utils`
- `data-slot` attributes for styling hooks
- `asChild` / `Slot` pattern for composition
- Tailwind v4 with `@theme inline` CSS variables
- OKLCH color system (not HSL)
