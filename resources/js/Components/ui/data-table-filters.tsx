"use client";

import type { Column } from "@tanstack/react-table";
import { cn } from "@/lib/utils";

interface DataTableFilterProps<TData, TValue> {
  column?: Column<TData, TValue>;
  title?: string;
  options: { label: string; value: string }[];
}

export function DataTableFilter<TData, TValue>({
  column,
  options,
}: DataTableFilterProps<TData, TValue>) {
  const facetedValues = column?.getFacetedUniqueValues();
  const selectedValues = new Set(column?.getFilterValue() as string[]);

  return (
    <div className="p-1">
      {options.map((option) => {
        const isSelected = selectedValues.has(option.value);
        return (
          <button
            key={option.value}
            className={cn(
              "flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-hidden hover:bg-accent hover:text-accent-foreground",
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
                "flex h-4 w-4 items-center justify-center rounded-sm border border-primary",
                isSelected ? "bg-primary text-primary-foreground" : "opacity-50 [&_svg]:hidden",
              )}
            >
              <svg
                width="10"
                height="10"
                viewBox="0 0 10 10"
                fill="none"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
                strokeLinejoin="round"
              >
                <polyline points="1.5,5 4,7.5 8.5,2" />
              </svg>
            </span>
            <span className="flex-1">{option.label}</span>
            {facetedValues?.get(option.value) && (
              <span className="ml-auto flex h-4 w-4 items-center justify-center font-mono text-xs">
                {facetedValues.get(option.value)}
              </span>
            )}
          </button>
        );
      })}
    </div>
  );
}
