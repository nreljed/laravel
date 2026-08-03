# Lexical Editor

Server-rendered rich text editor powered by Lexical 0.48 with Tailwind CSS, shadcn/ui, and custom nodes for images, YouTube embeds, tables, and multi-column layouts.

## Documents

| Document                        | Scope                                                                                           |
| ------------------------------- | ----------------------------------------------------------------------------------------------- |
| [Architecture](architecture.md) | Plugin composition, data flow, HTML serialization, component tree                               |
| [Custom Nodes](nodes.md)        | `ImageNode`, `YouTubeNode`, `LayoutContainerNode`, `LayoutItemNode`                             |
| [Toolbar](toolbar.md)           | `EditorToolbar`, block formatting, inline formatting, color picker, insert actions              |
| [Table](table.md)               | Full table feature parity vs playground, all commands/selection/keyboard/API details            |
| [Image](image.md)               | `ImageNode`, upload pipeline, resize handles, insert/edit dialogs, floating toolbar             |
| [Layout](layout.md)             | `LayoutContainerNode`, `LayoutItemNode`, `ColumnsPlugin`, `LayoutControlsPlugin`, insert dialog |
| [Plugins](plugins.md)           | Full plugin inventory — Lexical built-ins and custom plugins                                    |
| [Theme](theme.md)               | Tailwind CSS class mappings, dark mode, table cell styling                                      |

## Package Versions

| Package                                                    | Version    | Purpose                                                      |
| ---------------------------------------------------------- | ---------- | ------------------------------------------------------------ |
| `lexical`                                                  | `^0.48.0`  | Core runtime                                                 |
| `@lexical/react`                                           | `^0.48.0`  | React bindings, plugins                                      |
| `@lexical/table`                                           | `^0.48.0`  | Table nodes, commands, utilities                             |
| `@lexical/list`                                            | `^0.48.0`  | List nodes, insert/remove                                    |
| `@lexical/rich-text`                                       | `^0.48.0`  | `HeadingNode`, `QuoteNode`                                   |
| `@lexical/code`                                            | `^0.48.0`  | `CodeNode`, `CodeHighlightNode`                              |
| `@lexical/link`                                            | `^0.48.0`  | `LinkNode`, `AutoLinkNode`                                   |
| `@lexical/html`                                            | `^0.48.0`  | HTML serialization/deserialization                           |
| `@lexical/history`                                         | `^0.48.0`  | Undo/redo                                                    |
| `@lexical/markdown`                                        | `^0.48.0`  | Markdown shortcuts                                           |
| `@lexical/selection`                                       | `^0.48.0`  | Inline style patching                                        |
| `@lexical/utils`                                           | `^0.48.0`  | `$findMatchingParent`, `mergeRegister`, `calculateZoomLevel` |
| `@lexical/extension`                                       | `^0.48.0`  | `INSERT_HORIZONTAL_RULE_COMMAND`, `HorizontalRuleNode`       |
| `@floating-ui/react`                                       | `^0.27.20` | Floating element positioning                                 |
| `@atlaskit/pragmatic-drag-and-drop`                        | `^2.0.1`   | Column drag-reorder                                          |
| `resources/js/Components/editor/shared/drop-indicator.tsx` | —          | Local drop indicator visual                                  |

## File Tree

