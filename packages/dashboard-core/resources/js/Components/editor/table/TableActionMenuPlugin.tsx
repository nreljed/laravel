import { type RefObject, useCallback, useEffect, useRef, useState } from "react";
import { useLexicalComposerContext } from "@lexical/react/LexicalComposerContext";
import {
  $getSelection,
  $isRangeSelection,
  $isTextNode,
  $isElementNode,
  $setSelection,
  $getNodeByKey,
  COMMAND_PRIORITY_CRITICAL,
  SELECTION_CHANGE_COMMAND,
  registerEventListener,
} from "lexical";
import { mergeRegister } from "@lexical/utils";
import {
  $deleteTableColumnAtSelection,
  $deleteTableRowAtSelection,
  $getTableNodeFromLexicalNodeOrThrow,
  $getTableCellNodeFromLexicalNode,
  $getTableRowIndexFromTableCellNode,
  $getTableColumnIndexFromTableCellNode,
  $insertTableRowAtSelection,
  $insertTableColumnAtSelection,
  $isTableCellNode,
  $isTableSelection,
  $mergeCells,
  $unmergeCell,
  $setTableRowIsHeader,
  $setTableColumnIsHeader,
  TableCellHeaderStates,
  type TableCellNode,
  getTableElement,
  getTableObserverFromTableElement,
} from "@lexical/table";
import {
  ChevronDown,
  Merge,
  Ungroup,
  ArrowUp,
  ArrowDown,
  ArrowLeft,
  ArrowRight,
  Trash2,
  ToggleLeft,
  Paintbrush,
  AlignVerticalJustifyStart,
  AlignVerticalJustifyCenter,
  AlignVerticalJustifyEnd,
  Rows3,
  Columns3,
  Snowflake,
} from "lucide-react";
import { cn } from "@/lib/utils";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
  DropdownMenuSub,
  DropdownMenuSubContent,
  DropdownMenuSubTrigger,
} from "@/Components/ui/dropdown-menu";
import { useContainerPosition } from "../hooks/use-container-position";

const CELL_BG_COLORS = [
  { label: "Default", value: "" },
  { label: "Gray", value: "var(--cell-bg-gray)" },
  { label: "Red", value: "var(--cell-bg-red)" },
  { label: "Orange", value: "var(--cell-bg-orange)" },
  { label: "Yellow", value: "var(--cell-bg-yellow)" },
  { label: "Green", value: "var(--cell-bg-green)" },
  { label: "Blue", value: "var(--cell-bg-blue)" },
  { label: "Purple", value: "var(--cell-bg-purple)" },
  { label: "Teal", value: "var(--cell-bg-teal)" },
];

function getSelectionCounts(
  selection: ReturnType<typeof $getSelection>,
): { rows: number; columns: number } | null {
  if (!$isTableSelection(selection)) {
    return null;
  }

  const nodeMap = new Map<string, { row: number; column: number }>();

  for (const node of selection.getNodes()) {
    if (!$isTableCellNode(node)) continue;

    const key = node.getKey();
    if (nodeMap.has(key)) continue;

    const row = $getTableRowIndexFromTableCellNode(node);
    const column = $getTableColumnIndexFromTableCellNode(node);
    nodeMap.set(key, { row, column });
  }

  if (nodeMap.size === 0) return null;

  let minRow = Infinity;
  let maxRow = -Infinity;
  let minCol = Infinity;
  let maxCol = -Infinity;

  for (const { row, column } of nodeMap.values()) {
    minRow = Math.min(minRow, row);
    maxRow = Math.max(maxRow, row);
    minCol = Math.min(minCol, column);
    maxCol = Math.max(maxCol, column);
  }

  return {
    rows: maxRow - minRow + 1,
    columns: maxCol - minCol + 1,
  };
}

