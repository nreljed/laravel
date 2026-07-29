import { cn } from "@/lib/utils";
import { BugIcon, ChevronDownIcon, ChevronRightIcon, CodeIcon } from "lucide-react";
import * as React from "react";

interface DebugData {
  latitude: number | null;
  longitude: number | null;
  street: string;
  housenumber: string;
  name: string;
  city: string;
  state: string;
  postcode: string;
  country: string;
  countryCode: string;
  district: string;
  locality: string;
  fullAddress: string;
  displayName: string;
  matchedGovernorate: string | null;
  matchedCity: string | null;
  geocodingSource: string | null;
  isWithinTunisia: boolean;
  countryCodeMatch: boolean;
  configuredCountry?: string;
  osmId: number | null;
  osmType: string;
  rawJson: string;
  updatedAt: Date | null;
  overpassStreet?: string | null;
  overpassHighway?: string | null;
  overpassPerpDist?: number | null;
  overpassConfidence?: string | null;
}

interface MapDebugPanelProps {
  data: DebugData | null;
  zoom?: number;
  defaultOpen?: boolean;
  className?: string;
}

function formatCoord(value: number | null): string {
  if (value === null) return "\u{2014}";
  return value.toFixed(6);
}

function formatDate(date: Date | null): string {
  if (!date) return "\u{2014}";
  return date.toLocaleTimeString();
}

function RawJsonViewer({ json }: { json: string }) {
  const [expanded, setExpanded] = React.useState(false);
  if (!json) return <span className="text-muted-foreground italic">No data</span>;
  return (
    <div>
      <button
        type="button"
        onClick={() => setExpanded(!expanded)}
        className="flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground transition-colors"
      >
        <CodeIcon className="size-3" />
        {expanded ? "Hide raw" : "Show raw"}
      </button>
      {expanded && (
        <pre className="mt-1 p-1.5 bg-muted rounded text-[10px] leading-tight overflow-x-auto max-h-40 overflow-y-auto whitespace-pre-wrap break-all">
          {json}
        </pre>
      )}
    </div>
  );
}

function DebugRow({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <div className="flex items-center justify-between gap-2 px-3 py-1.5 text-sm">
      <span className="text-muted-foreground font-medium">{label}</span>
      <span
        className="text-foreground font-mono text-xs text-right max-w-[60%] truncate"
        title={typeof value === "string" ? value : undefined}
      >
        {value}
      </span>
    </div>
  );
}

function DebugFieldRow({
  label,
  value,
  present,
}: {
  label: string;
  value: string;
  present: boolean;
}) {
  return (
    <div className="flex items-center justify-between gap-2 px-3 py-1 text-sm">
      <div className="flex items-center gap-1.5">
        <span
          className={`size-1.5 rounded-full shrink-0 ${present ? "bg-green-500" : "bg-muted-foreground/30"}`}
        />
        <span className="text-muted-foreground font-medium">{label}</span>
      </div>
      <span
        className={`font-mono text-xs text-right max-w-[55%] truncate ${present ? "text-foreground" : "text-muted-foreground/50 italic"}`}
      >
        {present ? value : "\u{2014}"}
      </span>
    </div>
  );
}

function DebugSection({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div>
      <div className="px-3 pt-2 pb-1 text-xs font-semibold uppercase tracking-wider text-muted-foreground/60">
        {label}
      </div>
      {children}
    </div>
  );
}

