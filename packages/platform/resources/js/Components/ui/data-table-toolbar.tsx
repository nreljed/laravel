import { XIcon, SearchIcon, Settings2Icon } from "lucide-react";
import type { ReactTable, RowData } from "@tanstack/react-table";

import { AppTableFeatures } from "../../lib/table-features";
import { Button } from "./button";
import { Input } from "./input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "./select";
import {
  DropdownMenu,
  DropdownMenuCheckboxItem,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "./dropdown-menu";

interface DataTableToolbarProps<TData extends RowData> {
  table: ReactTable<AppTableFeatures, TData>;
  searchKey?: string;
  searchPlaceholder?: string;
  filterOptions?: { label: string; value: string }[];
  filterKey?: string;
  filterTitle?: string;
  loading?: boolean;
  toolbarRight?: React.ReactNode;
}

export function DataTableToolbar<TData extends RowData>({
  table,
  searchKey,
  searchPlaceholder = "Search...",
  filterOptions,
  filterKey,
  filterTitle,
  loading = false,
  toolbarRight,
}: DataTableToolbarProps<TData>) {
  const isFiltered = table.state.columnFilters.length > 0;

  return (
    <div
      data-slot="data-table-toolbar"
      className="flex w-full items-center justify-between gap-2"
    >
      <div className="flex flex-1 items-center gap-2">
        {searchKey && (
          <div className="relative">
            <SearchIcon className="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input
              placeholder={searchPlaceholder}
              value={(table.getColumn(searchKey)?.getFilterValue() as string) ?? ""}
              onChange={(event) => table.getColumn(searchKey)?.setFilterValue(event.target.value)}
              className="h-8 w-full max-w-sm pl-8 sm:w-[250px] lg:w-[300px]"
              disabled={loading}
            />
          </div>
        )}
        {filterKey && filterOptions && (
          <Select
            value={(table.getColumn(filterKey)?.getFilterValue() as string) ?? ""}
            onValueChange={(value) => {
              table.getColumn(filterKey)?.setFilterValue(value === "all" ? "" : value);
            }}
          >
            <SelectTrigger className="h-8 w-full sm:w-[150px]">
              <SelectValue placeholder={filterTitle ?? "Filter"} />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All</SelectItem>
              {filterOptions.map((opt) => (
                <SelectItem key={opt.value} value={opt.value}>
                  {opt.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        )}
        {isFiltered && (
          <Button
            variant="ghost"
            onClick={() => table.resetColumnFilters()}
            className="h-8 px-2 lg:px-3"
          >
            Reset
            <XIcon />
          </Button>
        )}
        {toolbarRight}
      </div>
      <div className="flex items-center gap-2">
        <DropdownMenu>
          <DropdownMenuTrigger
            render={
              <Button variant="outline" size="sm" className="hidden text-foreground md:flex" />
            }
          >
            <Settings2Icon className="mr-2 size-3.5" />
            View
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            <DropdownMenuGroup>
              <DropdownMenuLabel>Toggle columns</DropdownMenuLabel>
            </DropdownMenuGroup>
            <DropdownMenuSeparator />
            {table
              .getAllColumns()
              .filter((column) => column.getCanHide())
              .map((column) => (
                <DropdownMenuCheckboxItem
                  key={column.id}
                  className="capitalize text-foreground"
                  checked={column.getIsVisible()}
                  onCheckedChange={(value) => column.toggleVisibility(!!value)}
                >
                  {column.id}
                </DropdownMenuCheckboxItem>
              ))}
          </DropdownMenuContent>
        </DropdownMenu>
      </div>
    </div>
  );
}
