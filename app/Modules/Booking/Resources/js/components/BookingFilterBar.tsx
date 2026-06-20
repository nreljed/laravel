import { useState, useCallback } from "react";
import { Search, X } from "lucide-react";
import { Input } from "@/Components/ui/input";
import { Button } from "@/Components/ui/button";

interface BookingFilterBarProps {
  statuses: { value: string; label: string }[];
  properties: { id: number; name: string }[];
  onFiltersChange: (filters: Record<string, string>) => void;
  initialFilters?: Record<string, string>;
}

export function BookingFilterBar({
  statuses,
  properties,
  onFiltersChange,
  initialFilters = {},
}: BookingFilterBarProps) {
  const [search, setSearch] = useState(initialFilters.search || "");
  const [status, setStatus] = useState(initialFilters.status || "");
  const [propertyId, setPropertyId] = useState(initialFilters.property_id || "");

  const applyFilters = useCallback(() => {
    const filters: Record<string, string> = {};
    if (search) filters.search = search;
    if (status) filters.status = status;
    if (propertyId) filters.property_id = propertyId;
    onFiltersChange(filters);
  }, [search, status, propertyId, onFiltersChange]);

  const resetFilters = useCallback(() => {
    setSearch("");
    setStatus("");
    setPropertyId("");
    onFiltersChange({});
  }, [onFiltersChange]);

  const hasActiveFilters = status || propertyId;

  return (
    <div className="flex flex-wrap items-end gap-2">
      <div className="relative flex-1 min-w-[200px]">
        <Search className="absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
        <Input
          placeholder="Search guests or ID..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          onKeyDown={(e) => e.key === "Enter" && applyFilters()}
          className="pl-8"
        />
      </div>
      <select
        value={status}
        onChange={(e) => {
          setStatus(e.target.value);
          const filters: Record<string, string> = {};
          if (search) filters.search = search;
          if (e.target.value) filters.status = e.target.value;
          if (propertyId) filters.property_id = propertyId;
          onFiltersChange(filters);
        }}
        className="flex h-9 w-[130px] rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm"
      >
        {statuses.map((s) => (
          <option key={s.value} value={s.value}>{s.label}</option>
        ))}
      </select>
      <select
        value={propertyId}
        onChange={(e) => {
          setPropertyId(e.target.value);
          const filters: Record<string, string> = {};
          if (search) filters.search = search;
          if (status) filters.status = status;
          if (e.target.value) filters.property_id = e.target.value;
          onFiltersChange(filters);
        }}
        className="flex h-9 w-[180px] rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm"
      >
        <option value="">All Properties</option>
        {properties.map((p) => (
          <option key={p.id} value={String(p.id)}>{p.name}</option>
        ))}
      </select>
      <Button type="button" variant="secondary" size="sm" onClick={applyFilters}>
        Apply
      </Button>
      {hasActiveFilters && (
        <Button type="button" variant="ghost" size="sm" onClick={resetFilters}>
          <X className="mr-1 h-4 w-4" />
          Reset
        </Button>
      )}
    </div>
  );
}
