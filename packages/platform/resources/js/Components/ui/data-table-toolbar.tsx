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

/**
 * One faceted filter dropdown on a table toolbar.
 *
 * The single-filter props (`filterKey` / `filterTitle` / `filterOptions`) are
 * the original API and still work; `filters` exists for tables that need to
 * narrow on more than one column at a time - a product list asked to filter
 * both publication state and whether the product is in the store, for
 * instance. Two questions, two dropdowns, rather than making the second
 * question unreachable.
 */
export type DataTableFilter = {
  key: string;
  title?: string;
  options: { label: string; value: string }[];
};

interface DataTableToolbarProps<TData extends RowData> {
  table: ReactTable<AppTableFeatures, TData>;
  searchKey?: string;
  /**
   * Multi-column search mode: the box filters every row rather than one column,
   * narrowing on any listed key. The query was owned by the table (it must
   * pre-filter `data`), so the toolbar receives the value plus its setter.
   */
  searchKeys?: string[];
  searchQuery?: string;
  onSearchQueryChange?: (_value: string) => void;
  searchPlaceholder?: string;
  filterOptions?: { label: string; value: string }[];
  filterKey?: string;
  filterTitle?: string;
  filters?: DataTableFilter[];
  loading?: boolean;
  toolbarRight?: React.ReactNode;
}

export function DataTableToolbar<TData extends RowData>({
  table,
  searchKey,
  searchKeys,
  searchQuery = "",
  onSearchQueryChange,
  searchPlaceholder = "Search...",
  filterOptions,
  filterKey,
  filterTitle,
  filters,
  loading = false,
  toolbarRight,
}: DataTableToolbarProps<TData>) {
  const isFiltered = table.state.columnFilters.length > 0;

  const multiSearch = searchKeys && searchKeys.length > 0;
  const resolvedPlaceholder = searchPlaceholder !== "Search..." ? searchPlaceholder : multiSearch ? `Search ${searchKeys.join(", ")}...` : "Search...";

  // The legacy single filter is folded into the same list so there is one
  // rendering path and no chance of the two disagreeing about "All". Built
  // inline rather than memoised: it is two small objects, and this file
  // deliberately imports no React runtime of its own.
  const resolvedFilters: DataTableFilter[] = [];

  if (filterKey && filterOptions?.length) {
    resolvedFilters.push({ key: filterKey, title: filterTitle, options: filterOptions });
  }

  for (const filter of filters ?? []) {
    if (filter.key && filter.options?.length) resolvedFilters.push(filter);
  }

  return (
    <div
      data-slot="data-table-toolbar"
      className="flex w-full items-center justify-between gap-2"
    >
      <div className="flex flex-1 items-center gap-2">
        {multiSearch ? (
          <div className="relative">
            <SearchIcon className="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input
              placeholder={resolvedPlaceholder}
              value={searchQuery}
              onChange={(event) => onSearchQueryChange?.(event.target.value)}
              className="h-8 w-full max-w-sm pl-8 sm:w-[250px] lg:w-[300px]"
              disabled={loading}
            />
          </div>
        ) : searchKey ? (
          <div className="relative">
            <SearchIcon className="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input
              placeholder={resolvedPlaceholder}
              value={(table.getColumn(searchKey)?.getFilterValue() as string) ?? ""}
              onChange={(event) => table.getColumn(searchKey)?.setFilterValue(event.target.value)}
              className="h-8 w-full max-w-sm pl-8 sm:w-[250px] lg:w-[300px]"
              disabled={loading}
            />
          </div>
        ) : null}
        {resolvedFilters.map((filter) => (
          <Select
            key={filter.key}
            value={(table.getColumn(filter.key)?.getFilterValue() as string) ?? ""}
            onValueChange={(value) => {
              table.getColumn(filter.key)?.setFilterValue(value === "all" ? "" : value);
            }}
          >
            <SelectTrigger className="h-8 w-full sm:w-[150px]">
              <SelectValue placeholder={filter.title ?? "Filter"} />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All</SelectItem>
              {filter.options.map((opt) => (
                <SelectItem key={opt.value} value={opt.value}>
                  {opt.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        ))}
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
