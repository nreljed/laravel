import { XIcon } from "lucide-react";
import type { ReactTable, RowData } from "@tanstack/react-table";

import { AppTableFeatures } from "@/lib/table-features";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/Components/ui/select";

interface DataTableToolbarProps<TData extends RowData> {
  table: ReactTable<AppTableFeatures, TData>;
  searchKey?: string;
  searchPlaceholder?: string;
  filterOptions?: { label: string; value: string }[];
  filterKey?: string;
  filterTitle?: string;
}

export function DataTableToolbar<TData extends RowData>({
  table,
  searchKey,
  searchPlaceholder = "Search...",
  filterOptions,
  filterKey,
  filterTitle,
}: DataTableToolbarProps<TData>) {
  const isFiltered = table.state.columnFilters.length > 0;

  return (
    <div
      data-slot="data-table-toolbar"
      className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
    >
      <div className="flex flex-1 flex-col gap-2 sm:flex-row sm:items-center sm:gap-2">
        {searchKey && (
          <Input
            placeholder={searchPlaceholder}
            value={(table.getColumn(searchKey)?.getFilterValue() as string) ?? ""}
            onChange={(event) => table.getColumn(searchKey)?.setFilterValue(event.target.value)}
            className="h-8 w-full sm:w-[150px] lg:w-[250px]"
          />
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
      </div>
    </div>
  );
}
