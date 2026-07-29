---
name: lexical-editor-development
description: "Rich text editor built with Lexical v0.48 + React 19. Activate when creating custom Lexical nodes, modifying plugins, editor theme, toolbar, slash commands, image handling, table features, layout columns, YouTube embeds, code highlighting, find-and-replace, or any editor integration work."
license: MIT
metadata:
  author: costbreaker
---

# Lexical Editor Development

## When to Apply

Activate this skill when:

- Creating or modifying Lexical nodes (ElementNode, DecoratorNode, TextNode)
- Working with editor plugins (any file in `resources/js/Components/editor/`)
- Modifying the toolbar, slash commands, floating toolbar, or context menus
- Adding or changing image, table, code block, layout, or YouTube features
- Working with editor theme, serialization, HTML import/export
- Debugging editor behavior, selection, keyboard shortcuts, or drag-and-drop

## Documentation

Use `search-docs` with `packages: ["lexical"]` or `packages: ["@lexical/react"]` for version-specific Lexical patterns. Use `context7` for latest docs.

## Project Setup

- **Lexical version**: `^0.48.0` (all packages)
- **Composer**: `LexicalExtensionComposer` with `defineExtension` (modern pattern)
- **Additional deps**: `@floating-ui/react` ^0.27, `@atlaskit/pragmatic-drag-and-drop` ^2.0, `prismjs` ^1.30
- **Editor location**: `resources/js/Components/editor/`
- **Blog integration**: `app/Blog/Resources/js/Components/ContentBlockEditor.tsx`

## Architecture

```
resources/js/Components/editor/
├── rich-text-editor.tsx          # Main editor — LexicalExtensionComposer + defineExtension
├── editor-theme.ts               # Tailwind CSS theme for all node types
├── index.ts                      # Public API exports
│
├── toolbar/
│   ├── editor-toolbar.tsx        # Formatting toolbar (bold, italic, etc.)
│   ├── TextFormatDropdown.tsx    # Strikethrough, sub/super, highlight, case, clear
│   ├── InsertMenu.tsx           # Insert dropdown (link, image, table, embed, layout)
│   └── ElementFormatDropdown.tsx # Alignment, indent
│
├── floating/
│   ├── FloatingTextFormatPlugin.tsx  # Selection-based format toolbar
│   └── FloatingLinkEditor.tsx       # Inline link editor (create/edit/remove)
│
├── components/
│   ├── on-change-emitter.tsx     # HTML export (debounced 100ms)
│   ├── html-loader.tsx          # HTML import from Laravel (legacy plugin)
│   ├── floating-plugins.tsx     # Floating plugin composition
│   ├── context-menu-wrapper.tsx # Right-click context menu
│   └── code-highlight-plugin.tsx # Prism.js syntax highlighting (legacy plugin)
│
├── shared/
│   └── block-options.tsx        # Shared block option definitions
│
├── image/
│   ├── ImageNode.tsx            # Custom image DecoratorNode (updateFromJSON pattern)
│   ├── ImageComponent.tsx       # Image rendering with selection/resize
│   ├── ImageResizer.tsx         # 8-direction image resizer
│   ├── ImagePlugin.tsx          # Image commands, paste/drop, upload/delete
│   ├── ImageToolbarPlugin.tsx   # Image floating toolbar
│   ├── EditImageDialog.tsx      # Edit image dialog
│   └── ImageInsertDialog.tsx    # Image insert dialog
│
├── table/
│   ├── TableActionMenuPlugin.tsx    # Cell action menu (insert/delete/merge)
│   ├── TableCellResizer.tsx         # Cell resize handles
│   ├── TableHoverActionsPlugin.tsx  # Hover buttons, column reorder, sort
│   └── TableScrollShadowPlugin.tsx  # Scroll shadow indicators
│
├── layout/
│   ├── ColumnsPlugin.tsx            # Layout commands, transforms, arrow nav
│   ├── LayoutContainerNode.ts       # Custom LayoutContainer ElementNode
│   ├── LayoutItemNode.ts            # Custom LayoutItem ElementNode
│   ├── LayoutControlsPlugin.tsx     # Column controls (add/remove/drag)
│   └── InsertLayoutDialog.tsx       # Layout template picker
│
├── youtube/
│   ├── YouTubeNode.tsx              # Custom YouTube DecoratorNode (updateFromJSON pattern)
│   └── YouTubePlugin.tsx            # YouTube embed registration
│
└── plugin-helpers/
    ├── registerCommandWithPlugin.tsx
    ├── getNodesFromSelectedElements.tsx
    ├── useEditorRef.ts
    └── useReportValue.ts
```

## Key Patterns

### Extension System (Modern Pattern)

The editor uses `LexicalExtensionComposer` with `defineExtension` and first-class extensions:

