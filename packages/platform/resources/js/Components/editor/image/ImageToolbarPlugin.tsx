import { useCallback, useEffect, useRef, useState } from "react";
import { useLexicalComposerContext } from "@lexical/react/LexicalComposerContext";
import {
  $getNodeByKey,
  $nodesOfType,
  type LexicalEditor,
  type NodeKey,
  registerEventListener,
} from "lexical";
import { createPortal } from "react-dom";
import { Pencil, Trash2, Maximize2 } from "lucide-react";
import { $isImageNode, ImageNode } from "./ImageNode";
import { deleteImageFromServer } from "./ImagePlugin";
import { useFloatingPosition } from "../hooks/use-floating-position";

interface ImageToolbarProps {
  editor: LexicalEditor;
  onEdit: (_nodeKey: NodeKey) => void;
}

function ImageToolbar({ editor, onEdit }: ImageToolbarProps) {
  const { ref: toolbarRef, positionAbove } = useFloatingPosition();
  const [imageKey, setImageKey] = useState<NodeKey | null>(null);
  const imageKeysRef = useRef<Set<NodeKey>>(new Set());

  useEffect(() => {
    const populateKeys = () => {
      const keys = new Set<NodeKey>();
      editor.read(() => {
        const imageNodes = $nodesOfType(ImageNode);
        for (const node of imageNodes) {
          if (node.isAttached()) {
            keys.add(node.getKey());
          }
        }
      });
      imageKeysRef.current = keys;
    };
    populateKeys();
    return editor.registerUpdateListener(() => populateKeys());
  }, [editor]);

  const showToolbar = useCallback(
    (key: NodeKey, rect: DOMRect) => {
      setImageKey(key);
      requestAnimationFrame(() => positionAbove(rect));
    },
    [positionAbove],
  );

  const hideToolbar = useCallback(() => {
    setImageKey(null);
  }, []);

  useEffect(() => {
    const rootElement = editor.getRootElement();
    if (!rootElement) return;

    const handleMouseDown = (e: MouseEvent) => {
      const target = e.target as HTMLElement;
      if (!rootElement.contains(target)) return;

      for (const key of imageKeysRef.current) {
        const el = editor.getElementByKey(key);
        if (el && el.contains(target)) {
          e.preventDefault();
          showToolbar(key, el.getBoundingClientRect());
          return;
        }
      }
    };

    rootElement.addEventListener("mousedown", handleMouseDown);
    return () => rootElement.removeEventListener("mousedown", handleMouseDown);
  }, [editor, showToolbar]);

  useEffect(() => {
    const rootElement = editor.getRootElement();
    const handleMouseDown = (e: MouseEvent) => {
      const el = toolbarRef.current;
      if (!el) return;
      const target = e.target as Node;
      if (el.contains(target)) return;
      if (rootElement) {
        for (const key of imageKeysRef.current) {
          const nodeEl = editor.getElementByKey(key);
          if (nodeEl && nodeEl.contains(target)) return;
        }
      }
      hideToolbar();
    };
    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === "Escape") hideToolbar();
    };
    const cleanupMouseDown = registerEventListener(document, "mousedown", handleMouseDown);
    const cleanupKeyDown = registerEventListener(document, "keydown", handleKeyDown);
    return () => {
      cleanupMouseDown();
      cleanupKeyDown();
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [editor, hideToolbar]);

  useEffect(() => {
    if (!imageKey) return;
    const updatePosition = () => {
      const el = editor.getElementByKey(imageKey);
      if (el) positionAbove(el.getBoundingClientRect());
    };
    /* eslint-disable unicorn/prefer-observer-apis */
    window.addEventListener("scroll", updatePosition, { capture: true });
    window.addEventListener("resize", updatePosition);
    /* eslint-enable unicorn/prefer-observer-apis */
    return () => {
      window.removeEventListener("scroll", updatePosition, true);
      window.removeEventListener("resize", updatePosition);
    };
  }, [editor, imageKey, positionAbove]);

  const handleDelete = useCallback(async () => {
    if (imageKey == null) return;
    let imageSrc: string | null = null;
    editor.update(() => {
      const node = $getNodeByKey(imageKey);
      if (node && $isImageNode(node)) {
        imageSrc = node.getSrc();
        node.selectPrevious();
        node.remove();
      }
    });
    if (imageSrc) {
      try {
        await deleteImageFromServer(imageSrc);
      } catch {
        /*
        ignore
        */
      }
    }
    hideToolbar();
  }, [editor, imageKey, hideToolbar]);

  if (imageKey == null) return null;

  return createPortal(
    <div
      ref={toolbarRef}
      className="fixed z-[100] flex items-center gap-0.5 rounded-md border bg-popover p-1 shadow-md"
      role="toolbar"
      aria-label="Image toolbar"
    >
      <button
        type="button"
        title="Edit image"
        aria-label="Edit image"
        onClick={() => {
          onEdit(imageKey);
          hideToolbar();
        }}
        className="flex h-7 w-7 items-center justify-center rounded-sm text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
      >
        <Pencil className="h-4 w-4" />
      </button>
      <button
        type="button"
        title="Delete image"
        aria-label="Delete image"
        onClick={handleDelete}
        className="flex h-7 w-7 items-center justify-center rounded-sm text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive"
      >
        <Trash2 className="h-4 w-4" />
      </button>
      <div className="mx-0.5 h-4 w-px bg-border" />
      <button
        type="button"
        title="Image options"
        aria-label="Image options"
        onClick={() => {
          onEdit(imageKey);
          hideToolbar();
        }}
        className="flex h-7 items-center gap-1.5 rounded-sm px-2 text-xs text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
      >
        <Maximize2 className="h-3.5 w-3.5" />
        Options
      </button>
    </div>,
    document.body,
  );
}

export default function ImageToolbarPlugin({
  onEdit,
}: {
  onEdit: (_nodeKey: NodeKey) => void;
}): React.ReactPortal {
  const [editor] = useLexicalComposerContext();
  return createPortal(<ImageToolbar editor={editor} onEdit={onEdit} />, document.body);
}
