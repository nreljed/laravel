import type { ElementDragPayload } from "@atlaskit/pragmatic-drag-and-drop/element/adapter";
import type { LexicalEditor, EditorThemeClasses } from "lexical";
import { isHTMLElement } from "lexical";

export const INDICATOR_SIZE_PX = 18;
export const TOP_BUTTON_OVERHANG = INDICATOR_SIZE_PX / 2;

export function getCellSelector(getTheme: () => EditorThemeClasses | null | undefined): string {
  const theme = getTheme();
  const cellClassName = theme?.tableCell;
  if (!cellClassName) {
    return "td, th";
  }
  const escapedClasses = cellClassName
    .split(/\s+/)
    .filter(Boolean)
    .map((cls) => `.${CSS.escape(cls)}`)
    .join("");
  return `td${escapedClasses}, th${escapedClasses}`;
}

export function getTableFromTarget(
  target: EventTarget | null,
  cellSelector: string,
): {
  isOutside: boolean;
  tableElement: HTMLTableElement | null;
} {
  if (!isHTMLElement(target)) {
    return { isOutside: true, tableElement: null };
  }

  const cell = target.closest<HTMLTableCellElement>(cellSelector);
  const tableElement = cell?.closest<HTMLTableElement>("table") ?? null;

  return {
    isOutside: tableElement == null,
    tableElement,
  };
}

export function getClosestTopCellPosition(
  tableElement: HTMLTableElement,
  clientX: number,
): { centerX: number; top: number; cell: HTMLTableCellElement } | null {
  const firstRow = tableElement.rows[0];
  if (!firstRow) {
    return null;
  }

  let closest: {
    cell: HTMLTableCellElement;
    centerX: number;
    top: number;
  } | null = null;
  let smallestDelta = Infinity;

  for (const cell of firstRow.cells) {
    const rect = cell.getBoundingClientRect();
    const centerX = rect.left + rect.width / 2;
    const delta = Math.abs(centerX - clientX);
    if (delta < smallestDelta) {
      smallestDelta = delta;
      closest = { cell, centerX, top: rect.top };
    }
  }

  return closest;
}

export function isTableFromEditor(
  tableElement: HTMLTableElement | null,
  editor: LexicalEditor,
): boolean {
  const root = editor.getRootElement();
  return !!root && !!tableElement && root.contains(tableElement);
}

export type ColumnDragData = {
  columnIndex: number;
  tableKey: string | null;
  type: "table-column";
};

export type DropIndicatorState = {
  edge: "left" | "right";
  height: number;
  left: number;
  top: number;
};

export function getBoundaryIndex(cell: HTMLTableCellElement, clientX: number): number {
  const rect = cell.getBoundingClientRect();
  const isRightHalf = clientX > rect.left + rect.width / 2;
  const cellIndex = cell.cellIndex ?? 0;
  return cellIndex + (isRightHalf ? 1 : 0);
}

export function getDropIndicatorState(
  headerRow: HTMLTableRowElement,
  containerRect: DOMRect,
  container: HTMLElement,
  boundaryIndex: number,
): DropIndicatorState | null {
  const cellCount = headerRow.cells.length;
  if (cellCount === 0) {
    return null;
  }
  const clampedIndex = Math.max(0, Math.min(boundaryIndex, cellCount));
  if (clampedIndex === 0) {
    const firstCell = headerRow.cells[0];
    if (!firstCell) return null;
    const firstRect = firstCell.getBoundingClientRect();
    return {
      edge: "left",
      height: firstCell.closest("table")?.getBoundingClientRect().height ?? 0,
      left: firstRect.left - containerRect.left + container.scrollLeft,
      top: firstRect.top - containerRect.top + container.scrollTop,
    };
  }
  if (clampedIndex === cellCount) {
    const lastCell = headerRow.cells[cellCount - 1];
    if (!lastCell) return null;
    const lastRect = lastCell.getBoundingClientRect();
    return {
      edge: "right",
      height: lastCell.closest("table")?.getBoundingClientRect().height ?? 0,
      left: lastRect.right - containerRect.left + container.scrollLeft,
      top: lastRect.top - containerRect.top + container.scrollTop,
    };
  }
  const targetCell = headerRow.cells[clampedIndex];
  if (!targetCell) return null;
  const targetRect = targetCell.getBoundingClientRect();
  return {
    edge: "left",
    height: targetCell.closest("table")?.getBoundingClientRect().height ?? 0,
    left: targetRect.left - containerRect.left + container.scrollLeft,
    top: targetRect.top - containerRect.top + container.scrollTop,
  };
}

export function isColumnDrag(
  source: ElementDragPayload,
  tableKey: string | null,
): source is ElementDragPayload & { data: ColumnDragData } {
  const data = (source?.data as ColumnDragData | undefined) ?? undefined;
  return data?.type === "table-column" && data.tableKey === tableKey;
}

export function getTableKey(tableElement: HTMLTableElement | null): string | null {
  return tableElement?.dataset.lexicalKey ?? null;
}

export function isHTMLTableRowElement(node: unknown): node is HTMLTableRowElement {
  return isHTMLElement(node) && node.nodeName === "TR";
}