export function MapDebugPanel({ data, zoom, defaultOpen = false, className }: MapDebugPanelProps) {
  const [isOpen, setIsOpen] = React.useState(defaultOpen);

  const hasData = data && (data.latitude !== null || data.longitude !== null);

  return (
    <div className={cn("rounded-xl border bg-card text-card-foreground shadow-sm", className)}>
      <button
        type="button"
        onClick={() => setIsOpen(!isOpen)}
        className="flex w-full items-center justify-between px-4 py-2.5 text-sm font-medium hover:bg-muted/50 transition-colors rounded-t-xl"
      >
        <span className="flex items-center gap-2">
          <BugIcon className="size-4 text-muted-foreground" />
          Location Debug Data
          {hasData && (
            <span className="inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-semibold text-green-600 border-green-200 bg-green-50 dark:text-green-400 dark:border-green-800 dark:bg-green-950">
              LIVE
            </span>
          )}
        </span>
        {isOpen ? (
          <ChevronDownIcon className="size-4 text-muted-foreground" />
        ) : (
          <ChevronRightIcon className="size-4 text-muted-foreground" />
        )}
      </button>

      {isOpen && (
        <div className="border-t divide-y divide-border">
          {!hasData ? (
            <div className="px-4 py-6 text-center text-sm text-muted-foreground">
              <BugIcon className="mx-auto size-8 mb-2 opacity-40" />
              <p>No location data yet.</p>
              <p className="text-xs mt-1">
                Place a marker on the map or use the search to see debug info.
              </p>
            </div>
          ) : (
            <>
              <DebugSection label="Coordinates">
                <DebugRow label="Latitude" value={formatCoord(data!.latitude)} />
                <DebugRow label="Longitude" value={formatCoord(data!.longitude)} />
                <DebugRow
                  label="Zoom"
                  value={
                    zoom != null ? (
                      <span className="text-blue-600 dark:text-blue-400 font-semibold">{zoom}</span>
                    ) : (
                      "\u{2014}"
                    )
                  }
                />
                <DebugRow
                  label="Within bounds"
                  value={
                    data!.isWithinTunisia ? (
                      <span className="text-green-600 dark:text-green-400 font-semibold">
                        \u2713 Yes
                      </span>
                    ) : (
                      <span className="text-destructive font-semibold">\u2717 No</span>
                    )
                  }
                />
                <DebugRow
                  label={
                    data!.configuredCountry
                      ? `Matches ${data!.configuredCountry} (geo)`
                      : "Country match (geo)"
                  }
                  value={
                    data!.countryCodeMatch ? (
                      <span className="text-green-600 dark:text-green-400 font-semibold">
                        \u2713 Yes
                      </span>
                    ) : (
                      <span className="text-amber-500 dark:text-amber-400 font-semibold">
                        \u2717 No
                      </span>
                    )
                  }
                />
              </DebugSection>

              <DebugSection label="API Fields (green = populated)">
                <DebugFieldRow label="Name" value={data!.name} present={!!data!.name} />
                <DebugFieldRow
                  label="Housenumber"
                  value={data!.housenumber}
                  present={!!data!.housenumber}
                />
                <DebugFieldRow label="Street" value={data!.street} present={!!data!.street} />
                <DebugFieldRow label="District" value={data!.district} present={!!data!.district} />
                <DebugFieldRow label="City" value={data!.city} present={!!data!.city} />
                <DebugFieldRow label="State" value={data!.state} present={!!data!.state} />
                <DebugFieldRow label="Postcode" value={data!.postcode} present={!!data!.postcode} />
                <DebugFieldRow
                  label="Country"
                  value={data!.country || data!.countryCode}
                  present={!!data!.country}
                />
                <DebugFieldRow label="Locality" value={data!.locality} present={!!data!.locality} />
              </DebugSection>

              <DebugSection label="Full Address">
                <div className="px-3 pb-2">
                  <p className="text-xs font-mono text-foreground leading-relaxed break-all">
                    {data!.fullAddress || "\u{2014}"}
                  </p>
                </div>
              </DebugSection>

              <DebugSection label="City Matching">
                <DebugRow
                  label="Governorate (matched)"
                  value={
                    data!.matchedGovernorate ? (
                      <span className="text-green-600 dark:text-green-400">
                        {data!.matchedGovernorate}
                      </span>
                    ) : (
                      <span className="text-muted-foreground italic">Not matched</span>
                    )
                  }
                />
                <DebugRow
                  label="Locality (matched)"
                  value={
                    data!.matchedCity ? (
                      <span className="text-green-600 dark:text-green-400">
                        {data!.matchedCity}
                      </span>
                    ) : (
                      <span className="text-muted-foreground italic">Not matched</span>
                    )
                  }
                />
              </DebugSection>

              <DebugSection label="Metadata">
                <DebugRow label="Geocoding source" value={data!.geocodingSource || "\u{2014}"} />
                <DebugRow label="OSM ID" value={data!.osmId ? String(data!.osmId) : "\u{2014}"} />
                <DebugRow label="OSM type" value={data!.osmType || "\u{2014}"} />
                <DebugRow label="Last updated" value={formatDate(data!.updatedAt)} />
              </DebugSection>

              {data!.overpassStreet && (
                <DebugSection label="Overpass Street Detection">
                  <DebugFieldRow
                    label="Street"
                    value={data!.overpassStreet}
                    present={!!data!.overpassStreet}
                  />
                  <DebugFieldRow
                    label="Highway type"
                    value={data!.overpassHighway || ""}
                    present={!!data!.overpassHighway}
                  />
                  <DebugRow
                    label="Perp. distance"
                    value={
                      data!.overpassPerpDist != null
                        ? `${data!.overpassPerpDist.toFixed(1)}m`
                        : "\u{2014}"
                    }
                  />
                  <DebugRow
                    label="Confidence"
                    value={
                      data!.overpassConfidence === "high" ? (
                        <span className="text-green-600 dark:text-green-400 font-semibold">
                          High
                        </span>
                      ) : data!.overpassConfidence === "medium" ? (
                        <span className="text-amber-600 dark:text-amber-400">Medium</span>
                      ) : data!.overpassConfidence === "low" ? (
                        <span className="text-destructive">Low</span>
                      ) : (
                        "\u{2014}"
                      )
                    }
                  />
                </DebugSection>
              )}

              <DebugSection label="Raw Response">
                <div className="px-3 pb-2">
                  <RawJsonViewer json={data!.rawJson} />
                </div>
              </DebugSection>
            </>
          )}
        </div>
      )}
    </div>
  );
}

export type { DebugData, MapDebugPanelProps };
export default MapDebugPanel;