```tsx
import { LexicalExtensionComposer } from "@lexical/react/LexicalExtensionComposer";
import { defineExtension, configExtension } from "lexical";
import { RichTextExtension } from "@lexical/rich-text";
import { HistoryExtension } from "@lexical/history";
import { ListExtension, CheckListExtension } from "@lexical/list";
import { LinkExtension, AutoLinkExtension } from "@lexical/link";
import { TableExtension } from "@lexical/table";
import { CodePrismExtension } from "@lexical/code-prism";
import { OverflowExtension } from "@lexical/overflow";
import { HorizontalRuleExtension, AutoFocusExtension } from "@lexical/extension";
import { DragonExtension } from "@lexical/dragon";

const editorExtension = defineExtension({
  name: "[root]",
  namespace: "RichTextEditor",
  nodes: () => [CustomNodeA, CustomNodeB],
  dependencies: [
    RichTextExtension,
    HistoryExtension,
    ListExtension,
    CheckListExtension,
    configExtension(LinkExtension, { validateUrl, attributes: undefined }),
    configExtension(AutoLinkExtension, { matchers, excludeParents }),
    configExtension(TableExtension, { hasHorizontalScroll: true, hasCellMerge: true }),
    CodePrismExtension,
    OverflowExtension,
    HorizontalRuleExtension,
    configExtension(AutoFocusExtension, { defaultSelection: "rootStart", disabled: false }),
    DragonExtension,
  ],
  theme: editorTheme,
});

// Extension must be stable — define at module scope or use useMemo
<LexicalExtensionComposer extension={editorExtension} contentEditable={null}>
  {/* children */}
</LexicalExtensionComposer>;
```

**First-class extensions replace these legacy plugins:**

| Extension                 | Replaces                                                       |
| ------------------------- | -------------------------------------------------------------- |
| `RichTextExtension`       | `RichTextPlugin` + `HeadingNode` + `QuoteNode`                 |
| `HistoryExtension`        | `HistoryPlugin`                                                |
| `ListExtension`           | `ListPlugin` + `ListNode` + `ListItemNode`                     |
| `CheckListExtension`      | `CheckListPlugin`                                              |
| `LinkExtension`           | `LinkPlugin` + `LinkNode`                                      |
| `AutoLinkExtension`       | `AutoLinkPlugin` + `AutoLinkNode`                              |
| `TableExtension`          | `TablePlugin` + `TableNode` + `TableRowNode` + `TableCellNode` |
| `CodePrismExtension`      | `CodeHighlightPlugin` + `CodeNode` + `CodeHighlightNode`       |
| `OverflowExtension`       | `OverflowNode`                                                 |
| `HorizontalRuleExtension` | `HorizontalRulePlugin` + `HorizontalRuleNode`                  |
| `AutoFocusExtension`      | `AutoFocusPlugin`                                              |
| `DragonExtension`         | Dragon NaturallySpeaking support                               |

**Legacy plugins kept (no extension equivalent):**

- `MarkdownShortcutPlugin` — markdown typing shortcuts
- `CharacterLimitPlugin` — character count limit
- `LexicalAutoEmbedPlugin` — YouTube embed detection
- `HtmlLoader` — dynamic HTML import from props
- `OnChangeEmitter` — HTML export on change
- All custom plugins (toolbar, slash commands, find-and-replace, etc.)

### Plugin Registration (Legacy React Pattern)

Plugins that don't have first-class extensions still use `useLexicalComposerContext`:

```tsx
"use client";

import { useLexicalComposerContext } from "@lexical/react/LexicalComposerContext";
import { useEffect } from "react";

export function MyPlugin() {
  const [editor] = useLexicalComposerContext();

  useEffect(() => {
    return editor.registerUpdateListener(({ editorState }) => {
      // handle update
    });
  }, [editor]);

  return null;
}
```

### Custom Node Structure (updateFromJSON Pattern)

All custom nodes MUST use the modern `updateFromJSON` pattern:

```tsx
import type {
  EditorConfig,
  LexicalNode,
  LexicalUpdateJSON,
  SerializedLexicalNode,
  Spread,
} from "lexical";
import { $applyNodeReplacement, DecoratorNode } from "lexical";

export type SerializedMyNode = Spread<{ src: string; altText: string }, SerializedLexicalNode>;

export class MyNode extends DecoratorNode<JSX.Element> {
  static getType(): string {
    return "my-node";
  }

  static clone(node: MyNode): MyNode {
    return new MyNode(node.__src, node.__altText, node.__key);
  }

  static importJSON(json: SerializedMyNode): MyNode {
    return new MyNode().updateFromJSON(json);
  }

  static importDOM(): DOMConversionMap | null {
    return {
      img: (node: HTMLImageElement) => ({
        conversion: $convertMyElement,
        priority: 1,
      }),
    };
  }

  declare __src: string;
  declare __altText: string;

  constructor(src: string = "", altText: string = "", key?: NodeKey) {
    super(key);
    this.__src = src;
    this.__altText = altText;
  }

  updateFromJSON(json: LexicalUpdateJSON<SerializedMyNode>): this {
    const self = super.updateFromJSON(json);
    self.__src = json.src;
    self.__altText = json.altText;
    return self;
  }

  exportDOM(): DOMExportOutput {
    const element = document.createElement("img");
    element.setAttribute("src", this.__src);
    element.setAttribute("alt", this.__altText);
    return { element };
  }

  exportJSON(): SerializedMyNode {
    return { src: this.__src, altText: this.__altText, type: "my-node", version: 1 };
  }

  createDOM(config: EditorConfig): HTMLElement {
    const div = document.createElement("div");
    return div;
  }

  updateDOM(): false {
    return false;
  }

  isInline(): boolean {
    return false;
  }

  decorate(): JSX.Element {
    return <MyComponent src={this.__src} altText={this.__altText} />;
  }
}

export function $createMyNode(props: { src: string; altText: string }): MyNode {
  return $applyNodeReplacement(new MyNode(props.src, props.altText));
}

export function $isMyNode(node: LexicalNode | null | undefined): node is MyNode {
  return node instanceof MyNode;
}
```

