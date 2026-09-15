import { Head, Link, router, usePage } from "@inertiajs/react";
import { useState } from "react";
import {
  Bed,
  Bath,
  Move,
  MapPin,
  Search,
  SlidersHorizontal,
  X,
  ChevronLeft,
  ChevronRight,
  Home,
  Sparkles,
} from "lucide-react";
import { cn } from "@nreljed/platform/lib/utils";
import type { PageProps as AppPageProps } from "@nreljed/platform/types";
import { Button } from "@nreljed/platform/Components/ui/button";
import { Input } from "@nreljed/platform/Components/ui/input";
import { Badge } from "@nreljed/platform/Components/ui/badge";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@nreljed/platform/Components/ui/select";
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from "@nreljed/platform/Components/ui/sheet";
import { Separator } from "@nreljed/platform/Components/ui/separator";
import { SiteHeader } from "@nreljed/platform/Components/frontend/SiteHeader";
import { SiteFooter } from "@nreljed/platform/Components/frontend/SiteFooter";

interface TermItem {
  id: number;
  name: string;
  slug: string;
  icon?: string;
}

interface PropertyImage {
  id: number;
  url: string;
  thumbnail_url: string | null;
  alt_text: string | null;
  is_featured: boolean;
}

interface PropertyItem {
  id: number;
  name: string;
  slug: string;
  price_formatted: string;
  surface_formatted: string;
  featured: boolean;
  metadata: Record<string, unknown> | null;
  types: TermItem[];
  statuses: TermItem[];
  city: string | null;
  city_obj?: { id: number; name: string; slug: string } | null;
  featuredImage: PropertyImage | null;
  images: PropertyImage[];
  user: { id: number; name: string } | null;
}

interface PaginatedData {
  data: PropertyItem[];
  current_page: number;
  last_page: number;
  total: number;
  links: { url: string | null; label: string; active: boolean }[];
}

interface Filters {
  search?: string;
  type_id?: string;
  city_id?: string;
  status_id?: string;
  price_min?: string;
  price_max?: string;
  surface_min?: string;
  surface_max?: string;
  bedrooms?: string;
  sort?: string;
}

function getMetaNum(meta: Record<string, unknown> | null, key: string): number | null {
  if (!meta) return null;
  const val = meta[key];
  if (typeof val === "number") return val;
  if (typeof val === "string") {
    const n = Number(val);
    return Number.isNaN(n) ? null : n;
  }
  return null;
}

function PropertyCard({ property }: { property: PropertyItem }) {
  const imgUrl = property.featuredImage?.url || property.images?.[0]?.url || null;
  const locationName = property.city_obj?.name || property.city || null;
  const firstStatus = property.statuses?.[0] || null;
  const firstType = property.types?.[0] || null;
  const bedrooms = getMetaNum(property.metadata, "bedrooms");
  const bathrooms = getMetaNum(property.metadata, "bathrooms");

  return (
    <Link
      href={route("properties.show", property.slug)}
      className="group block overflow-hidden rounded-xl border border-border bg-card transition-all hover:shadow-lg hover:-translate-y-0.5"
    >
      <div className="relative aspect-[4/3] overflow-hidden bg-muted">
        {imgUrl ? (
          <img
            src={imgUrl}
            alt={property.name}
            className="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
          />
        ) : (
          <div className="flex h-full items-center justify-center">
            <Home className="h-12 w-12 text-muted-foreground/40" />
          </div>
        )}
        {property.featured && (
          <Badge className="absolute left-2 top-2 gap-1 bg-amber-500 text-white hover:bg-amber-500">
            <Sparkles className="h-3 w-3" />
            Featured
          </Badge>
        )}
        {firstStatus && (
          <Badge
            variant="secondary"
            className="absolute bottom-2 left-2 bg-background/90 backdrop-blur-sm"
          >
            {firstStatus.name}
          </Badge>
        )}
        {firstType && (
          <Badge
            variant="outline"
            className="absolute bottom-2 right-2 bg-background/90 backdrop-blur-sm"
          >
            {firstType.name}
          </Badge>
        )}
      </div>
      <div className="space-y-2 p-4">
        <div className="flex items-start justify-between gap-2">
          <div className="min-w-0 flex-1">
            <h3 className="truncate text-base font-semibold text-foreground">{property.name}</h3>
            {locationName && (
              <p className="mt-0.5 flex items-center gap-1 text-sm text-muted-foreground">
                <MapPin className="h-3.5 w-3.5 shrink-0" />
                <span className="truncate">{locationName}</span>
              </p>
            )}
          </div>
          {property.price_formatted && (
            <p className="shrink-0 text-lg font-bold text-primary">{property.price_formatted}</p>
          )}
        </div>
        <div className="flex flex-wrap items-center gap-3 text-sm text-muted-foreground">
          {bedrooms !== null && (
            <span className="flex items-center gap-1">
              <Bed className="h-3.5 w-3.5" />
              {bedrooms} {bedrooms === 1 ? "Bed" : "Beds"}
            </span>
          )}
          {bathrooms !== null && (
            <span className="flex items-center gap-1">
              <Bath className="h-3.5 w-3.5" />
              {bathrooms} {bathrooms === 1 ? "Bath" : "Baths"}
            </span>
          )}
          {property.surface_formatted && (
            <span className="flex items-center gap-1">
              <Move className="h-3.5 w-3.5" />
              {property.surface_formatted}
            </span>
          )}
        </div>
      </div>
    </Link>
  );
}

