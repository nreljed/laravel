# Plugins

Full inventory of Lexical plugins used in the editor, both built-in and custom.

## Built-in Plugins

From `@lexical/react`, `@lexical/list`, `@lexical/link`, `@lexical/code`, `@lexical/table`, `@lexical/history`, `@lexical/markdown`.

| Plugin                     | Package             | Purpose                                     |
| -------------------------- | ------------------- | ------------------------------------------- |
| `RichTextPlugin`           | `@lexical/react`    | ContentEditable + placeholder               |
| `HistoryPlugin`            | `@lexical/react`    | Undo/redo (200ms debounce)                  |
| `ListPlugin`               | `@lexical/list`     | Bullet, numbered, check lists               |
| `CheckListPlugin`          | `@lexical/list`     | Interactive checkbox items                  |
| `LinkPlugin`               | `@lexical/link`     | `LinkNode`, `AutoLinkNode` auto-detection   |
| `TablePlugin`              | `@lexical/table`    | Table nodes, cell selection, merge/unmerge  |
| `AutoFocusPlugin`          | `@lexical/react`    | Focus editor on mount (`"rootEnd"`)         |
| `CodeHighlightPlugin`      | `@lexical/code`     | PrismJS syntax highlighting registration    |
| `MarkdownShortcutPlugin`   | `@lexical/markdown` | Markdown shortcuts (`# `, `* `, `- `, etc.) |
| `FloatingLinkEditorPlugin` | `@lexical/react`    | Floating link edit popover                  |

## Custom Plugins

### ImagePlugin

`resources/js/Components/editor/image/ImagePlugin.tsx`

- Handles `INSERT_IMAGE_COMMAND`
- Registers paste/drop/dragover for image file upload
- Exports `uploadImageToServer()` and `deleteImageFromServer()`

### ColumnsPlugin

`resources/js/Components/editor/layout/ColumnsPlugin.tsx`

- Handles `INSERT_LAYOUT_COMMAND` and `UPDATE_LAYOUT_COMMAND`
- Arrow key navigation to escape layout containers
- Node transforms for structure enforcement
- Exports `$addColumnToLayout` and `$removeColumnFromLayout` for column manipulation

### LayoutControlsPlugin

`resources/js/Components/editor/layout/LayoutControlsPlugin.tsx`

- Floating grouped controls (drag handle + add + remove) at top-center of active column
- Uses `@atlaskit/pragmatic-drag-and-drop` for column reorder (draggable + dropTargetForElements)
- Drop indicator via `@atlaskit/drag-and-drop-indicator/box`
- Uses native `selectionchange` event (not Lexical's command — `isShadowRoot()` prevents tracking)
- DOM-based layout detection: walks ancestors for `data-lexical-layout-item`, matches to Lexical tree
- Portals into scroll container with `position: absolute`
- Positions update on scroll

### ContextMenuWrapper

`resources/js/Components/editor/components/context-menu-wrapper.tsx`

- Right-click context menu using `@lexical/react/LexicalNodeContextMenuPlugin`
- Standard items: Cut, Copy, Delete word, Delete line
- Layout-specific items (shown only inside column layouts):
  - **Add column** — appends new column
  - **Remove column** — removes current column (hidden when only 1 remains)
  - **Remove layout** — unwraps all columns and removes the container
- Uses `$showOn` callback to conditionally show items based on node type
- `$findLayoutContainerFromNode()` walks parent chain to detect layout context
- `$findLayoutContext()` reads editor state for the active layout item + container

### FloatingTextFormatPlugin

`resources/js/Components/editor/floating/FloatingTextFormatPlugin.tsx`

- Floating mini-toolbar for text formatting (bold, italic, underline, strikethrough)
- Appears on text selection (not on block selection)

### FloatingLinkEditor

`resources/js/Components/editor/floating/FloatingLinkEditor.tsx`

- Floating link editor (URL input, apply, remove)
- Appears when cursor is inside a `LinkNode` or text is selected

### TableActionMenuPlugin

`resources/js/Components/editor/table/TableActionMenuPlugin.tsx`

- Cell context menu (merge, insert, delete, headers, colors, alignment)
- Portals to `document.body`

### TableHoverActionsPlugin

`resources/js/Components/editor/table/TableHoverActionsPlugin.tsx`

- Top floating bar (drag handle, sort, add column)
- Left floating button (add row)
- Drop indicator during column drag
- Portals to `document.body`

### TableCellResizerPlugin

`resources/js/Components/editor/table/TableCellResizer.tsx`

- Custom cell resize handles (row height, column width)
- Portals into scroll container (not `document.body`)
- Initializes column widths to 92px for new tables

### TableScrollShadowPlugin

`resources/js/Components/editor/table/TableScrollShadowPlugin.tsx`

- Adds scroll shadow CSS classes to table overflow wrappers
- Uses MutationObserver + ResizeObserver + scroll listeners

### ImageToolbarPlugin

`resources/js/Components/editor/image/ImageToolbarPlugin.tsx`

- Floating toolbar above selected image
- Edit, resize controls

## Plugin Registration Order

In `rich-text-editor.tsx`, plugins are registered in this order:

```
1.  FloatingLinkEditorPlugin
2.  FloatingTextFormatPlugin
3.  TableActionMenuPlugin
4.  TableCellResizerPlugin (portals to scroll container)
5.  TableHoverActionsPlugin (portals to document.body)
6.  TableScrollShadowPlugin
7.  ImageToolbarPlugin
8.  HistoryPlugin
9.  ListPlugin
10. CheckListPlugin
11. LinkPlugin (openInNewTab={true})
12. TablePlugin (hasHorizontalScroll, hasCellMerge)
13. AutoFocusPlugin (defaultSelection="rootEnd")
14. ImagePlugin
15. ColumnsPlugin
16. LayoutControlsPlugin
17. CodeHighlightPlugin
18. MarkdownShortcutPlugin
```

## Plugin Categories

| Category     | Plugins                                                                                                                |
| ------------ | ---------------------------------------------------------------------------------------------------------------------- |
| Core editing | `RichTextPlugin`, `HistoryPlugin`, `AutoFocusPlugin`                                                                   |
| Formatting   | `FloatingTextFormatPlugin`, `CodeHighlightPlugin`                                                                      |
| Lists        | `ListPlugin`, `CheckListPlugin`                                                                                        |
| Links        | `FloatingLinkEditor`, `LinkPlugin`                                                                                     |
| Tables       | `TablePlugin`, `TableActionMenuPlugin`, `TableHoverActionsPlugin`, `TableCellResizerPlugin`, `TableScrollShadowPlugin` |
| Images       | `ImagePlugin`, `ImageToolbarPlugin`                                                                                    |
| Layout       | `ColumnsPlugin`, `LayoutControlsPlugin`                                                                                |
| Context menu | `ContextMenuWrapper` (via `LexicalNodeContextMenuPlugin`)                                                              |
| Markdown     | `MarkdownShortcutPlugin`                                                                                               |
