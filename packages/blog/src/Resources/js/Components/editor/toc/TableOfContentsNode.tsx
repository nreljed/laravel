import type {
  DOMConversionMap,
  DOMConversionOutput,
  DOMExportOutput,
  EditorConfig,
  LexicalNode,
  NodeKey,
  SerializedLexicalNode,
  Spread,
} from "lexical";
import { $applyNodeReplacement, DecoratorNode } from "lexical";
import { DecoratorErrorBoundary } from "../shared/DecoratorErrorBoundary";
import { TableOfContentsView } from "./TableOfContentsView";

export interface TableOfContentsOptions {
  title: string;
  collapsed: boolean;
  depth: number;
  numbered: boolean;
  smooth: boolean;
  spy: boolean;
}

export const DEFAULT_TOC_OPTIONS: TableOfContentsOptions = {
  title: "Table of Contents",
  collapsed: false,
  depth: 6,
  numbered: false,
  smooth: true,
  spy: true,
};

export const TOC_DEPTH_VALUES = [2, 3, 4, 5, 6] as const;

export type SerializedTableOfContentsNode = Spread<
  {
    title?: string;
    collapsed?: boolean;
    depth?: number;
    numbered?: boolean;
    smooth?: boolean;
    spy?: boolean;
  },
  SerializedLexicalNode
>;

function clampDepth(value: unknown, fallback = DEFAULT_TOC_OPTIONS.depth): number {
  const depth = typeof value === "number" && Number.isFinite(value) ? Math.round(value) : fallback;
  return Math.min(6, Math.max(2, depth));
}

function normalizeOptions(raw: SerializedTableOfContentsNode): TableOfContentsOptions {
  const defaults = DEFAULT_TOC_OPTIONS;
  return {
    title:
      typeof raw.title === "string" && raw.title.trim() !== "" ? raw.title.trim() : defaults.title,
    collapsed: typeof raw.collapsed === "boolean" ? raw.collapsed : defaults.collapsed,
    depth: clampDepth(raw.depth, defaults.depth),
    numbered: typeof raw.numbered === "boolean" ? raw.numbered : defaults.numbered,
    smooth: typeof raw.smooth === "boolean" ? raw.smooth : defaults.smooth,
    spy: typeof raw.spy === "boolean" ? raw.spy : defaults.spy,
  };
}

/**
 * Read the per-instance options back from a re-imported `<nav data-lexical-toc>`
 * marker. The saved/public nav carries the same data-toc-* attributes the
 * export writes, so options survive a save → public render → re-edit round trip.
 */
function optionsFromElement(element: HTMLElement): Partial<TableOfContentsOptions> {
  const options: Partial<TableOfContentsOptions> = {};

  const attr = (name: string): string | null => element.getAttribute(name);
  const flag = (name: string): boolean | undefined => {
    const value = attr(name);
    return value === null ? undefined : value === "1";
  };

  const title = attr("data-toc-title");
  if (title !== null && title.trim() !== "") {
    options.title = title.trim();
  }

  const collapsed = flag("data-toc-collapsed");
  if (collapsed !== undefined) {
    options.collapsed = collapsed;
  }

  const depth = attr("data-toc-depth");
  if (depth !== null && depth !== "") {
    options.depth = clampDepth(Number(depth));
  }

  const numbered = flag("data-toc-numbers");
  if (numbered !== undefined) {
    options.numbered = numbered;
  }

  const smooth = flag("data-toc-smooth");
  if (smooth !== undefined) {
    options.smooth = smooth;
  }

  const spy = flag("data-toc-spy");
  if (spy !== undefined) {
    options.spy = spy;
  }

  return options;
}

/**
 * Table of Contents block node.
 *
 * A DecoratorNode that lives directly in the document body (insertable via the
 * `/toc` slash command or the toolbar Insert menu). Its live editor view is a
 * collapsible, configurable heading outline. On export it emits only a marker
 * element carrying the per-instance options as `data-toc-*` attributes:
 *
 *   <nav data-lexical-toc="true" aria-label="Table of Contents"
 *        data-toc-title="Table of Contents" data-toc-collapsed="0"
 *        data-toc-depth="6" data-toc-numbers="0" data-toc-smooth="1"
 *        data-toc-spy="1"></nav>
 *
 * The marker carries no outline content because Lexical node keys are not
 * stable across saves — instead the save-time content pipeline assigns
 * deterministic `id` attributes to every heading and fills each marker with a
 * nested <ol> of anchor links (plus a native <details>/<summary> collapsible
 * header when there is anything to list), so the public renderer works with
 * zero JS. Re-importing that filled <nav> matches on `[data-lexical-toc]` and
 * recreates this live node with its options, ignoring any inner list.
 */
export class TableOfContentsNode extends DecoratorNode<React.ReactNode> {
  static getType(): string {
    return "toc";
  }

  static clone(node: TableOfContentsNode): TableOfContentsNode {
    return new TableOfContentsNode(node.__key, { ...node.#_options });
  }

  static importJSON(json: SerializedTableOfContentsNode): TableOfContentsNode {
    const node = $createTableOfContentsNode();
    node.#_options = normalizeOptions(json);
    return node;
  }

  static importDOM(): DOMConversionMap | null {
    return {
      nav: (node: Node) => {
        if (!(node instanceof HTMLElement) || node.dataset.lexicalToc === undefined) {
          return null;
        }
        return {
          conversion: (): DOMConversionOutput => ({
            node: $createTableOfContentsNode(optionsFromElement(node)),
          }),
          priority: 1,
        };
      },
    };
  }

  #_options: TableOfContentsOptions;

  constructor(key?: NodeKey, options?: Partial<TableOfContentsOptions>) {
    super(key);
    this.#_options = { ...DEFAULT_TOC_OPTIONS, ...options };
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
    const options = { ...this.#_options, title: this.#_options.title || DEFAULT_TOC_OPTIONS.title };
    const nav = document.createElement("nav");
    nav.dataset.lexicalToc = "true";
    nav.setAttribute("aria-label", options.title);
    nav.dataset.tocTitle = options.title;
    nav.dataset.tocCollapsed = options.collapsed ? "1" : "0";
    nav.dataset.tocDepth = String(clampDepth(options.depth));
    nav.dataset.tocNumbers = options.numbered ? "1" : "0";
    nav.dataset.tocSmooth = options.smooth ? "1" : "0";
    nav.dataset.tocSpy = options.spy ? "1" : "0";
    return { element: nav };
  }

  exportJSON(): SerializedTableOfContentsNode {
    return {
      type: "toc",
      version: 1,
      ...this.#_options,
    };
  }

  getOptions(): TableOfContentsOptions {
    return { ...this.#_options };
  }

  setOptions(options: TableOfContentsOptions): void {
    const self = this.getWritable();
    self.#_options = { ...options };
  }

  decorate(): React.ReactNode {
    return (
      <DecoratorErrorBoundary>
        <TableOfContentsView
          key={this.getKey()}
          nodeKey={this.getKey()}
          options={this.getOptions()}
        />
      </DecoratorErrorBoundary>
    );
  }

  isInline(): boolean {
    return false;
  }

  isIsolated(): boolean {
    return true;
  }
}

export function $createTableOfContentsNode(
  options?: Partial<TableOfContentsOptions>,
): TableOfContentsNode {
  return $applyNodeReplacement(new TableOfContentsNode(undefined, options));
}

export function $isTableOfContentsNode(
  node: LexicalNode | null | undefined,
): node is TableOfContentsNode {
  return node instanceof TableOfContentsNode;
}