```
resources/js/Components/editor/
  index.ts                              # Public barrel export
  rich-text-editor.tsx                  # Main component
  editor-theme.ts                       # Tailwind theme
  plugins/
    DraggableBlockPlugin.tsx            # Drag handle for reordering blocks
    SlashCommandsPlugin.tsx             # Slash command picker
    FindAndReplacePlugin.tsx            # Find & Replace dialog
    WordCountPlugin.tsx                 # Real-time word/character count
    TableOfContentsPlugin.tsx           # Heading traversal
    KeyboardShortcutsPlugin.tsx         # Global keyboard shortcuts
    CursorGuardPlugin.tsx               # Cursor position guard
    CodeBlockActionsPlugin.tsx          # Code block copy/language/format
  toolbar/
    index.ts
    editor-toolbar.tsx                  # Sticky toolbar
    InsertMenu.tsx                      # Consolidated insert dropdown
    ColorPicker.tsx                     # Color picker popover
  floating/
    index.ts
    FloatingTextFormatPlugin.tsx         # Floating mini-toolbar
    FloatingLinkEditor.tsx              # Floating link editor
  image/
    index.ts
    ImageNode.tsx                       # Custom DecoratorNode
    ImageComponent.tsx                  # React render component
    ImageResizer.tsx                    # 8-direction resize
    ImagePlugin.tsx                     # Paste/drop/command + server utils
    ImageInsertDialog.tsx               # URL/Upload dialog
    EditImageDialog.tsx                 # Edit dialog
    ImageToolbarPlugin.tsx              # Floating image actions
  youtube/
    index.ts
    YouTubeNode.tsx                     # Custom DecoratorNode
  table/
    index.ts
    InsertTableDialog.tsx               # Insert table dialog (4-option header)
    TableCellResizer.tsx                # Custom cell resizer
    TableActionMenuPlugin.tsx           # Cell context menu (merge, color, align, etc.)
    TableHoverActionsPlugin.tsx         # Hover insert/sort/drag-reorder
    TableScrollShadowPlugin.tsx         # Scroll shadow CSS
    table-hover-utils.ts               # Pure utility functions for hover actions
  layout/
    index.ts
    LayoutContainerNode.ts              # Grid container node
    LayoutItemNode.ts                   # Column item node
    ColumnsPlugin.tsx                   # Layout command + transforms + add/remove/move column
    InsertLayoutDialog.tsx              # Layout chooser
    LayoutControlsPlugin.tsx            # Floating grouped controls (drag + add + remove)
  components/
    context-menu-wrapper.tsx            # Right-click context menu (Cut/Copy/Delete + layout ops)
    floating-plugins.tsx                # Overlay/portal plugins
    html-loader.tsx                     # HTML → Lexical deserialization
    on-change-emitter.tsx               # Lexical → HTML serialization
  shared/
    block-options.tsx                   # Block option model (slash + context menu)
    block-picker-menu.tsx               # Slash command menu UI
    DecoratorErrorBoundary.tsx          # Error boundary for DecoratorNode outputs
  hooks/
    use-container-position.ts           # Shared positioning hook for floating elements
```

**Total**: 40 files, ~7,000 lines across 8 subdirectories.

## Consumer Entry Points

| Import                                          | Used By                                                   |
| ----------------------------------------------- | --------------------------------------------------------- |
| `@/Components/editor` → `RichTextEditor`        | Blog post form (`Blog/Form`)                              |
| `@/Components/editor` → `uploadImageToServer`   | Blog post form, `FeaturedImageSelector`                   |
| `@/Components/editor` → `deleteImageFromServer` | `FeaturedImageSelector`                                   |
| `RichTextEditor` with `compact` prop            | — (previously `ContentBlockEditor`, removed as dead code) |

## Gap Analysis vs Lexical Playground

### What We Have (Complete)

