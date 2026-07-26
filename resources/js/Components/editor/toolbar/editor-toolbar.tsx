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
import {
  $patchStyleText,
  $getSelectionStyleValueForProperty,
  $setBlocksType,
} from "@lexical/selection";
import {
  Bold,
  Italic,
  Underline,
  Code,
  List,
  ListOrdered,
  Undo2,
  Redo2,
  Heading1,
  Heading2,
  Heading3,
  Quote,
  ListChecks,
  CodeSquare,
  Type,
  Highlighter,
  Search,
  ChevronDown,
} from "lucide-react";
import { ColorPicker, TEXT_COLORS, HIGHLIGHT_COLORS } from "./ColorPicker";
import { TableOfContentsPlugin } from "../plugins/TableOfContentsPlugin";
import { ToggleGroup } from "@/Components/ui/toggle-group";
import { Toggle } from "@/Components/ui/toggle";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuShortcut,
  DropdownMenuTrigger,
} from "@/Components/ui/dropdown-menu";
import { Separator } from "@/Components/ui/separator";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/Components/ui/tooltip";
import { InsertMenu } from "./InsertMenu";
import { ElementFormatDropdown } from "./ElementFormatDropdown";
import { TextFormatDropdown } from "./TextFormatDropdown";

interface ToolbarState {
  isBold: boolean;
  isItalic: boolean;
  isUnderline: boolean;
  isStrikethrough: boolean;
  isCode: boolean;
  isSubscript: boolean;
  isSuperscript: boolean;
  isLowercase: boolean;
  isUppercase: boolean;
  isCapitalize: boolean;
  isHighlight: boolean;
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
    isLowercase: false,
    isUppercase: false,
    isCapitalize: false,
    isHighlight: false,
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
    const isLowercase = selection.hasFormat("lowercase");
    const isUppercase = selection.hasFormat("uppercase");
    const isCapitalize = selection.hasFormat("capitalize");
    const isHighlight = selection.hasFormat("highlight");

