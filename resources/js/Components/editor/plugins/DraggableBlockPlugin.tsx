import { useRef, useState, useCallback, useEffect, useMemo } from "react";
import { useLexicalComposerContext } from "@lexical/react/LexicalComposerContext";
import { DraggableBlockPlugin_EXPERIMENTAL } from "@lexical/react/LexicalDraggableBlockPlugin";
import {
  $getNearestNodeFromDOMNode,
  $getNodeByKey,
  $createParagraphNode,
  $createTextNode,
  $isParagraphNode,
  $isTextNode,
  type NodeKey,
  getComposedEventTarget,
  registerEventListener,
} from "lexical";
import { createPortal } from "react-dom";
import { GripVertical, Plus } from "lucide-react";
import { BlockOption, getBlockOptions } from "../shared/block-options";
import { BlockPickerMenu } from "../shared/block-picker-menu";

const DRAGGABLE_BLOCK_MENU_CLASSNAME = "draggable-block-menu";

function isOnMenu(element: HTMLElement): boolean {
  return !!(
    element.closest(`.${DRAGGABLE_BLOCK_MENU_CLASSNAME}`) || element.closest("[data-block-picker]")
  );
}

type PickerState = {
  insertBefore: boolean;
  targetNodeKey: NodeKey;
};

export default function DraggableBlockPlugin({
  anchorElem = document.body,
}: {
  anchorElem?: HTMLElement;
}): React.JSX.Element {
  const [editor] = useLexicalComposerContext();
  const menuRef = useRef<HTMLDivElement>(null);
  const pickerRef = useRef<HTMLDivElement>(null);
  const searchInputRef = useRef<HTMLInputElement>(null);
  const targetLineRef = useRef<HTMLDivElement>(null);
  const [draggableElement, setDraggableElement] = useState<HTMLElement | null>(null);
  const [pickerState, setPickerState] = useState<PickerState | null>(null);
  const [isPickerOpen, setIsPickerOpen] = useState(false);
  const [queryString, setQueryString] = useState("");
  const [highlightedIndex, setHighlightedIndex] = useState(0);
  const [pickerPosition, setPickerPosition] = useState<{
    left: number;
    top: number;
  } | null>(null);

  const [dialogs, setDialogs] = useState<
    Array<{ id: number; render: (_onClose: () => void) => React.ReactNode }>
  >([]);

  const showModal = useCallback(
    (_title: string, render: (_onClose: () => void) => React.ReactNode) => {
      const id = Date.now();
      setDialogs((prev) => [...prev, { id, render }]);
    },
    [],
  );

  const closeDialog = useCallback((id: number) => {
    setDialogs((prev) => prev.filter((d) => d.id !== id));
  }, []);

  const options = useMemo(() => {
    const baseOptions = getBlockOptions(editor, showModal);
    if (!queryString) {
      return baseOptions;
    }
    const escaped = queryString.replaceAll(/[.*+?^${}()|[\]\\]/g, String.raw`\$&`);
    const regex = new RegExp(escaped, "i");
    return baseOptions.filter(
      (option) =>
        regex.test(option.title) || option.keywords.some((keyword) => regex.test(keyword)),
    );
  }, [editor, queryString, showModal]);

  useEffect(() => {
    if (isPickerOpen && searchInputRef.current) {
      searchInputRef.current.focus();
    }
  }, [isPickerOpen]);

  useEffect(() => {
    if (!isPickerOpen || options.length === 0) {
      return;
    }
    // eslint-disable-next-line react-hooks/set-state-in-effect
    setHighlightedIndex((current) => Math.min(current, Math.max(options.length - 1, 0)));
  }, [isPickerOpen, options.length]);

  useEffect(() => {
    if (!isPickerOpen) {
      return;
    }
    const handleClickOutside = (event: MouseEvent) => {
      const target = getComposedEventTarget(event) as Node | null;
      if (
        (pickerRef.current && pickerRef.current.contains(target)) ||
        (menuRef.current && menuRef.current.contains(target))
      ) {
        return;
      }
      setIsPickerOpen(false);
      setPickerState(null);
    };
    return registerEventListener(document, "mousedown", handleClickOutside);
  }, [isPickerOpen]);

  const selectOption = useCallback(
    (option: BlockOption) => {
      if (!pickerState) {
        setIsPickerOpen(false);
        return;
      }
      setIsPickerOpen(false);
      editor.update(() => {
        const node = $getNodeByKey(pickerState.targetNodeKey);
        if (!node) {
          return;
        }
        const placeholder = $createParagraphNode();
        const textNode = $createTextNode("");
        placeholder.append(textNode);
        if (pickerState.insertBefore) {
          node.insertBefore(placeholder);
        } else {
          node.insertAfter(placeholder);
        }
        textNode.select();
        option.onSelect(queryString);
        if (option.key !== "paragraph") {
          const latestPlaceholder = placeholder.getLatest();
          if ($isParagraphNode(latestPlaceholder)) {
            const onlyChild = latestPlaceholder.getFirstChild();
            if (
              $isTextNode(onlyChild) &&
              onlyChild.getTextContent().length === 0 &&
              latestPlaceholder.getChildrenSize() === 1
            ) {
              latestPlaceholder.remove();
            }
          }
        }
      });
    },
    [editor, pickerState, queryString],
  );

  useEffect(() => {
    if (!isPickerOpen) {
      return;
    }
    const handleKeyDown = (event: KeyboardEvent) => {
      if (!isPickerOpen || options.length === 0) {
        return;
      }
      switch (event.key) {
        case "ArrowDown": {
          event.preventDefault();
          setHighlightedIndex((index) => (index + 1 >= options.length ? 0 : index + 1));

          break;
        }
        case "ArrowUp": {
          event.preventDefault();
          // eslint-disable-next-line unicorn/prefer-minimal-ternary, unicorn/no-subtraction-comparison
          setHighlightedIndex((index) => (index - 1 < 0 ? options.length - 1 : index - 1));

          break;
        }
        case "Enter": {
          event.preventDefault();
          const option = options[highlightedIndex];
          if (option) {
            selectOption(option);
          }

          break;
        }
        case "Escape": {
          event.preventDefault();
          setIsPickerOpen(false);
          setPickerState(null);

          break;
        }
        // No default
      }
    };
    return registerEventListener(globalThis, "keydown", handleKeyDown);
  }, [highlightedIndex, isPickerOpen, options, selectOption]);

  const openComponentPicker = useCallback(
    (e: React.MouseEvent) => {
      if (!draggableElement || !editor) {
        return;
      }

      let targetNodeKey: NodeKey | null = null;
      editor.read(() => {
        const resolvedNode = $getNearestNodeFromDOMNode(draggableElement);
        if (resolvedNode) {
          targetNodeKey = resolvedNode.getKey();
        }
      });

      if (!targetNodeKey) {
        return;
      }

      const insertBefore = e.altKey || e.ctrlKey;
      const rect = menuRef.current?.getBoundingClientRect();
      setPickerPosition(
        rect
          ? {
              left:
                rect.right + 8 > window.innerWidth
                  ? rect.left + window.scrollX - 208
                  : rect.left + rect.width + window.scrollX + 8,
              top: rect.top + window.scrollY,
            }
          : null,
      );
      setPickerState({ insertBefore, targetNodeKey });
      setQueryString("");
      setHighlightedIndex(0);
      setIsPickerOpen(true);
    },
    [draggableElement, editor],
  );

  return (
    <>
      {dialogs.map((dialog) => (
        <div key={dialog.id}>{dialog.render(() => closeDialog(dialog.id))}</div>
      ))}
      {isPickerOpen && pickerPosition
        ? createPortal(
            <div
              ref={pickerRef}
              data-block-picker
              className="z-50 max-h-[300px] min-w-[200px] overflow-auto rounded-lg border bg-popover p-1 text-popover-foreground shadow-md"
              style={{
                position: "absolute",
                left: pickerPosition.left,
                top: pickerPosition.top,
              }}
            >
              <input
                ref={searchInputRef}
                className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-hidden placeholder:text-muted-foreground"
                placeholder="Filter blocks..."
                value={queryString}
                onChange={(event) => setQueryString(event.target.value)}
              />
              <div className="mt-1">
                <BlockPickerMenu
                  options={options}
                  highlightedIndex={highlightedIndex}
                  onHighlight={setHighlightedIndex}
                  onSelect={(option) => selectOption(option)}
                />
              </div>
            </div>,
            document.body,
          )
        : null}
      <DraggableBlockPlugin_EXPERIMENTAL
        anchorElem={anchorElem}
        menuRef={menuRef}
        targetLineRef={targetLineRef}
        menuComponent={
          <div
            ref={menuRef}
            className="draggable-block-menu absolute left-0 top-0 flex cursor-grab items-center rounded border bg-background p-0.5 opacity-0 shadow-sm transition-opacity will-change-[transform,opacity] hover:bg-accent active:cursor-grabbing"
          >
            <button
              type="button"
              title="Click to add below"
              className="flex h-5 w-5 items-center justify-center rounded text-muted-foreground hover:text-foreground"
              onClick={openComponentPicker}
              onPointerDown={(e) => e.stopPropagation()}
            >
              <Plus className="h-3.5 w-3.5" />
            </button>
            <GripVertical className="h-3.5 w-3.5 text-muted-foreground" />
          </div>
        }
        targetLineComponent={
          <div
            ref={targetLineRef}
            className="pointer-events-none absolute left-0 top-0 h-[2px] bg-primary opacity-0 transition-opacity will-change-[transform,opacity]"
          />
        }
        isOnMenu={isOnMenu}
        onElementChanged={setDraggableElement}
      />
    </>
  );
}
