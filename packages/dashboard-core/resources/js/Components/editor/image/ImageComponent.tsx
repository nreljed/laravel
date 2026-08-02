import { useLexicalComposerContext } from "@lexical/react/LexicalComposerContext";
import { useLexicalNodeSelection } from "@lexical/react/useLexicalNodeSelection";
import {
  $getNodeByKey,
  $getSelection,
  $isNodeSelection,
  BLUR_COMMAND,
  CLICK_COMMAND,
  COMMAND_PRIORITY_LOW,
  KEY_ESCAPE_COMMAND,
} from "lexical";
import { useCallback, useEffect, useMemo, useRef, useState, type JSX } from "react";
import { $isImageNode } from "./ImageNode";
import ImageResizer from "./ImageResizer";

interface ImageComponentProps {
  src: string;
  altText: string;
  width: number | "inherit";
  height: number | "inherit";
  maxWidth: number;
  nodeKey: string;
}

export default function ImageComponent({
  src,
  altText,
  width,
  height,
  maxWidth,
  nodeKey,
}: ImageComponentProps): JSX.Element {
  const imageRef = useRef<HTMLImageElement | null>(null);
  const [isSelected, setSelected, clearSelection] = useLexicalNodeSelection(nodeKey);
  const [isResizing, setIsResizing] = useState(false);
  const [editor] = useLexicalComposerContext();
  const [, setLoadError] = useState(false);

  const isFocused = isSelected || isResizing;

  const isInNodeSelection = useMemo(
    () =>
      isSelected &&
      editor.read("latest", () => {
        const selection = $getSelection();
        return $isNodeSelection(selection) ? selection.has(nodeKey) : false;
      }),
    [editor, isSelected, nodeKey],
  );

  const onClick = useCallback(
    (payload: MouseEvent) => {
      if (isResizing) return true;
      const event = payload;
      if (event.target === imageRef.current) {
        if (event.shiftKey) {
          setSelected(!isSelected);
        } else {
          clearSelection();
          setSelected(true);
        }
        return true;
      }
      return false;
    },
    [isResizing, isSelected, setSelected, clearSelection],
  );

  const onEscape = useCallback(
    (_event: KeyboardEvent) => {
      if (isSelected) {
        clearSelection();
        editor.getRootElement()?.focus();
        return true;
      }
      return false;
    },
    [editor, isSelected, clearSelection],
  );

  useEffect(() => {
    const unregisterClick = editor.registerCommand<MouseEvent>(
      CLICK_COMMAND,
      onClick,
      COMMAND_PRIORITY_LOW,
    );
    const unregisterEscape = editor.registerCommand(
      KEY_ESCAPE_COMMAND,
      onEscape,
      COMMAND_PRIORITY_LOW,
    );
    const unregisterBlur = editor.registerCommand(
      BLUR_COMMAND,
      () => {
        clearSelection();
        return false;
      },
      COMMAND_PRIORITY_LOW,
    );
    return () => {
      unregisterClick();
      unregisterEscape();
      unregisterBlur();
    };
  }, [editor, onClick, onEscape, clearSelection]);

  const onResizeEnd = useCallback(
    (nextWidth: number, nextHeight: number) => {
      setTimeout(() => setIsResizing(false), 200);
      editor.update(() => {
        const node = $getNodeByKey(nodeKey);
        if ($isImageNode(node)) {
          node.setWidthAndHeight(nextWidth, nextHeight);
        }
      });
    },
    [editor, nodeKey],
  );

  const draggable = isInNodeSelection && !isResizing;

  return (
    <>
      <div draggable={draggable}>
        <img
          src={src}
          alt={altText}
          ref={imageRef}
          className={`max-w-full rounded-md object-cover transition-all ${
            isResizing
              ? "ring-2 ring-dashed ring-ring ring-offset-2"
              : isFocused
                ? "ring-2 ring-ring ring-offset-2"
                : ""
          } ${isInNodeSelection ? "cursor-grab" : ""}`}
          style={{
            maxWidth: `${maxWidth}%`,
            width: width === "inherit" ? undefined : width,
            height: height === "inherit" ? undefined : height,
          }}
          draggable={false}
          onError={() => setLoadError(true)}
        />
      </div>
      {isFocused && editor.isEditable() && (
        <ImageResizer
          editor={editor}
          imageRef={imageRef}
          maxWidth={maxWidth}
          onResizeStart={() => setIsResizing(true)}
          onResizeEnd={onResizeEnd}
        />
      )}
    </>
  );
}
