# Layout

`resources/js/Components/editor/layout/`

Multi-column layout system using CSS Grid with `LayoutContainerNode` and `LayoutItemNode`.

## Files

| File                       | Lines | Purpose                                                                           |
| -------------------------- | ----- | --------------------------------------------------------------------------------- |
| `LayoutContainerNode.ts`   | 127   | Grid container node (`ElementNode`)                                               |
| `LayoutItemNode.ts`        | —     | Column item node (`ElementNode`)                                                  |
| `ColumnsPlugin.tsx`        | 242   | Command handler, transforms, arrow key nav, add/remove/move column                |
| `InsertLayoutDialog.tsx`   | 121   | Layout picker dialog                                                              |
| `LayoutControlsPlugin.tsx` | ~400  | Floating grouped controls (drag handle + add + remove) with drag-and-drop reorder |
| `index.ts`                 | 24    | Public exports for all layout modules                                             |

## LayoutContainerNode

`resources/js/Components/editor/layout/LayoutContainerNode.ts`

### Properties

| Property            | Type     | Description                       |
| ------------------- | -------- | --------------------------------- |
| `__templateColumns` | `string` | CSS `grid-template-columns` value |

### Behavior

- `isShadowRoot()` → `true` (children not part of content-editable tree)
- `canBeEmpty()` → `false`
- `createDOM()` → `<div>` with inline `gridTemplateColumns` style
- `importDOM()` matches `div[data-lexical-layout-container]`
- `exportDOM()` sets `data-lexical-layout-container="true"` dataset
- `updateDOM()` only updates grid template when changed

### Factory

```typescript
$createLayoutContainerNode(templateColumns?: string): LayoutContainerNode
$isLayoutContainerNode(node): node is LayoutContainerNode
```

## LayoutItemNode

`resources/js/Components/editor/layout/LayoutItemNode.ts`

### Behavior

- `isShadowRoot()` → `true`
- `createDOM()` → `<div data-lexical-layout-item="true">`
- `importDOM()` matches `div[data-lexical-layout-item]`
- `collapseAtStart()` removes the entire layout container if all items are empty

### Factory

```typescript
$createLayoutItemNode(): LayoutItemNode
$isLayoutItemNode(node): node is LayoutItemNode
$isEmptyLayoutItemNode(node): boolean
```

## ColumnsPlugin

`resources/js/Components/editor/layout/ColumnsPlugin.tsx`

### Commands

| Command                 | Payload               | Description                               |
| ----------------------- | --------------------- | ----------------------------------------- |
| `INSERT_LAYOUT_COMMAND` | `string` (template)   | Insert layout container with items        |
| `UPDATE_LAYOUT_COMMAND` | `UpdateLayoutPayload` | Update grid template and add/remove items |

### Functions

| Function                                           | Description                                                             |
| -------------------------------------------------- | ----------------------------------------------------------------------- |
| `$addColumnToLayout(container)`                    | Appends a new `1fr` column to the layout                                |
| `$removeColumnFromLayout(container, item)`         | Removes the column; if last column, removes the entire container        |
| `$moveLayoutColumn(container, fromIndex, toIndex)` | Moves a column from one position to another, updating the grid template |
| `$getLayoutContainerNodeForNode(node)`             | Walks up parent chain to find enclosing `LayoutContainerNode`           |

### Arrow Key Navigation

| Key        | Handler                                 | Description                    |
| ---------- | --------------------------------------- | ------------------------------ |
| ArrowDown  | `$onEscapeDown($isLayoutContainerNode)` | Escape out of layout downward  |
| ArrowUp    | `$onEscapeUp($isLayoutContainerNode)`   | Escape out of layout upward    |
| ArrowLeft  | `$onEscapeUp($isLayoutContainerNode)`   | Escape out of layout leftward  |
| ArrowRight | `$onEscapeDown($isLayoutContainerNode)` | Escape out of layout rightward |

### Transforms

- **`LayoutItemNode`**: `$removeIsolatedLayoutItem()` — if item is outside a container, inline its children and remove. `$fillLayoutItemIfEmpty()` — ensures empty items have a paragraph child.
- **`LayoutContainerNode`**: If any child is not a `LayoutItemNode`, inline all children and remove the container.

## InsertLayoutDialog

`resources/js/Components/editor/layout/InsertLayoutDialog.tsx`

### Layout Options

