# Image

`resources/js/Components/editor/image/`

Full image feature with upload, resize, alt text, floating toolbar, paste/drop support, and server-side storage.

## Files

| File                     | Lines | Purpose                                        |
| ------------------------ | ----- | ---------------------------------------------- |
| `ImageNode.tsx`          | 208   | Custom `DecoratorNode` with HTML import/export |
| `ImageComponent.tsx`     | —     | React render with selection, resize, toolbar   |
| `ImageResizer.tsx`       | —     | 8-direction resize handles                     |
| `ImagePlugin.tsx`        | 141   | Paste/drop/upload handlers, server utils       |
| `ImageInsertDialog.tsx`  | —     | URL/Upload insert dialog                       |
| `EditImageDialog.tsx`    | —     | Edit alt text, dimensions                      |
| `ImageToolbarPlugin.tsx` | —     | Floating toolbar above selected image          |

## ImageNode

`resources/js/Components/editor/image/ImageNode.tsx`

### Properties

| Property     | Type                  | Default     | Description          |
| ------------ | --------------------- | ----------- | -------------------- |
| `__src`      | `string`              | —           | Image URL            |
| `__altText`  | `string`              | —           | Alt text             |
| `__width`    | `number \| "inherit"` | `"inherit"` | Width in pixels      |
| `__height`   | `number \| "inherit"` | `"inherit"` | Height in pixels     |
| `__maxWidth` | `number`              | `100`       | Max width percentage |

### Behavior

- `isInline()` → `false` (block-level)
- `isIsolated()` → `true` (cursor cannot enter)
- `createDOM()` → `<span style="position:relative; display:inline-block">`
- `exportDOM()` → `<span class="block my-4 rounded-md max-w-full"><img src="..." alt="..." class="max-w-full rounded-md object-cover transition-shadow">`
- `importDOM()` matches `<img>` elements; rejects `file:///` URLs
- `decorate()` → `<ImageComponent>` with selection, resize, drag support

### Methods

| Method                | Signature                                                         |
| --------------------- | ----------------------------------------------------------------- |
| `getSrc()`            | `(): string`                                                      |
| `setSrc()`            | `(src: string): void`                                             |
| `getAltText()`        | `(): string`                                                      |
| `setAltText()`        | `(altText: string): void`                                         |
| `setWidthAndHeight()` | `(width: number \| "inherit", height: number \| "inherit"): void` |
| `getMaxWidth()`       | `(): number`                                                      |
| `setMaxWidth()`       | `(maxWidth: number): void`                                        |

### Command

```typescript
INSERT_IMAGE_COMMAND: LexicalCommand<ImagePayload>;
```

`ImagePayload`:

```typescript
{ src: string; altText: string; width?: number; height?: number; maxWidth?: number }
```

### Factory

```typescript
$createImageNode(payload: ImagePayload): ImageNode
$isImageNode(node): node is ImageNode
```

## ImagePlugin

`resources/js/Components/editor/image/ImagePlugin.tsx`

### Commands

| Command                | Handler                                            |
| ---------------------- | -------------------------------------------------- |
| `INSERT_IMAGE_COMMAND` | Creates `ImageNode` and inserts via `$insertNodes` |

### Paste/Drop

Registers `paste`, `drop`, and `dragover` event handlers on the editor root element.

| Event      | Behavior                                                               |
| ---------- | ---------------------------------------------------------------------- |
| `paste`    | Extracts image files from clipboard, uploads each, inserts `ImageNode` |
| `drop`     | Extracts image files from drag data, uploads each, inserts `ImageNode` |
| `dragover` | Prevents default if files are being dragged over                       |

`extractImageFiles()` filters `DataTransfer.items` for `kind === "file"` with `type.startsWith("image/")`.

### Upload

```typescript
uploadImageToServer(file: File): Promise<{ url: string; path: string }>
```

- POSTs `FormData` with `file` to `route("dashboard.posts.upload-image")`
- Includes CSRF token via `meta[name="csrf-token"]`
- Sets `X-Requested-With: XMLHttpRequest` header
- Returns `{ url, path }` from server response

### Delete

```typescript
deleteImageFromServer(src: string): Promise<void>
```

- DELETEs to `route("dashboard.posts.delete-image")`
- Only processes `/storage/` URLs (extracts path)
- Includes CSRF token and JSON body `{ path }`

## ImageUpload

Server-side endpoints used by the image plugin:

| Route                          | Method | Controller                   | Description       |
| ------------------------------ | ------ | ---------------------------- | ----------------- |
| `dashboard.posts.upload-image` | POST   | `PostController@uploadImage` | Upload image file |
| `dashboard.posts.delete-image` | DELETE | `PostController@deleteImage` | Delete image file |

Both require `auth` + `verified` + `permission:posts.create` middleware.

## Exports

```typescript
export { ImageNode, INSERT_IMAGE_COMMAND, $createImageNode, $isImageNode } from "./ImageNode";
export { ImagePlugin, uploadImageToServer, deleteImageFromServer } from "./ImagePlugin";
export { ImageInsertDialog } from "./ImageInsertDialog";
export { EditImageDialog } from "./EditImageDialog";
export { ImageToolbarPlugin } from "./ImageToolbarPlugin";
```