    setState((prev) => ({
      isBold,
      isItalic,
      isUnderline,
      isStrikethrough,
      isCode,
      isSubscript,
      isSuperscript,
      isLowercase,
      isUppercase,
      isCapitalize,
      isHighlight,
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

type BlockOption = {
  value: string;
  label: string;
  icon: React.ComponentType<{ className?: string }>;
  shortcut: string;
};

const BLOCK_OPTIONS: BlockOption[] = [
  { value: "paragraph", label: "Normal", icon: Type, shortcut: "Ctrl+Alt+0" },
  { value: "h1", label: "Heading 1", icon: Heading1, shortcut: "Ctrl+Alt+1" },
  { value: "h2", label: "Heading 2", icon: Heading2, shortcut: "Ctrl+Alt+2" },
  { value: "h3", label: "Heading 3", icon: Heading3, shortcut: "Ctrl+Alt+3" },
  { value: "bullet", label: "Bullet List", icon: List, shortcut: "Ctrl+Shift+8" },
  { value: "number", label: "Numbered List", icon: ListOrdered, shortcut: "Ctrl+Shift+7" },
  { value: "check", label: "Check List", icon: ListChecks, shortcut: "Ctrl+Shift+9" },
  { value: "quote", label: "Quote", icon: Quote, shortcut: "Ctrl+Shift+Q" },
  { value: "code", label: "Code Block", icon: CodeSquare, shortcut: "Ctrl+Alt+C" },
];

function BlockFormatDropdown({ blockType }: { blockType: string }) {
  const [editor] = useLexicalComposerContext();

  const formatBlock = (value: string) => {
    editor.update(() => {
      const selection = $getSelection();
      if (!$isRangeSelection(selection)) return;

      switch (value) {
        case "paragraph": {
          $setBlocksType(selection, () => $createParagraphNode());
          break;
        }
        case "h1":
        case "h2":
        case "h3":
        case "h4":
        case "h5":
        case "h6": {
          $setBlocksType(selection, () =>
            $createHeadingNode(value as "h1" | "h2" | "h3" | "h4" | "h5" | "h6"),
          );
          break;
        }
        case "quote": {
          $setBlocksType(selection, () => $createQuoteNode());
          break;
        }
        case "code": {
          $setBlocksType(selection, () => $createCodeNode());
          break;
        }
        case "bullet": {
          if (blockType === "bullet") {
            editor.dispatchCommand(REMOVE_LIST_COMMAND, undefined);
          } else {
            editor.dispatchCommand(INSERT_UNORDERED_LIST_COMMAND, undefined);
          }
          break;
        }
        case "number": {
          if (blockType === "number") {
            editor.dispatchCommand(REMOVE_LIST_COMMAND, undefined);
          } else {
            editor.dispatchCommand(INSERT_ORDERED_LIST_COMMAND, undefined);
          }
          break;
        }
        case "check": {
          editor.dispatchCommand(INSERT_CHECK_LIST_COMMAND, undefined);
          break;
        }
        default: {
          break;
        }
      }
    });
  };

  const currentOption = BLOCK_OPTIONS.find((opt) => opt.value === blockType) ?? BLOCK_OPTIONS[0]!;
  const CurrentIcon = currentOption.icon;

  return (
    <TooltipProvider>
      <Tooltip>
        <DropdownMenu>
          <TooltipTrigger
            render={
              <DropdownMenuTrigger
                render={
                  <Toggle size="sm" variant="outline" aria-label="Block format" className="gap-1">
                    <CurrentIcon className="h-4 w-4" />
                    <span className="hidden sm:inline">{currentOption.label}</span>
                    <ChevronDown className="h-3 w-3 text-muted-foreground" />
                  </Toggle>
                }
              />
            }
          />
          <DropdownMenuContent className="w-52">
            {BLOCK_OPTIONS.map((option) => {
              const Icon = option.icon;
              return (
                <DropdownMenuItem key={option.value} onClick={() => formatBlock(option.value)}>
                  <Icon className="h-4 w-4" />
                  {option.label}
                  <DropdownMenuShortcut>{option.shortcut}</DropdownMenuShortcut>
                </DropdownMenuItem>
              );
            })}
          </DropdownMenuContent>
        </DropdownMenu>
        <TooltipContent>Block Format</TooltipContent>
      </Tooltip>
    </TooltipProvider>
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

  const formatUndo = () => editor.dispatchCommand(UNDO_COMMAND, undefined);
  const formatRedo = () => editor.dispatchCommand(REDO_COMMAND, undefined);

  return (
    <>
      <TooltipProvider>
        <div className="sticky top-16 z-40 flex items-center gap-1.5 border-b bg-background px-2 py-1.5 overflow-x-auto overflow-y-hidden scrollbar-thin">
          {/* Group: History */}
          <ToggleGroup spacing={1} className="flex-shrink-0">
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

          <ToolbarButton
            label="Find & Replace (Ctrl+F)"
            icon={Search}
            onClick={() => {
              dispatchEvent(new CustomEvent("lexical-find-replace"));
            }}
          />

          <Separator orientation="vertical" className="mx-1.5 h-6 flex-shrink-0" />

          {/* Group: Block Type & Navigation */}
          <div className="flex-shrink-0">
            <TableOfContentsPlugin />
          </div>

          <div className="flex-shrink-0">
            <BlockFormatDropdown blockType={toolbar.blockType} />
          </div>

          <Separator orientation="vertical" className="mx-1.5 h-6 flex-shrink-0" />

          {/* Group: Inline Text Formatting */}
          <ToggleGroup multiple spacing={1} className="flex-shrink-0">
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
              label="Inline Code"
              icon={Code}
              isActive={toolbar.isCode}
              onClick={formatCode}
            />
          </ToggleGroup>

          <div className="flex-shrink-0">
            <TextFormatDropdown
              isStrikethrough={toolbar.isStrikethrough}
              isSubscript={toolbar.isSubscript}
              isSuperscript={toolbar.isSuperscript}
              isLowercase={toolbar.isLowercase}
              isUppercase={toolbar.isUppercase}
              isCapitalize={toolbar.isCapitalize}
              isHighlight={toolbar.isHighlight}
            />
          </div>

          <Separator orientation="vertical" className="mx-1.5 h-6 flex-shrink-0" />

          {/* Group: Colors */}
          <ToggleGroup multiple spacing={1} className="flex-shrink-0">
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

          <Separator orientation="vertical" className="mx-1.5 h-6 flex-shrink-0" />

          {/* Group: Alignment + Indentation */}
          <div className="flex-shrink-0">
            <ElementFormatDropdown elementFormat={toolbar.elementFormat} />
          </div>

          <Separator orientation="vertical" className="mx-1.5 h-6 flex-shrink-0" />

          {/* Group: Utilities */}
          <div className="flex-shrink-0">
            <InsertMenu />
          </div>
        </div>
      </TooltipProvider>
    </>
  );
}
