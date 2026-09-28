import * as React from "react";
import type {
  CellData,
  ColumnDef,
  ColumnFiltersState,
  ColumnVisibilityState,
  RowData,
  SortingState,
} from "@tanstack/react-table";
import { flexRender, useTable } from "@tanstack/react-table";

import { AppTableFeatures, tableFeaturesConfig } from "../../lib/table-features";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "./table";
import { DataTablePagination } from "./data-table-pagination";
import { DataTableToolbar, type DataTableFilter } from "./data-table-toolbar";
import { Button } from "./button";
import { Checkbox } from "./checkbox";
import { Skeleton } from "./skeleton";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "./dropdown-menu";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "./alert-dialog";
import { AlertTriangleIcon, ChevronDownIcon } from "lucide-react";

/**
 * Exported so a declarative resource schema (lib/resource-schema.ts) can build
 * `bulkActions` without restating this shape and drifting from the renderer that
 * consumes it.
 */
export interface BulkAction {
  label: string;
  value: string;
  show?: (_statuses: string[]) => boolean;
  destructive?: boolean;
  confirmTitle?: string;
  confirmDescription?: string;
  confirmLabel?: string;
}

export interface DataTableProps<TData extends RowData> {
  columns: ColumnDef<AppTableFeatures, TData, CellData>[];
  data: TData[];
  searchKey?: string;
  searchPlaceholder?: string;
  filterOptions?: { label: string; value: string }[];
  filterKey?: string;
  filterTitle?: string;
  /**
   * Extra faceted filters, for tables that narrow on more than one column.
   * The `filterKey` / `filterTitle` / `filterOptions` triple above stays
   * supported and is rendered first.
   */
  filters?: DataTableFilter[];
  defaultPageSize?: number;
  pageSizeOptions?: number[];
  bulkActions?: BulkAction[];
  onBulkAction?: (_action: string, _ids: (number | string)[]) => void;
  emptyState?: React.ReactNode;
  loading?: boolean;
  toolbarRight?: React.ReactNode;
}

const SKELETON_ROWS = 5;

export type { DataTableFilter };

