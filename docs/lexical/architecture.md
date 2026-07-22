# Architecture

`resources/js/Components/editor/rich-text-editor.tsx`

The `RichTextEditor` component is a self-contained rich text editor that wraps all Lexical configuration, plugins, toolbar, and HTML serialization into a single consumer-facing component.

## Component Tree

```
<RichTextEditor value onChange placeholder compact className>
  LexicalComposer (namespace, theme, nodes, error handler)
    div.rounded.border.shadow
      EditorToolbar (hidden when compact=true)
      div.scroll-container (min-h-200, max-h-600, overflow-auto)
        RichTextPlugin > ContentEditable + placeholder
        FloatingPlugins (portals to document.body)
          FloatingLinkEditor
          FloatingTextFormatPlugin
          TableActionMenuPlugin
          TableCellResizerPlugin (portals into scroll container)
          TableHoverActionsPlugin (portals to document.body)
          TableScrollShadowPlugin
          ImageToolbarPlugin
          EditImageDialog
    HtmlLoader (hidden, loads value prop as HTML)
    OnChangeEmitter (hidden, emits HTML on every update)
    ContextMenuWrapper (right-click context menu with layout-specific items)
    HistoryPlugin
    ListPlugin
    CheckListPlugin
    LinkPlugin
    TablePlugin (hasHorizontalScroll, hasCellMerge)
    AutoFocusPlugin (defaultSelection="rootEnd")
    ImagePlugin
    ColumnsPlugin
    LayoutControlsPlugin
    CodeHighlightPlugin
    MarkdownShortcutPlugin
```

## Data Flow

```
1. Consumer passes `value` (HTML string) + `onChange` callback
2. HtmlLoader parses HTML → DOMParser → $generateNodesFromDOM → $getRoot().append()
3. User edits content in ContentEditable
4. OnChangeEmitter fires editor.registerUpdateListener
5. $generateHtmlFromNodes converts state → HTML string
6. onChange(html) called back to consumer
```

The `hasLoaded` ref in `HtmlLoader` ensures initial load runs exactly once. The `skipInitialChange` ref in `OnChangeEmitter` prevents firing `onChange` on the initial load before the user has typed.

## Registered Nodes

15 node types are registered in the `LexicalComposer` initial config:

| Node                  | Package              | Type            |
| --------------------- | -------------------- | --------------- |
| `HeadingNode`         | `@lexical/rich-text` | `ElementNode`   |
| `QuoteNode`           | `@lexical/rich-text` | `ElementNode`   |
| `ListNode`            | `@lexical/list`      | `ElementNode`   |
| `ListItemNode`        | `@lexical/list`      | `ElementNode`   |
| `LinkNode`            | `@lexical/link`      | `ElementNode`   |
| `AutoLinkNode`        | `@lexical/link`      | `ElementNode`   |
| `CodeNode`            | `@lexical/code`      | `ElementNode`   |
| `CodeHighlightNode`   | `@lexical/code`      | `TextNode`      |
| `TableNode`           | `@lexical/table`     | `ElementNode`   |
| `TableRowNode`        | `@lexical/table`     | `ElementNode`   |
| `TableCellNode`       | `@lexical/table`     | `ElementNode`   |
| `ImageNode`           | Custom               | `DecoratorNode` |
| `YouTubeNode`         | Custom               | `DecoratorNode` |
| `LayoutContainerNode` | Custom               | `ElementNode`   |
| `LayoutItemNode`      | Custom               | `ElementNode`   |

## HTML Serialization

### Export (Lexical → HTML)

`OnChangeEmitter` calls `$generateHtmlFromNodes(editor, null)` on every editor update (except initial load). Each node's `exportDOM()` method controls its HTML output:

| Node                  | HTML Output                                                                     |
| --------------------- | ------------------------------------------------------------------------------- |
| `HeadingNode`         | `<h1>` – `<h6>`                                                                 |
| `QuoteNode`           | `<blockquote>`                                                                  |
| `ListNode`            | `<ul>` / `<ol>`                                                                 |
| `ListItemNode`        | `<li>`                                                                          |
| `LinkNode`            | `<a href="...">`                                                                |
| `CodeNode`            | `<pre><code>`                                                                   |
| `ImageNode`           | `<span><img src="..." alt="..." class="..."></span>`                            |
| `YouTubeNode`         | `<div><iframe src="...">`                                                       |
| `LayoutContainerNode` | `<div data-lexical-layout-container="true" style="grid-template-columns: ...">` |
| `LayoutItemNode`      | `<div data-lexical-layout-item="true">`                                         |
| `TableCellNode`       | `<td>` / `<th>` with background-color via inline style                          |

### Import (HTML → Lexical)

`HtmlLoader` uses `DOMParser` to parse the HTML string, then `$generateNodesFromDOM` to convert DOM nodes to Lexical nodes. Each node's `importDOM()` method controls parsing:

| Node                  | HTML Selector                        |
| --------------------- | ------------------------------------ |
| `ImageNode`           | `<img>` (rejects `file:///` URLs)    |
| `LayoutContainerNode` | `div[data-lexical-layout-container]` |
| `LayoutItemNode`      | `div[data-lexical-layout-item]`      |

`HtmlLoader` also strips `background-color` from `<th>` elements during import to prevent stale background colors from persisting across renders.

## Props

| Prop          | Type                     | Default              | Description                         |
| ------------- | ------------------------ | -------------------- | ----------------------------------- |
| `value`       | `string`                 | `""`                 | HTML string loaded into editor      |
| `onChange`    | `(html: string) => void` | —                    | Called on every content change      |
| `placeholder` | `string`                 | `"Start writing..."` | Placeholder text when empty         |
| `compact`     | `boolean`                | `false`              | Hides toolbar for inline usage      |
| `className`   | `string`                 | `""`                 | Additional classes on outer wrapper |

## Internal Plugins

| Plugin                | Purpose                                         |
| --------------------- | ----------------------------------------------- |
| `HtmlLoader`          | Parses `value` HTML into Lexical nodes on mount |
| `OnChangeEmitter`     | Converts state to HTML and calls `onChange`     |
| `CodeHighlightPlugin` | Registers PrismJS syntax highlighting           |

## External Plugin Composition

`FloatingPlugins` is an internal component that composes all overlay/portal plugins. It is rendered inside the scroll container so that floating elements can position relative to the editor viewport.

`FloatingPlugins` manages state for the `EditImageDialog` (open/close, nodeKey) and passes it down to `ImageToolbarPlugin`.

## Public API

```typescript
import { RichTextEditor, uploadImageToServer, deleteImageFromServer } from "@/Components/editor";
```

| Export                  | Type      | Description                      |
| ----------------------- | --------- | -------------------------------- |
| `RichTextEditor`        | Component | Main editor component            |
| `RichTextEditorProps`   | Type      | Props interface                  |
| `uploadImageToServer`   | Function  | Uploads `File` → `{ url, path }` |
| `deleteImageFromServer` | Function  | Deletes image by path            |
