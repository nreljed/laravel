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

import { AppTableFeatures, tableFeaturesConfig } from "@/lib/table-features";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/Components/ui/table";
import { DataTablePagination } from "@/Components/ui/data-table-pagination";
import { DataTableToolbar } from "@/Components/ui/data-table-toolbar";
import { Button } from "@/Components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/Components/ui/dropdown-menu";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/Components/ui/alert-dialog";
import { AlertTriangleIcon, ChevronDownIcon } from "lucide-react";
import { Checkbox } from "@/Components/ui/checkbox";

interface BulkAction {
  label: string;
  value: string;
  show?: (_statuses: string[]) => boolean;
  destructive?: boolean;
  confirmTitle?: string;
  confirmDescription?: string;
  confirmLabel?: string;
}

interface DataTableProps<TData extends RowData> {
  columns: ColumnDef<AppTableFeatures, TData, CellData>[];
  data: TData[];
  searchKey?: string;
  searchPlaceholder?: string;
  filterOptions?: { label: string; value: string }[];
  filterKey?: string;
  filterTitle?: string;
  pageSize?: number;
  bulkActions?: BulkAction[];
  onBulkAction?: (_action: string, _ids: number[]) => void;
  emptyState?: React.ReactNode;
}

export function DataTable<TData extends RowData>({
  columns,
  data,
  searchKey,
  searchPlaceholder,
  filterOptions,
  filterKey,
  filterTitle,
  pageSize = 10,
  bulkActions,
  onBulkAction,
  emptyState,
}: DataTableProps<TData>) {
  const [rowSelection, setRowSelection] = React.useState({});
  const [columnVisibility, setColumnVisibility] = React.useState<ColumnVisibilityState>({});
  const [columnFilters, setColumnFilters] = React.useState<ColumnFiltersState>([]);
  const [sorting, setSorting] = React.useState<SortingState>([]);
  const [confirmAction, setConfirmAction] = React.useState<{
    action: BulkAction;
    ids: number[];
  } | null>(null);

  const tableColumns = React.useMemo<ColumnDef<AppTableFeatures, TData, CellData>[]>(() => {
    if (!bulkActions) return columns;
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
  }, [columns, bulkActions]);

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
        pageSize,
      },
    },
  });

  return (
    <div data-slot="data-table" className="space-y-4">
      <DataTableToolbar
        table={table}
        searchKey={searchKey}
        searchPlaceholder={searchPlaceholder}
        filterOptions={filterOptions}
        filterKey={filterKey}
        filterTitle={filterTitle}
      />
      {bulkActions && onBulkAction && table.getFilteredSelectedRowModel().rows.length > 0 && (
        <div className="flex items-center gap-2 px-2 py-1">
          <span className="text-sm text-muted-foreground">
            {table.getFilteredSelectedRowModel().rows.length} selected
          </span>
          <DropdownMenu>
            <DropdownMenuTrigger render={<Button variant="outline" size="sm" />}>
              Bulk Actions <ChevronDownIcon />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start">
              {bulkActions.map((action) => {
                const selectedStatuses = table
                  .getFilteredSelectedRowModel()
                  .rows.map((r) => (r.original as { status?: string }).status ?? "");
                if (action.show && !action.show(selectedStatuses)) return null;
                return (
                  <DropdownMenuItem
                    key={action.value}
                    onClick={() => {
                      const ids = table
                        .getFilteredSelectedRowModel()
                        .rows.map((r) => (r.original as Record<string, unknown>).id as number)
                        .filter((id): id is number => id != null);
                      if (action.destructive) {
                        setConfirmAction({ action, ids });
                      } else {
                        onBulkAction(action.value, ids);
                      }
                    }}
                  >
                    {action.label}
                  </DropdownMenuItem>
                );
              })}
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      )}
      <div className="rounded-md border">
        <Table>
          <TableHeader>
            {table.getHeaderGroups().map((headerGroup) => (
              <TableRow key={headerGroup.id}>
                {headerGroup.headers.map((header) => (
                  <TableHead key={header.id} colSpan={header.colSpan}>
                    {header.isPlaceholder
                      ? null
                      : flexRender(header.column.columnDef.header, header.getContext())}
                  </TableHead>
                ))}
              </TableRow>
            ))}
          </TableHeader>
          <TableBody>
            {table.getRowModel().rows?.length ? (
              table.getRowModel().rows.map((row) => (
                <TableRow key={row.id} data-state={row.getIsSelected() ? "selected" : undefined}>
                  {row.getVisibleCells().map((cell) => (
                    <TableCell key={cell.id}>
                      {flexRender(cell.column.columnDef.cell, cell.getContext())}
                    </TableCell>
                  ))}
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
      <DataTablePagination table={table} />
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