| Category             | Feature                                                     | Status                     |
| -------------------- | ----------------------------------------------------------- | -------------------------- |
| **Text Formatting**  | Bold, Italic, Underline, Strikethrough, Inline Code         | Done                       |
| **Text Formatting**  | Subscript, Superscript                                      | Missing                    |
| **Text Styling**     | Text Color (15 presets + custom hex)                        | Done                       |
| **Text Styling**     | Background/Highlight Color (15 presets + custom hex)        | Done                       |
| **Text Styling**     | Font Family dropdown                                        | Missing                    |
| **Text Styling**     | Font Size dropdown                                          | Missing                    |
| **Text Styling**     | Lowercase/Uppercase/Capitalize transform                    | Missing                    |
| **Text Styling**     | Clear Formatting                                            | Missing                    |
| **Block Formatting** | Paragraph, H1, H2, H3, Blockquote, Code Block               | Done                       |
| **Block Formatting** | H4, H5, H6                                                  | Missing                    |
| **Alignment**        | Left, Center, Right, Justify                                | Done                       |
| **Alignment**        | Indent/Outdent                                              | Missing                    |
| **Lists**            | Bullet, Numbered, Check List                                | Done                       |
| **Links**            | Insert/Edit/Delete link                                     | Done                       |
| **Links**            | Auto-link detection (URL + email)                           | Done                       |
| **Tables**           | Insert table (rows × cols, 4-option headers)                | Done                       |
| **Tables**           | Insert/Delete row & column (multi-select aware)             | Done                       |
| **Tables**           | Merge cells                                                 | Done (fixed selection bug) |
| **Tables**           | Unmerge cells                                               | Done                       |
| **Tables**           | Cell background color (9 presets)                           | Done                       |
| **Tables**           | Vertical align (top/middle/bottom)                          | Done                       |
| **Tables**           | Row/column header toggle                                    | Done                       |
| **Tables**           | Row striping toggle                                         | Done                       |
| **Tables**           | Freeze first row/column                                     | Done                       |
| **Tables**           | Cell resize (drag handles)                                  | Done                       |
| **Tables**           | Hover insert row/column buttons                             | Done                       |
| **Tables**           | Column drag-and-drop reorder                                | Done                       |
| **Tables**           | Sort ascending/descending                                   | Done                       |
| **Tables**           | Scroll shadow indicators                                    | Done                       |
| **Tables**           | Delete entire table                                         | Done                       |
| **Images**           | Insert via URL or file upload                               | Done                       |
| **Images**           | Server-side upload + delete                                 | Done                       |
| **Images**           | 8-direction resize handles                                  | Done                       |
| **Images**           | Floating toolbar (edit/delete/options)                      | Done                       |
| **Images**           | Edit dialog (URL, replace, alt text)                        | Done                       |
| **Images**           | Drag & paste from clipboard                                 | Done                       |
| **Layout**           | Multi-column layout (5 presets: 2/3/4 col)                  | Done                       |
| **Layout**           | Arrow key navigation between columns                        | Done                       |
| **Layout**           | Add/remove column buttons (floating controls)               | Done                       |
| **Layout**           | Column drag-and-drop reorder (grip handle + drop indicator) | Done                       |
| **Layout**           | Right-click context menu (add/remove/remove layout)         | Done                       |
| **Media**            | YouTube embed (URL parsing + iframe)                        | Done                       |
| **Code**             | Syntax highlighting (Prism)                                 | Done                       |
| **Code**             | Language detection from markdown                            | Done                       |
| **Context Menu**     | Right-click: Cut, Copy, Delete word, Delete line            | Done                       |
| **Formatting**       | Horizontal rule                                             | Done                       |
| **Formatting**       | Markdown shortcuts (`#`, `**`, `- `, etc.)                  | Done                       |
| **Navigation**       | Auto-focus                                                  | Done                       |
| **Limits**           | Character limit (UTF-16, configurable)                      | Done                       |
| **Floating**         | Selection mini-toolbar (bold/italic/underline/etc.)         | Done                       |
| **Floating**         | Link editor (view/edit/delete)                              | Done                       |
| **History**          | Undo/Redo                                                   | Done                       |

### What's Missing — Priority-Ordered

#### High Priority (Blog Editor Essentials)

| Feature                   | Playground Plugin        | Effort | Notes                                             |
| ------------------------- | ------------------------ | ------ | ------------------------------------------------- |
| **Find & Replace**        | `FindReplaceExtension`   | Medium | Built-in Lexical utility. Users expect Ctrl+F/H   |
| **Subscript/Superscript** | `FORMAT_TEXT_COMMAND`    | Low    | Just add to toolbar + keyboard shortcuts          |
| **Code Block Actions**    | `CodeActionMenuPlugin`   | Medium | Copy button, language selector, Prettier format   |
| **Clear Formatting**      | `FORMAT_TEXT_COMMAND`    | Low    | Button in toolbar + Ctrl+\ shortcut               |
| **Table of Contents**     | `TableOfContentsPlugin`  | Low    | Heading traversal, sidebar or dropdown            |
| **Font Family**           | Font selector            | Low    | Dropdown with system fonts + custom               |
| **Font Size**             | Size selector            | Low    | Dropdown: 10px–20px                               |
| **H4/H5/H6**              | `HeadingNode`            | Low    | Already registered, just add to BlockFormatSelect |
| **Indent/Outdent**        | `FORMAT_ELEMENT_COMMAND` | Low    | Tab/Shift+Tab support                             |

