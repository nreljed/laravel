import {
  $applyNodeReplacement,
  addClassNamesToElement,
  type DOMConversionMap,
  type DOMExportOutput,
  type EditorConfig,
  ElementNode,
  type LexicalNode,
  type LexicalUpdateJSON,
  type NodeKey,
  type SerializedElementNode,
  type Spread,
} from "lexical";

export type ResponsiveMode = "full" | "mobile-only" | "none";

export const RESPONSIVE_MODE_LABELS: Record<ResponsiveMode, string> = {
  full: "Stack on tablet + mobile",
  "mobile-only": "Stack on mobile only",
  none: "Never stack",
};

export type SerializedLayoutContainerNode = Spread<
  {
    templateColumns: string;
    responsiveMode?: ResponsiveMode;
    responsive?: boolean;
  },
  SerializedElementNode
>;

const RESPONSIVE_MODES = ["full", "mobile-only", "none"] as const;

function isResponsiveMode(value: string): value is ResponsiveMode {
  return (RESPONSIVE_MODES as readonly string[]).includes(value);
}

function migrateResponsive(responsive: boolean | undefined): ResponsiveMode {
  if (responsive === undefined) return "full";
  return responsive ? "full" : "none";
}

export class LayoutContainerNode extends ElementNode {
  static getType(): string {
    return "layout-container";
  }

  static clone(node: LayoutContainerNode): LayoutContainerNode {
    return new LayoutContainerNode(node.__templateColumns, node.__responsiveMode, node.__key);
  }

  static importJSON(json: SerializedLayoutContainerNode): LayoutContainerNode {
    return $createLayoutContainerNode().updateFromJSON(json);
  }

  static importDOM(): DOMConversionMap | null {
    return {
      div: (node: Node) => {
        if (
          !(node instanceof HTMLElement) ||
          !(node instanceof HTMLDivElement) ||
          !node.dataset.lexicalLayoutContainer
        ) {
          return null;
        }
        return {
          conversion: (element: HTMLElement) => {
            const raw = element.dataset.responsiveMode;
            let mode: ResponsiveMode = "full";
            if (typeof raw === "string" && isResponsiveMode(raw)) {
              mode = raw;
            } else if (element.dataset.layoutResponsive !== undefined) {
              mode = migrateResponsive(element.dataset.layoutResponsive === "true");
            }
            return {
              node: $createLayoutContainerNode(element.style.gridTemplateColumns || "", mode),
            };
          },
          priority: 1,
        };
      },
    };
  }

  // eslint-disable-next-line unicorn/prefer-private-class-fields
  __responsiveMode: ResponsiveMode;

  // Lexical uses double-underscore convention for node properties
  // eslint-disable-next-line unicorn/prefer-private-class-fields
  __templateColumns: string;

  constructor(templateColumns: string, responsiveMode: ResponsiveMode = "full", key?: NodeKey) {
    super(key);
    this.__templateColumns = templateColumns;
    this.__responsiveMode = responsiveMode;
  }

  createDOM(config: EditorConfig): HTMLElement {
    const dom = document.createElement("div");
    dom.dataset.lexicalLayoutContainer = "true";
    dom.style.gridTemplateColumns = this.__templateColumns;
    if (this.__responsiveMode !== "none") {
      dom.dataset.responsiveMode = this.__responsiveMode;
    }
    if (typeof config.theme.layoutContainer === "string") {
      addClassNamesToElement(dom, config.theme.layoutContainer);
    }
    return dom;
  }

  exportDOM(): DOMExportOutput {
    const element = document.createElement("div");
    element.style.gridTemplateColumns = this.__templateColumns;
    element.dataset.lexicalLayoutContainer = "true";
    if (this.__responsiveMode !== "none") {
      element.dataset.responsiveMode = this.__responsiveMode;
    }
    return { element };
  }

  updateDOM(prevNode: this, dom: HTMLElement): boolean {
    if (prevNode.__templateColumns !== this.__templateColumns) {
      dom.style.gridTemplateColumns = this.__templateColumns;
    }
    if (prevNode.__responsiveMode !== this.__responsiveMode) {
      if (this.__responsiveMode !== "none") {
        dom.dataset.responsiveMode = this.__responsiveMode;
      } else {
        delete dom.dataset.responsiveMode;
      }
    }
    return false;
  }

  updateFromJSON(serializedNode: LexicalUpdateJSON<SerializedLayoutContainerNode>): this {
    const raw = serializedNode.responsiveMode ?? serializedNode.responsive;
    let mode: ResponsiveMode = "full";
    if (typeof raw === "string" && isResponsiveMode(raw)) {
      mode = raw;
    } else if (typeof raw === "boolean") {
      mode = migrateResponsive(raw);
    }
    return super
      .updateFromJSON(serializedNode)
      .setTemplateColumns(serializedNode.templateColumns)
      .setResponsiveMode(mode);
  }

  isShadowRoot(): boolean {
    return true;
  }

  canBeEmpty(): boolean {
    return false;
  }

  exportJSON(): SerializedLayoutContainerNode {
    return {
      ...super.exportJSON(),
      responsiveMode: this.__responsiveMode,
      templateColumns: this.__templateColumns,
    };
  }

  getTemplateColumns(): string {
    return this.getLatest().__templateColumns;
  }

  setTemplateColumns(templateColumns: string): this {
    const self = this.getWritable();
    self.__templateColumns = templateColumns;
    return self;
  }

  getResponsiveMode(): ResponsiveMode {
    return this.getLatest().__responsiveMode;
  }

  setResponsiveMode(mode: ResponsiveMode): this {
    const self = this.getWritable();
    self.__responsiveMode = mode;
    return self;
  }

  getResponsive(): boolean {
    return this.getLatest().__responsiveMode !== "none";
  }

  setResponsive(responsive: boolean): this {
    return this.setResponsiveMode(responsive ? "full" : "none");
  }
}

export function $createLayoutContainerNode(
  templateColumns: string = "",
  responsiveMode: ResponsiveMode = "full",
): LayoutContainerNode {
  return $applyNodeReplacement(new LayoutContainerNode(templateColumns, responsiveMode));
}

export function $isLayoutContainerNode(
  node: LexicalNode | null | undefined,
): node is LayoutContainerNode {
  return node instanceof LayoutContainerNode;
}
