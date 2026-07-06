import * as React from "react";
import { Section, SectionField } from "@/Components/section";
import { Input } from "@/Components/ui/input";
import { Switch } from "@/Components/ui/switch";
import { Separator } from "@/Components/ui/separator";
import { Building2, MapIcon, Globe, ZoomIn, BugIcon, ArrowRight } from "lucide-react";
import { getCountryBounds, getCountryName } from "@/lib/country-bounds";

interface PropertiesSectionProps {
  data: Record<string, string | undefined>;
  onChange: (_field: string, _value: string) => void;
  errors: Record<string, string>;
  loading?: boolean;
  onSubmit?: (_e: React.FormEvent) => void;
  asAccordion?: boolean;
}

export function PropertiesSection({
  data,
  onChange,
  errors,
  loading,
  onSubmit,
  asAccordion = false,
}: PropertiesSectionProps) {
  const autoFillFromCountry = React.useCallback(
    (code: string) => {
      onChange("map_country_code", code);
      const cb = getCountryBounds(code);
      if (!cb) return;
      onChange("map_center_lat", String(cb.center[0]));
      onChange("map_center_lng", String(cb.center[1]));
      onChange("map_bounds_south_west_lat", String(cb.bounds[0]));
      onChange("map_bounds_south_west_lng", String(cb.bounds[1]));
      onChange("map_bounds_north_east_lat", String(cb.bounds[2]));
      onChange("map_bounds_north_east_lng", String(cb.bounds[3]));
      onChange("map_min_zoom", String(cb.minZoom));
      onChange("map_default_zoom", String(cb.defaultZoom));
    },
    [onChange],
  );

  const handleMapCountry = React.useCallback(
    (field: string, value: string) => {
      if (field === "map_country_code" && value.length === 2) {
        autoFillFromCountry(value.toUpperCase());
      } else {
        onChange(field, value);
      }
    },
    [onChange, autoFillFromCountry],
  );

  const countryCode = (data?.map_country_code || "").toUpperCase();
  const countryName = getCountryName(countryCode);
  const countryInfo = React.useMemo(() => {
    const cb = getCountryBounds(countryCode);
    if (!cb) return null;
    return cb;
  }, [countryCode]);

  return (
    <div className="space-y-6">
      <div className="rounded-md border border-border bg-muted/40 px-4 py-3 text-sm text-muted-foreground">
        <div className="flex items-start gap-2">
          <ArrowRight className="mt-0.5 size-4 shrink-0" />
          <span>
            Currency, number formatting, date format, time format, and timezone are set globally in{" "}
            <a
              href="/dashboard/admin/settings?tab=general"
              className="font-medium text-foreground underline underline-offset-2 hover:text-primary"
            >
              General settings
            </a>
            .
          </span>
        </div>
      </div>

      <Section
        title="Map Settings"
        description="Country restriction and zoom defaults for the property map"
        icon={MapIcon}
        asAccordion={asAccordion}
        onSubmit={onSubmit}
        loading={loading}
      >
        <div className="flex items-center gap-2 pb-1">
          <Globe className="size-4 text-muted-foreground" />
          <span className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
            Country & Region
          </span>
        </div>

        <SectionField
          label="Country"
          htmlFor="map_country_code"
          error={errors?.map_country_code}
          description="ISO 3166-1 alpha-2 code. Type 2 letters to auto-configure bounds, center, and zoom."
        >
          <div className="flex items-center gap-2">
            <Input
              id="map_country_code"
              type="text"
              value={countryCode}
              onChange={(e) => handleMapCountry("map_country_code", e.target.value.toUpperCase())}
              placeholder="TN"
              maxLength={2}
              className="w-20"
              disabled={loading}
            />
            {countryName && <span className="text-sm text-muted-foreground">{countryName}</span>}
            {!countryName && countryCode.length === 2 && (
              <span className="text-sm text-destructive">Unknown country code</span>
            )}
          </div>
        </SectionField>

        {countryInfo && (
          <div className="rounded-md bg-muted/50 border border-border px-3 py-2 text-xs text-muted-foreground space-y-0.5">
            <div className="flex items-center gap-1.5">
              <span className="font-medium">Auto-configured from country selection</span>
            </div>
            <p>
              Region: {countryName || countryCode} &mdash; Center:{" "}
              {countryInfo.center[0].toFixed(1)}&deg;N, {countryInfo.center[1].toFixed(1)}&deg;E
              &mdash; Zoom: {countryInfo.minZoom}&ndash;{data?.map_max_zoom || 18} &mdash; Bounds:{" "}
              {countryInfo.bounds[0].toFixed(1)}&ndash;{countryInfo.bounds[2].toFixed(1)}&deg;N
              &times; {countryInfo.bounds[1].toFixed(1)}&ndash;{countryInfo.bounds[3].toFixed(1)}
              &deg;E
            </p>
            <p>Changing the country code above will automatically update these values on save.</p>
          </div>
        )}

        <Separator className="my-4" />

        <div className="flex items-center gap-2 pb-1">
          <ZoomIn className="size-4 text-muted-foreground" />
          <span className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
            Zoom Levels
          </span>
        </div>

        <p className="text-xs text-muted-foreground mb-3">
          These four levels control at what zoom the map starts, how close it gets when a marker is
          clicked, and how far it can zoom in/out.
        </p>

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <SectionField
            label="Initial Default Zoom"
            htmlFor="map_default_zoom"
            error={errors?.map_default_zoom}
            description="What the map shows on first load, before any marker is placed. Used on the create form and when no location is saved. Auto-set per country."
          >
            <Input
              id="map_default_zoom"
              type="number"
              min={1}
              max={20}
              value={data?.map_default_zoom ?? ""}
              onChange={(e) => handleMapCountry("map_default_zoom", e.target.value)}
              placeholder={String(countryInfo?.defaultZoom ?? 7)}
              disabled={loading}
            />
          </SectionField>

          <SectionField
            label="Marker Reveal Zoom"
            htmlFor="map_marker_zoom"
            error={errors?.map_marker_zoom}
            description="How close the map zooms when a marker is placed or selected. Typically 15&ndash;18 for street-level detail."
          >
            <Input
              id="map_marker_zoom"
              type="number"
              min={1}
              max={20}
              value={data?.map_marker_zoom ?? ""}
              onChange={(e) => handleMapCountry("map_marker_zoom", e.target.value)}
              placeholder="18"
              disabled={loading}
            />
          </SectionField>

          <SectionField
            label="Minimum Zoom (outer limit)"
            htmlFor="map_min_zoom"
            error={errors?.map_min_zoom}
            description="How far the map can zoom out when locked to the country. Stops the user from seeing too much outside the configured region. Auto-set per country."
          >
            <Input
              id="map_min_zoom"
              type="number"
              min={1}
              max={20}
              value={data?.map_min_zoom ?? ""}
              onChange={(e) => handleMapCountry("map_min_zoom", e.target.value)}
              placeholder={String(countryInfo?.minZoom ?? 6)}
              disabled={loading}
            />
          </SectionField>

          <SectionField
            label="Maximum Zoom (inner limit)"
            htmlFor="map_max_zoom"
            error={errors?.map_max_zoom}
            description="How far the map can zoom in. Practical maximum is 18&ndash;19 for most tile servers."
          >
            <Input
              id="map_max_zoom"
              type="number"
              min={1}
              max={20}
              value={data?.map_max_zoom ?? ""}
              onChange={(e) => handleMapCountry("map_max_zoom", e.target.value)}
              placeholder="18"
              disabled={loading}
            />
          </SectionField>
        </div>

        <Separator className="my-4" />

        <div className="flex items-center gap-2 pb-1">
          <BugIcon className="size-4 text-muted-foreground" />
          <span className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
            Debug
          </span>
        </div>

        <SectionField
          label="Show Debug Panel"
          htmlFor="map_debug_show"
          error={errors?.map_debug_show}
          description="Enable the debug panel by default on the property map."
        >
          <Switch
            id="map_debug_show"
            checked={data?.map_debug_show !== "0"}
            onCheckedChange={(checked) => handleMapCountry("map_debug_show", checked ? "1" : "0")}
            disabled={loading}
          />
        </SectionField>
      </Section>

      <Section
        title="Feature Toggles"
        description="Enable or disable property-specific features"
        icon={Building2}
        asAccordion
        onSubmit={onSubmit}
        loading={loading}
      >
        <div className="flex items-center gap-6 pt-2">
          <label className="flex items-center gap-2 cursor-pointer">
            <Switch
              id="enable_property_reviews"
              checked={data?.enable_property_reviews !== "0"}
              onCheckedChange={(checked) =>
                onChange("enable_property_reviews", checked ? "1" : "0")
              }
              disabled={loading}
            />
            <span className="text-sm">Enable Reviews</span>
          </label>
          <label className="flex items-center gap-2 cursor-pointer">
            <Switch
              id="require_approval"
              checked={data?.require_approval === "1"}
              onCheckedChange={(checked) => onChange("require_approval", checked ? "1" : "0")}
              disabled={loading}
            />
            <span className="text-sm">Require Approval</span>
          </label>
        </div>
      </Section>
    </div>
  );
}
