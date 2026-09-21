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

### DraggableBlockPlugin

`src/Resources/js/Components/editor/plugins/DraggableBlockPlugin.tsx`

- Drag handle for reordering blocks
- Uses `@atlaskit/pragmatic-drag-and-drop` for block reorder
- Drop indicator rendered via an inline `bg-primary` element
- Portals to `document.body`

### SlashCommandsPlugin

`src/Resources/js/Components/editor/plugins/SlashCommandsPlugin.tsx`

- `/` trigger, block-type picker
- Categories: Inline, Block, Layout, Media
- Keyboard navigation (up/down/enter/escape)
- Portals to `document.body`

### FindAndReplacePlugin

`src/Resources/js/Components/editor/plugins/FindAndReplacePlugin.tsx`

- Find & Replace dialog (Ctrl+F / Ctrl+H)
- Regex support, match count, navigation
- Portals to `document.body`

### WordCountPlugin

`src/Resources/js/Components/editor/plugins/WordCountPlugin.tsx`

- Real-time word/character count
- Displays in toolbar

### TableOfContentsPlugin

`src/Resources/js/Components/editor/plugins/TableOfContentsPlugin.tsx`

- Heading traversal, sidebar or dropdown
- Displays in toolbar

### KeyboardShortcutsPlugin

`src/Resources/js/Components/editor/plugins/KeyboardShortcutsPlugin.tsx`

- Global keyboard shortcuts (bold, italic, underline, strikethrough, code)
- Ctrl+B, Ctrl+I, Ctrl+U, Ctrl+Shift+S, Ctrl+`

### CursorGuardPlugin

`src/Resources/js/Components/editor/plugins/CursorGuardPlugin.tsx`

- Prevents cursor from jumping outside content area
- Monitors selection changes and corrects position

### CodeBlockActionsPlugin

`src/Resources/js/Components/editor/plugins/CodeBlockActionsPlugin.tsx`

- Floating actions for code blocks (copy, language selector, Prettier format)
- Portals into scroll container

## Plugin Registration Order

In `rich-text-editor.tsx`, plugins are registered in this order:

```
1.  HistoryPlugin
2.  ListPlugin
3.  CheckListPlugin
4.  LinkPlugin (openInNewTab={true})
5.  TablePlugin (hasHorizontalScroll, hasCellMerge)
6.  AutoFocusPlugin (defaultSelection="rootEnd")
7.  ImagePlugin
8.  ColumnsPlugin
9.  LayoutControlsPlugin
10. MarkdownShortcutPlugin
11. DraggableBlockPlugin
12. SlashCommandsPlugin
13. FindAndReplacePlugin
14. WordCountPlugin
15. TableOfContentsPlugin
16. KeyboardShortcutsPlugin
17. CursorGuardPlugin
18. CodeBlockActionsPlugin
```

## Plugin Categories

| Category     | Plugins                                                                                                                |
| ------------ | ---------------------------------------------------------------------------------------------------------------------- |
| Core editing | `RichTextPlugin`, `HistoryPlugin`, `AutoFocusPlugin`, `CursorGuardPlugin`                                              |
| Formatting   | `FloatingTextFormatPlugin`, `KeyboardShortcutsPlugin`, `CodeBlockActionsPlugin`                                        |
| Lists        | `ListPlugin`, `CheckListPlugin`                                                                                        |
| Links        | `FloatingLinkEditor`, `LinkPlugin`                                                                                     |
| Tables       | `TablePlugin`, `TableActionMenuPlugin`, `TableHoverActionsPlugin`, `TableCellResizerPlugin`, `TableScrollShadowPlugin` |
| Images       | `ImagePlugin`, `ImageToolbarPlugin`                                                                                    |
| Layout       | `ColumnsPlugin`, `LayoutControlsPlugin`                                                                                |
| Block ops    | `DraggableBlockPlugin`, `SlashCommandsPlugin`                                                                          |
| Context menu | `ContextMenuWrapper` (via `LexicalNodeContextMenuPlugin`)                                                              |
| Utility      | `FindAndReplacePlugin`, `WordCountPlugin`, `TableOfContentsPlugin`                                                     |
| Markdown     | `MarkdownShortcutPlugin`                                                                                               |
