"use client";
import { cn } from "@/lib/utils";
import {
  Command,
  CommandEmpty,
  CommandGroup,
  CommandItem,
  CommandList,
} from "@/Components/ui/command";
import { InputGroup, InputGroupAddon, InputGroupInput } from "@/Components/ui/input-group";
import { Spinner } from "@/Components/ui/spinner";
import { MapPinIcon, NavigationIcon, SearchIcon, XIcon } from "lucide-react";
import * as React from "react";

interface PhotonProperties {
  osm_id: number;
  name?: string;
  street?: string;
  housenumber?: string;
  city?: string;
  locality?: string;
  state?: string;
  country?: string;
  [key: string]: unknown;
}

interface PhotonFeature {
  properties: PhotonProperties;
  geometry: {
    coordinates: [number, number];
    type: string;
  };
  type: string;
}

interface PhotonResponse {
  features: PhotonFeature[];
}

interface BuildSearchUrlParams {
  query: string;
  bbox?: [number, number, number, number];
  lang?: string;
  lat?: number;
  limit?: number;
  locationBiasScale?: number;
  lon?: number;
  zoom?: number;
}

function formatAddress(properties: PhotonProperties): string {
  const parts: string[] = [];
  if (properties.name) {
    parts.push(properties.name);
  }
  if (properties.housenumber && properties.street) {
    parts.push(`${properties.housenumber} ${properties.street}`);
  } else if (properties.street) {
    parts.push(properties.street);
  }
  if (properties.city) {
    parts.push(properties.city);
  } else if (properties.locality) {
    parts.push(properties.locality);
  }
  if (properties.state && properties.state !== properties.city) {
    parts.push(properties.state);
  }
  if (properties.country) {
    parts.push(properties.country);
  }
  return [...new Set(parts)].join(", ");
}

function buildSearchUrl({
  query,
  bbox,
  lang,
  lat,
  limit,
  locationBiasScale,
  lon,
  zoom,
}: BuildSearchUrlParams): string {
  const url = new URL("https://photon.komoot.io/api");
  url.searchParams.set("q", query);
  if (lang) url.searchParams.set("lang", lang);
  if (limit) url.searchParams.set("limit", String(limit));
  if (bbox) url.searchParams.set("bbox", bbox.join(","));
  if (lat !== void 0 && lon !== void 0) {
    url.searchParams.set("lat", String(lat));
    url.searchParams.set("lon", String(lon));
  }
  if (zoom !== void 0) url.searchParams.set("zoom", String(zoom));
  if (locationBiasScale !== void 0)
    url.searchParams.set("location_bias_scale", String(locationBiasScale));
  return url.href;
}

function useDebounce(value: string, delay = 300): string {
  const [debouncedValue, setDebouncedValue] = React.useState(value);
  React.useEffect(() => {
    const timer = setTimeout(() => setDebouncedValue(value), delay);
    return () => clearTimeout(timer);
  }, [value, delay]);
  return debouncedValue;
}

interface UsePlaceSearchProps {
  debounceMs?: number;
  query: string;
  lang?: string;
  limit?: number;
  bbox?: [number, number, number, number];
  lat?: number;
  lon?: number;
  zoom?: number;
  locationBiasScale?: number;
}

