import { useCallback, useEffect, useRef, useState } from "react";
import { useLexicalComposerContext } from "@lexical/react/LexicalComposerContext";
import { autoUpdate, flip, inline, offset, shift, useFloating } from "@floating-ui/react";
import {
  $getSelection,
  $isRangeSelection,
  $findMatchingParent,
  getDOMSelection,
  getDOMSelectionPoints,
  type LexicalEditor,
  registerEventListener,
  SELECTION_CHANGE_COMMAND,
  KEY_ESCAPE_COMMAND,
  COMMAND_PRIORITY_HIGH,
  COMMAND_PRIORITY_LOW,
} from "lexical";
import { mergeRegister } from "@lexical/utils";
import { $isLinkNode, TOGGLE_LINK_COMMAND, type LinkNode } from "@lexical/link";
import { ExternalLink, Pencil, Trash2, Check, X, LinkIcon } from "lucide-react";
import { createPortal } from "react-dom";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";

function sanitizeUrl(url: string): string {
  const trimmed = url.trim();
  if (!trimmed) return "";
  if (/^https?:\/\//i.test(trimmed)) return trimmed;
  return `https://${trimmed}`;
}

function $getSelectedLinkNode(
  selection: ReturnType<typeof $getSelection> & object,
): LinkNode | null {
  if (!$isRangeSelection(selection)) return null;

  const node = selection.anchor.getNode();

  if ($isLinkNode(node)) return node;

  const linkParent = $findMatchingParent(node, (n) => $isLinkNode(n));
  if ($isLinkNode(linkParent)) return linkParent;

  if (selection.isCollapsed()) {
    const anchor = selection.anchor;
    if (anchor.type === "text") {
      const anchorNode = anchor.getNode();
      if (anchor.offset === anchorNode.getTextContentSize()) {
        const nextSibling = anchorNode.getNextSibling();
        if ($isLinkNode(nextSibling)) return nextSibling;
      }
    }
  }

  return null;
}

function FloatingLinkEditorInner({ editor }: { editor: LexicalEditor }) {
  const editorRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);
  const [isLink, setIsLink] = useState(false);
  const [isEditMode, setIsEditMode] = useState(false);
  const [linkUrl, setLinkUrl] = useState("");
  const [editedUrl, setEditedUrl] = useState("https://");

  const { refs, floatingStyles } = useFloating({
    middleware: [
      inline(),
      offset(10),
      flip({
        padding: 10,
      }),
      shift({
        crossAxis: true,
        mainAxis: true,
        padding: 10,
      }),
    ],
    placement: "bottom-start",
    strategy: "fixed",
    whileElementsMounted: (...args) => autoUpdate(...args, { ancestorScroll: true }),
  });

  const $updateLinkEditor = useCallback(() => {
    const selection = $getSelection();
    if ($isRangeSelection(selection)) {
      const linkNode = $getSelectedLinkNode(selection);
      if (linkNode) {
        setLinkUrl(linkNode.getURL());
        setIsLink(true);
      } else {
        setLinkUrl("");
        setIsLink(false);
        setIsEditMode(false);
      }
    } else {
      setIsLink(false);
      setIsEditMode(false);
      setLinkUrl("");
    }

    // eslint-disable-next-line unicorn/prefer-global-this -- globalThis can't be cast to Window
    const win = (editor.getRootElement()?.ownerDocument.defaultView ?? window) as Window;
    const nativeSelection = getDOMSelection(win);
    const rootElement = editor.getRootElement();
    const selectionPoints =
      nativeSelection !== null && rootElement !== null
        ? getDOMSelectionPoints(nativeSelection, rootElement)
        : null;

    if (
      rootElement &&
      selectionPoints &&
      nativeSelection !== null &&
      selection !== null &&
      $isRangeSelection(selection) &&
      nativeSelection.rangeCount > 0 &&
      rootElement.contains(selectionPoints.anchorNode)
    ) {
      const linkNode = $getSelectedLinkNode(selection);
      if (linkNode) {
        const linkElement = editor.getElementByKey(linkNode.getKey());
        if (linkElement) {
          refs.setPositionReference(linkElement);
          return;
        }
      }
    }

    if (nativeSelection && nativeSelection.rangeCount > 0) {
      const range = nativeSelection.getRangeAt(0);
      if (rootElement && rootElement.contains(range.startContainer)) {
        refs.setPositionReference({
          getBoundingClientRect: () => range.getBoundingClientRect(),
          getClientRects: () => range.getClientRects(),
        } as Element);
      }
    }
  }, [editor, refs]);

  useEffect(() => {
    return mergeRegister(
      editor.registerUpdateListener(({ editorState }) => {
        editorState.read(() => {
          $updateLinkEditor();
        });
      }),
      editor.registerCommand(
        SELECTION_CHANGE_COMMAND,
        () => {
          $updateLinkEditor();
          return false;
        },
        COMMAND_PRIORITY_LOW,
      ),
      editor.registerCommand(
        KEY_ESCAPE_COMMAND,
        () => {
          if (isLink) {
            setIsLink(false);
            setIsEditMode(false);
            return true;
          }
          return false;
        },
        COMMAND_PRIORITY_HIGH,
      ),
    );
  }, [editor, $updateLinkEditor, isLink]);

  useEffect(() => {
    if (!isEditMode || !inputRef.current) return;
    inputRef.current.focus();
    inputRef.current.select();
  }, [isEditMode]);

  useEffect(() => {
    if (!isLink || isEditMode) return;

    const handleBlur = (event: FocusEvent) => {
      if (!editorRef.current) return;
      if (editorRef.current.contains(event.relatedTarget as Element)) return;
      setIsLink(false);
      setIsEditMode(false);
    };
    return registerEventListener(document, "focusout", handleBlur as EventListener);
  }, [isLink, isEditMode]);

  const handleLinkSubmission = useCallback(() => {
    const url = editedUrl.trim();
    if (url) {
      editor.dispatchCommand(TOGGLE_LINK_COMMAND, sanitizeUrl(url));
    } else {
      editor.dispatchCommand(TOGGLE_LINK_COMMAND, null);
    }
    setIsEditMode(false);
  }, [editor, editedUrl]);

  const removeLink = useCallback(() => {
    editor.dispatchCommand(TOGGLE_LINK_COMMAND, null);
    setIsLink(false);
    setIsEditMode(false);
  }, [editor]);

  if (!isLink) return null;

  return createPortal(
    <div
      ref={(el) => {
        editorRef.current = el;
        refs.setFloating(el);
      }}
      className="fixed z-[100] flex items-center gap-1 rounded-md border bg-popover p-1 shadow-md"
      style={floatingStyles}
    >
      {isEditMode ? (
        <div className="flex items-center gap-1">
          <Input
            ref={inputRef}
            value={editedUrl}
            onChange={(e) => setEditedUrl(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === "Enter") {
                e.preventDefault();
                handleLinkSubmission();
              } else if (e.key === "Escape") {
                setIsEditMode(false);
              }
            }}
            placeholder="https://example.com"
            className="h-7 w-[260px] text-xs"
          />
          <Button
            size="sm"
            variant="default"
            className="h-7 w-7 p-0"
            onClick={handleLinkSubmission}
            aria-label="Apply link"
          >
            <Check className="h-3 w-3" />
          </Button>
          <Button
            size="sm"
            variant="ghost"
            className="h-7 w-7 p-0"
            onClick={() => setIsEditMode(false)}
            aria-label="Cancel"
          >
            <X className="h-3 w-3" />
          </Button>
        </div>
      ) : (
        <div className="flex items-center gap-1">
          <a
            href={sanitizeUrl(linkUrl)}
            target="_blank"
            rel="noopener noreferrer"
            className="flex items-center gap-1 rounded-sm px-2 py-1 text-xs text-primary underline hover:bg-accent"
          >
            <LinkIcon className="h-3 w-3 shrink-0" />
            {linkUrl}
            <ExternalLink className="h-2.5 w-2.5 shrink-0 opacity-50" />
          </a>
          <Button
            size="sm"
            variant="ghost"
            className="h-7 w-7 p-0"
            onClick={() => {
              setEditedUrl(linkUrl);
              setIsEditMode(true);
            }}
            aria-label="Edit link"
          >
            <Pencil className="h-3 w-3" />
          </Button>
          <Button
            size="sm"
            variant="ghost"
            className="h-7 w-7 p-0 text-destructive"
            onClick={removeLink}
            aria-label="Remove link"
          >
            <Trash2 className="h-3 w-3" />
          </Button>
        </div>
      )}
    </div>,
    document.body,
  );
}

export function FloatingLinkEditor() {
  const [editor] = useLexicalComposerContext();
  return <FloatingLinkEditorInner editor={editor} />;
}
