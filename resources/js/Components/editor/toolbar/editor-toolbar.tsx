import { useCallback, useEffect, useState } from "react";
import { useLexicalComposerContext } from "@lexical/react/LexicalComposerContext";
import {
  $createParagraphNode,
  $getSelection,
  $isRangeSelection,
  CAN_REDO_COMMAND,
  CAN_UNDO_COMMAND,
  COMMAND_PRIORITY_LOW,
  FORMAT_TEXT_COMMAND,
  REDO_COMMAND,
  SELECTION_CHANGE_COMMAND,
  UNDO_COMMAND,
  type ElementFormatType,
  type LexicalEditor,
  type LexicalNode,
  $isElementNode,
  $isTextNode,
} from "lexical";
import {
  INSERT_ORDERED_LIST_COMMAND,
  INSERT_UNORDERED_LIST_COMMAND,
  REMOVE_LIST_COMMAND,
  $isListNode,
  INSERT_CHECK_LIST_COMMAND,
} from "@lexical/list";
import { $isLinkNode } from "@lexical/link";
import { $findMatchingParent, mergeRegister } from "@lexical/utils";
import { $createHeadingNode, $isHeadingNode, $createQuoteNode } from "@lexical/rich-text";
import { $createCodeNode } from "@lexical/code";
import { $patchStyleText, $getSelectionStyleValueForProperty } from "@lexical/selection";
import {
  Bold,
  Italic,
  Underline,
  Strikethrough,
  Code,
  List,
  ListOrdered,
  Undo2,
  Redo2,
  Heading1,
  Heading2,
  Heading3,
  Heading4,
  Heading5,
  Heading6,
  Quote,
  ListChecks,
  CodeSquare,
  Type,
  Highlighter,
  Subscript as SubscriptIcon,
  Superscript as SuperscriptIcon,
  RemoveFormatting,
  Search,
} from "lucide-react";
import { ColorPicker, TEXT_COLORS, HIGHLIGHT_COLORS } from "./ColorPicker";
import { TableOfContentsPlugin } from "../TableOfContentsPlugin";
import { ToggleGroup } from "@/Components/ui/toggle-group";
import { Toggle } from "@/Components/ui/toggle";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/Components/ui/select";
import { Separator } from "@/Components/ui/separator";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/Components/ui/tooltip";
import { InsertMenu } from "./InsertMenu";
import { ElementFormatDropdown } from "./ElementFormatDropdown";

interface ToolbarState {
  isBold: boolean;
  isItalic: boolean;
  isUnderline: boolean;
  isStrikethrough: boolean;
  isCode: boolean;
  isSubscript: boolean;
  isSuperscript: boolean;
  blockType: string;
  elementFormat: ElementFormatType;
  isLink: boolean;
  canUndo: boolean;
  canRedo: boolean;
  isCodeBlock: boolean;
  textColor: string;
  highlightColor: string;
}

function $getBlockType(anchorNode: LexicalNode): string {
  if ($isElementNode(anchorNode)) {
    if ($isHeadingNode(anchorNode)) {
      return anchorNode.getTag();
    }
    if ($isListNode(anchorNode)) {
      const parent = anchorNode.getParent();
      if (parent && $isListNode(parent)) {
        return "nested-list";
      }
      return anchorNode.getListType();
    }
    return anchorNode.getType();
  }
  return "paragraph";
}

