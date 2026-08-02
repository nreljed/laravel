import { useCallback, useEffect, useRef, useState, type RefObject } from "react";
import { useLexicalComposerContext } from "@lexical/react/LexicalComposerContext";
import { $getNodeByKey, $getRoot, type NodeKey } from "lexical";
import { createPortal } from "react-dom";
import { Plus, X, GripVertical, MonitorSmartphone, Smartphone, ScreenShare } from "lucide-react";
import {
  draggable,
  dropTargetForElements,
} from "@atlaskit/pragmatic-drag-and-drop/element/adapter";
import type { ElementDragPayload } from "@atlaskit/pragmatic-drag-and-drop/element/adapter";
import { $isLayoutContainerNode } from "./LayoutContainerNode";
import type { ResponsiveMode } from "./LayoutContainerNode";
import { $isLayoutItemNode } from "./LayoutItemNode";
import {
  $addColumnToLayout,
  $removeColumnFromLayout,
  $moveLayoutColumn,
  SET_LAYOUT_RESPONSIVE_MODE_COMMAND,
} from "./ColumnsPlugin";
import { useContainerPosition } from "../hooks/use-container-position";
import { DropIndicator } from "../shared/drop-indicator";
import { cn } from "@/lib/utils";

type ColumnDragData = {
  columnIndex: number;
  layoutKey: string;
  type: "layout-column";
};

function isLayoutColumnDrag(
  source: ElementDragPayload,
  layoutKey: string,
): source is ElementDragPayload & { data: ColumnDragData } {
  const data = (source?.data as ColumnDragData | undefined) ?? undefined;
  return data?.type === "layout-column" && data.layoutKey === layoutKey;
}

function getLayoutInfoFromDOM(
  editor: ReturnType<typeof useLexicalComposerContext>[0],
): { layoutKey: NodeKey; itemKey: NodeKey; columnCount: number; columnIndex: number } | null {
  const selection = getSelection();
  if (!selection || selection.rangeCount === 0) {
    return null;
  }
  let domNode: HTMLElement | null =
    selection.anchorNode instanceof HTMLElement
      ? selection.anchorNode
      : (selection.anchorNode?.parentElement ?? null);
  let itemDom: HTMLElement | null = null;
  let containerDom: HTMLElement | null = null;
  while (domNode) {
    if (domNode.getAttribute?.("data-lexical-layout-item") === "true") {
      itemDom = domNode;
    }
    if (!containerDom && domNode.getAttribute?.("data-lexical-layout-container") === "true") {
      containerDom = domNode;
    }
    domNode = domNode.parentElement;
  }
  if (!containerDom || !itemDom) {
    return null;
  }
  const itemIndex = [...containerDom.querySelectorAll('[data-lexical-layout-item="true"]')].indexOf(
    itemDom,
  );
  let result: {
    layoutKey: NodeKey;
    itemKey: NodeKey;
    columnCount: number;
    columnIndex: number;
  } | null = null;
  editor.getEditorState().read(() => {
    const root = $getRoot();
    const walk = (node: import("lexical").LexicalNode) => {
      if (result) {
        return;
      }
      if ($isLayoutContainerNode(node)) {
        const dom = editor.getElementByKey(node.getKey());
        if (dom === containerDom) {
          const layoutItems = node.getChildren().filter($isLayoutItemNode);
          const columnCount = layoutItems.length;
          const matchedItem = itemIndex !== -1 ? (layoutItems[itemIndex] ?? null) : null;
          if (matchedItem) {
            result = {
              layoutKey: node.getKey(),
              itemKey: matchedItem.getKey(),
              columnCount,
              columnIndex: itemIndex,
            };
          }
          return;
        }
        return;
      }
      if ("getChildren" in node && typeof node.getChildren === "function") {
        for (const child of node.getChildren()) {
          walk(child);
          if (result) {
            return;
          }
        }
      }
    };
    for (const child of root.getChildren()) {
      walk(child);
      if (result) {
        return;
      }
    }
  });
  return result;
}

