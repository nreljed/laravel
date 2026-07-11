import { Search, X } from "lucide-react";
import type { FormEvent } from "react";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/Components/ui/select";

interface FilterOption {
  value: string;
  label: string;
}

interface FilterConfig {
  key: string;
  label: string;
  placeholder?: string;
  options: FilterOption[];
}

interface FilterBarProps {
  searchPlaceholder?: string;
  searchValue: string;
  onSearchChange: (value: string) => void;
  onSubmit: (e: FormEvent) => void;
  filters: FilterConfig[];
  filterValues: Record<string, string>;
  onFilterChange: (key: string, value: string) => void;
  onClear: () => void;
  showClear?: boolean;
}

export default function FilterBar({
  searchPlaceholder = "Search...",
  searchValue,
  onSearchChange,
  onSubmit,
  filters,
  filterValues,
  onFilterChange,
  onClear,
  showClear = false,
}: FilterBarProps) {
  return (
    <div className="flex flex-wrap items-center gap-3">
      <form onSubmit={onSubmit} className="flex items-center gap-2">
        <div className="relative">
          <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
          <Input
            placeholder={searchPlaceholder}
            value={searchValue}
            onChange={(e) => onSearchChange(e.target.value)}
            className="w-60 pl-8"
          />
        </div>
        <Button type="submit" variant="secondary" size="sm">
          Search
        </Button>
      </form>

      {filters.map((filter) => (
        <Select
          key={filter.key}
          value={filterValues[filter.key] || ""}
          onValueChange={(v) => onFilterChange(filter.key, v)}
        >
          <SelectTrigger className="w-40">
            <SelectValue placeholder={filter.placeholder || `All ${filter.label.toLowerCase()}`} />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="">{filter.placeholder || `All ${filter.label.toLowerCase()}`}</SelectItem>
            {filter.options.map((opt) => (
              <SelectItem key={opt.value} value={opt.value}>
                {opt.label}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      ))}

      {showClear && (
        <Button variant="ghost" size="sm" onClick={onClear}>
          <X className="mr-1 h-3 w-3" /> Clear
        </Button>
      )}
    </div>
  );
}
