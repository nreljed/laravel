import { useCallback, useEffect, useState } from "react";
import { useLexicalComposerContext } from "@lexical/react/LexicalComposerContext";
import {
  $getSelection,
  $isRangeSelection,
  COMMAND_PRIORITY_LOW,
  FORMAT_TEXT_COMMAND,
  registerEventListener,
  SELECTION_CHANGE_COMMAND,
  type LexicalEditor,
} from "lexical";
import { mergeRegister } from "@lexical/utils";
import { $isCodeNode } from "@lexical/code";
import { $isLinkNode, TOGGLE_LINK_COMMAND } from "@lexical/link";
import { autoUpdate, flip, inline, offset, shift, useFloating } from "@floating-ui/react";
import { createPortal } from "react-dom";
import { Bold, Italic, Underline, Strikethrough, Code, Link } from "lucide-react";
import { cn } from "@/lib/utils";

function FloatingToolbarButton({
  icon: Icon,
  label,
  isActive,
  onClick,
}: {
  icon: React.ComponentType<{ className?: string }>;
  label: string;
  isActive: boolean;
  onClick: () => void;
}) {
  return (
    <button
      type="button"
      title={label}
      aria-label={label}
      onClick={onClick}
      className={cn(
        "flex h-7 w-7 items-center justify-center rounded-sm transition-colors",
        isActive
          ? "bg-primary text-primary-foreground"
          : "text-muted-foreground hover:bg-accent hover:text-accent-foreground",
      )}
    >
      <Icon className="h-4 w-4" />
    </button>
  );
}

function useFloatingTextFormatToolbar(editor: LexicalEditor, isLinkEditMode: boolean) {
  const [isText, setIsText] = useState(false);
  const [isLink, setIsLink] = useState(false);
  const [isBold, setIsBold] = useState(false);
  const [isItalic, setIsItalic] = useState(false);
  const [isUnderline, setIsUnderline] = useState(false);
  const [isStrikethrough, setIsStrikethrough] = useState(false);
  const [isCode, setIsCode] = useState(false);

  const { refs, floatingStyles, update } = useFloating({
    middleware: [
      inline(),
      offset(8),
      flip({ padding: 10 }),
      shift({ crossAxis: true, mainAxis: true, padding: 10 }),
    ],
    placement: "top",
    strategy: "fixed",
    whileElementsMounted: (...args) => autoUpdate(...args, { ancestorScroll: true }),
  });

  const $updatePopup = useCallback(() => {
    editor.read(() => {
      if (editor.isComposing()) return;

      const selection = $getSelection();
      if (!$isRangeSelection(selection)) {
        setIsText(false);
        return;
      }

      if (selection.isCollapsed() || selection.getTextContent().replaceAll("\n", "") === "") {
        setIsText(false);
        return;
      }

      const anchorNode = selection.anchor.getNode();
      if ($isCodeNode(anchorNode.getParent()) || $isCodeNode(anchorNode)) {
        setIsText(false);
        return;
      }

      setIsText(true);
      setIsBold(selection.hasFormat("bold"));
      setIsItalic(selection.hasFormat("italic"));
      setIsUnderline(selection.hasFormat("underline"));
      setIsStrikethrough(selection.hasFormat("strikethrough"));
      setIsCode(selection.hasFormat("code"));

      const node = selection.anchor.getNode();
      const parent = node.getParent();
      setIsLink($isLinkNode(parent) || $isLinkNode(node));

      const rootElement = editor.getRootElement();
      if (!rootElement) return;

      const nativeSelection = getSelection();
      if (!nativeSelection || nativeSelection.rangeCount === 0) return;

      const range = nativeSelection.getRangeAt(0);
      if (!rootElement.contains(range.startContainer)) return;

      refs.setReference({
        getBoundingClientRect: () => range.getBoundingClientRect(),
        getClientRects: () => range.getClientRects(),
      } as Element);
    });
  }, [editor, refs]);

  useEffect(() => {
    return registerEventListener(document, "selectionchange", () => $updatePopup());
  }, [$updatePopup]);

  useEffect(() => {
    return mergeRegister(
      editor.registerUpdateListener(() => $updatePopup()),
      editor.registerCommand(
        SELECTION_CHANGE_COMMAND,
        () => {
          $updatePopup();
          return false;
        },
        COMMAND_PRIORITY_LOW,
      ),
    );
  }, [editor, $updatePopup]);

  useEffect(() => {
    if (isText && update) {
      update();
    }
  }, [isText, update]);

  const handleToggleLink = useCallback(() => {
    if (isLink) {
      editor.dispatchCommand(TOGGLE_LINK_COMMAND, null);
    } else {
      editor.dispatchCommand(TOGGLE_LINK_COMMAND, "https://");
    }
  }, [editor, isLink]);

  if (!isText || isLinkEditMode) return null;

  return createPortal(
    <div
      ref={refs.setFloating}
      className="fixed z-[100] flex items-center gap-0.5 rounded-md border bg-popover p-1 shadow-md"
      style={floatingStyles}
      role="toolbar"
      aria-label="Floating text format toolbar"
      onPointerDown={(e) => e.stopPropagation()}
    >
      <FloatingToolbarButton
        icon={Bold}
        label="Bold"
        isActive={isBold}
        onClick={() => editor.dispatchCommand(FORMAT_TEXT_COMMAND, "bold")}
      />
      <FloatingToolbarButton
        icon={Italic}
        label="Italic"
        isActive={isItalic}
        onClick={() => editor.dispatchCommand(FORMAT_TEXT_COMMAND, "italic")}
      />
      <FloatingToolbarButton
        icon={Underline}
        label="Underline"
        isActive={isUnderline}
        onClick={() => editor.dispatchCommand(FORMAT_TEXT_COMMAND, "underline")}
      />
      <FloatingToolbarButton
        icon={Strikethrough}
        label="Strikethrough"
        isActive={isStrikethrough}
        onClick={() => editor.dispatchCommand(FORMAT_TEXT_COMMAND, "strikethrough")}
      />
      <FloatingToolbarButton
        icon={Code}
        label="Code"
        isActive={isCode}
        onClick={() => editor.dispatchCommand(FORMAT_TEXT_COMMAND, "code")}
      />
      <div className="mx-0.5 h-4 w-px bg-border" />
      <FloatingToolbarButton
        icon={Link}
        label="Insert link"
        isActive={isLink}
        onClick={handleToggleLink}
      />
    </div>,
    document.body,
  );
}

export default function FloatingTextFormatPlugin({
  isLinkEditMode = false,
}: {
  isLinkEditMode?: boolean;
} = {}) {
  const [editor] = useLexicalComposerContext();
  return useFloatingTextFormatToolbar(editor, isLinkEditMode);
}