function getScrollContainer(
  editor: ReturnType<typeof useLexicalComposerContext>[0],
): HTMLElement | null {
  const rootElement = editor.getRootElement();
  if (!rootElement) {
    return null;
  }
  return rootElement.closest<HTMLElement>(".overflow-auto") ?? rootElement.parentElement;
}

type DropState = {
  left: number;
  top: number;
  height: number;
  edge: "left" | "right";
};

function LayoutControls({
  editor,
  layoutInfo,
  scrollContainerRef,
}: {
  editor: ReturnType<typeof useLexicalComposerContext>[0];
  layoutInfo: { layoutKey: NodeKey; itemKey: NodeKey; columnCount: number; columnIndex: number };
  scrollContainerRef: RefObject<HTMLElement | null>;
}) {
  const { ref: groupRef, positionRelative: positionGroup } =
    useContainerPosition(scrollContainerRef);
  const addBtnRef = useRef<HTMLButtonElement | null>(null);
  const removeBtnRef = useRef<HTMLButtonElement | null>(null);
  const dragHandleRef = useRef<HTMLButtonElement | null>(null);
  const [ready, setReady] = useState(false);
  const [dropState, setDropState] = useState<DropState | null>(null);
  const [responsiveMode, setResponsiveMode] = useState<ResponsiveMode>("full");
  const dropCleanupRef = useRef<(() => void)[]>([]);

  useEffect(() => {
    editor.getEditorState().read(() => {
      const container = $getNodeByKey(layoutInfo.layoutKey);
      if ($isLayoutContainerNode(container)) {
        setResponsiveMode(container.getResponsiveMode());
      }
    });
  }, [editor, layoutInfo.layoutKey]);

  const computePositions = useCallback(() => {
    const itemDom = editor.getElementByKey(layoutInfo.itemKey);
    const layoutDom = editor.getElementByKey(layoutInfo.layoutKey);

    if (itemDom) {
      const rect = itemDom.getBoundingClientRect();
      positionGroup(rect, { anchor: "top-center" });
    }

    setReady(!!layoutDom && !!itemDom);
  }, [editor, layoutInfo.layoutKey, layoutInfo.itemKey, positionGroup]);

  useEffect(() => {
    const raf = requestAnimationFrame(() => {
      computePositions();
    });
    return () => cancelAnimationFrame(raf);
  }, [computePositions]);

  useEffect(() => {
    if (!ready) return;
    const raf = requestAnimationFrame(() => {
      computePositions();
    });
    return () => cancelAnimationFrame(raf);
  }, [ready, computePositions]);

  useEffect(() => {
    const container = scrollContainerRef.current;
    if (!container) return;
    const onScroll = () => computePositions();
    container.addEventListener("scroll", onScroll, { passive: true });
    return () => container.removeEventListener("scroll", onScroll);
  }, [scrollContainerRef, computePositions]);

  // Draggable setup
  useEffect(() => {
    const handle = dragHandleRef.current;
    const layoutDom = editor.getElementByKey(layoutInfo.layoutKey);
    if (!handle || !layoutDom) return;

    return draggable({
      element: handle,
      getInitialData: () => ({
        columnIndex: layoutInfo.columnIndex,
        layoutKey: layoutInfo.layoutKey,
        type: "layout-column" as const,
      }),
      onDrop: () => setDropState(null),
    });
  }, [editor, layoutInfo.layoutKey, layoutInfo.columnIndex, ready]);

  // Drop targets setup
  useEffect(() => {
    for (const fn of dropCleanupRef.current) {
      fn();
    }
    dropCleanupRef.current = [];

    const layoutDom = editor.getElementByKey(layoutInfo.layoutKey);
    if (!layoutDom) return;

    const items = layoutDom.querySelectorAll<HTMLElement>('[data-lexical-layout-item="true"]');
    const container = scrollContainerRef.current;

    const registerDropTarget = (itemEl: HTMLElement, colIndex: number) =>
      dropTargetForElements({
        canDrop: ({ source }) => isLayoutColumnDrag(source, layoutInfo.layoutKey),
        element: itemEl,
        onDrag: ({ location, source }) => {
          if (!container || !isLayoutColumnDrag(source, layoutInfo.layoutKey)) return;
          const containerRect = container.getBoundingClientRect();
          const rect = itemEl.getBoundingClientRect();
          const isRightHalf = location.current.input.clientX > rect.left + rect.width / 2;
          const indicatorLeft =
            (isRightHalf ? rect.right : rect.left) - containerRect.left + container.scrollLeft;
          setDropState({
            left: indicatorLeft,
            top: rect.top - containerRect.top + container.scrollTop,
            height: rect.height,
            edge: isRightHalf ? "right" : "left",
          });
        },
        onDragEnter: ({ location, source }) => {
          if (!container || !isLayoutColumnDrag(source, layoutInfo.layoutKey)) return;
          const containerRect = container.getBoundingClientRect();
          const rect = itemEl.getBoundingClientRect();
          const isRightHalf = location.current.input.clientX > rect.left + rect.width / 2;
          const indicatorLeft =
            (isRightHalf ? rect.right : rect.left) - containerRect.left + container.scrollLeft;
          setDropState({
            left: indicatorLeft,
            top: rect.top - containerRect.top + container.scrollTop,
            height: rect.height,
            edge: isRightHalf ? "right" : "left",
          });
        },
        onDragLeave: () => setDropState(null),
        onDrop: ({ location, source }) => {
          setDropState(null);
          const data = (source?.data ?? {}) as ColumnDragData;
          if (data.columnIndex == null || !isLayoutColumnDrag(source, layoutInfo.layoutKey)) return;

          const rect = itemEl.getBoundingClientRect();
          const isRightHalf = location.current.input.clientX > rect.left + rect.width / 2;
          const dropIndex = isRightHalf ? colIndex + 1 : colIndex;

          editor.update(() => {
            const container = $getNodeByKey(layoutInfo.layoutKey);
            if (!$isLayoutContainerNode(container)) return;
            const children = container.getChildren().filter($isLayoutItemNode);
            const startIndex = data.columnIndex;
            if (startIndex < 0 || startIndex >= children.length) return;
            const finishIndex = dropIndex > startIndex ? dropIndex - 1 : dropIndex;
            $moveLayoutColumn(container, startIndex, finishIndex);
          });
        },
      });

    dropCleanupRef.current = [...items].map((item, i) => registerDropTarget(item, i));

    return () => {
      for (const fn of dropCleanupRef.current) {
        fn();
      }
      dropCleanupRef.current = [];
      setDropState(null);
    };
  }, [editor, layoutInfo.layoutKey, scrollContainerRef, ready]);

  const handleAdd = useCallback(() => {
    editor.update(() => {
      const container = $getNodeByKey(layoutInfo.layoutKey);
      if ($isLayoutContainerNode(container)) {
        $addColumnToLayout(container);
      }
    });
  }, [editor, layoutInfo.layoutKey]);

  const handleRemove = useCallback(() => {
    editor.update(() => {
      const container = $getNodeByKey(layoutInfo.layoutKey);
      const item = $getNodeByKey(layoutInfo.itemKey);
      if ($isLayoutContainerNode(container) && $isLayoutItemNode(item)) {
        $removeColumnFromLayout(container, item);
      }
    });
  }, [editor, layoutInfo.layoutKey, layoutInfo.itemKey]);

  const handleCycleResponsive = useCallback(() => {
    const order: ResponsiveMode[] = ["full", "mobile-only", "none"];
    const nextIndex = (order.indexOf(responsiveMode) + 1) % order.length;
    const next = order[nextIndex] ?? "full";
    editor.dispatchCommand(SET_LAYOUT_RESPONSIVE_MODE_COMMAND, {
      nodeKey: layoutInfo.layoutKey,
      mode: next,
    });
    setResponsiveMode(next);
  }, [editor, layoutInfo.layoutKey, responsiveMode]);

  if (!ready) {
    return null;
  }

  return (
    <>
      <div ref={groupRef} className="absolute z-50 flex items-center gap-0.5">
        <button
          ref={dragHandleRef}
          type="button"
          className="inline-flex h-[18px] w-[18px] cursor-grab items-center justify-center rounded-sm border border-border bg-background shadow-xs transition-colors hover:bg-accent active:cursor-grabbing"
          aria-label="Drag to reorder column"
          onPointerDown={(e) => e.stopPropagation()}
        >
          <GripVertical className="h-3 w-3 text-muted-foreground" />
        </button>
        <button
          ref={addBtnRef}
          type="button"
          className="inline-flex h-[18px] w-[18px] items-center justify-center rounded-sm border border-border bg-background shadow-xs transition-colors hover:bg-accent"
          aria-label="Add column"
          onPointerDown={(e) => e.stopPropagation()}
          onClick={(e) => {
            e.stopPropagation();
            handleAdd();
          }}
        >
          <Plus className="h-3 w-3 text-muted-foreground" />
        </button>
        <button
          ref={removeBtnRef}
          type="button"
          className="inline-flex h-[18px] w-[18px] items-center justify-center rounded-sm border border-border bg-background shadow-xs transition-colors hover:bg-destructive hover:text-destructive-foreground"
          aria-label="Remove column"
          onPointerDown={(e) => e.stopPropagation()}
          onClick={(e) => {
            e.stopPropagation();
            handleRemove();
          }}
        >
          <X className="h-3 w-3 text-muted-foreground" />
        </button>
        <button
          type="button"
          className={cn(
            "inline-flex h-[18px] w-[18px] items-center justify-center rounded-sm border bg-background shadow-xs transition-colors",
            responsiveMode !== "none"
              ? "border-primary text-primary"
              : "border-border text-muted-foreground hover:bg-accent",
          )}
          aria-label={`Responsive: ${responsiveMode}`}
          title={
            responsiveMode === "full"
              ? "Stack on tablet + mobile"
              : responsiveMode === "mobile-only"
                ? "Stack on mobile only"
                : "Never stack"
          }
          onPointerDown={(e) => e.stopPropagation()}
          onClick={(e) => {
            e.stopPropagation();
            handleCycleResponsive();
          }}
        >
          {responsiveMode === "full" ? (
            <MonitorSmartphone className="h-3 w-3" />
          ) : responsiveMode === "mobile-only" ? (
            <Smartphone className="h-3 w-3" />
          ) : (
            <ScreenShare className="h-3 w-3" />
          )}
        </button>
      </div>
      {dropState ? (
        <div
          style={{
            height: dropState.height,
            left: dropState.left,
            pointerEvents: "none",
            position: "absolute",
            top: dropState.top,
            width: 2,
            zIndex: 90,
          }}
        >
          <DropIndicator edge={dropState.edge} />
        </div>
      ) : null}
    </>
  );
}