| Label                       | Template          | Columns |
| --------------------------- | ----------------- | ------- |
| 2 columns (equal width)     | `1fr 1fr`         | 2       |
| 2 columns (25% - 75%)       | `1fr 3fr`         | 2       |
| 3 columns (equal width)     | `1fr 1fr 1fr`     | 3       |
| 3 columns (25% - 50% - 25%) | `1fr 2fr 1fr`     | 3       |
| 4 columns (equal width)     | `1fr 1fr 1fr 1fr` | 4       |

Each option has a visual preview using `LayoutPreview` — flex container with proportional-width bars.

### Dialog

- Selectable cards with `LayoutPreview` thumbnails
- Dispatches `INSERT_LAYOUT_COMMAND` on insert
- Cancel closes without action

## HTML Serialization

### Export

```
<div data-lexical-layout-container="true" style="grid-template-columns: 1fr 1fr">
  <div data-lexical-layout-item="true">
    <p>Column 1 content</p>
  </div>
  <div data-lexical-layout-item="true">
    <p>Column 2 content</p>
  </div>
</div>
```

### Import

`importDOM()` on `LayoutContainerNode` matches `div[data-lexical-layout-container]` and reads `gridTemplateColumns` from the inline style. `LayoutItemNode` matches `div[data-lexical-layout-item]`.

## Exports

```typescript
export {
  LayoutContainerNode,
  $createLayoutContainerNode,
  $isLayoutContainerNode,
} from "./LayoutContainerNode";
export {
  LayoutItemNode,
  $createLayoutItemNode,
  $isLayoutItemNode,
  $isEmptyLayoutItemNode,
} from "./LayoutItemNode";
export {
  default as ColumnsPlugin,
  INSERT_LAYOUT_COMMAND,
  UPDATE_LAYOUT_COMMAND,
  $getLayoutContainerNodeForNode,
  $addColumnToLayout,
  $removeColumnFromLayout,
  $moveLayoutColumn,
} from "./ColumnsPlugin";
export { InsertLayoutDialog } from "./InsertLayoutDialog";
export { default as LayoutControlsPlugin } from "./LayoutControlsPlugin";
```

## LayoutControlsPlugin

`resources/js/Components/editor/layout/LayoutControlsPlugin.tsx`

Floating grouped controls that appear when the cursor is inside a layout item. All three buttons (drag handle, add, remove) are grouped in a single horizontal row at the top-center of the active column.

### Visual Layout

```
  ┌─────────┬─────────┐
  │[⋮][+][×]│  col 2  │  ← clicking col 1
  └─────────┴─────────┘

  ┌─────────┬─────────┐
  │  col 1  │[⋮][+][×]│  ← clicking col 2
  └─────────┴─────────┘
```

| Button        | Icon           | Action                                           |
| ------------- | -------------- | ------------------------------------------------ |
| Drag handle   | `GripVertical` | Drag to reorder column                           |
| Add column    | `Plus`         | Append new column after current                  |
| Remove column | `X`            | Remove current column (or entire layout if last) |

### Behavior

- Listens to native `document.addEventListener("selectionchange")` (not Lexical's `SELECTION_CHANGE_COMMAND` — `isShadowRoot()` on layout nodes prevents Lexical from tracking selections inside them)
- DOM-based detection: walks up from `window.getSelection().anchorNode` looking for `data-lexical-layout-item` attribute and a `grid` ancestor container
- Matches DOM elements to Lexical nodes via `editor.getEditorState().read()` tree walk + `editor.getElementByKey()` reference comparison
- Portals into the scroll container (`.overflow-auto` div) with `position: absolute`
- Uses a single `useContainerPosition` ref to position the button group at `top-center` of the hovered column
- Updates button positions on scroll via scroll event listener

### Drag-and-Drop Reorder

Uses `@atlaskit/pragmatic-drag-and-drop` for column reordering within a layout:

1. **Drag source**: The grip handle button registers as a `draggable()` element with payload `{ columnIndex, layoutKey, type: "layout-column" }`
2. **Drop targets**: Each `LayoutItemNode` DOM element registers as a `dropTargetForElements()` — detects left/right half of the column to determine insertion point
3. **Drop indicator**: `@atlaskit/drag-and-drop-indicator/box` renders a vertical line at the drop position during drag
4. **On drop**: Calls `$moveLayoutColumn(container, fromIndex, toIndex)` to reorder the Lexical tree and update the grid template

### Edge Cases

- When only 1 column remains and remove is clicked, `$removeColumnFromLayout` removes the entire layout container
- Drag handle hidden when layout has only 1 column (no reorder possible)
- Drop indicator clears on `onDragLeave` and `onDrop`
