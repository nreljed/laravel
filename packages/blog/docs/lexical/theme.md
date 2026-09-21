# Theme

`src/Resources/js/Components/editor/editor-theme.ts`

Tailwind CSS utility class mappings for all Lexical node types. Used as the `theme` prop in `LexicalComposer`.

## Text

| Node Type            | Classes                                   |
| -------------------- | ----------------------------------------- |
| `paragraph`          | `leading-7 [&:not(:first-child)]:mt-6`    |
| `text.bold`          | `font-bold`                               |
| `text.italic`        | `italic`                                  |
| `text.underline`     | `underline`                               |
| `text.strikethrough` | `line-through`                            |
| `text.code`          | `bg-muted rounded font-mono text-[0.9em]` |
| `text.subscript`     | `sub`                                     |
| `text.superscript`   | `sup`                                     |
| `text.highlight`     | `bg-yellow-200 dark:bg-yellow-800`        |
| `ltr` / `rtl`        | `text-left` / `text-right`                |

## Headings

| Level | Classes                                                                      |
| ----- | ---------------------------------------------------------------------------- |
| `h1`  | `scroll-m-20 text-4xl font-extrabold tracking-tight lg:text-4xl`             |
| `h2`  | `scroll-m-20 border-b pb-2 text-3xl font-semibold tracking-tight first:mt-0` |
| `h3`  | `scroll-m-20 text-2xl font-semibold tracking-tight`                          |
| `h4`  | `scroll-m-20 text-xl font-semibold tracking-tight`                           |
| `h5`  | `scroll-m-20 text-lg font-semibold tracking-tight`                           |
| `h6`  | `scroll-m-20 text-base font-semibold tracking-tight`                         |

## Lists

| Type             | Classes                                                               |
| ---------------- | --------------------------------------------------------------------- |
| `list.ul`        | `m-0 p-0 list-outside list-disc [&>li]:mt-2 ml-6`                     |
| `list.ol`        | `m-0 p-0 list-outside list-decimal [&>li]:mt-2 ml-6`                  |
| `list.ulDepth`   | 5 levels, all `list-disc ml-6`                                        |
| `list.olDepth`   | 5 levels: decimal, upper-roman, lower-roman, upper-alpha, lower-alpha |
| `list.checklist` | Custom checkbox via `::before` pseudo-element (`☑` / `☐`)             |

## Code

| Element | Classes                                                     |
| ------- | ----------------------------------------------------------- |
| `code`  | `bg-muted text-foreground p-4 rounded-lg font-mono text-sm` |

## Blockquote

| Element | Classes                                                      |
| ------- | ------------------------------------------------------------ |
| `quote` | `border-l-2 border-border pl-6 italic text-muted-foreground` |

## Link

| Element | Classes                                                                                       |
| ------- | --------------------------------------------------------------------------------------------- |
| `link`  | `text-link font-medium underline underline-offset-4 decoration-link/40 hover:decoration-link` |

## Table

| Element                          | Classes                                                                                              |
| -------------------------------- | ---------------------------------------------------------------------------------------------------- |
| `table`                          | `border-collapse border-spacing-0 table-layout-fixed w-fit mt-[25px] mb-[30px]`                      |
| `tableScrollableWrapper`         | `overflow-x-auto mb-[5px] [&_table]:mt-0 [&_table]:mb-0`                                             |
| `tableCell`                      | `w-[75px] border border-border px-2 py-1.5 text-start align-top relative outline-none overflow-auto` |
| `tableCellHeader`                | `w-[75px] bg-muted border border-border px-2 py-1.5 text-start font-semibold align-top`              |
| `tableCellSelected`              | `lexical-table-cell-selected` (CSS class)                                                            |
| `tableRowStriping`               | `[&_tr:nth-child(even)]:bg-muted/50`                                                                 |
| `tableRowSelected`               | `""` (empty)                                                                                         |
| `tableSelected`                  | `[&:focus-within]:outline-2 [&:focus-within]:outline-ring`                                           |
| `tableFrozenRow`                 | `table-frozen-row` (CSS class)                                                                       |
| `tableFrozenColumn`              | `table-frozen-col` (CSS class)                                                                       |
| `tableCellResizer`               | `table-cell-resizer` (CSS class)                                                                     |
| `tableCellActionButtonContainer` | `table-cell-action-btn-container` (CSS class)                                                        |
| `tableCellActionButton`          | `table-cell-action-btn` (CSS class)                                                                  |
| `tableAddColumns`                | `table-add-columns` (CSS class)                                                                      |

### Dark Mode

`<th>` background is overridden in `app.css`:

```css
.dark th {
  background-color: oklch(26.9% 0 0);
}
```

This overrides the `bg-muted` Tailwind class for dark mode, using a direct oklch value that matches the design system.

## Layout

| Element           | Classes           |
| ----------------- | ----------------- |
| `layoutContainer` | `grid gap-4 my-4` |
| `layoutItem`      | `min-h-[2rem]`    |

## Image

| Element | Classes                            |
| ------- | ---------------------------------- |
| `image` | `block my-4 rounded-md max-w-full` |

## Horizontal Rule

| Element          | Classes                       |
| ---------------- | ----------------------------- |
| `horizontalLine` | `border-t border-border my-6` |

## Indentation

| Element  | Value                                                     |
| -------- | --------------------------------------------------------- |
| `indent` | `--lexical-indent-base-value: 40px` (CSS custom property) |

## Cell Selector

The `TableHoverActionsPlugin` constructs a CSS selector from `theme.tableCell` by splitting the multi-class string and escaping each class individually:

```typescript
function getCellSelector(getTheme) {
  const cellClassName = getTheme()?.tableCell;
  if (!cellClassName) return "td, th";
  return cellClassName
    .split(/\s+/)
    .filter(Boolean)
    .map((cls) => `.${CSS.escape(cls)}`)
    .join("");
}
```

This is required because the `tableCell` theme is a multi-class Tailwind string, and `CSS.escape()` applied to the whole string breaks the selector.

## Conventions

- All table-related classes use Tailwind utilities (no CSS modules)
- `bg-muted` for header cells and striped rows
- `border-border` for consistent border coloring across themes
- `lexical-table-cell-selected` is a plain CSS class (defined in `app.css`) for selection highlighting
- `table-frozen-row` and `table-frozen-col` are plain CSS classes for sticky positioning
