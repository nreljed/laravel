import { useState, useCallback } from "react";
import { Search, SlidersHorizontal, X } from "lucide-react";
import { Input } from "@nreljed/platform/Components/ui/input";
import { Button } from "@nreljed/platform/Components/ui/button";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@nreljed/platform/Components/ui/select";
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@nreljed/platform/Components/ui/popover";
import { cn } from "@nreljed/platform/lib/utils";

interface FilterItem {
  id: number;
  name: string;
}

interface FilterState {
  search: string;
  status: string;
  type_ids: string[];
  category_ids: string[];
  feature_ids: string[];
  featured: string;
  price_min: string;
  price_max: string;
}

interface PropertyFilterBarProps {
  types: FilterItem[];
  categories: FilterItem[];
  statuses?: { value: string; label: string }[];
  onFiltersChange: (filters: Partial<FilterState>) => void;
  initialFilters?: Partial<FilterState>;
}

const DEFAULT_STATUSES = [
  { value: "", label: "All Statuses" },
  { value: "draft", label: "Draft" },
  { value: "published", label: "Published" },
  { value: "archived", label: "Archived" },
];

export function PropertyFilterBar({
  types,
  categories,
  statuses = DEFAULT_STATUSES,
  onFiltersChange,
  initialFilters = {},
}: PropertyFilterBarProps) {
  const [showAdvanced, setShowAdvanced] = useState(false);
  const [filters, setFilters] = useState<FilterState>({
    search: initialFilters.search || "",
    status: initialFilters.status || "",
    type_ids: initialFilters.type_ids || [],
    category_ids: initialFilters.category_ids || [],
    feature_ids: initialFilters.feature_ids || [],
    featured: initialFilters.featured || "",
    price_min: initialFilters.price_min || "",
    price_max: initialFilters.price_max || "",
  });

  const updateFilter = useCallback(
    (key: keyof FilterState, value: string | string[]) => {
      const newFilters = { ...filters, [key]: value };
      setFilters(newFilters);
      onFiltersChange(newFilters);
    },
    [filters, onFiltersChange],
  );

  const handleSearch = useCallback(
    (value: string) => {
      updateFilter("search", value);
    },
    [updateFilter],
  );

  const resetFilters = useCallback(() => {
    const reset: FilterState = {
      search: "",
      status: "",
      type_ids: [],
      category_ids: [],
      feature_ids: [],
      featured: "",
      price_min: "",
      price_max: "",
    };
    setFilters(reset);
    onFiltersChange(reset);
  }, [onFiltersChange]);

  const hasActiveFilters =
    filters.status || filters.type_ids.length || filters.category_ids.length ||
    filters.feature_ids.length || filters.featured || filters.price_min || filters.price_max;

  return (
    <div className="space-y-3">
      <div className="flex items-center gap-2">
        <div className="relative flex-1">
          <Search className="absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
          <Input
            placeholder="Search properties..."
            value={filters.search}
            onChange={(e) => handleSearch(e.target.value)}
            className="pl-8"
          />
        </div>
        <Button
          type="button"
          variant="outline"
          size="sm"
          onClick={() => setShowAdvanced(!showAdvanced)}
          className={cn(showAdvanced && "bg-accent")}
        >
          <SlidersHorizontal className="mr-1 h-4 w-4" />
          Filters
        </Button>
        {hasActiveFilters && (
          <Button type="button" variant="ghost" size="sm" onClick={resetFilters}>
            <X className="mr-1 h-4 w-4" />
            Reset
          </Button>
        )}
      </div>

      {showAdvanced && (
        <div className="grid grid-cols-2 gap-3 rounded-md border p-3 sm:grid-cols-3 lg:grid-cols-4">
          <div className="space-y-1">
            <label className="text-xs font-medium text-muted-foreground">Status</label>
            <Select
              value={filters.status}
              onValueChange={(v) => updateFilter("status", v)}
            >
              <SelectTrigger className="h-8">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {statuses.map((s) => (
                  <SelectItem key={s.value} value={s.value}>{s.label}</SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <div className="space-y-1">
            <label className="text-xs font-medium text-muted-foreground">Featured</label>
            <Select
              value={filters.featured}
              onValueChange={(v) => updateFilter("featured", v)}
            >
              <SelectTrigger className="h-8">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="">All</SelectItem>
                <SelectItem value="1">Featured</SelectItem>
                <SelectItem value="0">Not Featured</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div className="space-y-1">
            <label className="text-xs font-medium text-muted-foreground">Types</label>
            <Popover>
              <PopoverTrigger
                render={<Button variant="outline" className="h-8 w-full justify-start text-xs font-normal" />}
              >
                {filters.type_ids.length > 0
                  ? `${filters.type_ids.length} selected`
                  : "All types"}
              </PopoverTrigger>
              <PopoverContent className="w-56 p-2">
                <div className="space-y-1">
                  {types.map((type) => (
                    <label
                      key={type.id}
                      className="flex items-center gap-2 rounded px-2 py-1 text-xs hover:bg-accent cursor-pointer"
                    >
                      <input
                        type="checkbox"
                        checked={filters.type_ids.includes(String(type.id))}
                        onChange={() => {
                          const next = filters.type_ids.includes(String(type.id))
                            ? filters.type_ids.filter((id) => id !== String(type.id))
                            : [...filters.type_ids, String(type.id)];
                          updateFilter("type_ids", next);
                        }}
                        className="h-3.5 w-3.5"
                      />
                      {type.name}
                    </label>
                  ))}
                </div>
              </PopoverContent>
            </Popover>
          </div>

          <div className="space-y-1">
            <label className="text-xs font-medium text-muted-foreground">City</label>
            <Popover>
              <PopoverTrigger
                render={<Button variant="outline" className="h-8 w-full justify-start text-xs font-normal" />}
              >
                {filters.category_ids.length > 0
                  ? `${filters.category_ids.length} selected`
                  : "All categories"}
              </PopoverTrigger>
              <PopoverContent className="w-56 p-2">
                <div className="space-y-1">
                  {categories.map((cat) => (
                    <label
                      key={cat.id}
                      className="flex items-center gap-2 rounded px-2 py-1 text-xs hover:bg-accent cursor-pointer"
                    >
                      <input
                        type="checkbox"
                        checked={filters.category_ids.includes(String(cat.id))}
                        onChange={() => {
                          const next = filters.category_ids.includes(String(cat.id))
                            ? filters.category_ids.filter((id) => id !== String(cat.id))
                            : [...filters.category_ids, String(cat.id)];
                          updateFilter("category_ids", next);
                        }}
                        className="h-3.5 w-3.5"
                      />
                      {cat.name}
                    </label>
                  ))}
                </div>
              </PopoverContent>
            </Popover>
          </div>

          <div className="space-y-1">
            <label className="text-xs font-medium text-muted-foreground">Min Price</label>
            <Input
              type="number"
              placeholder="0"
              value={filters.price_min}
              onChange={(e) => updateFilter("price_min", e.target.value)}
              className="h-8"
            />
          </div>

          <div className="space-y-1">
            <label className="text-xs font-medium text-muted-foreground">Max Price</label>
            <Input
              type="number"
              placeholder="9999999"
              value={filters.price_max}
              onChange={(e) => updateFilter("price_max", e.target.value)}
              className="h-8"
            />
          </div>
        </div>
      )}
    </div>
  );
}
