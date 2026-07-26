import type {
  DOMConversionMap,
  DOMConversionOutput,
  DOMExportOutput,
  EditorConfig,
  LexicalCommand,
  LexicalNode,
  NodeKey,
  SerializedLexicalNode,
  Spread,
} from "lexical";
import { $applyNodeReplacement, createCommand, DecoratorNode } from "lexical";
import ImageComponent from "./ImageComponent";
import { DecoratorErrorBoundary } from "../shared/DecoratorErrorBoundary";

export const INSERT_IMAGE_COMMAND: LexicalCommand<ImagePayload> =
  createCommand("INSERT_IMAGE_COMMAND");

function $convertImageElement(domNode: Node): null | DOMConversionOutput {
  if (domNode instanceof HTMLImageElement) {
    const src = domNode.getAttribute("src");
    if (!src || src.startsWith("file:///")) {
      return null;
    }
    const altText = domNode.getAttribute("alt") || "";
    const width = domNode.width || undefined;
    const height = domNode.height || undefined;
    return { node: $createImageNode({ altText, height, src, width }) };
  }
  return null;
}

export interface ImagePayload {
  src: string;
  altText: string;
  width?: number;
  height?: number;
  maxWidth?: number;
}

export type SerializedImageNode = Spread<
  {
    src: string;
    altText: string;
    width?: number;
    height?: number;
    maxWidth: number;
  },
  SerializedLexicalNode
>;

export class ImageNode extends DecoratorNode<React.ReactNode> {
  static getType(): string {
    return "image";
  }

  static clone(node: ImageNode): ImageNode {
    return new ImageNode(
      node.__src,
      node.__altText,
      node.__maxWidth,
      node.__width,
      node.__height,
      node.__key,
    );
  }

  static importJSON(json: SerializedImageNode): ImageNode {
    return new ImageNode().updateFromJSON(json);
  }

  static importDOM(): DOMConversionMap | null {
    return {
      img: () => ({
        conversion: $convertImageElement,
        priority: 0,
      }),
    };
  }

  declare __src: string;
  declare __altText: string;
  declare __width: number | "inherit";
  declare __height: number | "inherit";
  declare __maxWidth: number;

  constructor(
    src: string = "",
    altText: string = "",
    maxWidth: number = 100,
    width?: number | "inherit",
    height?: number | "inherit",
    key?: NodeKey,
  ) {
    super(key);
    this.__src = src;
    this.__altText = altText;
    this.__width = width || "inherit";
    this.__height = height || "inherit";
    this.__maxWidth = maxWidth;
  }

  updateFromJSON(json: SerializedImageNode): this {
    const self = super.updateFromJSON(json);
    self.__src = json.src;
    self.__altText = json.altText;
    self.__width = json.width || "inherit";
    self.__height = json.height || "inherit";
    self.__maxWidth = json.maxWidth;
    return self;
  }

  createDOM(_config: EditorConfig): HTMLElement {
    const span = document.createElement("span");
    span.style.position = "relative";
    span.style.display = "inline-block";
    const className = _config.theme.image;
    if (className !== undefined) {
      span.className = className;
    }
    return span;
  }

  updateDOM(): false {
    return false;
  }

  decorate(): React.ReactNode {
    return (
      <DecoratorErrorBoundary>
        <ImageComponent
          src={this.__src}
          altText={this.__altText}
          width={this.__width}
          height={this.__height}
          maxWidth={this.__maxWidth}
          nodeKey={this.__key}
        />
      </DecoratorErrorBoundary>
    );
  }

  exportDOM(): DOMExportOutput {
    const span = document.createElement("span");
    span.className = "block my-4 rounded-md max-w-full";
    span.style.position = "relative";
    span.style.display = "inline-block";

    const img = document.createElement("img");
    img.setAttribute("src", this.__src);
    img.setAttribute("alt", this.__altText);
    img.className = "max-w-full rounded-md object-cover transition-shadow";
    if (this.__width !== "inherit" && this.__width !== 0) {
      img.setAttribute("width", String(this.__width));
    }
    if (this.__height !== "inherit" && this.__height !== 0) {
      img.setAttribute("height", String(this.__height));
    }
    span.appendChild(img);

    return { element: span };
  }

  exportJSON(): SerializedImageNode {
    return {
      type: "image",
      version: 1,
      src: this.__src,
      altText: this.__altText,
      width: this.__width === "inherit" ? 0 : this.__width,
      height: this.__height === "inherit" ? 0 : this.__height,
      maxWidth: this.__maxWidth,
    };
  }

  getSrc(): string {
    return this.getLatest().__src;
  }

  getAltText(): string {
    return this.getLatest().__altText;
  }

  setWidthAndHeight(width: number | "inherit", height: number | "inherit"): void {
    const writable = this.getWritable();
    writable.__width = width;
    writable.__height = height;
  }

  setSrc(src: string): void {
    this.getWritable().__src = src;
  }

  setAltText(altText: string): void {
    this.getWritable().__altText = altText;
  }

  getMaxWidth(): number {
    return this.getLatest().__maxWidth;
  }

  setMaxWidth(maxWidth: number): void {
    this.getWritable().__maxWidth = maxWidth;
  }

  isInline(): boolean {
    return false;
  }

  isIsolated(): boolean {
    return true;
  }
}

export function $createImageNode(payload: ImagePayload): ImageNode {
  const { src, altText, width, height, maxWidth } = payload;
  return $applyNodeReplacement(new ImageNode(src, altText, maxWidth, width, height));
}

export function $isImageNode(node: LexicalNode | null | undefined): node is ImageNode {
  return node instanceof ImageNode;
}
