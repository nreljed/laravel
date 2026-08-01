# Table

`resources/js/Components/editor/table/`

Full table feature with insert, resize, context menu, hover actions, column drag-reorder, sort, scroll shadows, cell background colors, merge/unmerge, headers, striping, and freeze. Compared feature-for-feature against the [official Lexical playground](https://github.com/facebook/lexical/tree/main/packages/lexical-playground).

## Feature Coverage vs Playground

### Feature Parity Matrix

| Feature                                       | Playground                          | Ours                      | Status                                 |
| --------------------------------------------- | ----------------------------------- | ------------------------- | -------------------------------------- |
| Insert table (rows × cols)                    | `InsertTableDialog`                 | `InsertTableDialog`       | **Parity**                             |
| Context menu — merge cells                    | `TableActionMenuPlugin`             | `TableActionMenuPlugin`   | **Parity**                             |
| Context menu — unmerge cells                  | same                                | same                      | **Parity**                             |
| Context menu — background color               | `ColorPicker` modal (full wheel)    | 9-color preset grid       | **Different**                          |
| Context menu — vertical align                 | Top/Middle/Bottom                   | Top/Middle/Bottom         | **Parity**                             |
| Context menu — row striping toggle            | same                                | same                      | **Parity**                             |
| Context menu — freeze row/col                 | same                                | same                      | **Parity**                             |
| Context menu — insert row above/below (batch) | same                                | same                      | **Parity**                             |
| Context menu — insert col left/right (batch)  | same                                | same                      | **Parity**                             |
| Context menu — delete row/col/table           | same                                | same                      | **Parity**                             |
| Context menu — row/col header toggle          | same                                | same                      | **Parity**                             |
| Context menu — selection count display        | "N rows × M cols"                   | "N rows × M cols"         | **Parity**                             |
| Hover — add column                            | `TableHoverActionsV2Plugin`         | `TableHoverActionsPlugin` | **Parity**                             |
| Hover — add row                               | same                                | same                      | **Parity**                             |
| Hover — sort ascending/descending             | same                                | same                      | **Parity**                             |
| Hover — column drag-reorder                   | `@atlaskit/pragmatic-drag-and-drop` | same                      | **Parity**                             |
| Cell resize — column width                    | `TableCellResizer`                  | `TableCellResizerPlugin`  | **Parity**                             |
| Cell resize — row height                      | same                                | same                      | **Parity**                             |
| Scroll shadows                                | `TableScrollShadowPlugin`           | `TableScrollShadowPlugin` | **Parity**                             |
| Tab → next cell                               | `hasTabHandler=true`                | default `true`            | **Parity**                             |
| Shift+Tab → prev cell                         | same                                | same                      | **Parity**                             |
| Arrow key navigation                          | `TablePlugin` internals             | same                      | **Parity**                             |
| Escape → exit selection                       | same                                | same                      | **Parity**                             |
| Backspace/Delete → clear cells                | same                                | same                      | **Parity**                             |
| Ctrl+A → select all                           | same                                | same                      | **Parity**                             |
| Copy/Cut/Paste cells                          | same                                | same                      | **Parity**                             |
| Click + drag range selection                  | same                                | same                      | **Parity** (fixed)                     |
| Shift+click range selection                   | same                                | same                      | **Parity**                             |
| Triple-click → select paragraph               | same                                | same                      | **Parity**                             |
| Insert table with header config               | `includeHeaders` payload            | 4-option header selector  | **Parity**                             |
| Full color picker (hex/wheel)                 | `ColorPicker` component             | Preset grid only          | **Different**                          |
| Insert label shows count                      | "Insert 3 rows above"               | "Insert 3 rows above"     | **Parity**                             |
| Block drag reordering                         | `DraggableBlockPlugin`              | Not present               | **Gap** (not table-specific)           |
| Table of contents                             | `TableOfContentsPlugin`             | Not present               | **Gap** (not table-specific)           |
| Native right-click context menu               | `ContextMenuPlugin`                 | Not present               | **Different** (we use floating button) |
| Nested tables                                 | `hasNestedTables` prop              | Not enabled               | **Intentional**                        |
| `TableFitNestedTablePlugin`                   | Present                             | Not present               | **N/A** (no nested tables)             |

### Summary

- **Parity**: 32+ features fully matched
- **Intentional differences**: Background color UX (preset vs wheel), context menu trigger (button vs right-click)
- **Not applicable**: Nested tables, block drag, TOC — not table-specific features

## Files

| File                          | Lines | Purpose                                                                                 |
| ----------------------------- | ----- | --------------------------------------------------------------------------------------- |
| `InsertTableDialog.tsx`       | 117   | Insert table dialog (rows × columns)                                                    |
| `TableActionMenuPlugin.tsx`   | 795   | Cell context menu (merge, insert, delete, headers, colors, alignment, striping, freeze) |
| `TableHoverActionsPlugin.tsx` | 796   | Top bar (drag handle, sort, add column) + left button (add row) + drop indicator        |
| `TableCellResizer.tsx`        | 543   | Custom cell resizer (row height, column width)                                          |
| `TableScrollShadowPlugin.tsx` | 135   | Scroll shadow CSS classes on overflow                                                   |

## Plugin Registration

`rich-text-editor.tsx` registers these table plugins:

```tsx
<TablePlugin hasHorizontalScroll hasCellMerge />
```

| Prop                     | Value             | Effect                                     |
| ------------------------ | ----------------- | ------------------------------------------ |
| `hasHorizontalScroll`    | `true`            | Wraps table in scrollable `<div>`          |
| `hasCellMerge`           | `true`            | Enables `colSpan`/`rowSpan` (merged cells) |
| `hasCellBackgroundColor` | `true` (default)  | Enables `setBackgroundColor()` on cells    |
| `hasTabHandler`          | `true` (default)  | Tab/Shift+Tab navigates between cells      |
| `hasNestedTables`        | `false` (default) | Rejects table-inside-table                 |

The `TablePlugin` from `@lexical/react` internally calls:

- `registerTablePlugin(editor)` — registers `INSERT_TABLE_COMMAND`, `SELECT_ALL_COMMAND`, clipboard handlers
- `registerTableSelectionObserver(editor, hasTabHandler)` — registers arrow keys (4 directions at `COMMAND_PRIORITY_HIGH`), Tab, Escape, Backspace/Delete, Copy/Cut/Paste, Format, Selection Change handlers
- `setScrollableTablesActive(editor, true)` — wraps tables in scroll divs

Additional floating plugins:

```tsx
<TableActionMenuPlugin />              // cell chevron menu
<TableCellResizerPlugin />             // drag handles
<TableHoverActionsPlugin />            // hover add/sort/drag
<TableScrollShadowPlugin />            // scroll shadows
```

## Lexical Internal Commands

Registered automatically by `TablePlugin` (not our code):

| Command                             | Priority                  | Handler                             |
| ----------------------------------- | ------------------------- | ----------------------------------- |
| `INSERT_TABLE_COMMAND`              | `COMMAND_PRIORITY_EDITOR` | `$insertTable()`                    |
| `SELECT_ALL_COMMAND`                | `COMMAND_PRIORITY_LOW`    | `$tableSelectAllCommand()`          |
| `CLICK_COMMAND`                     | `COMMAND_PRIORITY_EDITOR` | Triple-click selects cell paragraph |
| `KEY_ARROW_DOWN_COMMAND`            | `COMMAND_PRIORITY_HIGH`   | `$handleArrowKey('down')`           |
| `KEY_ARROW_UP_COMMAND`              | `COMMAND_PRIORITY_HIGH`   | `$handleArrowKey('up')`             |
| `KEY_ARROW_LEFT_COMMAND`            | `COMMAND_PRIORITY_HIGH`   | `$handleArrowKey('backward')`       |
| `KEY_ARROW_RIGHT_COMMAND`           | `COMMAND_PRIORITY_HIGH`   | `$handleArrowKey('forward')`        |
| `KEY_TAB_COMMAND`                   | `COMMAND_PRIORITY_HIGH`   | `$selectAdjacentCell()`             |
| `KEY_ESCAPE_COMMAND`                | `COMMAND_PRIORITY_HIGH`   | Exits `TableSelection`              |
| `KEY_BACKSPACE_COMMAND`             | `COMMAND_PRIORITY_HIGH`   | `$deleteCellHandler()`              |
| `KEY_DELETE_COMMAND`                | `COMMAND_PRIORITY_HIGH`   | `$deleteCellHandler()`              |
| `DELETE_WORD_COMMAND`               | `COMMAND_PRIORITY_HIGH`   | `deleteTextHandler()`               |
| `DELETE_LINE_COMMAND`               | `COMMAND_PRIORITY_HIGH`   | `deleteTextHandler()`               |
| `DELETE_CHARACTER_COMMAND`          | `COMMAND_PRIORITY_HIGH`   | `deleteTextHandler()`               |
| `CUT_COMMAND`                       | `COMMAND_PRIORITY_HIGH`   | Copy + delete cells                 |
| `FORMAT_TEXT_COMMAND`               | `COMMAND_PRIORITY_HIGH`   | `$formatCells()` (bold/italic)      |
| `FORMAT_ELEMENT_COMMAND`            | `COMMAND_PRIORITY_HIGH`   | Cell/table alignment                |
| `CONTROLLED_TEXT_INSERTION_COMMAND` | `COMMAND_PRIORITY_HIGH`   | Edge paragraph insertion            |
| `INSERT_PARAGRAPH_COMMAND`          | `COMMAND_PRIORITY_HIGH`   | Paragraph at table edges            |
| `FOCUS_COMMAND`                     | `COMMAND_PRIORITY_HIGH`   | Prevents focus when selected        |
| `SELECTION_CHANGE_COMMAND`          | `COMMAND_PRIORITY_HIGH`   | Syncs selection state               |

## InsertTableDialog

`resources/js/Components/editor/table/InsertTableDialog.tsx`

Dialog for creating a new table with header options.

| Field   | Min | Max | Default      |
| ------- | --- | --- | ------------ |
| Rows    | 1   | 500 | 5            |
| Columns | 1   | 50  | 5            |
| Headers | —   | —   | Row + Column |

### Header Options

| Value             | `includeHeaders` payload         | Result                                    |
| ----------------- | -------------------------------- | ----------------------------------------- |
| Row + Column      | `true`                           | First row = `<th>`, first column = `<th>` |
| First row only    | `{ rows: true, columns: false }` | Only first row = `<th>`                   |
| First column only | `{ rows: false, columns: true }` | Only first column = `<th>`                |
| No headers        | `false`                          | All cells = `<td>`                        |

- Saves/restores selection on open/close
- Dispatches `INSERT_TABLE_COMMAND` from `@lexical/table`
- Enter key submits the dialog

## TableActionMenuPlugin

`resources/js/Components/editor/table/TableActionMenuPlugin.tsx`

Cell context menu — a chevron button at the top-right corner of each table cell that opens a dropdown menu. Matches every action from the playground's `TableActionMenuPlugin`.

### Menu Items

| Section             | Items                 | Description                                                                          |
| ------------------- | --------------------- | ------------------------------------------------------------------------------------ |
| Selection info      | —                     | Shows `N rows × M columns selected` for table selections                             |
| Merge               | Merge cells           | Available when 2+ cells selected in `TableSelection`                                 |
| Unmerge             | Unmerge cells         | Available when cell has `colSpan > 1` or `rowSpan > 1`                               |
| Cell background     | 9 colors              | CSS variable presets (default, gray, red, orange, yellow, green, blue, purple, teal) |
| Vertical align      | Top / Middle / Bottom | Sets `verticalAlign` on cell(s)                                                      |
| Insert row above    | —                     | `$insertTableRowAtSelection(false)` × N (N = selected rows)                          |
| Insert row below    | —                     | `$insertTableRowAtSelection(true)` × N                                               |
| Insert column left  | —                     | `$insertTableColumnAtSelection(false)` × N (N = selected columns)                    |
| Insert column right | —                     | `$insertTableColumnAtSelection(true)` × N                                            |
| Row header          | Toggle                | `$setTableRowIsHeader()` — toggles `ROW` header state                                |
| Column header       | Toggle                | `$setTableColumnIsHeader()` — toggles `COLUMN` header state                          |
| Row striping        | Toggle                | `tableNode.setRowStriping()`                                                         |
| Freeze first row    | Toggle                | `tableNode.setFrozenRows(0 → 1)`                                                     |
| Freeze first column | Toggle                | `tableNode.setFrozenColumns(0 → 1)`                                                  |
| Delete row          | —                     | `$deleteTableRowAtSelection()`                                                       |
| Delete column       | —                     | `$deleteTableColumnAtSelection()`                                                    |
| Delete table        | Destructive           | `tableNode.remove()`                                                                 |

### Playground Difference: Background Color

The playground uses a full `ColorPicker` modal with hex input and color wheel. Our implementation uses a 9-color preset grid via CSS custom properties. This is an intentional UX choice — simpler, faster, and avoids color consistency issues.

### Multi-Row/Column Insert

When cells are selected, the menu counts rows and columns. Inserting adds N rows/columns to match the selection size.

### Header Toggle Fix

After toggling row or column header, `$clearHighlight()` is called on the `TableObserver` to remove stale highlight state.

### Menu Positioning

- `TableCellActionMenuContainer` uses `SELECTION_CHANGE_COMMAND` and `registerUpdateListener` to reposition
- Button positioned at `cellDOM.getBoundingClientRect().right - 24px`, `cellDOM.getBoundingClientRect().top`
- Hidden during active `TableSelection` (user is selecting cells)
- Guarded by `isMenuOpen` — does not reposition while dropdown is open (prevents focus-steal)

### Multi-Cell Operations

When the dropdown opens during a `$isTableSelection`, all selected cell keys are captured in `selectedCellKeys[]` state. This prevents the dropdown focus-steal from losing the selection. Background color and vertical align use `selectedCellKeys` to apply to all selected cells.

## TableHoverActionsPlugin

`resources/js/Components/editor/table/TableHoverActionsPlugin.tsx`

Floating toolbar that appears on hover — top bar with drag handle + sort + add column, left button for add row, and drop indicator during column drag. Matches all features from the playground's `TableHoverActionsV2Plugin`.

### Top Actions (on first row hover)

| Button      | Icon           | Condition                   | Action                                                                         |
| ----------- | -------------- | --------------------------- | ------------------------------------------------------------------------------ |
| Drag handle | `GripVertical` | `canReorder` (simple table) | Initiates column drag via `@atlaskit/pragmatic-drag-and-drop`                  |
| Sort        | `ArrowUpDown`  | `canReorder`                | Dropdown with "Sort ascending" / "Sort descending" — uses `$moveTableColumn()` |
| Add column  | `Plus`         | Always                      | `$insertTableColumnAtSelection()`                                              |

### Sort Dropdown

The sort button opens a `DropdownMenu` with two options:

| Option          | Icon          | Direction | Description                     |
| --------------- | ------------- | --------- | ------------------------------- |
| Sort ascending  | `ArrowDownAZ` | `"asc"`   | A→Z, 1→9 (natural numeric sort) |
| Sort descending | `ArrowUpZA`   | `"desc"`  | Z→A, 9→1                        |

Both use `localeCompare` with `{ numeric: true }` for natural number sorting. Header rows with `hasHeader()` are excluded from sorting.

### Left Action (on first column hover)

| Button  | Icon   | Action                         |
| ------- | ------ | ------------------------------ |
| Add row | `Plus` | `$insertTableRowAtSelection()` |

### Positioning

Both use `@floating-ui/react` with `useFloating` hook:

- Top bar: `placement: "top"`, `offset({ mainAxis: -9 })`, `shift({ padding: 8 })`
- Left button: `placement: "left"`, `offset({ mainAxis: -9 })`, `shift({ padding: 8 })`
- Strategy: `fixed`, `whileElementsMounted: autoUpdate`

### Column Drag & Drop

Powered by `@atlaskit/pragmatic-drag-and-drop`:

1. **Drag source**: `draggable({ element: dragHandleRef })` — registers the drag handle
2. **Drop targets**: Each cell in the header row gets `dropTargetForElements({})` — registers as a valid drop zone
3. **Drag data**: `{ columnIndex, tableKey, type: "table-column" }`
4. **Drop indicator**: `DropIndicator` from `../shared/drop-indicator` — brand-colored line at drop position
5. **Execute**: `$moveTableColumn(tableNode, startIndex, finishIndex)` on drop

### Sort Implementation

Sorts rows by the text content of the hovered column, with user-chosen direction:

1. User picks ascending (A→Z) or descending (Z→A) from the dropdown
2. Gets all rows (skips header row if `hasHeader()` is true)
3. Sorts with `localeCompare` using `{ numeric: true }` for natural number sorting
4. Uses `$getSiblingCaret` / `$getChildCaret` + `splice()` to reorder nodes in-place

### Visibility Logic

| Condition                         | Top Bar              | Left Button          |
| --------------------------------- | -------------------- | -------------------- |
| Mouse in first row                | Visible              | —                    |
| Mouse in first column (not row 0) | —                    | Visible              |
| Mouse outside table               | Hidden               | Hidden               |
| Mouse in floating element         | Visible (maintained) | Visible (maintained) |
| `isEditable = false`              | —                    | —                    |

### getCellSelector

The `theme.tableCell` theme value is a multi-class Tailwind string. `getCellSelector()` splits and escapes each class individually:

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

This produces `td.w-\[75px\].border...., th.w-\[75px\].border....` — each class individually escaped.

### getTableKey

Returns `tableElement.getAttribute("data-lexical-key")`. Currently returns `null` because Lexical tables don't set this attribute. This is fine — `isColumnDrag()` compares `null === null` which is always `true` within the same editor.

## TableCellResizer

`resources/js/Components/editor/table/TableCellResizer.tsx`

Custom cell resizer replacing the Lexical default. Matches the playground's `TableCellResizer`.

### Behavior

- Shows two invisible drag zones (16px wide): one at the right edge (`col-resize`), one at the bottom edge (`row-resize`)
- On hover: blue highlight line via `backgroundImage: linear-gradient`
- On drag: full-width/height blue line across the entire table
- Portals into the scroll container (not `document.body`)
- `MIN_ROW_HEIGHT = 33`, `MIN_COLUMN_WIDTH = 92`

### Resize Handling

| Direction | Updates                    | Method                                               |
| --------- | -------------------------- | ---------------------------------------------------- |
| Right     | `tableNode.setColWidths()` | `$computeTableMapSkipCellCheck` to find column index |
| Bottom    | `tableRow.setHeight()`     | Reads current height from DOM or row node            |

### Default Column Widths

`registerNodeTransform(TableNode)` initializes column widths to `MIN_COLUMN_WIDTH` (92px) for new tables with no existing widths.

## TableScrollShadowPlugin

`resources/js/Components/editor/table/TableScrollShadowPlugin.tsx`

Adds scroll shadow CSS classes to table overflow wrappers. Matches the playground's `TableScrollShadowPlugin`.

### Classes Applied

| Condition                  | Class                        |
| -------------------------- | ---------------------------- |
| Scrollable, scrolled right | `table-scroll-shadow-left`   |
| Scrollable, scrolled left  | `table-scroll-shadow-right`  |
| Scrollable, in middle      | `table-scroll-shadow-middle` |
| Not scrollable             | None                         |

### Observer Stack

- `MutationObserver` on editor root — watches for new tables
- `ResizeObserver` on each wrapper — watches for size changes
- `scroll` event listeners — updates shadow state on scroll

## Table Commands

### Our Custom Commands

| Command                | Source           | Description                       |
| ---------------------- | ---------------- | --------------------------------- |
| `INSERT_TABLE_COMMAND` | `@lexical/table` | Creates table with rows × columns |

### Lexical API Functions Used

| Function                             | Source           | Used In                                |
| ------------------------------------ | ---------------- | -------------------------------------- |
| `$insertTableRowAtSelection()`       | `@lexical/table` | ActionMenu, HoverActions               |
| `$insertTableColumnAtSelection()`    | `@lexical/table` | ActionMenu, HoverActions, InsertDialog |
| `$deleteTableRowAtSelection()`       | `@lexical/table` | ActionMenu                             |
| `$deleteTableColumnAtSelection()`    | `@lexical/table` | ActionMenu                             |
| `$mergeCells()`                      | `@lexical/table` | ActionMenu                             |
| `$unmergeCell()`                     | `@lexical/table` | ActionMenu                             |
| `$setTableRowIsHeader()`             | `@lexical/table` | ActionMenu                             |
| `$setTableColumnIsHeader()`          | `@lexical/table` | ActionMenu                             |
| `$moveTableColumn()`                 | `@lexical/table` | HoverActions (drag-reorder)            |
| `$computeTableMapSkipCellCheck()`    | `@lexical/table` | HoverActions (sort), CellResizer       |
| `$isSimpleTable()`                   | `@lexical/table` | HoverActions (reorder guard)           |
| `$getNodeByKey()`                    | `lexical`        | ActionMenu (multi-cell ops)            |
| `$setSelection(null)`                | `lexical`        | ActionMenu (clear after ops)           |
| `$clearHighlight()`                  | `TableObserver`  | ActionMenu (after header toggle)       |
| `getTableElement()`                  | `@lexical/table` | ActionMenu, CellResizer                |
| `getTableObserverFromTableElement()` | `@lexical/table` | ActionMenu                             |
| `getDOMCellFromTarget()`             | `@lexical/table` | CellResizer                            |
| `calculateZoomLevel()`               | `@lexical/utils` | CellResizer                            |

## Table Theme

### Tailwind Classes (`editor-theme.ts`)

| Key                              | Value                                                                                                |
| -------------------------------- | ---------------------------------------------------------------------------------------------------- |
| `table`                          | `border-collapse border-spacing-0 table-layout-fixed w-fit mt-[25px] mb-[30px]`                      |
| `tableScrollableWrapper`         | `overflow-x-auto mb-[5px] [&_table]:mt-0 [&_table]:mb-0`                                             |
| `tableRow`                       | `""` (empty)                                                                                         |
| `tableCell`                      | `w-[75px] border border-border px-2 py-1.5 text-start align-top relative outline-none overflow-auto` |
| `tableCellHeader`                | `w-[75px] bg-muted border border-border px-2 py-1.5 text-start font-semibold align-top`              |
| `tableCellSelected`              | `lexical-table-cell-selected`                                                                        |
| `tableRowSelected`               | `""` (empty)                                                                                         |
| `tableSelected`                  | `[&:focus-within]:outline-2 [&:focus-within]:outline-ring`                                           |
| `tableRowStriping`               | `[&_tr:nth-child(even)]:bg-muted/50`                                                                 |
| `tableFrozenRow`                 | `table-frozen-row`                                                                                   |
| `tableFrozenColumn`              | `table-frozen-col`                                                                                   |
| `tableCellResizer`               | `table-cell-resizer`                                                                                 |
| `tableCellActionButtonContainer` | `table-cell-action-btn-container`                                                                    |
| `tableCellActionButton`          | `table-cell-action-btn`                                                                              |
| `tableAddColumns`                | `table-add-columns`                                                                                  |
| `tableAddRows`                   | `table-add-rows`                                                                                     |
| `tableSelection`                 | `table-selection`                                                                                    |
| `tableAlignment.center`          | `mx-auto`                                                                                            |
| `tableAlignment.right`           | `ml-auto`                                                                                            |

### CSS Classes (`app.css`)

| Selector                                                             | Purpose                                              |
| -------------------------------------------------------------------- | ---------------------------------------------------- |
| `.lexical-table-cell-selected`                                       | `caret-color: transparent` during selection          |
| `[contenteditable]:focus-within .lexical-table-cell-selected::after` | Blue highlight overlay (`rgba(130, 200, 255, 0.35)`) |
| `.table-selection *::selection`                                      | Transparent native selection during table drag       |
| `.table-add-columns`                                                 | Absolute-positioned add-column button                |
| `.table-add-rows`                                                    | Absolute-positioned add-row button                   |
| `.table-scroll-shadow-*`                                             | Left/right/both scroll shadows                       |
| `.table-cell-resizer`                                                | Invisible drag zone + hover/active blue line         |
| `.dark table th`                                                     | Dark mode header background (`hsl(var(--muted))`)    |
| `.dark table td, .dark table th`                                     | Dark mode border color (`hsl(var(--border))`)        |
| `--cell-bg-*`                                                        | 9 CSS custom properties for cell background colors   |

### Table Selection CSS (Fixed)

The `.table-selection` class is applied to the `<table>` element by Lexical's `$disableHighlightStyle()` when a multi-cell selection is active. It must NOT have `pointer-events: none` — that breaks `elementsFromPoint()` during mouse drag. Only `::selection` transparency is needed:

```css
.table-selection *::selection {
  background-color: transparent;
}
```

## HTML Import/Export

### Import (`HtmlLoader`)

```typescript
dom.querySelectorAll("th").forEach((th) => {
  th.style.removeProperty("background-color");
});
const nodes = $generateNodesFromDOM(editor, dom);
```

Strips `background-color` from `<th>` elements to prevent dark header backgrounds from persisting on import. All other table attributes (colSpan, rowSpan, width, verticalAlign, backgroundColor on td) are preserved by Lexical's built-in `importDOM`.

### Export (`OnChangeEmitter`)

```typescript
const html = $generateHtmlFromNodes(editor, null);
```

Standard Lexical HTML serialization. TableNode exports: `<table>` with `<colgroup>` for widths, `<tr>` rows, `<th>`/`<td>` cells with inline styles for colSpan, rowSpan, width, verticalAlign, backgroundColor.

## Node Properties

### TableNode

| Property              | Type                | Description              |
| --------------------- | ------------------- | ------------------------ |
| `__rowStriping`       | `boolean`           | Alternating row colors   |
| `__frozenColumnCount` | `number`            | Sticky columns from left |
| `__frozenRowCount`    | `number`            | Sticky rows from top     |
| `__colWidths`         | `readonly number[]` | Per-column pixel widths  |

### TableCellNode

| Property            | Type                   | Description                            |
| ------------------- | ---------------------- | -------------------------------------- |
| `__colSpan`         | `number`               | Columns spanned                        |
| `__rowSpan`         | `number`               | Rows spanned                           |
| `__headerState`     | `TableCellHeaderState` | Bitmask: `ROW=1`, `COLUMN=2`, `BOTH=3` |
| `__width`           | `number`               | Column width in pixels                 |
| `__backgroundColor` | `null \| string`       | Cell background color                  |
| `__verticalAlign`   | `string`               | `'top'`, `'middle'`, `'bottom'`        |

### TableRowNode

| Property   | Type     | Description          |
| ---------- | -------- | -------------------- |
| `__height` | `number` | Row height in pixels |

## Keyboard Shortcuts

All handled by Lexical internals via `registerTableSelectionObserver`:

| Key                 | Context                     | Action                                   |
| ------------------- | --------------------------- | ---------------------------------------- |
| Tab                 | Cursor in cell (collapsed)  | Move to next cell                        |
| Shift+Tab           | Cursor in cell (collapsed)  | Move to previous cell                    |
| Arrow keys          | Inside table                | Navigate between cells, enter/exit cells |
| Escape              | `TableSelection` active     | Exit to `RangeSelection` in focus cell   |
| Backspace/Delete    | `TableSelection` active     | Clear all selected cells' text           |
| Ctrl/Cmd+A          | Table is only child of root | Select all cells                         |
| Ctrl/Cmd+C          | `TableSelection` active     | Copy cell content                        |
| Ctrl/Cmd+X          | `TableSelection` active     | Cut cell content                         |
| Paste               | `TableSelection` active     | Paste into selected cells                |
| Typing at cell edge | First/last cell boundary    | Insert paragraph before/after table      |
| Triple-click        | Any cell                    | Select paragraph inside cell             |

## Selection Behaviors

| Behavior                | Implementation                                                            |
| ----------------------- | ------------------------------------------------------------------------- |
| Single click            | Sets `RangeSelection` inside the clicked cell                             |
| Click + drag            | Starts `TableSelection` — anchor on `pointerdown`, focus on `pointermove` |
| Shift + click           | Creates `TableSelection` from previous anchor to clicked cell             |
| Triple click            | Selects the paragraph inside the cell                                     |
| Auto-scroll during drag | Detects scroll container edges (40px zone), applies auto-scroll           |
| Touch tap               | Does NOT initiate table selection (only sets anchor if not touch)         |
| Touch drag              | Creates `TableSelection` when `isSelecting` is true                       |

## Internal Utilities Used

| Function                                  | Source           | Purpose                                   |
| ----------------------------------------- | ---------------- | ----------------------------------------- |
| `$getNodeTriplet()`                       | `@lexical/table` | Returns `[cell, row, table]` for any node |
| `$getTableCellNodeFromLexicalNode()`      | `@lexical/table` | Walks up to find nearest cell             |
| `$getTableRowIndexFromTableCellNode()`    | `@lexical/table` | Row index of a cell                       |
| `$getTableColumnIndexFromTableCellNode()` | `@lexical/table` | Column index of a cell                    |
| `$getTableNodeFromLexicalNodeOrThrow()`   | `@lexical/table` | Find parent table or throw                |
| `getTableElement()`                       | `@lexical/table` | Get DOM element for table node            |
| `getTableObserverFromTableElement()`      | `@lexical/table` | Get `TableObserver` from DOM              |
| `getDOMCellFromTarget()`                  | `@lexical/table` | Walk DOM up to find cell                  |
| `$computeTableMapSkipCellCheck()`         | `@lexical/table` | 2D grid map (skips merge validation)      |
| `$isSimpleTable()`                        | `@lexical/table` | No merged cells                           |
| `calculateZoomLevel()`                    | `@lexical/utils` | Zoom-aware coordinates                    |