function useToolbarState(editor: LexicalEditor): ToolbarState {
  const [state, setState] = useState<ToolbarState>({
    isBold: false,
    isItalic: false,
    isUnderline: false,
    isStrikethrough: false,
    isCode: false,
    isSubscript: false,
    isSuperscript: false,
    blockType: "paragraph",
    elementFormat: "left",
    isLink: false,
    canUndo: false,
    canRedo: false,
    isCodeBlock: false,
    textColor: "",
    highlightColor: "",
  });

  const updateToolbar = useCallback(() => {
    const selection = $getSelection();
    if (!$isRangeSelection(selection)) {
      return;
    }

    const anchorNode = selection.anchor.getNode();
    const parent =
      anchorNode.getKey() === "root" ? anchorNode : anchorNode.getTopLevelElementOrThrow();

    const blockType = $getBlockType(parent);

    const elementFormat: ElementFormatType = $isElementNode(parent)
      ? parent.getFormatType() || "left"
      : "left";

    const linkParent = $findMatchingParent(anchorNode, (node) => $isLinkNode(node));
    const isLink = linkParent !== null;

    const isCodeBlock = blockType === "code";

    const textColor = $getSelectionStyleValueForProperty(selection, "color", "");
    const highlightColor = $getSelectionStyleValueForProperty(selection, "background-color", "");
    const isBold = selection.hasFormat("bold");
    const isItalic = selection.hasFormat("italic");
    const isUnderline = selection.hasFormat("underline");
    const isStrikethrough = selection.hasFormat("strikethrough");
    const isCode = selection.hasFormat("code");
    const isSubscript = selection.hasFormat("subscript");
    const isSuperscript = selection.hasFormat("superscript");

    setState((prev) => ({
      isBold,
      isItalic,
      isUnderline,
      isStrikethrough,
      isCode,
      isSubscript,
      isSuperscript,
      blockType,
      elementFormat,
      isLink,
      isCodeBlock,
      textColor,
      highlightColor,
      canUndo: prev.canUndo,
      canRedo: prev.canRedo,
    }));
  }, []);

  useEffect(() => {
    return mergeRegister(
      editor.registerUpdateListener(({ editorState }) => {
        editorState.read(() => {
          updateToolbar();
        });
      }),
      editor.registerCommand(
        SELECTION_CHANGE_COMMAND,
        () => {
          updateToolbar();
          return false;
        },
        COMMAND_PRIORITY_LOW,
      ),
      editor.registerCommand(
        CAN_UNDO_COMMAND,
        (payload) => {
          setState((prev) => ({ ...prev, canUndo: payload }));
          return false;
        },
        COMMAND_PRIORITY_LOW,
      ),
      editor.registerCommand(
        CAN_REDO_COMMAND,
        (payload) => {
          setState((prev) => ({ ...prev, canRedo: payload }));
          return false;
        },
        COMMAND_PRIORITY_LOW,
      ),
    );
  }, [editor, updateToolbar]);

  return state;
}

function BlockFormatSelect({ blockType }: { blockType: string }) {
  const [editor] = useLexicalComposerContext();

  const formatBlock = (value: string) => {
    editor.update(() => {
      const selection = $getSelection();
      if (!$isRangeSelection(selection)) return;

      const anchorNode = selection.anchor.getNode();
      const parent =
        anchorNode.getKey() === "root" ? anchorNode : anchorNode.getTopLevelElementOrThrow();

      switch (value) {
        case "paragraph": {
          const node = $createParagraphNode();
          // eslint-disable-next-line unicorn/no-unsafe-string-replacement
          parent.replace(node, true);
          node.select();
          break;
        }
        case "h1":
        case "h2":
        case "h3":
        case "h4":
        case "h5":
        case "h6": {
          const heading = $createHeadingNode(value as "h1" | "h2" | "h3" | "h4" | "h5" | "h6");
          // eslint-disable-next-line unicorn/no-unsafe-string-replacement
          parent.replace(heading, true);
          heading.select();
          break;
        }
        case "quote": {
          const quote = $createQuoteNode();
          // eslint-disable-next-line unicorn/no-unsafe-string-replacement
          parent.replace(quote, true);
          quote.select();
          break;
        }
        case "code": {
          const codeNode = $createCodeNode();
          // eslint-disable-next-line unicorn/no-unsafe-string-replacement
          parent.replace(codeNode, true);
          codeNode.select();
          break;
        }
        default: {
          break;
        }
      }
    });
  };

  const displayValue = (() => {
    if (["h1", "h2", "h3", "h4", "h5", "h6"].includes(blockType)) return blockType;
    if (["bullet", "number"].includes(blockType)) return "paragraph";
    return blockType;
  })();

  return (
    <Select value={displayValue} onValueChange={(value) => formatBlock(value ?? "")}>
      <SelectTrigger className="h-8 w-[130px]">
        <SelectValue />
      </SelectTrigger>
      <SelectContent>
        <SelectItem value="paragraph">Normal</SelectItem>
        <SelectItem value="h1">
          <Heading1 className="mr-2 inline h-4 w-4" />
          Heading 1
        </SelectItem>
        <SelectItem value="h2">
          <Heading2 className="mr-2 inline h-4 w-4" />
          Heading 2
        </SelectItem>
        <SelectItem value="h3">
          <Heading3 className="mr-2 inline h-4 w-4" />
          Heading 3
        </SelectItem>
        <SelectItem value="h4">
          <Heading4 className="mr-2 inline h-4 w-4" />
          Heading 4
        </SelectItem>
        <SelectItem value="h5">
          <Heading5 className="mr-2 inline h-4 w-4" />
          Heading 5
        </SelectItem>
        <SelectItem value="h6">
          <Heading6 className="mr-2 inline h-4 w-4" />
          Heading 6
        </SelectItem>
        <SelectItem value="quote">
          <Quote className="mr-2 inline h-4 w-4" />
          Blockquote
        </SelectItem>
        <SelectItem value="code">
          <CodeSquare className="mr-2 inline h-4 w-4" />
          Code Block
        </SelectItem>
      </SelectContent>
    </Select>
  );
}

