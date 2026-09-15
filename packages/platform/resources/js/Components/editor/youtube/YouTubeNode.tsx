import type {
  DOMConversionMap,
  DOMConversionOutput,
  DOMExportOutput,
  EditorConfig,
  LexicalEditor,
  LexicalNode,
  NodeKey,
  SerializedLexicalNode,
  Spread,
} from "lexical";
import { $applyNodeReplacement, DecoratorNode, $insertNodes } from "lexical";
import { DecoratorErrorBoundary } from "../shared/DecoratorErrorBoundary";

export type SerializedYouTubeNode = Spread<{ videoId: string }, SerializedLexicalNode>;

export class YouTubeNode extends DecoratorNode<React.ReactNode> {
  static getType(): string {
    return "youtube";
  }

  static clone(node: YouTubeNode): YouTubeNode {
    return new YouTubeNode(node.__videoId, node.__key);
  }

  static importJSON(json: SerializedYouTubeNode): YouTubeNode {
    return new YouTubeNode().updateFromJSON(json);
  }

  static importDOM(): DOMConversionMap | null {
    return {
      iframe: (node: Node) => {
        if (!(node instanceof HTMLIFrameElement)) {
          return null;
        }
        const src = node.getAttribute("src") || "";
        const match = src.match(YOUTUBE_URL_REGEX);
        if (!match) {
          return null;
        }
        return {
          conversion: (): DOMConversionOutput => ({
            node: $createYouTubeNode(match[1]!),
          }),
          priority: 1,
        };
      },
    };
  }

  declare __videoId: string;

  constructor(videoId: string = "", key?: NodeKey) {
    super(key);
    this.__videoId = videoId;
  }

  updateFromJSON(json: SerializedYouTubeNode): this {
    const self = super.updateFromJSON(json);
    self.__videoId = json.videoId;
    return self;
  }

  createDOM(_config: EditorConfig): HTMLElement {
    const div = document.createElement("div");
    div.style.display = "contents";
    return div;
  }

  updateDOM(): false {
    return false;
  }

  exportDOM(): DOMExportOutput {
    const iframe = document.createElement("iframe");
    iframe.setAttribute("src", `https://www.youtube.com/embed/${this.__videoId}`);
    iframe.setAttribute("title", "YouTube video");
    iframe.setAttribute(
      "allow",
      "accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture",
    );
    iframe.setAttribute("allowFullScreen", "true");
    iframe.style.width = "100%";
    iframe.style.aspectRatio = "16/9";
    return { element: iframe };
  }

  exportJSON(): SerializedYouTubeNode {
    return {
      type: "youtube",
      version: 1,
      videoId: this.__videoId,
    };
  }

  decorate(): React.ReactNode {
    return (
      <DecoratorErrorBoundary>
        <div className="my-4 aspect-video w-full overflow-hidden rounded-lg">
          <iframe
            src={`https://www.youtube.com/embed/${this.__videoId}`}
            title="YouTube video"
            className="h-full w-full border-0"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
            allowFullScreen
          />
        </div>
      </DecoratorErrorBoundary>
    );
  }

  getVideoId(): string {
    return this.__videoId;
  }

  isInline(): boolean {
    return false;
  }

  isIsolated(): boolean {
    return true;
  }

  hasControlledIndentation(): boolean {
    return false;
  }
}

const YOUTUBE_URL_REGEX =
  /(?:youtube\.com\/(?:watch\?v=|embed\/)|youtube-nocookie\.com\/embed\/|youtu\.be\/)([a-zA-Z0-9_-]{11})/;

export function $createYouTubeNode(videoId: string): YouTubeNode {
  return $applyNodeReplacement(new YouTubeNode(videoId));
}

export function $isYouTubeNode(node: LexicalNode | null | undefined): node is YouTubeNode {
  return node instanceof YouTubeNode;
}

export const YouTubeEmbedConfig = {
  type: "youtube" as const,
  parseUrl: (url: string): { id: string; url: string } | null => {
    const match = url.match(YOUTUBE_URL_REGEX);
    return match ? { id: match[1]!, url } : null;
  },
  insertNode: (editor: LexicalEditor, result: { id: string }) => {
    editor.update(() => {
      const node = $createYouTubeNode(result.id);
      $insertNodes([node]);
    });
  },
};
