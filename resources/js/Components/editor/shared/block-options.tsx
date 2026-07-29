import { MenuOption } from "@lexical/react/LexicalTypeaheadMenuPlugin";
import {
  $createParagraphNode,
  $getSelection,
  $isRangeSelection,
  FORMAT_ELEMENT_COMMAND,
  type LexicalEditor,
} from "lexical";
import { $setBlocksType } from "@lexical/selection";
import { $createHeadingNode, $createQuoteNode, type HeadingTagType } from "@lexical/rich-text";
import { $createCodeNode } from "@lexical/code";
import {
  INSERT_ORDERED_LIST_COMMAND,
  INSERT_UNORDERED_LIST_COMMAND,
  INSERT_CHECK_LIST_COMMAND,
} from "@lexical/list";
import { INSERT_HORIZONTAL_RULE_COMMAND } from "@lexical/extension";
import {
  Type,
  Heading1,
  Heading2,
  Heading3,
  Heading4,
  Heading5,
  Heading6,
  List,
  ListOrdered,
  CheckSquare,
  Quote,
  Code2,
  Minus,
  Table,
  ImagePlus,
  Columns3,
  AlignLeft,
  AlignCenter,
  AlignRight,
  AlignJustify,
  type LucideIcon,
} from "lucide-react";
import { ImageInsertDialog } from "../image";
import { InsertTableDialog } from "../table";
import { InsertLayoutDialog } from "../layout";

export class BlockOption extends MenuOption {
  title: string;
  IconComponent: LucideIcon;
  keywords: string[];
  onSelect: (_queryString: string) => void;

  constructor(
    title: string,
    options: {
      icon: LucideIcon;
      keywords?: string[];
      onSelect: (_queryString: string) => void;
    },
  ) {
    super(title);
    this.title = title;
    this.IconComponent = options.icon;
    this.keywords = options.keywords || [];
    this.onSelect = options.onSelect.bind(this);
  }
}

export function getBlockOptions(
  editor: LexicalEditor,
  showModal: (_title: string, _render: (_onClose: () => void) => React.ReactNode) => void,
): BlockOption[] {
  const headingTags: { tag: HeadingTagType; icon: LucideIcon; label: string }[] = [
    { tag: "h1", icon: Heading1, label: "Heading 1" },
    { tag: "h2", icon: Heading2, label: "Heading 2" },
    { tag: "h3", icon: Heading3, label: "Heading 3" },
    { tag: "h4", icon: Heading4, label: "Heading 4" },
    { tag: "h5", icon: Heading5, label: "Heading 5" },
    { tag: "h6", icon: Heading6, label: "Heading 6" },
  ];

  return [
    new BlockOption("Paragraph", {
      icon: Type,
      keywords: ["normal", "paragraph", "p", "text"],
      onSelect: () =>
        editor.update(() => {
          const selection = $getSelection();
          if ($isRangeSelection(selection)) {
            $setBlocksType(selection, () => $createParagraphNode());
          }
        }),
    }),
    ...headingTags.map(
      ({ tag, icon, label }) =>
        new BlockOption(label, {
          icon,
          keywords: ["heading", "header", tag],
          onSelect: () =>
            editor.update(() => {
              const selection = $getSelection();
              if ($isRangeSelection(selection)) {
                $setBlocksType(selection, () => $createHeadingNode(tag));
              }
            }),
        }),
    ),
    new BlockOption("Bullet List", {
      icon: List,
      keywords: ["bullet", "unordered", "ul", "list"],
      onSelect: () => editor.dispatchCommand(INSERT_UNORDERED_LIST_COMMAND, undefined),
    }),
    new BlockOption("Numbered List", {
      icon: ListOrdered,
      keywords: ["numbered", "ordered", "ol", "list"],
      onSelect: () => editor.dispatchCommand(INSERT_ORDERED_LIST_COMMAND, undefined),
    }),
    new BlockOption("Check List", {
      icon: CheckSquare,
      keywords: ["check", "todo", "checkbox"],
      onSelect: () => editor.dispatchCommand(INSERT_CHECK_LIST_COMMAND, undefined),
    }),
    new BlockOption("Quote", {
      icon: Quote,
      keywords: ["blockquote", "quote"],
      onSelect: () =>
        editor.update(() => {
          const selection = $getSelection();
          if ($isRangeSelection(selection)) {
            $setBlocksType(selection, () => $createQuoteNode());
          }
        }),
    }),
    new BlockOption("Code Block", {
      icon: Code2,
      keywords: ["code", "pre", "codeblock"],
      onSelect: () =>
        editor.update(() => {
          const selection = $getSelection();
          if ($isRangeSelection(selection)) {
            if (selection.isCollapsed()) {
              $setBlocksType(selection, () => $createCodeNode());
            } else {
              const textContent = selection.getTextContent();
              const codeNode = $createCodeNode();
              selection.insertNodes([codeNode]);
              selection.insertRawText(textContent);
            }
          }
        }),
    }),
    new BlockOption("Horizontal Rule", {
      icon: Minus,
      keywords: ["divider", "hr", "line"],
      onSelect: () => editor.dispatchCommand(INSERT_HORIZONTAL_RULE_COMMAND, undefined),
    }),
    new BlockOption("Table", {
      icon: Table,
      keywords: ["table", "grid", "spreadsheet"],
      onSelect: () =>
        showModal("Insert Table", (onClose) => (
          <InsertTableDialog
            open={true}
            onOpenChange={(open) => {
              if (!open) onClose();
            }}
          />
        )),
    }),
    new BlockOption("Image", {
      icon: ImagePlus,
      keywords: ["image", "photo", "picture"],
      onSelect: () =>
        showModal("Insert Image", (onClose) => (
          <ImageInsertDialog
            open={true}
            onOpenChange={(open) => {
              if (!open) onClose();
            }}
          />
        )),
    }),
    new BlockOption("Columns Layout", {
      icon: Columns3,
      keywords: ["columns", "layout", "grid"],
      onSelect: () =>
        showModal("Insert Columns Layout", (onClose) => (
          <InsertLayoutDialog
            open={true}
            onOpenChange={(open) => {
              if (!open) onClose();
            }}
          />
        )),
    }),
    ...(["left", "center", "right", "justify"] as const).map(
      (alignment) =>
        new BlockOption(`Align ${alignment.charAt(0).toUpperCase() + alignment.slice(1)}`, {
          icon:
            alignment === "left"
              ? AlignLeft
              : alignment === "center"
                ? AlignCenter
                : alignment === "right"
                  ? AlignRight
                  : AlignJustify,
          keywords: ["align", "justify", alignment],
          onSelect: () => editor.dispatchCommand(FORMAT_ELEMENT_COMMAND, alignment),
        }),
    ),
  ];
}