**Key rules for nodes:**

- Constructor parameters MUST have default values (empty string for strings)
- `importJSON` creates empty instance then calls `updateFromJSON`
- `updateFromJSON` calls `super.updateFromJSON()` then sets custom properties
- `declare` fields come before constructor, constructor before `updateFromJSON`
- All factory functions use `$applyNodeReplacement()`

### HTML Serialization (Our Pattern)

The editor uses HTML strings (not JSON) for Laravel integration:

- **Export**: `OnChangeEmitter` calls `$generateHtmlFromNodes(editor, null)` with 100ms debounce
- **Import**: `HtmlLoader` calls `$generateNodesFromDOM` then `$insertNodes`
- **Storage**: HTML string stored in Laravel DB (string column)

### Editor Theme

Theme is in `editor-theme.ts`. Use Tailwind CSS classes. Structure follows Lexical's theme keys:

- `text` — bold, italic, underline, strikethrough, code, subscript, superscript, highlight, case
- `heading` — h1-h6
- `list` — ul, ol, nested, checklist
- `table*` — all table theme keys
- `image`, `layoutContainer`, `layoutItem`, `horizontalLine`, `code`, `codeHighlight`

## Important Conventions

- All editor files use `"use client"` directive
- Import `useLexicalComposerContext` from `@lexical/react/LexicalComposerContext`
- All node factory functions use `$applyNodeReplacement()`
- Type guards use `$is*Node()` pattern
- Node mutations use `getWritable()` (not direct `__` property access)
- Node reads use `getLatest()` (not direct `__` property access)
- Image upload uses backend routes: `POST blog/posts/upload-image` and `DELETE blog/posts/image`
- Floating UI via `@floating-ui/react` — use `useFloating` hook for positioned elements
- Drag and drop via `@atlaskit/pragmatic-drag-and-drop`
- Code highlighting via `prismjs` (via `CodePrismExtension`)
- Slash commands via `@lexical/react/LexicalTypeaheadMenuPlugin`
- Extension definition MUST be stable — define at module scope or use `useMemo`

## Blog Integration

The blog feature uses this editor via `ContentBlockEditor.tsx` in `app/Blog/Resources/js/Components/`. The editor is instantiated with `<RichTextEditor html={content} onChange={handleChange} />` where `content` is a raw HTML string from Laravel.

## Commands

Editor commands are defined via `createCommand<type>()` and dispatched via `editor.dispatchCommand(COMMAND, payload)`. Key commands:

- `INSERT_IMAGE_COMMAND` — payload: `{ src, altText, width?, height? }`
- `INSERT_TABLE_COMMAND` — payload: `{ rows, columns }`
- `INSERT_YOUTUBE_COMMAND` — payload: YouTube URL string
- `FORMAT_TEXT_COMMAND` — payload: `"bold" | "italic" | "underline" | "strikethrough" | "code" | "subscript" | "superscript" | "highlight"`
- `FORMAT_ELEMENT_COMMAND` — payload: `"left" | "center" | "right" | "justify" | "indent" | "outdent"`
- `PASTE_COMMAND` — payload: Clipboard event

## Common Pitfalls

- Never use `config().set()` at runtime in editor context
- Always return cleanup functions from `useEffect` in plugins
- Never access `__` properties directly — use `getWritable()` or `getLatest()`
- Custom nodes MUST call `$applyNodeReplacement()` in factory functions
- `OnChangeEmitter` captures final state on unmount via `editor.getEditorState()`
- `HtmlLoader` is a React component (not a utility function) — it renders `{children}` when loaded
- Context menu cut is atomic: clipboard write happens before node deletion
- Image resizer captures `positioningRef.current` before cleanup to avoid stale refs on Firefox
- `CursorGuardPlugin` only renders inside table selection contexts
- Extension definition MUST be stable — never define inside render function without `useMemo`