export function DataTable<TData extends RowData>({
  columns,
  data,
  searchKey,
  searchPlaceholder,
  filterOptions,
  filterKey,
  filterTitle,
  filters,
  defaultPageSize = 10,
  pageSizeOptions = [10, 20, 50, 100],
  bulkActions,
  onBulkAction,
  emptyState,
  loading = false,
  toolbarRight,
}: DataTableProps<TData>) {
  const [rowSelection, setRowSelection] = React.useState({});
  const [columnVisibility, setColumnVisibility] = React.useState<ColumnVisibilityState>({});
  const [columnFilters, setColumnFilters] = React.useState<ColumnFiltersState>([]);
  const [sorting, setSorting] = React.useState<SortingState>([]);
  const [confirmAction, setConfirmAction] = React.useState<{
    action: BulkAction;
    ids: (number | string)[];
  } | null>(null);

  const tableColumns = React.useMemo<ColumnDef<AppTableFeatures, TData, CellData>[]>(() => {
    const selectCol: ColumnDef<AppTableFeatures, TData, CellData> = {
      id: "select",
      header: ({ table }) => (
        <Checkbox
          checked={table.getIsAllPageRowsSelected()}
          indeterminate={table.getIsSomePageRowsSelected()}
          onCheckedChange={(value) => table.toggleAllPageRowsSelected(!!value)}
          aria-label="Select all"
        />
      ),
      cell: ({ row }) => (
        <Checkbox
          checked={row.getIsSelected()}
          onCheckedChange={(value) => row.toggleSelected(!!value)}
          aria-label="Select row"
        />
      ),
      enableSorting: false,
      enableHiding: false,
    };
    return [selectCol, ...columns];
  }, [columns]);

  const table = useTable({
    features: tableFeaturesConfig,
    data,
    columns: tableColumns,
    state: {
      sorting,
      columnVisibility,
      rowSelection,
      columnFilters,
    },
    enableRowSelection: true,
    onRowSelectionChange: setRowSelection,
    onSortingChange: setSorting,
    onColumnFiltersChange: setColumnFilters,
    onColumnVisibilityChange: setColumnVisibility,
    initialState: {
      pagination: {
        pageIndex: 0,
        pageSize: defaultPageSize,
      },
    },
  });

  const selectedRows = table.getFilteredSelectedRowModel().rows;
  const selectedRowsCount = selectedRows.length;

  const selectedStatuses = React.useMemo(
    () =>
      selectedRows
        .map((r) => (r.original as { status?: string }).status ?? "")
        .filter((s): s is string => s !== ""),
    [selectedRows],
  );

  const effectiveBulkActions = React.useMemo(() => {
    if (bulkActions) return bulkActions;
    const hasDraftable = selectedStatuses.some((s) => ["publish", "future"].includes(s));
    const hasPublishable = selectedStatuses.some((s) => ["draft", "pending", "future"].includes(s));
    const defaults: BulkAction[] = [];
    if (hasPublishable) defaults.push({ label: "Publish", value: "publish" });
    if (hasDraftable) defaults.push({ label: "Move to Draft", value: "draft" });
    defaults.push({ label: "Delete", value: "delete", destructive: true });
    return defaults;
  }, [bulkActions, selectedStatuses]);

  return (
    <div data-slot="data-table" className="space-y-4">
      <DataTableToolbar
        table={table}
        searchKey={searchKey}
        searchPlaceholder={searchPlaceholder}
        filterOptions={filterOptions}
        filterKey={filterKey}
        filterTitle={filterTitle}
        filters={filters}
        loading={loading}
        toolbarRight={toolbarRight}
      />
      {selectedRowsCount > 0 && onBulkAction && (
        <div className="flex items-center gap-2 px-2 py-1">
          <span className="text-sm text-muted-foreground">
            {selectedRowsCount} selected
          </span>
          <DropdownMenu>
            <DropdownMenuTrigger render={<Button variant="outline" size="sm" />}>
              Bulk Actions <ChevronDownIcon />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start">
              {effectiveBulkActions
                .filter((action) => !action.show || action.show(selectedStatuses))
                .map((action) => (
                  <DropdownMenuItem
                    key={action.value}
                    onClick={() => {
                      const ids = selectedRows
                        .map((r) => (r.original as Record<string, unknown>).id as number | string)
                        .filter((id): id is number | string => id != null);
                      if (action.destructive) {
                        setConfirmAction({ action, ids });
                      } else {
                        onBulkAction(action.value, ids);
                      }
                    }}
                  >
                    {action.label}
                  </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      )}
      <div className="rounded-md border">
        <Table>
          <TableHeader>
            {table.getHeaderGroups().map((headerGroup) => (
              <TableRow key={headerGroup.id}>
                {headerGroup.headers.map((header) => {
                  const columnMeta = header.column.columnDef.meta as
                    | undefined
                    | { className?: string };
                  return (
                    <TableHead key={header.id} colSpan={header.colSpan} className={columnMeta?.className}>
                      {header.isPlaceholder
                        ? null
                        : flexRender(header.column.columnDef.header, header.getContext())}
                    </TableHead>
                  );
                })}
              </TableRow>
            ))}
          </TableHeader>
          <TableBody>
            {loading ? (
              Array.from({ length: SKELETON_ROWS }).map((_, index) => (
                <TableRow key={`skeleton-${index}`}>
                  <TableCell>
                    <Skeleton className="h-4 w-4" />
                  </TableCell>
                  {columns.map((_, colIndex) => (
                    <TableCell key={colIndex}>
                      <Skeleton className="h-6 w-full" />
                    </TableCell>
                  ))}
                </TableRow>
              ))
            ) : table.getRowModel().rows?.length ? (
              table.getRowModel().rows.map((row) => (
                <TableRow key={row.id} data-state={row.getIsSelected() ? "selected" : undefined}>
                  {row.getVisibleCells().map((cell) => {
                    const columnMeta = cell.column.columnDef.meta as
                      | undefined
                      | { className?: string };
                    return (
                      <TableCell key={cell.id} className={columnMeta?.className}>
                        {flexRender(cell.column.columnDef.cell, cell.getContext())}
                      </TableCell>
                    );
                  })}
                </TableRow>
              ))
            ) : (
              <TableRow>
                <TableCell colSpan={tableColumns.length} className="h-24 text-center">
                  {emptyState ?? "No results."}
                </TableCell>
              </TableRow>
            )}
          </TableBody>
        </Table>
      </div>
      <DataTablePagination table={table} pageSizeOptions={pageSizeOptions} />
      <AlertDialog
        open={!!confirmAction}
        onOpenChange={(open) => {
          if (!open) setConfirmAction(null);
        }}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle className="flex items-center gap-2">
              <AlertTriangleIcon className="size-5 text-destructive" />
              {confirmAction?.action.confirmTitle ?? "Confirm Action"}
            </AlertDialogTitle>
            <AlertDialogDescription>
              {confirmAction?.action.confirmDescription ??
                `Are you sure you want to ${confirmAction?.action.label.toLowerCase()} ${confirmAction?.ids.length} selected item(s)?`}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction
              variant="destructive"
              onClick={() => {
                if (!(confirmAction && onBulkAction)) return;
                onBulkAction(confirmAction.action.value, confirmAction.ids);
                setConfirmAction(null);
              }}
            >
              {confirmAction?.action.confirmLabel ?? confirmAction?.action.label}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}
