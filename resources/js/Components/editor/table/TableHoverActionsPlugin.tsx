import {
  draggable,
  dropTargetForElements,
} from "@atlaskit/pragmatic-drag-and-drop/element/adapter";
import { useLexicalComposerContext } from "@lexical/react/LexicalComposerContext";
import { useLexicalEditable } from "@lexical/react/useLexicalEditable";
import {
  $computeTableMapSkipCellCheck,
  $insertTableColumnAtSelection,
  $insertTableRowAtSelection,
  $isSimpleTable,
  $isTableCellNode,
  $isTableNode,
  $isTableRowNode,
  $moveTableColumn,
} from "@lexical/table";
import {
  $getChildCaret,
  $getNearestNodeFromDOMNode,
  $getSiblingCaret,
  getComposedEventTarget,
  isDOMNode,
  isHTMLElement,
  registerEventListener,
} from "lexical";
import { mergeRegister } from "@lexical/utils";
import { useCallback, useEffect, useRef, useState, type RefObject } from "react";
import { createPortal } from "react-dom";
import { Plus, GripVertical, ArrowUpDown, ArrowDownAZ, ArrowUpZA } from "lucide-react";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/Components/ui/dropdown-menu";
import { useContainerPosition } from "../hooks/use-container-position";
import { DropIndicator } from "../shared/drop-indicator";
import {
  TOP_BUTTON_OVERHANG,
  getCellSelector,
  getTableFromTarget,
  getClosestTopCellPosition,
  isTableFromEditor,
  getTableKey,
  isColumnDrag,
  getBoundaryIndex,
  getDropIndicatorState,
  isHTMLTableRowElement,
  type ColumnDragData,
  type DropIndicatorState,
} from "./table-hover-utils";

