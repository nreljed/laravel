import { CheckIcon } from "lucide-react";
import type { CellData, Column, RowData } from "@tanstack/react-table";

import { AppTableFeatures } from "@/lib/table-features";
import { cn } from "@/lib/utils";
import { Button } from "@/Components/ui/button";

interface DataTableFilterProps<TData extends RowData, TValue extends CellData = CellData> {
  column?: Column<AppTableFeatures, TData, TValue>;
  title?: string;
  options: { label: string; value: string }[];
}

export function DataTableFilter<TData extends RowData, TValue extends CellData = CellData>({
  column,
  options,
}: DataTableFilterProps<TData, TValue>) {
  const facetedValues = column?.getFacetedUniqueValues();
  const selectedValues = new Set(column?.getFilterValue() as string[]);

  return (
    <div data-slot="data-table-filters" className="p-1">
      {options.map((option) => {
        const isSelected = selectedValues.has(option.value);
        return (
          <Button
            key={option.value}
            variant="ghost"
            className={cn(
              "flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-sm font-normal justify-start",
              isSelected && "bg-accent",
            )}
            onClick={() => {
              const newSelected = new Set(selectedValues);
              if (isSelected) {
                newSelected.delete(option.value);
              } else {
                newSelected.add(option.value);
              }
              const filterValues = [...newSelected];
              column?.setFilterValue(filterValues.length > 0 ? filterValues : undefined);
            }}
          >
            <span
              className={cn(
                "flex size-4 shrink-0 items-center justify-center rounded-sm border border-primary",
                isSelected ? "bg-primary text-primary-foreground" : "opacity-50",
              )}
            >
              {isSelected && <CheckIcon className="size-3" />}
            </span>
            <span className="flex-1 text-left">{option.label}</span>
            {facetedValues?.get(option.value) && (
              <span className="ml-auto flex size-4 items-center justify-center font-mono text-xs">
                {facetedValues.get(option.value)}
              </span>
            )}
          </Button>
        );
      })}
    </div>
  );
}
