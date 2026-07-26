# Toolbar

`resources/js/Components/editor/toolbar/editor-toolbar.tsx`

The sticky toolbar at the top of the editor. Hidden when `RichTextEditor` is rendered with `compact={true}`.

## Layout

```
[Undo] [Redo] | [TOC] [Block ▾] | [B] [I] [U] [Code] [⋯ ▾] | [Color] [Highlight] | [Align ▾] | [Find] [Insert ▾]
```

- **Block ▾** — Block format dropdown (headings, lists, quote, code)
- **⋯ ▾** — Text format dropdown (strikethrough, subscript, case transforms, etc.)
- **Align ▾** — Alignment + indent/outdent
- **Insert ▾** — Insert link, image, table, columns

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
| `isSubscript`     | `boolean`           | Subscript format active                                                                               |
| `isSuperscript`   | `boolean`           | Superscript format active                                                                             |
| `isLowercase`     | `boolean`           | Lowercase text transform active                                                                       |
| `isUppercase`     | `boolean`           | Uppercase text transform active                                                                       |
| `isCapitalize`    | `boolean`           | Capitalize text transform active                                                                      |
| `isHighlight`     | `boolean`           | Highlight format active                                                                               |
| `blockType`       | `string`            | `"paragraph"` / `"h1"` / `"h2"` / `"h3"` / `"bullet"` / `"number"` / `"code"` / `"quote"` / `"check"` |
| `elementFormat`   | `ElementFormatType` | `"left"` / `"center"` / `"right"` / `"justify"`                                                       |
| `isLink`          | `boolean`           | Cursor is inside a `LinkNode`                                                                         |
| `canUndo`         | `boolean`           | Undo stack non-empty                                                                                  |
| `canRedo`         | `boolean`           | Redo stack non-empty                                                                                  |
| `isCodeBlock`     | `boolean`           | Current block is `CodeNode`                                                                           |
| `textColor`       | `string`            | Current text color (inline style)                                                                     |
| `highlightColor`  | `string`            | Current highlight color (inline style)                                                                |

## Block Format Dropdown

`BlockFormatDropdown` — dropdown menu with `<Toggle>` trigger + `<ChevronDown>`, matching `ElementFormatDropdown` pattern. Uses `$setBlocksType()` from `@lexical/selection` for block node replacement.

| Value       | Node Created               | Icon          | Shortcut     |
| ----------- | -------------------------- | ------------- | ------------ |
| `paragraph` | `$createParagraphNode()`   | `Type`        | Ctrl+Alt+0   |
| `h1`        | `$createHeadingNode("h1")` | `Heading1`    | Ctrl+Alt+1   |
| `h2`        | `$createHeadingNode("h2")` | `Heading2`    | Ctrl+Alt+2   |
| `h3`        | `$createHeadingNode("h3")` | `Heading3`    | Ctrl+Alt+3   |
| `bullet`    | `INSERT_UNORDERED_LIST`    | `List`        | Ctrl+Shift+8 |
| `number`    | `INSERT_ORDERED_LIST`      | `ListOrdered` | Ctrl+Shift+7 |
| `check`     | `INSERT_CHECK_LIST`        | `ListChecks`  | Ctrl+Shift+9 |
| `quote`     | `$createQuoteNode()`       | `Quote`       | Ctrl+Shift+Q |
| `code`      | `$createCodeNode()`        | `CodeSquare`  | Ctrl+Alt+C   |

Trigger shows current block icon + label + chevron (e.g. `H1 ▾`). Lists toggle off if already active.

## Inline Formatting

Primary formats are top-level toolbar buttons. Secondary formats are in the **Text Format Dropdown** (`⋯`).

### Primary (toolbar buttons)

| Button      | Format        | Command               |
| ----------- | ------------- | --------------------- |
| Bold        | `"bold"`      | `FORMAT_TEXT_COMMAND` |
| Italic      | `"italic"`    | `FORMAT_TEXT_COMMAND` |
| Underline   | `"underline"` | `FORMAT_TEXT_COMMAND` |
| Inline Code | `"code"`      | `FORMAT_TEXT_COMMAND` |

### Secondary (Text Format Dropdown)

| Item             | Format            | Command               | Shortcut     |
| ---------------- | ----------------- | --------------------- | ------------ |
| Strikethrough    | `"strikethrough"` | `FORMAT_TEXT_COMMAND` | Ctrl+Shift+X |
| Subscript        | `"subscript"`     | `FORMAT_TEXT_COMMAND` | Ctrl+,       |
| Superscript      | `"superscript"`   | `FORMAT_TEXT_COMMAND` | Ctrl+.       |
| Highlight        | `"highlight"`     | `FORMAT_TEXT_COMMAND` | —            |
| Lowercase        | `"lowercase"`     | `FORMAT_TEXT_COMMAND` | Ctrl+Shift+1 |
| Uppercase        | `"uppercase"`     | `FORMAT_TEXT_COMMAND` | Ctrl+Shift+2 |
| Capitalize       | `"capitalize"`    | `FORMAT_TEXT_COMMAND` | Ctrl+Shift+3 |
| Clear Formatting | —                 | clears format+style   | Ctrl+\       |

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
export { TextFormatDropdown } from "./TextFormatDropdown";
```