export default function LayoutControlsPlugin() {
  const [editor] = useLexicalComposerContext();
  const [layoutInfo, setLayoutInfo] = useState<{
    layoutKey: NodeKey;
    itemKey: NodeKey;
    columnCount: number;
    columnIndex: number;
  } | null>(null);
  const [scrollContainer, setScrollContainer] = useState<HTMLElement | null>(null);
  const scrollContainerRef = useRef<HTMLElement | null>(null);

  useEffect(() => {
    const container = getScrollContainer(editor);
    if (container) {
      // eslint-disable-next-line react-hooks/set-state-in-effect
      setScrollContainer(container);
      scrollContainerRef.current = container;
    }
  }, [editor]);

  useEffect(() => {
    const onSelectionChange = () => {
      const info = getLayoutInfoFromDOM(editor);
      setLayoutInfo(info);
    };

    document.addEventListener("selectionchange", onSelectionChange, { passive: true });
    return () => {
      document.removeEventListener("selectionchange", onSelectionChange);
    };
  }, [editor]);

  if (!layoutInfo || !scrollContainer) {
    return null;
  }

  return createPortal(
    <LayoutControls
      editor={editor}
      layoutInfo={layoutInfo}
      scrollContainerRef={scrollContainerRef}
    />,
    scrollContainer,
  );
}
