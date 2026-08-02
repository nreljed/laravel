import { useCallback, useEffect, useMemo, useRef, useState, type RefObject } from "react";
import { useLexicalComposerContext } from "@lexical/react/LexicalComposerContext";
import { useLexicalEditable } from "@lexical/react/useLexicalEditable";
import {
  $computeTableMapSkipCellCheck,
  $getTableNodeFromLexicalNodeOrThrow,
  $getTableRowIndexFromTableCellNode,
  $isTableCellNode,
  $isTableRowNode,
  getDOMCellFromTarget,
  getTableElement,
  type TableCellNode,
  type TableDOMCell,
  type TableMapType,
  TableNode,
} from "@lexical/table";
import { calculateZoomLevel, mergeRegister } from "@lexical/utils";
import {
  $getNearestNodeFromDOMNode,
  isHTMLElement,
  type LexicalEditor,
  type NodeKey,
  registerEventListener,
  registerEventListeners,
  SKIP_SCROLL_INTO_VIEW_TAG,
} from "lexical";
import { createPortal } from "react-dom";

type PointerPosition = {
  x: number;
  y: number;
};

type PointerDraggingDirection = "right" | "bottom";

const MIN_ROW_HEIGHT = 33;
const MIN_COLUMN_WIDTH = 92;
const ACTIVE_RESIZER_COLOR = "#76b6ff";