function TableHoverActions({
  scrollContainerRef,
}: {
  scrollContainerRef: RefObject<HTMLDivElement | null>;
}) {
  const [editor, { getTheme }] = useLexicalComposerContext();
  const isEditable = useLexicalEditable();

  const { ref: topButtonRef, positionAbove } = useContainerPosition(scrollContainerRef);
  const { ref: leftButtonRef, positionRelative: positionLeftAt } =
    useContainerPosition(scrollContainerRef);

  const [isVisible, setIsVisible] = useState(false);
  const [isLeftVisible, setIsLeftVisible] = useState(false);
  const floatingElemRef = useRef<HTMLElement | null>(null);
  const leftFloatingElemRef = useRef<HTMLElement | null>(null);
  const dragHandleRef = useRef<HTMLButtonElement | null>(null);
  const hoveredLeftCellRef = useRef<HTMLTableCellElement | null>(null);
  const hoveredTopCellRef = useRef<HTMLTableCellElement | null>(null);
  const dropIndicatorCleanupRef = useRef<(() => void)[]>([]);
  const [hoveredTable, setHoveredTable] = useState<HTMLTableElement | null>(null);
  const [hoveredColumnIndex, setHoveredColumnIndex] = useState<number | null>(null);
  const [canReorder, setCanReorder] = useState(false);
  const [dropIndicatorState, setDropIndicatorState] = useState<DropIndicatorState | null>(null);

  const scrollHideTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    if (!isEditable) {
      return;
    }

    const hide = () => {
      setIsVisible(false);
      setIsLeftVisible(false);
    };

    const onScroll = () => {
      if (scrollHideTimeoutRef.current !== null) {
        clearTimeout(scrollHideTimeoutRef.current);
      }
      scrollHideTimeoutRef.current = setTimeout(() => {
        scrollHideTimeoutRef.current = null;
        hide();
      }, 0);
    };

    // eslint-disable-next-line @eslint-react/web-api-no-leaked-event-listener -- paired removeEventListener in the cleanup below
    window.addEventListener("scroll", onScroll, {
      capture: true,
      passive: true,
    });

    const container = scrollContainerRef.current;
    if (container) {
      container.addEventListener("scroll", onScroll, {
        passive: true,
      });
    }

    return () => {
      if (scrollHideTimeoutRef.current !== null) {
        clearTimeout(scrollHideTimeoutRef.current);
        scrollHideTimeoutRef.current = null;
      }
      window.removeEventListener("scroll", onScroll, {
        capture: true,
      } as EventListenerOptions);
      if (container) {
        container.removeEventListener("scroll", onScroll);
      }
    };
  }, [isEditable, scrollContainerRef]);

  useEffect(() => {
    if (!isEditable) {
      return;
    }

    const handleMouseMove = (event: MouseEvent) => {
      if (scrollHideTimeoutRef.current !== null) {
        clearTimeout(scrollHideTimeoutRef.current);
        scrollHideTimeoutRef.current = null;
      }

      const target = getComposedEventTarget(event);
      if (
        (floatingElemRef.current &&
          isDOMNode(target) &&
          floatingElemRef.current.contains(target)) ||
        (leftFloatingElemRef.current &&
          isDOMNode(target) &&
          leftFloatingElemRef.current.contains(target))
      ) {
        return;
      }

      const topRect = floatingElemRef.current?.getBoundingClientRect();
      const leftRect = leftFloatingElemRef.current?.getBoundingClientRect();
      const BUFFER = 10;
      if (
        (topRect &&
          event.clientX >= topRect.left - BUFFER &&
          event.clientX <= topRect.right + BUFFER &&
          event.clientY >= topRect.top - BUFFER &&
          event.clientY <= topRect.bottom + BUFFER) ||
        (leftRect &&
          event.clientX >= leftRect.left - BUFFER &&
          event.clientX <= leftRect.right + BUFFER &&
          event.clientY >= leftRect.top - BUFFER &&
          event.clientY <= leftRect.bottom + BUFFER)
      ) {
        return;
      }

      const container = scrollContainerRef.current;

      const cellSelector = getCellSelector(getTheme);
      const { tableElement, isOutside } = getTableFromTarget(target, cellSelector);

      const editorRoot = editor.getRootElement();

      if (
        isOutside ||
        tableElement == null ||
        (editorRoot !== null && !editorRoot.contains(tableElement))
      ) {
        setIsVisible(false);
        setIsLeftVisible(false);
        setHoveredTable(null);
        setHoveredColumnIndex(null);
        setDropIndicatorState(null);
        return;
      }

      const hoveredCell = isHTMLElement(target)
        ? target.closest<HTMLTableCellElement>(cellSelector)
        : null;

      if (!hoveredCell) {
        setIsVisible(false);
        setIsLeftVisible(false);
        hoveredTopCellRef.current = null;
        hoveredLeftCellRef.current = null;
        return;
      }

      if (!container) {
        return;
      }

      const rowIndex = isHTMLTableRowElement(hoveredCell.parentElement)
        ? hoveredCell.parentElement.rowIndex
        : -1;
      const colIndex = hoveredCell.cellIndex ?? -1;

      const closestTopCell = getClosestTopCellPosition(tableElement, event.clientX);

      if (!closestTopCell || rowIndex !== 0) {
        setIsVisible(false);
        hoveredTopCellRef.current = null;
        setHoveredTable(null);
        setHoveredColumnIndex(null);
      } else {
        hoveredTopCellRef.current = closestTopCell.cell;
        setHoveredTable(tableElement);
        setHoveredColumnIndex(closestTopCell.cell.cellIndex ?? null);

        positionAbove(closestTopCell.cell.getBoundingClientRect(), TOP_BUTTON_OVERHANG);
        setIsVisible(true);
      }

      if (colIndex !== 0) {
        setIsLeftVisible(false);
        hoveredLeftCellRef.current = null;
      } else {
        hoveredLeftCellRef.current = hoveredCell;
        positionLeftAt(hoveredCell.getBoundingClientRect(), { anchor: "top-left" });
        setIsLeftVisible(true);
      }
    };

    return mergeRegister(registerEventListener(document, "mousemove", handleMouseMove), () => {
      setIsVisible(false);
      setIsLeftVisible(false);
    });
  }, [editor, getTheme, isEditable, scrollContainerRef, positionAbove, positionLeftAt]);

  useEffect(() => {
    const handleMouseLeave = (event: MouseEvent) => {
      const nextTarget = event.relatedTarget;
      if (
        nextTarget &&
        floatingElemRef.current &&
        floatingElemRef.current.contains(nextTarget as Node)
      ) {
        return;
      }
      if (
        nextTarget &&
        leftFloatingElemRef.current &&
        leftFloatingElemRef.current.contains(nextTarget as Node)
      ) {
        return;
      }
      if (
        nextTarget &&
        scrollContainerRef.current &&
        scrollContainerRef.current.contains(nextTarget as Node)
      ) {
        return;
      }
      setIsVisible(false);
      setIsLeftVisible(false);
    };

    return editor.registerRootListener((rootElement) => {
      if (rootElement) {
        return registerEventListener(rootElement, "mouseleave", handleMouseLeave);
      }
    });
  }, [editor, scrollContainerRef]);

  useEffect(() => {
    if (!hoveredTable) {
      // eslint-disable-next-line react-hooks/set-state-in-effect
      setCanReorder(false);
      return;
    }
    if (!isTableFromEditor(hoveredTable, editor)) {
      setCanReorder(false);
      return;
    }
    editor.read("latest", () => {
      const tableNode = $getNearestNodeFromDOMNode(hoveredTable);
      setCanReorder($isTableNode(tableNode) && $isSimpleTable(tableNode));
    });
  }, [editor, hoveredTable]);

  useEffect(() => {
    const handle = dragHandleRef.current;
    const tableElement = hoveredTable;
    const columnIndex = hoveredColumnIndex;
    if (!canReorder || !handle || tableElement == null || columnIndex == null) {
      return;
    }

    return draggable({
      canDrag: () => canReorder,
      element: handle,
      getInitialData: () => ({
        columnIndex,
        tableKey: getTableKey(tableElement),
        type: "table-column" as const,
      }),
      onDrop: () => setDropIndicatorState(null),
    });
  }, [canReorder, hoveredColumnIndex, hoveredTable]);

  useEffect(() => {
    for (const cleanup of dropIndicatorCleanupRef.current) {
      cleanup();
    }
    dropIndicatorCleanupRef.current = [];
    if (!hoveredTable || !canReorder) {
      return;
    }
    const headerRow = hoveredTable.rows[0];
    if (!headerRow) {
      return;
    }
    if (!isTableFromEditor(hoveredTable, editor)) {
      return;
    }
    const tableKey = getTableKey(hoveredTable);
    const container = scrollContainerRef.current;
    const registerDropTarget = (cell: HTMLTableCellElement) =>
      dropTargetForElements({
        canDrop: ({ source }) => isColumnDrag(source, tableKey),
        element: cell,
        getData: ({ input }) => ({
          boundaryIndex: getBoundaryIndex(cell, input.clientX),
        }),
        onDrag: ({ location, source }) => {
          if (!container || !isColumnDrag(source, tableKey)) {
            return;
          }
          const boundaryIndex = getBoundaryIndex(cell, location.current.input.clientX);
          const containerRect = container.getBoundingClientRect();
          setDropIndicatorState(
            getDropIndicatorState(headerRow, containerRect, container, boundaryIndex),
          );
        },
        onDragEnter: ({ location, source }) => {
          if (!container || !isColumnDrag(source, tableKey)) {
            return;
          }
          const boundaryIndex = getBoundaryIndex(cell, location.current.input.clientX);
          const containerRect = container.getBoundingClientRect();
          setDropIndicatorState(
            getDropIndicatorState(headerRow, containerRect, container, boundaryIndex),
          );
        },
        onDragLeave: () => setDropIndicatorState(null),
        onDrop: ({ location, source }) => {
          setDropIndicatorState(null);
          const data = (source?.data ?? {}) as ColumnDragData;
          if (data.columnIndex == null) {
            return;
          }
          const boundaryIndex = getBoundaryIndex(cell, location.current.input.clientX);
          const targetTable = cell.closest("table");
          if (!isHTMLElement(targetTable) || !isColumnDrag(source, tableKey)) {
            return;
          }
          editor.update(() => {
            const tableNode = $getNearestNodeFromDOMNode(targetTable);
            if (!$isTableNode(tableNode)) {
              return;
            }
            const columnCount = tableNode.getColumnCount();
            const clampedBoundary = Math.max(0, Math.min(boundaryIndex, columnCount));
            const startIndex = data.columnIndex;
            if (
              clampedBoundary === startIndex ||
              clampedBoundary === startIndex + 1 ||
              startIndex < 0 ||
              startIndex >= columnCount
            ) {
              return;
            }
            const finishIndex =
              clampedBoundary > startIndex ? clampedBoundary - 1 : clampedBoundary;
            $moveTableColumn(tableNode, startIndex, finishIndex);
          });
        },
      });

    dropIndicatorCleanupRef.current = [...headerRow.cells].map((cell) => registerDropTarget(cell));

    return () => {
      for (const cleanup of dropIndicatorCleanupRef.current) {
        cleanup();
      }
      dropIndicatorCleanupRef.current = [];
      setDropIndicatorState(null);
    };
  }, [canReorder, editor, hoveredTable, scrollContainerRef]);

  const handleAddColumn = useCallback(() => {
    const targetCell = hoveredTopCellRef.current;
    if (!targetCell) {
      return;
    }
    editor.update(() => {
      const maybeCellNode = $getNearestNodeFromDOMNode(targetCell);
      if ($isTableCellNode(maybeCellNode)) {
        maybeCellNode.selectEnd();
        $insertTableColumnAtSelection();
      }
    });
  }, [editor]);

  const handleAddRow = useCallback(() => {
    const targetCell = hoveredLeftCellRef.current;
    if (!targetCell) {
      return;
    }
    editor.update(() => {
      const maybeCellNode = $getNearestNodeFromDOMNode(targetCell);
      if ($isTableCellNode(maybeCellNode)) {
        maybeCellNode.selectEnd();
        $insertTableRowAtSelection();
      }
    });
  }, [editor]);

  const handleSortColumn = useCallback(
    (direction: "asc" | "desc") => {
      const targetCell = hoveredTopCellRef.current;
      if (!targetCell) {
        return;
      }

      editor.update(() => {
        const cellNode = $getNearestNodeFromDOMNode(targetCell);
        if (!$isTableCellNode(cellNode)) {
          return;
        }

        const rowNode = cellNode.getParent();
        if (!rowNode || !$isTableRowNode(rowNode)) {
          return;
        }

        const tableNode = rowNode.getParent();
        if (!$isTableNode(tableNode) || !$isSimpleTable(tableNode)) {
          return;
        }

        const colIndex = cellNode.getIndexWithinParent();
        const rows = tableNode.getChildren().filter($isTableRowNode);

        const [tableMap] = $computeTableMapSkipCellCheck(tableNode, cellNode, cellNode);

        const headerCell = tableMap[0]?.[colIndex]?.cell;
        const shouldSkipTopRow = headerCell?.hasHeader() ?? false;

        const sortableRows = shouldSkipTopRow ? rows.slice(1) : rows;

        if (sortableRows.length <= 1) {
          return;
        }

        sortableRows.sort((a, b) => {
          const aRowIndex = rows.indexOf(a);
          const bRowIndex = rows.indexOf(b);

          const aMapRow = tableMap[aRowIndex] ?? [];
          const bMapRow = tableMap[bRowIndex] ?? [];

          const aCellValue = aMapRow[colIndex];
          const bCellValue = bMapRow[colIndex];

          const aText = aCellValue?.cell.getTextContent() ?? "";
          const bText = bCellValue?.cell.getTextContent() ?? "";
          const result = aText.localeCompare(bText, undefined, {
            numeric: true,
          });
          return direction === "asc" ? -result : result;
        });

        const insertionCaret = shouldSkipTopRow
          ? $getSiblingCaret(rows[0]!, "next")
          : $getChildCaret(tableNode, "next");

        insertionCaret?.splice(0, sortableRows);
      });
    },
    [editor],
  );

  if (!isEditable) {
    return null;
  }

  return (
    <>
      {/* Top actions: drag handle + sort + add column */}
      <div
        ref={(node) => {
          floatingElemRef.current = node;
          topButtonRef(node);
        }}
        className="absolute z-30 flex items-center gap-1"
        style={{
          opacity: isVisible ? 1 : 0,
          pointerEvents: isVisible ? "auto" : "none",
        }}
      >
        {canReorder && (
          <button
            ref={dragHandleRef}
            className="inline-flex h-[18px] w-[18px] cursor-grab items-center justify-center rounded-sm border border-border bg-background shadow-xs transition-colors hover:bg-accent active:cursor-grabbing"
            aria-label="Drag to reorder column"
            type="button"
            onPointerDown={(e) => e.stopPropagation()}
          >
            <GripVertical className="h-3 w-3 text-muted-foreground" />
          </button>
        )}
        {canReorder && (
          <DropdownMenu>
            <DropdownMenuTrigger
              render={
                <button
                  className="inline-flex h-[18px] w-[18px] items-center justify-center rounded-sm border border-border bg-background shadow-xs transition-colors hover:bg-accent"
                  aria-label="Sort column"
                  type="button"
                  onPointerDown={(e) => e.stopPropagation()}
                />
              }
            >
              <ArrowUpDown className="h-3 w-3 text-muted-foreground" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" side="bottom" sideOffset={4}>
              <DropdownMenuItem onClick={() => handleSortColumn("asc")}>
                <ArrowDownAZ className="mr-2 h-3.5 w-3.5" />
                Sort ascending
              </DropdownMenuItem>
              <DropdownMenuItem onClick={() => handleSortColumn("desc")}>
                <ArrowUpZA className="mr-2 h-3.5 w-3.5" />
                Sort descending
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        )}
        <button
          className="inline-flex h-[18px] w-[18px] items-center justify-center rounded-sm border border-border bg-background shadow-xs transition-colors hover:bg-accent"
          aria-label="Add column"
          type="button"
          onClick={handleAddColumn}
          onPointerDown={(e) => e.stopPropagation()}
        >
          <Plus className="h-3 w-3 text-muted-foreground" />
        </button>
      </div>

      {/* Left action: add row */}
      <button
        ref={(node) => {
          leftFloatingElemRef.current = node;
          leftButtonRef(node as unknown as HTMLDivElement);
        }}
        className="absolute z-30 inline-flex h-[18px] w-[18px] items-center justify-center rounded-sm border border-border bg-background shadow-xs transition-colors hover:bg-accent"
        aria-label="Add row"
        type="button"
        onClick={handleAddRow}
        onPointerDown={(e) => e.stopPropagation()}
        style={{
          opacity: isLeftVisible ? 1 : 0,
          pointerEvents: isLeftVisible ? "auto" : "none",
          transform: "translate(-50%, -50%)",
        }}
      >
        <Plus className="h-3 w-3 text-muted-foreground" />
      </button>

      {/* Drop indicator during column drag */}
      {dropIndicatorState ? (
        <div
          style={{
            height: dropIndicatorState.height,
            left: dropIndicatorState.left,
            pointerEvents: "none",
            position: "absolute",
            top: dropIndicatorState.top,
            width: 2,
            zIndex: 90,
          }}
        >
          <DropIndicator edge={dropIndicatorState.edge} />
        </div>
      ) : null}
    </>
  );
}

export default function TableHoverActionsPlugin({
  scrollContainerRef,
}: {
  scrollContainerRef?: RefObject<HTMLDivElement | null>;
}): React.ReactPortal | null {
  const isEditable = useLexicalEditable();
  const [editor] = useLexicalComposerContext();

  const [target, setTarget] = useState<HTMLDivElement | null>(null);

  useEffect(() => {
    if (scrollContainerRef) {
      setTarget(scrollContainerRef.current);
      return;
    }
    const root = editor.getRootElement();
    if (root) {
      const scrollContainer = root.closest<HTMLDivElement>(".overflow-auto");
      setTarget(scrollContainer);
    }
  }, [scrollContainerRef, editor]);

  if (!isEditable || !target) {
    return null;
  }

  return createPortal(<TableHoverActions scrollContainerRef={{ current: target }} />, target);
}