interface FilterContext {
  filters: Filters;
  statuses: TermItem[];
  types: TermItem[];
  cities: TermItem[];
  applyFilter: (_key: string, _value: string | undefined) => void;
  hasActiveFilters: boolean;
  clearFilters: () => void;
}

function FilterContent({ column, ctx }: { column?: boolean; ctx: FilterContext }) {
  const { filters, statuses, types, cities, applyFilter, hasActiveFilters, clearFilters } = ctx;
  return (
    <div className={cn("space-y-5", column && "flex flex-col")}>
      <div className="space-y-2">
        <label className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
          Status
        </label>
        <div className="flex flex-wrap gap-1.5">
          {statuses.map((s) => (
            <Badge
              key={s.id}
              variant={filters.status_id === String(s.id) ? "default" : "outline"}
              className="cursor-pointer px-3 py-1.5 text-xs"
              onClick={() =>
                applyFilter("status_id", filters.status_id === String(s.id) ? "" : String(s.id))
              }
            >
              {s.name}
            </Badge>
          ))}
        </div>
      </div>
      <Separator />
      <div className="space-y-2">
        <label className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
          Type
        </label>
        <Select value={filters.type_id || ""} onValueChange={(v) => applyFilter("type_id", v)}>
          <SelectTrigger>
            <SelectValue placeholder="All types" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value=" ">All types</SelectItem>
            {types.map((t) => (
              <SelectItem key={t.id} value={String(t.id)}>
                {t.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>
      <div className="space-y-2">
        <label className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
          City
        </label>
        <Select value={filters.city_id || ""} onValueChange={(v) => applyFilter("city_id", v)}>
          <SelectTrigger>
            <SelectValue placeholder="All cities" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value=" ">All cities</SelectItem>
            {cities.map((c) => (
              <SelectItem key={c.id} value={String(c.id)}>
                {c.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>
      <Separator />
      <div className="space-y-2">
        <label className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
          Price Range
        </label>
        <div className="flex items-center gap-2">
          <Input
            type="number"
            placeholder="Min"
            defaultValue={filters.price_min || ""}
            className="h-9"
            onBlur={(e) => applyFilter("price_min", e.target.value || "")}
          />
          <span className="text-muted-foreground">-</span>
          <Input
            type="number"
            placeholder="Max"
            defaultValue={filters.price_max || ""}
            className="h-9"
            onBlur={(e) => applyFilter("price_max", e.target.value || "")}
          />
        </div>
      </div>
      <div className="space-y-2">
        <label className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
          Surface (m²)
        </label>
        <div className="flex items-center gap-2">
          <Input
            type="number"
            placeholder="Min"
            defaultValue={filters.surface_min || ""}
            className="h-9"
            onBlur={(e) => applyFilter("surface_min", e.target.value || "")}
          />
          <span className="text-muted-foreground">-</span>
          <Input
            type="number"
            placeholder="Max"
            defaultValue={filters.surface_max || ""}
            className="h-9"
            onBlur={(e) => applyFilter("surface_max", e.target.value || "")}
          />
        </div>
      </div>
      <div className="space-y-2">
        <label className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
          Bedrooms
        </label>
        <div className="flex flex-wrap gap-1.5">
          {[1, 2, 3, 4, 5].map((n) => (
            <Badge
              key={n}
              variant={filters.bedrooms === String(n) ? "default" : "outline"}
              className="cursor-pointer px-3 py-1.5 text-xs"
              onClick={() =>
                applyFilter("bedrooms", filters.bedrooms === String(n) ? "" : String(n))
              }
            >
              {n}+
            </Badge>
          ))}
        </div>
      </div>
      {hasActiveFilters && (
        <Button
          variant="ghost"
          size="sm"
          className="w-full gap-2 text-muted-foreground"
          onClick={clearFilters}
        >
          <X className="h-4 w-4" />
          Clear all filters
        </Button>
      )}
    </div>
  );
}

export default function PropertyIndex() {
  const { props } = usePage<
    AppPageProps<{
      properties: PaginatedData;
      filters: Filters;
      types: TermItem[];
      cities: TermItem[];
      statuses: TermItem[];
    }>
  >();

  const { properties, filters, types, cities, statuses } = props;

  const [search, setSearch] = useState(filters.search || "");
  const [mobileFiltersOpen, setMobileFiltersOpen] = useState(false);

  const applyFilter = (key: string, value: string | undefined) => {
    const params: Record<string, string> = {};
    if (search.trim()) params.search = search.trim();
    if (filters.type_id) params.type_id = filters.type_id;
    if (filters.city_id) params.city_id = filters.city_id;
    if (filters.status_id) params.status_id = filters.status_id;
    if (filters.price_min) params.price_min = filters.price_min;
    if (filters.price_max) params.price_max = filters.price_max;
    if (filters.surface_min) params.surface_min = filters.surface_min;
    if (filters.surface_max) params.surface_max = filters.surface_max;
    if (filters.bedrooms) params.bedrooms = filters.bedrooms;
    if (filters.sort) params.sort = filters.sort;
    if (value) params[key] = value;
    router.get(route("properties.index"), params, { preserveState: true, replace: true });
  };

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    applyFilter("search", search);
  };

  const clearFilters = () => {
    setSearch("");
    router.get(route("properties.index"), {}, { preserveState: true, replace: true });
  };

  const hasActiveFilters = Boolean(
    filters.type_id ||
    filters.city_id ||
    filters.status_id ||
    filters.price_min ||
    filters.price_max ||
    filters.surface_min ||
    filters.surface_max ||
    filters.bedrooms ||
    filters.search,
  );

  const filterContext = {
    filters,
    statuses,
    types,
    cities,
    applyFilter,
    hasActiveFilters,
    clearFilters,
  };

  return (
    <>
      <Head title="Properties" />
      <div className="flex min-h-screen flex-col bg-background">
        <SiteHeader />
        <main className="flex-1">
          <div className="bg-gradient-to-b from-primary/5 to-background py-12">
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
              <h1 className="text-3xl font-bold tracking-tight text-foreground sm:text-4xl">
                Properties
              </h1>
              <p className="mt-2 text-muted-foreground">
                Find your perfect property from our curated selection
              </p>
              <form onSubmit={handleSearch} className="mt-6 flex max-w-xl gap-2">
                <div className="relative flex-1">
                  <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                  <Input
                    placeholder="Search properties..."
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    className="pl-9"
                  />
                </div>
                <Button type="submit">Search</Button>
              </form>
            </div>
          </div>
          <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <div className="flex items-center justify-between gap-4">
              <div className="flex items-center gap-2">
                <p className="text-sm text-muted-foreground">
                  <span className="font-medium text-foreground">{properties.total}</span>{" "}
                  {properties.total === 1 ? "property" : "properties"} found
                </p>
                {hasActiveFilters && (
                  <Button
                    variant="ghost"
                    size="sm"
                    className="gap-1 text-xs"
                    onClick={clearFilters}
                  >
                    <X className="h-3 w-3" />
                    Clear
                  </Button>
                )}
              </div>
              <div className="flex items-center gap-2">
                <Select
                  value={filters.sort || "date"}
                  onValueChange={(v) => applyFilter("sort", v)}
                >
                  <SelectTrigger className="w-[180px]">
                    <SelectValue placeholder="Sort by" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="date">Newest First</SelectItem>
                    <SelectItem value="date_asc">Oldest First</SelectItem>
                    <SelectItem value="price">Price: Low to High</SelectItem>
                    <SelectItem value="price_desc">Price: High to Low</SelectItem>
                    <SelectItem value="name">Name: A-Z</SelectItem>
                  </SelectContent>
                </Select>
                <Sheet open={mobileFiltersOpen} onOpenChange={setMobileFiltersOpen}>
                  <SheetTrigger render={<Button variant="outline" size="sm" className="gap-2 lg:hidden" />}>
                    <SlidersHorizontal className="h-4 w-4" />
                    Filters
                  </SheetTrigger>
                  <SheetContent side="left" className="w-full max-w-xs overflow-y-auto">
                    <SheetHeader>
                      <SheetTitle>Filters</SheetTitle>
                    </SheetHeader>
                    <div className="mt-6">
                      <FilterContent column ctx={filterContext} />
                    </div>
                  </SheetContent>
                </Sheet>
              </div>
            </div>
            <div className="mt-6 flex gap-8">
              <aside className="hidden w-64 shrink-0 lg:block">
                <div className="sticky top-24 rounded-xl border border-border bg-card p-5">
                  <FilterContent ctx={filterContext} />
                </div>
              </aside>
              <div className="flex-1">
                {properties.data.length === 0 ? (
                  <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-border py-20 text-center">
                    <Home className="h-12 w-12 text-muted-foreground/40" />
                    <h3 className="mt-4 text-lg font-semibold">No properties found</h3>
                    <p className="mt-1 text-sm text-muted-foreground">
                      Try adjusting your search or filters
                    </p>
                    {hasActiveFilters && (
                      <Button variant="outline" size="sm" className="mt-4" onClick={clearFilters}>
                        Clear all filters
                      </Button>
                    )}
                  </div>
                ) : (
                  <>
                    <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-3">
                      {properties.data.map((property) => (
                        <PropertyCard key={property.id} property={property} />
                      ))}
                    </div>
                    {properties.last_page > 1 && (
                      <div className="mt-10 flex items-center justify-center gap-2">
                        {properties.current_page > 1 && (
                          <Button
                            variant="outline"
                            size="sm"
                            onClick={() =>
                              router.get(
                                route("properties.index"),
                                { ...filters, page: properties.current_page - 1 },
                                { preserveState: true, replace: true },
                              )
                            }
                          >
                            <ChevronLeft className="h-4 w-4" />
                            Previous
                          </Button>
                        )}
                        <div className="flex items-center gap-1">
                          {Array.from({ length: properties.last_page }, (_, i) => i + 1)
                            .filter((p) => {
                              const cur = properties.current_page;
                              return (
                                p === 1 || p === properties.last_page || Math.abs(p - cur) <= 2
                              );
                            })
                            .reduce<(number | "...")[]>((acc, p, idx, arr) => {
                              if (idx > 0 && p - (arr[idx - 1] as number) > 1) acc.push("...");
                              acc.push(p);
                              return acc;
                            }, [])
                            .map((item, i) =>
                              item === "..." ? (
                                <span key={`e${i}`} className="px-1 text-muted-foreground">
                                  ...
                                </span>
                              ) : (
                                <Button
                                  key={item}
                                  variant={item === properties.current_page ? "default" : "outline"}
                                  size="sm"
                                  className="min-w-[36px]"
                                  onClick={() =>
                                    router.get(
                                      route("properties.index"),
                                      { ...filters, page: item },
                                      { preserveState: true, replace: true },
                                    )
                                  }
                                >
                                  {item}
                                </Button>
                              ),
                            )}
                        </div>
                        {properties.current_page < properties.last_page && (
                          <Button
                            variant="outline"
                            size="sm"
                            onClick={() =>
                              router.get(
                                route("properties.index"),
                                { ...filters, page: properties.current_page + 1 },
                                { preserveState: true, replace: true },
                              )
                            }
                          >
                            Next
                            <ChevronRight className="h-4 w-4" />
                          </Button>
                        )}
                      </div>
                    )}
                  </>
                )}
              </div>
            </div>
          </div>
        </main>
        <SiteFooter />
      </div>
    </>
  );
}
