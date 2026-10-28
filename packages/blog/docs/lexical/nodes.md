# Custom Nodes

Four custom Lexical nodes extend the default node set for images, YouTube embeds, and multi-column layouts.

## ImageNode

`src/Resources/js/Components/editor/image/ImageNode.tsx`

Custom `DecoratorNode` for inline images with upload, resize, alt text, and HTML serialization.

### Properties

| Property     | Type                  | Default     | Description          |
| ------------ | --------------------- | ----------- | -------------------- |
| `__src`      | `string`              | —           | Image URL            |
| `__altText`  | `string`              | —           | Alt text             |
| `__width`    | `number \| "inherit"` | `"inherit"` | Width in pixels      |
| `__height`   | `number \| "inherit"` | `"inherit"` | Height in pixels     |
| `__maxWidth` | `number`              | `100`       | Max width percentage |

### Methods

| Method                | Signature                                                         | Description       |
| --------------------- | ----------------------------------------------------------------- | ----------------- |
| `getSrc()`            | `(): string`                                                      | Returns image URL |
| `setSrc()`            | `(src: string): void`                                             | Sets image URL    |
| `getAltText()`        | `(): string`                                                      | Returns alt text  |
| `setAltText()`        | `(altText: string): void`                                         | Sets alt text     |
| `setWidthAndHeight()` | `(width: number \| "inherit", height: number \| "inherit"): void` | Sets dimensions   |
| `getMaxWidth()`       | `(): number`                                                      | Returns max width |
| `setMaxWidth()`       | `(maxWidth: number): void`                                        | Sets max width    |

### Behavior

- `isInline()` returns `false` (block-level node)
- `isIsolated()` returns `true`
- `createDOM()` creates `<span style="position:relative; display:inline-block">`
- `decorate()` returns `<ImageComponent>` with selection, resize, and drag support
- `exportDOM()` creates `<span><img ...></span>` with Tailwind classes
- `importDOM()` matches `<img>` elements, extracts `src`, `alt`, `width`, `height`; rejects `file:///` URLs

### Commands

| Command                | Payload        | Description          |
| ---------------------- | -------------- | -------------------- |
| `INSERT_IMAGE_COMMAND` | `ImagePayload` | Insert an image node |

### Factory

```typescript
$createImageNode({ src, altText, width?, height?, maxWidth? }): ImageNode
$isImageNode(node): node is ImageNode
```

## YouTubeNode

`src/Resources/js/Components/editor/youtube/YouTubeNode.tsx`

Custom `DecoratorNode` for YouTube video embeds.

### Properties

| Property    | Type     | Description                   |
| ----------- | -------- | ----------------------------- |
| `__videoId` | `string` | 11-character YouTube video ID |

### Behavior

- `isInline()` returns `false`
- `isIsolated()` returns `true`
- `createDOM()` creates `<div style="display:contents">`
- `decorate()` renders responsive `<iframe>` with `aspect-video` class
- No `importDOM` — only created via URL paste or config

### URL Parsing

`YouTubeEmbedConfig.parseUrl` matches three URL patterns:

| Pattern                | Regex                                                                        |
| ---------------------- | ---------------------------------------------------------------------------- |
| `youtube.com/watch?v=` | `/(?:youtube\.com\/(?:watch\?v=\|embed\/)\|youtu\.be\/)([a-zA-Z0-9_-]{11})/` |
| `youtube.com/embed/`   | Same regex                                                                   |
| `youtu.be/`            | Same regex                                                                   |

### Factory

```typescript
$createYouTubeNode(videoId: string): YouTubeNode
$isYouTubeNode(node): node is YouTubeNode
```

## LayoutContainerNode

`src/Resources/js/Components/editor/layout/LayoutContainerNode.ts`

Custom `ElementNode` representing a CSS grid container for multi-column layouts.

### Properties

| Property            | Type     | Description                                           |
| ------------------- | -------- | ----------------------------------------------------- |
| `__templateColumns` | `string` | CSS `grid-template-columns` value (e.g., `"1fr 1fr"`) |

### Behavior

- `isShadowRoot()` returns `true` (children not part of content-editable tree)
- `canBeEmpty()` returns `false`
- `createDOM()` creates `<div>` with inline `gridTemplateColumns` style
- `importDOM()` matches `div[data-lexical-layout-container]`, reads `gridTemplateColumns` from style
- `exportDOM()` sets `data-lexical-layout-container="true"` dataset attribute
- `updateDOM()` only updates grid template when changed

### Factory

```typescript
$createLayoutContainerNode(templateColumns?: string): LayoutContainerNode
$isLayoutContainerNode(node): node is LayoutContainerNode
```

## LayoutItemNode

`src/Resources/js/Components/editor/layout/LayoutItemNode.ts`

Custom `ElementNode` representing a single column within a layout container.

### Behavior

- `isShadowRoot()` returns `true`
- `createDOM()` creates `<div data-lexical-layout-item="true">`
- `importDOM()` matches `div[data-lexical-layout-item]`
- `collapseAtStart()` removes the entire layout container if all items are empty (prevents empty layout from lingering)

### Helpers

```typescript
$createLayoutItemNode(): LayoutItemNode
$isLayoutItemNode(node): node is LayoutItemNode
$isEmptyLayoutItemNode(node): boolean  // true if child is a single empty paragraph
```

## Built-in Nodes (Registered)

| Node                | Package              | Base Class    |
| ------------------- | -------------------- | ------------- |
| `HeadingNode`       | `@lexical/rich-text` | `ElementNode` |
| `QuoteNode`         | `@lexical/rich-text` | `ElementNode` |
| `ListNode`          | `@lexical/list`      | `ElementNode` |
| `ListItemNode`      | `@lexical/list`      | `ElementNode` |
| `LinkNode`          | `@lexical/link`      | `ElementNode` |
| `AutoLinkNode`      | `@lexical/link`      | `ElementNode` |
| `CodeNode`          | `@lexical/code`      | `ElementNode` |
| `CodeHighlightNode` | `@lexical/code`      | `TextNode`    |
| `TableNode`         | `@lexical/table`     | `ElementNode` |
| `TableRowNode`      | `@lexical/table`     | `ElementNode` |
| `TableCellNode`     | `@lexical/table`     | `ElementNode` |