function usePlaceSearch({ debounceMs, query, ...props }: UsePlaceSearchProps): {
  results: PhotonFeature[];
  isLoading: boolean;
  error: Error | null;
  hasSearched: boolean;
} {
  const [results, setResults] = React.useState<PhotonFeature[]>([]);
  const [isLoading, setIsLoading] = React.useState(false);
  const [error, setError] = React.useState<Error | null>(null);
  const [hasSearched, setHasSearched] = React.useState(false);
  const debouncedQuery = useDebounce(query, debounceMs);

  React.useEffect(() => {
    if (!debouncedQuery.trim()) {
      const timer = setTimeout(() => {
        setResults([]);
        setIsLoading(false);
        setHasSearched(false);
      }, 0);
      return () => clearTimeout(timer);
    }

    const abortController = new AbortController();

    async function fetchResults() {
      setIsLoading(true);
      setError(null);
      setHasSearched(true);

      try {
        const url = buildSearchUrl({ query: debouncedQuery, ...props });
        const response = await fetch(url, { signal: abortController.signal });

        if (!response.ok) {
          throw new Error(`Photon API error: ${response.status} ${response.statusText}`);
        }

        const data: PhotonResponse = await response.json();
        const addressOsmIds = new Set<number>();
        const dedupedFeatures = data.features.filter((feature) => {
          const id = feature.properties.osm_id;
          if (addressOsmIds.has(id)) return false;
          addressOsmIds.add(id);
          return true;
        });

        setResults(dedupedFeatures);
      } catch (error_) {
        if (error_ instanceof Error && error_.name !== "AbortError") {
          setError(error_);
          setResults([]);
        }
      } finally {
        setIsLoading(false);
      }
    }

    fetchResults();

    return () => abortController.abort();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [
    debouncedQuery,
    props.lang,
    props.limit,
    props.bbox,
    props.lat,
    props.lon,
    props.zoom,
    props.locationBiasScale,
  ]);

  return { results, isLoading, error, hasSearched };
}

interface PlaceAutocompleteProps {
  debounceMs?: number;
  lang?: string;
  limit?: number;
  bbox?: [number, number, number, number];
  lat?: number;
  lon?: number;
  zoom?: number;
  locationBiasScale?: number;
  className?: string;
  value?: string;
  defaultValue?: string;
  onChange?: (_value: string) => void;
  onClear?: () => void;
  onPlaceSelect?: (_feature: PhotonFeature) => void;
  onResultsChange?: (_results: PhotonFeature[]) => void;
  onLocateClick?: () => void;
}

function PlaceAutocomplete({
  debounceMs = 300,
  lang,
  limit = 5,
  bbox,
  lat,
  lon,
  zoom,
  locationBiasScale,
  className,
  value: controlledValue,
  defaultValue = "",
  onChange: controlledOnChange,
  onClear,
  onPlaceSelect,
  onResultsChange,
  onLocateClick,
}: PlaceAutocompleteProps) {
  const [internalValue, setInternalValue] = React.useState(defaultValue);
  const [searchQuery, setSearchQuery] = React.useState("");

  const isControlled = controlledValue !== void 0;
  const displayValue = isControlled ? controlledValue : internalValue;

  const { results, isLoading, error, hasSearched } = usePlaceSearch({
    query: searchQuery,
    debounceMs,
    lang,
    limit,
    bbox,
    lat,
    lon,
    zoom,
    locationBiasScale,
  });

  React.useEffect(() => {
    onResultsChange?.(results);
  }, [results, onResultsChange]);

  const hasNoResults = hasSearched && !isLoading && !error && results.length === 0;
  const showCommandList = error || hasNoResults || results.length > 0;

  return (
    <Command className={cn("h-fit overflow-visible", className)} shouldFilter={false} loop>
      <div className="relative">
        <InputGroup
          className={cn("border-input! bg-popover! ring-0!", showCommandList && "rounded-b-none")}
        >
          <InputGroupAddon>
            <SearchIcon />
          </InputGroupAddon>
          <InputGroupInput
            placeholder="Search"
            value={displayValue}
            onChange={(event: React.ChangeEvent<HTMLInputElement>) => {
              const newValue = event.target.value;
              if (!isControlled) setInternalValue(newValue);
              setSearchQuery(newValue);
              controlledOnChange?.(newValue);
            }}
          />
          {displayValue && (
            <button
              type="button"
              onClick={() => {
                if (!isControlled) setInternalValue("");
                setSearchQuery("");
                controlledOnChange?.("");
                onClear?.();
              }}
              className="bg-transparent! hover:bg-slate-100 dark:hover:bg-slate-800 text-muted-foreground mr-1 rounded-full p-1 transition-colors"
              aria-label="Clear search"
            >
              <XIcon className="h-4 w-4" />
            </button>
          )}
          {isLoading && (
            <InputGroupAddon align="inline-end">
              <Spinner />
            </InputGroupAddon>
          )}
        </InputGroup>
        {showCommandList && (
          <CommandList
            data-state={showCommandList ? "open" : "closed"}
            className={cn(
              "bg-popover border-input absolute top-full right-0 left-0 rounded-b-md border border-t-0 shadow-md z-[1020]",
              "data-[state=open]:animate-in data-[state=closed]:animate-out",
              "data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0",
              "data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95",
              "data-[state=open]:slide-in-from-top-2 data-[state=closed]:slide-out-to-top-2",
            )}
          >
            {error && <CommandEmpty>Error: {error.message}</CommandEmpty>}

            {onLocateClick && (
              <CommandGroup>
                <CommandItem
                  onSelect={() => {
                    setSearchQuery("");
                    onLocateClick();
                  }}
                >
                  <NavigationIcon className="size-4" />
                  <div className="flex flex-col items-start text-start">
                    <span className="font-medium">Use current location</span>
                    <span className="text-muted-foreground text-xs">GPS</span>
                  </div>
                </CommandItem>
              </CommandGroup>
            )}

            {hasNoResults && !onLocateClick && (
              <CommandEmpty>Can't find {displayValue}.</CommandEmpty>
            )}

            {results.length > 0 && (
              <CommandGroup>
                {results.map((feature) => {
                  const formattedAddress = formatAddress(feature.properties);
                  return (
                    <CommandItem
                      key={feature.properties.osm_id}
                      value={String(feature.properties.osm_id)}
                      onSelect={() => {
                        const formattedAddress2 = formatAddress(feature.properties);
                        if (!isControlled) setInternalValue(formattedAddress2);
                        setSearchQuery("");
                        controlledOnChange?.(formattedAddress2);
                        onPlaceSelect?.(feature);
                      }}
                    >
                      <MapPinIcon />
                      <div className="flex flex-col items-start text-start">
                        <span className="font-medium">
                          {feature.properties.name || feature.properties.street || "Unknown"}
                        </span>
                        <span className="text-muted-foreground text-xs">{formattedAddress}</span>
                      </div>
                    </CommandItem>
                  );
                })}
              </CommandGroup>
            )}
          </CommandList>
        )}
      </div>
    </Command>
  );
}

export { PlaceAutocomplete, formatAddress };