function ToolbarButton({
  label,
  icon: Icon,
  isActive,
  onClick,
  disabled,
}: {
  label: string;
  icon: React.ComponentType<{ className?: string }>;
  isActive?: boolean;
  onClick: () => void;
  disabled?: boolean;
}) {
  return (
    <Tooltip>
      <TooltipTrigger
        render={
          <Toggle
            size="sm"
            variant="outline"
            pressed={isActive}
            onPressedChange={onClick}
            disabled={disabled}
            aria-label={label}
          >
            <Icon className="h-4 w-4" />
          </Toggle>
        }
      />
      <TooltipContent>{label}</TooltipContent>
    </Tooltip>
  );
}

export function EditorToolbar() {
  const [editor] = useLexicalComposerContext();
  const toolbar = useToolbarState(editor);

  const formatBold = () => editor.dispatchCommand(FORMAT_TEXT_COMMAND, "bold");
  const formatItalic = () => editor.dispatchCommand(FORMAT_TEXT_COMMAND, "italic");
  const formatUnderline = () => editor.dispatchCommand(FORMAT_TEXT_COMMAND, "underline");
  const formatStrikethrough = () => editor.dispatchCommand(FORMAT_TEXT_COMMAND, "strikethrough");
  const formatCode = () => editor.dispatchCommand(FORMAT_TEXT_COMMAND, "code");

  const formatTextColor = (color: string) => {
    editor.update(() => {
      const selection = $getSelection();
      if ($isRangeSelection(selection)) {
        $patchStyleText(selection, { color: color });
      }
    });
  };

  const formatHighlightColor = (color: string) => {
    editor.update(() => {
      const selection = $getSelection();
      if ($isRangeSelection(selection)) {
        $patchStyleText(selection, { "background-color": color });
      }
    });
  };

  const formatBulletList = () => {
    if (toolbar.blockType === "bullet") {
      editor.dispatchCommand(REMOVE_LIST_COMMAND, undefined);
    } else {
      editor.dispatchCommand(INSERT_UNORDERED_LIST_COMMAND, undefined);
    }
  };
  const formatNumberedList = () => {
    if (toolbar.blockType === "number") {
      editor.dispatchCommand(REMOVE_LIST_COMMAND, undefined);
    } else {
      editor.dispatchCommand(INSERT_ORDERED_LIST_COMMAND, undefined);
    }
  };
  const formatCheckList = () => {
    editor.dispatchCommand(INSERT_CHECK_LIST_COMMAND, undefined);
  };

  const formatUndo = () => editor.dispatchCommand(UNDO_COMMAND, undefined);
  const formatRedo = () => editor.dispatchCommand(REDO_COMMAND, undefined);

  const formatSubscript = () => editor.dispatchCommand(FORMAT_TEXT_COMMAND, "subscript");
  const formatSuperscript = () => editor.dispatchCommand(FORMAT_TEXT_COMMAND, "superscript");

  const formatClearFormatting = () => {
    editor.update(() => {
      const selection = $getSelection();
      if (!$isRangeSelection(selection)) return;
      const nodes = selection.getNodes();
      for (const node of nodes) {
        if (!$isTextNode(node)) {
          continue;
        }

        node.setFormat(0);
        node.setStyle("");
      }
    });
  };

  return (
    <>
      <TooltipProvider>
        <div className="sticky top-16 z-20 flex flex-wrap items-center gap-1.5 border-b bg-background px-2 py-1.5">
          {/* Group: History */}
          <ToggleGroup spacing={1}>
            <ToolbarButton
              label="Undo"
              icon={Undo2}
              onClick={formatUndo}
              disabled={!toolbar.canUndo}
            />
            <ToolbarButton
              label="Redo"
              icon={Redo2}
              onClick={formatRedo}
              disabled={!toolbar.canRedo}
            />
          </ToggleGroup>

          <Separator orientation="vertical" className="mx-1.5 h-6" />

          {/* Group: Block Type & Navigation */}
          <TableOfContentsPlugin />

          <BlockFormatSelect blockType={toolbar.blockType} />

          <Separator orientation="vertical" className="mx-1.5 h-6" />

          {/* Group: Inline Text Formatting */}
          <ToggleGroup multiple spacing={1}>
            <ToolbarButton
              label="Bold"
              icon={Bold}
              isActive={toolbar.isBold}
              onClick={formatBold}
            />
            <ToolbarButton
              label="Italic"
              icon={Italic}
              isActive={toolbar.isItalic}
              onClick={formatItalic}
            />
            <ToolbarButton
              label="Underline"
              icon={Underline}
              isActive={toolbar.isUnderline}
              onClick={formatUnderline}
            />
            <ToolbarButton
              label="Strikethrough"
              icon={Strikethrough}
              isActive={toolbar.isStrikethrough}
              onClick={formatStrikethrough}
            />
            <ToolbarButton
              label="Inline Code"
              icon={Code}
              isActive={toolbar.isCode}
              onClick={formatCode}
            />
            <ToolbarButton
              label="Subscript"
              icon={SubscriptIcon}
              isActive={toolbar.isSubscript}
              onClick={formatSubscript}
            />
            <ToolbarButton
              label="Superscript"
              icon={SuperscriptIcon}
              isActive={toolbar.isSuperscript}
              onClick={formatSuperscript}
            />
          </ToggleGroup>

          <ToolbarButton
            label="Clear Formatting"
            icon={RemoveFormatting}
            onClick={formatClearFormatting}
          />

          <Separator orientation="vertical" className="mx-1.5 h-6" />

          {/* Group: Colors */}
          <ToggleGroup multiple spacing={1}>
            <ColorPicker
              colors={TEXT_COLORS}
              currentColor={toolbar.textColor}
              onSelect={formatTextColor}
              label="Text Color"
              icon={Type}
            />
            <ColorPicker
              colors={HIGHLIGHT_COLORS}
              currentColor={toolbar.highlightColor}
              onSelect={formatHighlightColor}
              label="Highlight Color"
              icon={Highlighter}
            />
          </ToggleGroup>

          <Separator orientation="vertical" className="mx-1.5 h-6" />

          {/* Group: Alignment + Indentation */}
          <ElementFormatDropdown elementFormat={toolbar.elementFormat} />

          <Separator orientation="vertical" className="mx-1.5 h-6" />

          {/* Group: Lists */}
          <ToggleGroup multiple spacing={1}>
            <ToolbarButton
              label="Bullet List"
              icon={List}
              isActive={toolbar.blockType === "bullet"}
              onClick={formatBulletList}
            />
            <ToolbarButton
              label="Numbered List"
              icon={ListOrdered}
              isActive={toolbar.blockType === "number"}
              onClick={formatNumberedList}
            />
            <ToolbarButton label="Check List" icon={ListChecks} onClick={formatCheckList} />
          </ToggleGroup>

          <Separator orientation="vertical" className="mx-1.5 h-6" />

          {/* Group: Utilities */}
          <ToggleGroup spacing={1}>
            <ToolbarButton
              label="Find & Replace (Ctrl+F)"
              icon={Search}
              onClick={() => {
                dispatchEvent(new CustomEvent("lexical-find-replace"));
              }}
            />
            <InsertMenu />
          </ToggleGroup>
        </div>
      </TooltipProvider>
    </>
  );
}