#### Medium Priority (Nice-to-Have)

| Feature                  | Playground Plugin           | Effort | Notes                             |
| ------------------------ | --------------------------- | ------ | --------------------------------- |
| **Slash Commands**       | `ComponentPickerPlugin`     | High   | `/` trigger, block-type picker    |
| **Draggable Blocks**     | `DraggableBlockPlugin`      | High   | Drag handle for reordering blocks |
| **Collapsible Sections** | `CollapsibleExtension`      | Medium | Accordion/summary+details         |
| **Text Case Transform**  | Toolbar action              | Low    | lowercase/uppercase/capitalize    |
| **Emoji Picker**         | `EmojiPickerPlugin`         | Medium | `:emoji:` auto-replace + picker   |
| **Page Break**           | `PageBreakExtension`        | Low    | Visual divider node               |
| **Nested Tables**        | `TableFitNestedTablePlugin` | Medium | Tables inside table cells         |

#### Low Priority / Not Applicable to Blog

| Feature                  | Playground Plugin       | Why Not                           |
| ------------------------ | ----------------------- | --------------------------------- |
| Collaboration (Yjs)      | `VersionsPlugin`        | No collab in SaaS blog editor     |
| Excalidraw diagrams      | `ExcalidrawNode`        | Not a diagramming tool            |
| Figma embeds             | `FigmaNode`             | Not needed for blog content       |
| Twitter/X embeds         | `TweetNode`             | Use YouTube-style iframe or link  |
| Polls                    | `PollNode`              | Not a blog feature                |
| Sticky notes             | `StickyNode`            | Internal playground tool          |
| Mentions (@user)         | `MentionsExtension`     | Not a social platform             |
| Speech-to-text           | `SpeechToTextPlugin`    | Niche accessibility feature       |
| Inline equations (KaTeX) | `EquationsExtension`    | Math blog could use, but niche    |
| TreeView debug           | `TreeViewPlugin`        | Dev tool, not production          |
| Test Recorder            | `TestRecorderPlugin`    | Dev tool only                     |
| Import/Export JSON       | `ActionsPlugin`         | Playground-specific               |
| Read-only lock           | `ActionsPlugin`         | Use Lexical `editable` prop       |
| Shadow DOM rendering     | Settings toggle         | Not needed                        |
| Typing perf measurement  | `TypingPerfPlugin`      | Dev tool only                     |
| Autocomplete             | `AutocompleteExtension` | Disabled by default in playground |

### Summary Counts

| Metric             | Playground | Ours | Coverage |
| ------------------ | ---------- | ---- | -------- |
| Custom nodes       | 17         | 4    | 24%      |
| Plugins/extensions | 48         | 25   | 52%      |
| Table features     | 16         | 14   | 88%      |
| Text formatting    | 8          | 5    | 63%      |
| Toolbar actions    | 20+        | 14   | 70%      |
| Keyboard shortcuts | 30+        | 12   | 40%      |
| Embed types        | 5          | 1    | 20%      |

### Key Insight

The playground is a **showcase app** designed to demonstrate every Lexical capability. Many of its 48 plugins are:

- **Dev tools** (TreeView, TestRecorder, TypingPerf, PasteLog) — not production features
- **Experimental** (collaboration v2, pages, shadow DOM) — not stable
- **Niche embeds** (Excalidraw, Figma, Tweet, Poll, Sticky) — not blog content
- **Playground-specific** (ActionsPlugin import/export, Docs sidebar, Settings panel)

For a SaaS blog editor, our **88% table coverage** and **70% toolbar coverage** are strong. The high-priority gaps (Find & Replace, subscript/superscript, code block actions, clear formatting) are straightforward additions.