function CellBgColorPicker({
  currentColor,
  onSelect,
}: {
  currentColor: string;
  onSelect: (_color: string) => void;
}) {
  return (
    <DropdownMenuSub>
      <DropdownMenuSubTrigger>
        <Paintbrush className="mr-2 h-4 w-4" />
        Cell background
        <div
          className="ml-auto h-4 w-4 rounded-sm border"
          style={{ backgroundColor: currentColor || "transparent" }}
        />
      </DropdownMenuSubTrigger>
      <DropdownMenuSubContent className="w-auto p-2">
        <div className="grid grid-cols-3 gap-1.5">
          {CELL_BG_COLORS.map((color) => (
            <DropdownMenuItem
              key={color.value || "default"}
              onClick={() => onSelect(color.value)}
              className={cn(
                "flex h-8 w-8 items-center justify-center rounded-sm p-0",
                currentColor === color.value && "ring-2 ring-ring",
              )}
            >
              {color.value ? (
                <div
                  className="h-6 w-6 rounded-sm border border-border/50"
                  style={{ backgroundColor: color.value }}
                />
              ) : (
                <span className="text-xs text-muted-foreground">A</span>
              )}
            </DropdownMenuItem>
          ))}
        </div>
      </DropdownMenuSubContent>
    </DropdownMenuSub>
  );
}

