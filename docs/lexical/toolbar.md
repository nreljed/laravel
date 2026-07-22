# Toolbar

`resources/js/Components/editor/toolbar/editor-toolbar.tsx`

The sticky toolbar at the top of the editor. Hidden when `RichTextEditor` is rendered with `compact={true}`.

## Layout

```
[Undo] [Redo] | [Block Select] | [B] [I] [U] [S] [Code] | [Text Color] [Highlight] | [Align L/C/R/J] | [List] [Ordered] [Check] | [Link] [Image] | [Table] | [Columns]
```

Fixed position: `sticky top-16 z-20`, background `bg-background`, bottom border.

## ToolbarState

Tracked via `useToolbarState(editor)` hook — updates on every `SELECTION_CHANGE_COMMAND` and `editor.registerUpdateListener`.

| Property          | Type                | Description                                                                                           |
| ----------------- | ------------------- | ----------------------------------------------------------------------------------------------------- |
| `isBold`          | `boolean`           | Bold format active                                                                                    |
| `isItalic`        | `boolean`           | Italic format active                                                                                  |
| `isUnderline`     | `boolean`           | Underline format active                                                                               |
| `isStrikethrough` | `boolean`           | Strikethrough format active                                                                           |
| `isCode`          | `boolean`           | Inline code format active                                                                             |
| `blockType`       | `string`            | `"paragraph"` / `"h1"` / `"h2"` / `"h3"` / `"bullet"` / `"number"` / `"code"` / `"quote"` / `"check"` |
| `elementFormat`   | `ElementFormatType` | `"left"` / `"center"` / `"right"` / `"justify"`                                                       |
| `isLink`          | `boolean`           | Cursor is inside a `LinkNode`                                                                         |
| `canUndo`         | `boolean`           | Undo stack non-empty                                                                                  |
| `canRedo`         | `boolean`           | Redo stack non-empty                                                                                  |
| `isCodeBlock`     | `boolean`           | Current block is `CodeNode`                                                                           |
| `textColor`       | `string`            | Current text color (inline style)                                                                     |
| `highlightColor`  | `string`            | Current highlight color (inline style)                                                                |

## Block Format Select

`BlockFormatSelect` — dropdown with `<Select>` from shadcn/ui.

| Value       | Node Created               | Icon         |
| ----------- | -------------------------- | ------------ |
| `paragraph` | `$createParagraphNode()`   | —            |
| `h1`        | `$createHeadingNode("h1")` | `Heading1`   |
| `h2`        | `$createHeadingNode("h2")` | `Heading2`   |
| `h3`        | `$createHeadingNode("h3")` | `Heading3`   |
| `quote`     | `$createQuoteNode()`       | `Quote`      |
| `code`      | `$createCodeNode()`        | `CodeSquare` |

All block formats replace the current block node via `parent.replace(node, true)`.

## Inline Formatting

Dispatches `FORMAT_TEXT_COMMAND` with the format type.

| Button        | Format            | Command               |
| ------------- | ----------------- | --------------------- |
| Bold          | `"bold"`          | `FORMAT_TEXT_COMMAND` |
| Italic        | `"italic"`        | `FORMAT_TEXT_COMMAND` |
| Underline     | `"underline"`     | `FORMAT_TEXT_COMMAND` |
| Strikethrough | `"strikethrough"` | `FORMAT_TEXT_COMMAND` |
| Inline Code   | `"code"`          | `FORMAT_TEXT_COMMAND` |

## Text & Highlight Colors

Both use `$patchStyleText()` from `@lexical/selection`.

| Picker          | Style Property     | Colors                       |
| --------------- | ------------------ | ---------------------------- |
| Text Color      | `color`            | 16 colors (Tailwind palette) |
| Highlight Color | `background-color` | 16 colors (Tailwind palette) |

Both implemented via `ColorPicker` component — popover with grid of color swatches.

## Text Alignment

Dispatches `FORMAT_ELEMENT_COMMAND` with alignment value.

| Button       | Value       |
| ------------ | ----------- |
| Align Left   | `"left"`    |
| Align Center | `"center"`  |
| Align Right  | `"right"`   |
| Justify      | `"justify"` |

## Lists

| Button        | Command                         | Toggle Behavior                   |
| ------------- | ------------------------------- | --------------------------------- |
| Bullet List   | `INSERT_UNORDERED_LIST_COMMAND` | Toggles off if already `"bullet"` |
| Numbered List | `INSERT_ORDERED_LIST_COMMAND`   | Toggles off if already `"number"` |
| Check List    | `INSERT_CHECK_LIST_COMMAND`     | Always inserts (no toggle)        |

Toggle lists dispatch `REMOVE_LIST_COMMAND` when the block is already the active list type.

## Links

Dispatches `TOGGLE_LINK_COMMAND`:

- Active link → `null` (removes link)
- No link → `"https://"` (creates link with default URL)

## Insert Dialogs

| Button  | Dialog               | State              |
| ------- | -------------------- | ------------------ |
| Image   | `ImageInsertDialog`  | `imageDialogOpen`  |
| Table   | `InsertTableDialog`  | `tableDialogOpen`  |
| Columns | `InsertLayoutDialog` | `layoutDialogOpen` |

All three dialogs are rendered inside `EditorToolbar` and controlled by local `useState`.

## ToolbarButton

Reusable component wrapping `Toggle` from shadcn/ui with `Tooltip`.

| Prop       | Type                  | Description       |
| ---------- | --------------------- | ----------------- |
| `label`    | `string`              | Tooltip text      |
| `icon`     | `React.ComponentType` | Lucide icon       |
| `isActive` | `boolean`             | Highlighted state |
| `onClick`  | `() => void`          | Click handler     |
| `disabled` | `boolean`             | Disabled state    |

## Visibility Rules

| Condition          | Toolbar Visible                                          |
| ------------------ | -------------------------------------------------------- |
| `compact={true}`   | Hidden (not rendered)                                    |
| Table cell focused | Button at cell top-right (from `TableActionMenuPlugin`)  |
| Image selected     | Floating toolbar above image (from `ImageToolbarPlugin`) |

## Exports

```typescript
export { EditorToolbar } from "./editor-toolbar";
export { ColorPicker, TEXT_COLORS, HIGHLIGHT_COLORS } from "./ColorPicker";
```