function TableCellResizer({
  editor,
  scrollContainerRef,
}: {
  editor: LexicalEditor;
  scrollContainerRef: RefObject<HTMLDivElement | null>;
}) {
  const targetRef = useRef<HTMLElement | null>(null);
  const resizerRef = useRef<HTMLDivElement | null>(null);
  const tableRectRef = useRef<DOMRect | null>(null);
  const [hasTable, setHasTable] = useState(false);

  const pointerStartPosRef = useRef<PointerPosition | null>(null);
  const [pointerCurrentPos, updatePointerCurrentPos] = useState<PointerPosition | null>(null);
  const pointerCurrentPosRef = useRef<PointerPosition | null>(null);

  const [activeCell, updateActiveCell] = useState<TableDOMCell | null>(null);
  const activeCellRef = useRef<TableDOMCell | null>(null);
  const [draggingDirection, updateDraggingDirection] = useState<PointerDraggingDirection | null>(
    null,
  );
  const [hoveredDirection, updateHoveredDirection] = useState<PointerDraggingDirection | null>(
    null,
  );

  const pointerUpHandlerRef = useRef<((_event: PointerEvent) => void) | null>(null);

  const [scrollOffset, setScrollOffset] = useState({ x: 0, y: 0 });

  // Keep refs in sync with state so pointerUpHandler always reads current values
  useEffect(() => {
    activeCellRef.current = activeCell;
  }, [activeCell]);

  useEffect(() => {
    pointerCurrentPosRef.current = pointerCurrentPos;
  }, [pointerCurrentPos]);

  useEffect(() => {
    return () => {
      if (!pointerUpHandlerRef.current) {
        return;
      }

      document.removeEventListener("pointerup", pointerUpHandlerRef.current);
      pointerUpHandlerRef.current = null;
    };
  }, []);

  useEffect(() => {
    const container = scrollContainerRef.current;
    if (!container) {
      return;
    }

    const handleScroll = () => {
      setScrollOffset({ x: container.scrollLeft, y: container.scrollTop });
    };

    container.addEventListener("scroll", handleScroll, { passive: true });
    return () => {
      container.removeEventListener("scroll", handleScroll);
    };
  }, [scrollContainerRef]);

  const resetState = useCallback(() => {
    updateActiveCell(null);
    targetRef.current = null;
    updateDraggingDirection(null);
    updateHoveredDirection(null);
    pointerStartPosRef.current = null;
    tableRectRef.current = null;
  }, []);

  useEffect(() => {
    const tableKeys = new Set<NodeKey>();
    return mergeRegister(
      editor.registerMutationListener(TableNode, (nodeMutations) => {
        for (const [nodeKey, mutation] of nodeMutations) {
          if (mutation === "destroyed") {
            tableKeys.delete(nodeKey);
          } else {
            tableKeys.add(nodeKey);
          }
        }
        setHasTable(tableKeys.size > 0);
      }),
      editor.registerNodeTransform(TableNode, (tableNode) => {
        if (tableNode.getColWidths()) {
          return tableNode;
        }

        const numColumns = tableNode.getColumnCount();
        const columnWidth = MIN_COLUMN_WIDTH;

        tableNode.setColWidths(Array.from({ length: numColumns }, () => columnWidth));
        return tableNode;
      }),
    );
  }, [editor]);

  useEffect(() => {
    if (!hasTable) {
      return;
    }

    const onPointerMove = (event: PointerEvent) => {
      const target = event.target;
      if (!isHTMLElement(target)) {
        return;
      }

      if (draggingDirection) {
        event.preventDefault();
        event.stopPropagation();
        updatePointerCurrentPos({
          x: event.clientX,
          y: event.clientY,
        });
        return;
      }
      if (resizerRef.current && resizerRef.current.contains(target)) {
        return;
      }

      if (targetRef.current !== target) {
        targetRef.current = target;
        const cell = getDOMCellFromTarget(target);

        if (cell && activeCell !== cell) {
          editor.read("latest", () => {
            const tableCellNode = $getNearestNodeFromDOMNode(cell.elem);
            if (!tableCellNode) {
              return;
            }

            const tableNode = $getTableNodeFromLexicalNodeOrThrow(tableCellNode);
            const tableElement = getTableElement(
              tableNode,
              editor.getElementByKey(tableNode.getKey()),
            );

            if (!tableElement) {
              return;
            }

            targetRef.current = target;
            tableRectRef.current = tableElement.getBoundingClientRect();
            updateActiveCell(cell);
          });
        } else if (cell == null) {
          resetState();
        }
      }
    };

    const onPointerDown = (event: PointerEvent) => {
      const isTouchEvent = event.pointerType === "touch";
      if (isTouchEvent) {
        onPointerMove(event);
      }
    };

    const resizerContainer = resizerRef.current;
    return mergeRegister(
      editor.registerRootListener((rootElement) => {
        if (rootElement) {
          return registerEventListeners(rootElement, {
            pointerdown: onPointerDown,
            pointermove: onPointerMove,
          });
        }
      }),
      resizerContainer
        ? registerEventListener(resizerContainer, "pointermove", onPointerMove, { capture: true })
        : () => {},
    );
  }, [activeCell, draggingDirection, editor, resetState, hasTable]);

  const isHeightChanging = (direction: PointerDraggingDirection) => {
    return direction === "bottom";
  };

  const updateRowHeight = useCallback(
    (heightChange: number) => {
      if (!activeCell) {
        throw new Error("Expected activeCell to be set when updating row height");
      }

      if (!activeCell.elem.isConnected) {
        return;
      }

      editor.update(
        () => {
          const tableCellNode = $getNearestNodeFromDOMNode(activeCell.elem);
          if (!$isTableCellNode(tableCellNode)) {
            return;
          }

          const tableNode = $getTableNodeFromLexicalNodeOrThrow(tableCellNode);
          const baseRowIndex = $getTableRowIndexFromTableCellNode(tableCellNode);
          const tableRows = tableNode.getChildren();

          const isFullRowMerge = tableCellNode.getColSpan() === tableNode.getColumnCount();

          const tableRowIndex = isFullRowMerge
            ? baseRowIndex
            : baseRowIndex + tableCellNode.getRowSpan() - 1;

          if (tableRowIndex >= tableRows.length || tableRowIndex < 0) {
            return;
          }

          const tableRow = tableRows[tableRowIndex];

          if (!$isTableRowNode(tableRow)) {
            return;
          }

          let height = tableRow.getHeight();
          if (height === undefined) {
            const rowCells = tableRow.getChildren().filter($isTableCellNode);
            height = Math.min(
              // eslint-disable-next-line react-hooks/immutability
              ...rowCells.map((cell) => getCellNodeHeight(cell, editor) ?? Infinity),
            );
          }

          const newHeight = Math.max(height + heightChange, MIN_ROW_HEIGHT);
          tableRow.setHeight(newHeight);
        },
        { tag: SKIP_SCROLL_INTO_VIEW_TAG },
      );
    },
    [activeCell, editor],
  );

  const getCellNodeHeight = (
    cell: TableCellNode,
    activeEditor: LexicalEditor,
  ): number | undefined => {
    const domCellNode = activeEditor.getElementByKey(cell.getKey());
    return domCellNode?.clientHeight;
  };

  const getCellColumnIndex = (tableCellNode: TableCellNode, tableMap: TableMapType) => {
    for (const tableRow of tableMap) {
      if (!tableRow) continue;
      for (const [column, cell] of tableRow.entries()) {
        if (cell?.cell === tableCellNode) {
          return column;
        }
      }
    }
  };

  const updateColumnWidth = useCallback(
    (widthChange: number) => {
      if (!activeCell) {
        throw new Error("Expected activeCell to be set when updating column width");
      }

      if (!activeCell.elem.isConnected) {
        return;
      }

      editor.update(
        () => {
          const tableCellNode = $getNearestNodeFromDOMNode(activeCell.elem);
          if (!$isTableCellNode(tableCellNode)) {
            return;
          }

          const tableNode = $getTableNodeFromLexicalNodeOrThrow(tableCellNode);
          const [tableMap] = $computeTableMapSkipCellCheck(tableNode, null, null);
          const columnIndex = getCellColumnIndex(tableCellNode, tableMap);
          if (columnIndex === undefined) {
            return;
          }

          const colWidths = tableNode.getColWidths();
          if (!colWidths) {
            return;
          }
          const width = colWidths[columnIndex];
          if (width === undefined) {
            return;
          }
          const newColWidths = [...colWidths];
          const newWidth = Math.max(width + widthChange, MIN_COLUMN_WIDTH);
          newColWidths[columnIndex] = newWidth;
          tableNode.setColWidths(newColWidths);
        },
        { tag: SKIP_SCROLL_INTO_VIEW_TAG },
      );
    },
    [activeCell, editor],
  );

  const toggleResize = useCallback(
    (direction: PointerDraggingDirection): React.PointerEventHandler<HTMLDivElement> =>
      (event) => {
        event.preventDefault();
        event.stopPropagation();

        if (!activeCell) {
          return;
        }

        pointerStartPosRef.current = {
          x: event.clientX,
          y: event.clientY,
        };
        updatePointerCurrentPos(pointerStartPosRef.current);
        updateDraggingDirection(direction);

        if (pointerUpHandlerRef.current) {
          document.removeEventListener("pointerup", pointerUpHandlerRef.current);
        }
        const handler = (e: PointerEvent) => {
          e.preventDefault();
          e.stopPropagation();

          const currentCell = activeCellRef.current;
          if (!currentCell) {
            return;
          }

          if (pointerStartPosRef.current) {
            const { x, y } = pointerStartPosRef.current;

            const zoom = calculateZoomLevel(e.target as Element);

            if (isHeightChanging(direction)) {
              const heightChange = (e.clientY - y) / zoom;
              updateRowHeight(heightChange);
            } else {
              const widthChange = (e.clientX - x) / zoom;
              updateColumnWidth(widthChange);
            }

            resetState();
          }
          document.removeEventListener("pointerup", handler);
          pointerUpHandlerRef.current = null;
        };
        pointerUpHandlerRef.current = handler;
        document.addEventListener("pointerup", handler);
      },
    [activeCell, resetState, updateColumnWidth, updateRowHeight],
  );

  const resizerStyles = useMemo(() => {
    /* eslint-disable react-hooks/refs, react-hooks/exhaustive-deps */
    if (activeCell) {
      if (!activeCell.elem.isConnected) {
        return { bottom: null, left: null, right: null, top: null };
      }

      const containerEl = scrollContainerRef.current;
      const containerRect = containerEl?.getBoundingClientRect();
      if (!containerRect) {
        return { bottom: null, left: null, right: null, top: null };
      }

      const { height, width, top, left } = activeCell.elem.getBoundingClientRect();
      const zoom = calculateZoomLevel(activeCell.elem);
      const zoneWidth = 16;

      // Position relative to the scroll origin (not the visible area) because the
      // resizer is portaled inside the scrollable container and absolute positioning
      // is relative to the container's content area, not its viewport edge.
      const scrollLeft = containerEl?.scrollLeft ?? 0;
      const scrollTop = containerEl?.scrollTop ?? 0;
      const relLeft = left - containerRect.left + scrollLeft;
      const relTop = top - containerRect.top + scrollTop;

      const styles: Record<string, React.CSSProperties> = {
        bottom: {
          backgroundColor: "transparent",
          cursor: "row-resize",
          height: `${zoneWidth}px`,
          left: `${relLeft}px`,
          top: `${relTop + height - zoneWidth / 2}px`,
          width: `${width}px`,
        },
        right: {
          backgroundColor: "transparent",
          cursor: "col-resize",
          height: `${height}px`,
          left: `${relLeft + width - zoneWidth / 2}px`,
          top: `${relTop}px`,
          width: `${zoneWidth}px`,
        },
      };

      const tableRect = tableRectRef.current;

      if (draggingDirection && pointerCurrentPos && tableRect) {
        const dirStyle = styles[draggingDirection]!;
        if (isHeightChanging(draggingDirection)) {
          dirStyle.left = `${tableRect.left - containerRect.left + scrollLeft}px`;
          dirStyle.top = `${pointerCurrentPos.y / zoom - containerRect.top + scrollTop}px`;
          dirStyle.height = "3px";
          dirStyle.width = `${tableRect.width}px`;
        } else {
          dirStyle.top = `${tableRect.top - containerRect.top + scrollTop}px`;
          dirStyle.left = `${pointerCurrentPos.x / zoom - containerRect.left + scrollLeft}px`;
          dirStyle.width = "3px";
          dirStyle.height = `${tableRect.height}px`;
        }

        dirStyle.backgroundColor = "#adf";
        dirStyle.mixBlendMode = "unset";
      } else if (!draggingDirection && hoveredDirection === "right") {
        const rightStyle = styles.right!;
        const halfZoneWidth = zoneWidth / 2;
        const highlightWidth = 2;
        const highlightStart = halfZoneWidth - highlightWidth / 2;
        rightStyle.backgroundImage = `linear-gradient(90deg, transparent ${highlightStart}px, ${ACTIVE_RESIZER_COLOR} ${highlightStart}px, ${ACTIVE_RESIZER_COLOR} ${highlightStart + highlightWidth}px, transparent ${highlightStart + highlightWidth}px)`;
        rightStyle.mixBlendMode = "unset";
        if (tableRect) {
          rightStyle.top = `${tableRect.top - containerRect.top + scrollTop}px`;
          rightStyle.height = `${tableRect.height}px`;
        }
      }

      return styles;
    }

    return {
      bottom: null,
      left: null,
      right: null,
      top: null,
    };
    // eslint-enable react-hooks/refs
  }, [
    activeCell,
    draggingDirection,
    hoveredDirection,
    pointerCurrentPos,
    scrollContainerRef,
    scrollOffset,
  ]);

  const handlePointerEnter = useCallback(
    (direction: PointerDraggingDirection): React.PointerEventHandler<HTMLDivElement> =>
      () => {
        if (!draggingDirection) {
          updateHoveredDirection(direction);
        }
      },
    [draggingDirection],
  );

  const handlePointerLeave = useCallback(() => {
    if (!draggingDirection) {
      updateHoveredDirection(null);
    }
  }, [draggingDirection]);

  return (
    <div ref={resizerRef}>
      {activeCell != null && (
        <>
          <div
            className="pointer-events-auto absolute z-10"
            style={resizerStyles.right || undefined}
            onPointerEnter={handlePointerEnter("right")}
            onPointerLeave={handlePointerLeave}
            onPointerDown={toggleResize("right")}
          />
          <div
            className="pointer-events-auto absolute z-10"
            style={resizerStyles.bottom || undefined}
            onPointerDown={toggleResize("bottom")}
          />
        </>
      )}
    </div>
  );
}

export default function TableCellResizerPlugin({
  scrollContainerRef,
}: {
  scrollContainerRef: RefObject<HTMLDivElement | null>;
}): React.ReactPortal | null {
  const [editor] = useLexicalComposerContext();
  const isEditable = useLexicalEditable();
  const [container, setContainer] = useState<HTMLDivElement | null>(null);

  useEffect(() => {
    if (scrollContainerRef.current) {
      setContainer(scrollContainerRef.current);
    }
  }, [scrollContainerRef]);

  return useMemo(
    () =>
      isEditable && container
        ? createPortal(
            <TableCellResizer editor={editor} scrollContainerRef={scrollContainerRef} />,
            container,
          )
        : null,
    [editor, isEditable, container, scrollContainerRef],
  );
}