function TableActionMenu({
  onClose,
  tableCellNode,
  selectedCellKeys,
}: {
  onClose: () => void;
  tableCellNode: TableCellNode;
  selectedCellKeys: string[];
}) {
  const [editor] = useLexicalComposerContext();
  const [canMergeCells, setCanMergeCells] = useState(false);
  const [canUnmergeCell, setCanUnmergeCell] = useState(false);
  const [cellBgColor, setCellBgColor] = useState<string>("");
  const [isRowStriped, setIsRowStriped] = useState(false);
  const [frozenRows, setFrozenRows] = useState(0);
  const [frozenColumns, setFrozenColumns] = useState(0);
  const [selectionCounts, setSelectionCounts] = useState<{
    rows: number;
    columns: number;
  } | null>(null);
  const [isRowHeader, setIsRowHeader] = useState(false);
  const [isColumnHeader, setIsColumnHeader] = useState(false);
  const tableCellNodeRef = useRef(tableCellNode);
  // eslint-disable-next-line react-hooks/refs
  tableCellNodeRef.current = tableCellNode;

  const clearTableSelection = useCallback(() => {
    editor.update(() => {
      const cell = tableCellNodeRef.current;
      if (!cell || !cell.isAttached()) return;
      const tableNode = $getTableNodeFromLexicalNodeOrThrow(cell.getLatest());
      const tableElement = getTableElement(tableNode, editor.getElementByKey(tableNode.getKey()));
      if (tableElement) {
        const observer = getTableObserverFromTableElement(tableElement);
        if (observer) {
          observer.$clearHighlight();
        }
      }
      tableNode.markDirty();
      $setSelection(null);
    });
  }, [editor]);

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect
    setCanMergeCells(selectedCellKeys.length > 1);
  }, [selectedCellKeys]);

  useEffect(() => {
    return editor.registerUpdateListener(() => {
      editor.read(() => {
        const selection = $getSelection();
        if ($isTableSelection(selection)) {
          setSelectionCounts(getSelectionCounts(selection));
        } else {
          setSelectionCounts(null);
        }

        const canUnmerge = (() => {
          if (!$isRangeSelection(selection)) return false;
          if (!selection.isCollapsed()) return false;
          const node = selection.anchor.getNode();
          const cell = $getTableCellNodeFromLexicalNode(node);
          if (!cell) return false;
          return cell.getColSpan() > 1 || cell.getRowSpan() > 1;
        })();
        setCanUnmergeCell(canUnmerge);

        // Sync cell background color from the current cell
        if ($isRangeSelection(selection)) {
          const cell = $getTableCellNodeFromLexicalNode(selection.anchor.getNode());
          setCellBgColor(cell?.getBackgroundColor() ?? "");
        } else if ($isTableSelection(selection)) {
          const firstCell = selection.getNodes().find($isTableCellNode);
          setCellBgColor(firstCell?.getBackgroundColor() ?? "");
        }

        // Sync table-level state
        const anchorNode =
          $isRangeSelection(selection) || $isTableSelection(selection)
            ? selection.anchor.getNode()
            : null;
        if (anchorNode) {
          try {
            const tableNode = $getTableNodeFromLexicalNodeOrThrow(anchorNode);
            setIsRowStriped(tableNode.getRowStriping());
            setFrozenRows(tableNode.getFrozenRows());
            setFrozenColumns(tableNode.getFrozenColumns());
          } catch {
            // Not inside a table
          }
        }

        // Sync header state from the current cell (must be inside editor.read)
        const headerCell = $isRangeSelection(selection)
          ? $getTableCellNodeFromLexicalNode(selection.anchor.getNode())
          : $isTableSelection(selection)
            ? selection.getNodes().find($isTableCellNode)
            : null;
        if (headerCell) {
          try {
            const latest = headerCell.getLatest();
            setIsRowHeader(latest.hasHeaderState(TableCellHeaderStates.ROW));
            setIsColumnHeader(latest.hasHeaderState(TableCellHeaderStates.COLUMN));
          } catch {
            setIsRowHeader(false);
            setIsColumnHeader(false);
          }
        }
      });
    });
  }, [editor]);

  const mergeTableCellsAtSelection = useCallback(() => {
    editor.update(() => {
      // Use keys captured when the menu opened
      // (dropdown steals focus so $getSelection is no longer a TableSelection)
      const tableCells: TableCellNode[] = [];
      for (const key of selectedCellKeys) {
        const node = $getNodeByKey(key);
        if (node && $isTableCellNode(node)) {
          tableCells.push(node);
        }
      }
      if (tableCells.length < 2) return;
      const targetCell = $mergeCells(tableCells);
      if (targetCell) {
        const lastDescendant = targetCell.getLastDescendant();
        if ($isTextNode(lastDescendant)) {
          lastDescendant.select();
        } else if ($isElementNode(lastDescendant)) {
          lastDescendant.selectEnd();
        } else if (lastDescendant !== null) {
          lastDescendant.selectNext();
        }
      }
      onClose();
    });
  }, [editor, onClose, selectedCellKeys]);

  const unmergeTableCellsAtSelection = useCallback(() => {
    editor.update(() => {
      $unmergeCell();
    });
    clearTableSelection();
    onClose();
  }, [editor, onClose, clearTableSelection]);

  const insertTableRow = useCallback(
    (after: boolean) => {
      editor.update(() => {
        const count = selectionCounts?.rows ?? 1;
        for (let i = 0; i < count; i++) {
          $insertTableRowAtSelection(after);
        }
      });
      onClose();
    },
    [editor, onClose, selectionCounts],
  );

  const insertTableColumn = useCallback(
    (after: boolean) => {
      editor.update(() => {
        const count = selectionCounts?.columns ?? 1;
        for (let i = 0; i < count; i++) {
          $insertTableColumnAtSelection(after);
        }
      });
      onClose();
    },
    [editor, onClose, selectionCounts],
  );

  const deleteTableRowAtSelection = useCallback(() => {
    editor.update(() => {
      $deleteTableRowAtSelection();
    });
    onClose();
  }, [editor, onClose]);

  const deleteTableColumnAtSelection = useCallback(() => {
    editor.update(() => {
      $deleteTableColumnAtSelection();
    });
    onClose();
  }, [editor, onClose]);

  const deleteTableAtSelection = useCallback(() => {
    editor.update(() => {
      const cell = tableCellNodeRef.current;
      if (!cell) return;
      const tableNode = $getTableNodeFromLexicalNodeOrThrow(cell.getLatest());
      tableNode.remove();
    });
    clearTableSelection();
    onClose();
  }, [editor, onClose, clearTableSelection]);

  const toggleRowHeader = useCallback(() => {
    editor.update(() => {
      const cell = tableCellNodeRef.current;
      if (!cell) return;
      const latestCell = cell.getLatest();
      const tableNode = $getTableNodeFromLexicalNodeOrThrow(latestCell);
      const rowIndex = $getTableRowIndexFromTableCellNode(latestCell);
      const isHeader = !latestCell.hasHeaderState(TableCellHeaderStates.ROW);
      $setTableRowIsHeader(tableNode, rowIndex, isHeader);
    });
    clearTableSelection();
    onClose();
  }, [editor, onClose, clearTableSelection]);

  const toggleColumnHeader = useCallback(() => {
    editor.update(() => {
      const cell = tableCellNodeRef.current;
      if (!cell) return;
      const latestCell = cell.getLatest();
      const tableNode = $getTableNodeFromLexicalNodeOrThrow(latestCell);
      const columnIndex = $getTableColumnIndexFromTableCellNode(latestCell);
      const isHeader = !latestCell.hasHeaderState(TableCellHeaderStates.COLUMN);
      $setTableColumnIsHeader(tableNode, columnIndex, isHeader);
    });
    clearTableSelection();
    onClose();
  }, [editor, onClose, clearTableSelection]);

  const setCellBackgroundColor = useCallback(
    (color: string) => {
      editor.update(() => {
        // Multi-cell: use keys captured when the menu opened
        // (dropdown steals focus so $getSelection is no longer a TableSelection)
        if (selectedCellKeys.length > 1) {
          for (const key of selectedCellKeys) {
            const node = $getNodeByKey(key);
            if (node && $isTableCellNode(node)) {
              node.setBackgroundColor(color);
            }
          }
          return;
        }

        // Single cell: use the node captured when the menu opened
        const cell = tableCellNodeRef.current;
        if (cell && cell.isAttached()) {
          cell.getLatest().setBackgroundColor(color);
        }
      });
    },
    [editor, selectedCellKeys],
  );

  const setCellVerticalAlign = useCallback(
    (align: string) => {
      editor.update(() => {
        // Multi-cell: use keys captured when the menu opened
        if (selectedCellKeys.length > 1) {
          for (const key of selectedCellKeys) {
            const node = $getNodeByKey(key);
            if (node && $isTableCellNode(node)) {
              node.setVerticalAlign(align);
            }
          }
        } else {
          const cell = tableCellNodeRef.current;
          if (cell) {
            cell.getLatest().setVerticalAlign(align);
          }
        }
      });
      onClose();
    },
    [editor, onClose, selectedCellKeys],
  );

  const toggleRowStriping = useCallback(() => {
    editor.update(() => {
      const cell = tableCellNodeRef.current;
      if (!cell) return;
      const tableNode = $getTableNodeFromLexicalNodeOrThrow(cell.getLatest());
      tableNode.setRowStriping(!tableNode.getRowStriping());
    });
    clearTableSelection();
    onClose();
  }, [editor, onClose, clearTableSelection]);

  const toggleFrozenRow = useCallback(() => {
    editor.update(() => {
      const cell = tableCellNodeRef.current;
      if (!cell) return;
      const tableNode = $getTableNodeFromLexicalNodeOrThrow(cell.getLatest());
      tableNode.setFrozenRows(tableNode.getFrozenRows() === 0 ? 1 : 0);
    });
    clearTableSelection();
    onClose();
  }, [editor, onClose, clearTableSelection]);

  const toggleFrozenColumn = useCallback(() => {
    editor.update(() => {
      const cell = tableCellNodeRef.current;
      if (!cell) return;
      const tableNode = $getTableNodeFromLexicalNodeOrThrow(cell.getLatest());
      tableNode.setFrozenColumns(tableNode.getFrozenColumns() === 0 ? 1 : 0);
    });
    clearTableSelection();
    onClose();
  }, [editor, onClose, clearTableSelection]);

  // isRowHeader and isColumnHeader are now synced via editor.read in the update listener above

  return (
    <DropdownMenuContent className="w-56">
      {selectionCounts && (
        <>
          <div className="px-2 py-1.5 text-xs font-medium text-muted-foreground">
            {selectionCounts.rows} row{selectionCounts.rows !== 1 ? "s" : ""} &times;{" "}
            {selectionCounts.columns} column
            {selectionCounts.columns !== 1 ? "s" : ""} selected
          </div>
          <DropdownMenuSeparator />
        </>
      )}

      {canMergeCells && (
        <>
          <DropdownMenuItem onClick={mergeTableCellsAtSelection}>
            <Merge className="mr-2 h-4 w-4" />
            Merge cells
          </DropdownMenuItem>
          <DropdownMenuSeparator />
        </>
      )}
      {canUnmergeCell && (
        <>
          <DropdownMenuItem onClick={unmergeTableCellsAtSelection}>
            <Ungroup className="mr-2 h-4 w-4" />
            Unmerge cells
          </DropdownMenuItem>
          <DropdownMenuSeparator />
        </>
      )}

      <CellBgColorPicker currentColor={cellBgColor} onSelect={setCellBackgroundColor} />

      <DropdownMenuSub>
        <DropdownMenuSubTrigger>
          <AlignVerticalJustifyCenter className="mr-2 h-4 w-4" />
          Vertical align
        </DropdownMenuSubTrigger>
        <DropdownMenuSubContent>
          <DropdownMenuItem onClick={() => setCellVerticalAlign("top")}>
            <AlignVerticalJustifyStart className="mr-2 h-4 w-4" />
            Top
          </DropdownMenuItem>
          <DropdownMenuItem onClick={() => setCellVerticalAlign("middle")}>
            <AlignVerticalJustifyCenter className="mr-2 h-4 w-4" />
            Middle
          </DropdownMenuItem>
          <DropdownMenuItem onClick={() => setCellVerticalAlign("bottom")}>
            <AlignVerticalJustifyEnd className="mr-2 h-4 w-4" />
            Bottom
          </DropdownMenuItem>
        </DropdownMenuSubContent>
      </DropdownMenuSub>

      <DropdownMenuSeparator />

      <DropdownMenuItem onClick={() => insertTableRow(false)}>
        <ArrowUp className="mr-2 h-4 w-4" />
        Insert {selectionCounts?.rows ? `${selectionCounts.rows} ` : ""}row
        {selectionCounts?.rows !== 1 ? "s" : ""} above
      </DropdownMenuItem>
      <DropdownMenuItem onClick={() => insertTableRow(true)}>
        <ArrowDown className="mr-2 h-4 w-4" />
        Insert {selectionCounts?.rows ? `${selectionCounts.rows} ` : ""}row
        {selectionCounts?.rows !== 1 ? "s" : ""} below
      </DropdownMenuItem>
      <DropdownMenuSeparator />
      <DropdownMenuItem onClick={() => insertTableColumn(false)}>
        <ArrowLeft className="mr-2 h-4 w-4" />
        Insert {selectionCounts?.columns ? `${selectionCounts.columns} ` : ""}column
        {selectionCounts?.columns !== 1 ? "s" : ""} left
      </DropdownMenuItem>
      <DropdownMenuItem onClick={() => insertTableColumn(true)}>
        <ArrowRight className="mr-2 h-4 w-4" />
        Insert {selectionCounts?.columns ? `${selectionCounts.columns} ` : ""}column
        {selectionCounts?.columns !== 1 ? "s" : ""} right
      </DropdownMenuItem>
      <DropdownMenuSeparator />

      <DropdownMenuItem onClick={toggleRowHeader}>
        <ToggleLeft className="mr-2 h-4 w-4" />
        {isRowHeader ? "Remove row header" : "Add row header"}
      </DropdownMenuItem>
      <DropdownMenuItem onClick={toggleColumnHeader}>
        <ToggleLeft className="mr-2 h-4 w-4" />
        {isColumnHeader ? "Remove column header" : "Add column header"}
      </DropdownMenuItem>
      <DropdownMenuSeparator />

      <DropdownMenuItem onClick={toggleRowStriping}>
        <Rows3 className="mr-2 h-4 w-4" />
        {isRowStriped ? "Disable row striping" : "Enable row striping"}
      </DropdownMenuItem>
      <DropdownMenuItem onClick={toggleFrozenRow}>
        <Snowflake className="mr-2 h-4 w-4" />
        {frozenRows > 0 ? "Unfreeze first row" : "Freeze first row"}
      </DropdownMenuItem>
      <DropdownMenuItem onClick={toggleFrozenColumn}>
        <Columns3 className="mr-2 h-4 w-4" />
        {frozenColumns > 0 ? "Unfreeze first column" : "Freeze first column"}
      </DropdownMenuItem>
      <DropdownMenuSeparator />

      <DropdownMenuItem onClick={deleteTableRowAtSelection}>
        <Trash2 className="mr-2 h-4 w-4" />
        Delete row
      </DropdownMenuItem>
      <DropdownMenuItem onClick={deleteTableColumnAtSelection}>
        <Trash2 className="mr-2 h-4 w-4" />
        Delete column
      </DropdownMenuItem>
      <DropdownMenuItem
        onClick={deleteTableAtSelection}
        className="text-destructive focus:text-destructive"
      >
        <Trash2 className="mr-2 h-4 w-4" />
        Delete table
      </DropdownMenuItem>
    </DropdownMenuContent>
  );
}

function TableCellActionMenuContainer({
  scrollContainerRef,
}: {
  scrollContainerRef: RefObject<HTMLDivElement | null>;
}): React.ReactElement {
  const [editor] = useLexicalComposerContext();
  const [isMenuOpen, setIsMenuOpen] = useState(false);
  const [isVisible, setIsVisible] = useState(false);
  const { ref: menuRef, positionRelative } = useContainerPosition(scrollContainerRef);
  const [tableCellNode, setTableCellNode] = useState<TableCellNode | null>(null);
  const [selectedCellKeys, setSelectedCellKeys] = useState<string[]>([]);

  const $moveMenu = useCallback(() => {
    if (isMenuOpen) {
      return;
    }

    const selection = $getSelection();
    const rootElement = editor.getRootElement();

    function disable() {
      setIsVisible(false);
      setTableCellNode(null);
      setSelectedCellKeys([]);
    }

    if (!rootElement || selection == null) {
      return disable();
    }

    function positionMenu(cellDOM: HTMLElement) {
      setIsVisible(true);
      requestAnimationFrame(() => {
        positionRelative(cellDOM.getBoundingClientRect(), { anchor: "top-right" });
      });
    }

    if ($isRangeSelection(selection) && selection.isCollapsed()) {
      const cellNode = $getTableCellNodeFromLexicalNode(selection.anchor.getNode());

      if (!cellNode || !cellNode.isAttached()) {
        return disable();
      }

      const cellDOM = editor.getElementByKey(cellNode.getKey());
      if (!cellDOM) {
        return disable();
      }

      const tableNode = $getTableNodeFromLexicalNodeOrThrow(cellNode);
      const tableElement = getTableElement(tableNode, editor.getElementByKey(tableNode.getKey()));

      if (!tableElement) {
        return disable();
      }

      const tableObserver = getTableObserverFromTableElement(tableElement);
      if (tableObserver && tableObserver.isSelecting) {
        return disable();
      }

      positionMenu(cellDOM);

      setTableCellNode(cellNode);
      setSelectedCellKeys([cellNode.getKey()]);
    } else if ($isTableSelection(selection)) {
      const anchorNode = $getTableCellNodeFromLexicalNode(selection.anchor.getNode());
      if (!anchorNode || !$isTableCellNode(anchorNode)) {
        return disable();
      }
      const cellDOM = editor.getElementByKey(anchorNode.getKey());
      if (!cellDOM) {
        return disable();
      }

      const tableNode = $getTableNodeFromLexicalNodeOrThrow(anchorNode);
      const tableElement = getTableElement(tableNode, editor.getElementByKey(tableNode.getKey()));
      if (!tableElement) {
        return disable();
      }

      const tableObserver = getTableObserverFromTableElement(tableElement);
      if (tableObserver && tableObserver.isSelecting) {
        return disable();
      }

      positionMenu(cellDOM);

      const cells = selection
        .getNodes()
        .filter($isTableCellNode)
        .map((c) => c.getKey());
      setSelectedCellKeys([...new Set(cells)]);
      setTableCellNode(anchorNode);
    } else {
      disable();
    }
  }, [editor, isMenuOpen, positionRelative]);

  useEffect(() => {
    let timeoutId: ReturnType<typeof setTimeout> | undefined;
    const callback = () => {
      timeoutId = undefined;
      editor.read($moveMenu);
    };
    const delayedCallback = () => {
      if (timeoutId === undefined) {
        timeoutId = setTimeout(callback, 0);
      }
      return false;
    };
    return mergeRegister(
      editor.registerUpdateListener(delayedCallback),
      editor.registerCommand(SELECTION_CHANGE_COMMAND, delayedCallback, COMMAND_PRIORITY_CRITICAL),
      editor.registerRootListener((rootElement) => {
        if (!rootElement) {
          return;
        }

        delayedCallback();
        return registerEventListener(rootElement, "pointerup", delayedCallback);
      }),
      () => clearTimeout(timeoutId),
    );
  }, [editor, $moveMenu]);

  const prevTableCellDOM = useRef(tableCellNode);
  useEffect(() => {
    if (prevTableCellDOM.current !== tableCellNode) {
      setIsMenuOpen(false);
    }
    prevTableCellDOM.current = tableCellNode;
  }, [tableCellNode]);

  useEffect(() => {
    const scrollContainer = scrollContainerRef.current;
    if (!scrollContainer || !isMenuOpen) return;
    const handleScroll = () => {
      setIsMenuOpen(false);
    };
    scrollContainer.addEventListener("scroll", handleScroll, { passive: true });
    return () => scrollContainer.removeEventListener("scroll", handleScroll);
  }, [scrollContainerRef, isMenuOpen]);

  return (
    <div
      ref={menuRef}
      style={{
        display: isVisible ? "flex" : "none",
        position: "absolute",
        zIndex: 30,
      }}
    >
      {tableCellNode != null && (
        <DropdownMenu open={isMenuOpen} onOpenChange={setIsMenuOpen}>
          <DropdownMenuTrigger
            render={
              <button
                type="button"
                className={cn(
                  "flex h-6 w-6 items-center justify-center rounded-md border bg-background text-muted-foreground shadow-sm transition-colors hover:bg-accent hover:text-accent-foreground",
                )}
                onClick={(e) => {
                  e.stopPropagation();
                }}
              />
            }
          >
            <ChevronDown className="h-3.5 w-3.5" />
          </DropdownMenuTrigger>
          <TableActionMenu
            onClose={() => setIsMenuOpen(false)}
            tableCellNode={tableCellNode}
            selectedCellKeys={selectedCellKeys}
          />
        </DropdownMenu>
      )}
    </div>
  );
}

export default function TableActionMenuPlugin({
  scrollContainerRef,
}: {
  scrollContainerRef: RefObject<HTMLDivElement | null>;
}): null | React.ReactElement {
  return <TableCellActionMenuContainer scrollContainerRef={scrollContainerRef} />;
}
