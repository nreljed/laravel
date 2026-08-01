// @ts-nocheck
import * as React from "react";
import { useMap } from "react-leaflet";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/Components/ui/card";
import { Alert, AlertDescription } from "@/Components/ui/alert";
import { Button } from "@/Components/ui/button";
import {
  Map,
  MapTileLayer,
  MapDrawControl,
  MapDrawMarker,
  MapDrawEdit,
  MapDrawDelete,
  MapDrawUndo,
  useLeaflet,
  MapLayers,
  MapControls,
  MapSearchControl,
} from "./map";
import { MapDebugPanel } from "./map-debug-panel";
import type { DebugData } from "./map-debug-panel";
import { reverseGeocode, TUNISIA_BOUNDS, TUNISIA_CENTER } from "@/lib/geocoding";
import type { AddressData } from "@/lib/geocoding";
import { getCountryBounds } from "@/lib/country-bounds";
import { AlertTriangleIcon, BugIcon, MapPin } from "lucide-react";
import { toast } from "@/Components/ui/toast";

interface MapOptions {
  countryCode?: string;
  center?: [number, number];
  defaultZoom?: number;
  minZoom?: number;
  maxZoom?: number;
  markerZoom?: number;
  showDebug?: boolean;
  searchLang?: string;
  searchBbox?: [number, number, number, number];
}

interface MapSectionInnerProps {
  latitude: number | null;
  longitude: number | null;
  address: AddressData | null;
  mapZoom: number | null;
  mapData?: string | null;
  onLatitudeChange: (_lat: number | null) => void;
  onLongitudeChange: (_lng: number | null) => void;
  onAddressChange: (_address: AddressData | null) => void;
  onMapZoomChange: (_zoom: number | null) => void;
  onMapDataChange?: (_geoJson: string | null) => void;
  title?: string;
  description?: string;
  options?: MapOptions;
  errors?: Record<string, string>;
  loading?: boolean;
  className?: string;
}

interface GeoJsonFeature {
  type: string;
  geometry?: { type: string; coordinates: number[] };
  properties?: Record<string, unknown>;
}

function formatAddress(properties: Record<string, unknown>): string {
  const parts: string[] = [];
  if (properties.name) parts.push(properties.name as string);
  if (properties.formatted_address) parts.push(properties.formatted_address as string);
  if (properties.city || properties.locality)
    parts.push((properties.city || properties.locality) as string);
  if (properties.region || properties.state)
    parts.push((properties.region || properties.state) as string);
  if (properties.country) parts.push(properties.country as string);
  return parts.join(", ");
}

function MapLockController({
  isLocked,
  minZoom,
  swLat,
  swLng,
  neLat,
  neLng,
  defaultZoom,
}: {
  isLocked: boolean;
  minZoom: number;
  swLat: number;
  swLng: number;
  neLat: number;
  neLng: number;
  defaultZoom: number;
}) {
  const map = useMap();

  React.useLayoutEffect(() => {
    const handlers: Array<[string, { enable: () => void; disable: () => void } | undefined]> = [
      ["dragging", map.dragging],
      ["scrollWheelZoom", map.scrollWheelZoom],
      ["doubleClickZoom", map.doubleClickZoom],
      ["touchZoom", map.touchZoom],
      ["keyboard", map.keyboard],
      ["boxZoom", map.boxZoom],
    ];

    for (const [, handler] of handlers) {
      if (!handler) continue;
      if (isLocked) handler.disable();
      else handler.enable();
    }

    const effectiveMinZoom = isLocked ? minZoom : defaultZoom;
    map.setMinZoom(effectiveMinZoom);
    map.setMaxBounds([
      [swLat, swLng],
      [neLat, neLng],
    ]);
  }, [map, isLocked, minZoom, swLat, swLng, neLat, neLng, defaultZoom]);

  return null;
}

export default function MapSectionInner({
  latitude,
  longitude,
  address,
  mapZoom,
  mapData,
  onLatitudeChange,
  onLongitudeChange,
  onAddressChange,
  onMapZoomChange,
  onMapDataChange,
  title = "Location",
  description = "Set a map location. Search or click the map to place a marker.",
  options = {},
  errors,
  loading = false,
  className,
}: MapSectionInnerProps) {
  const [searchPlace, setSearchPlace] = React.useState(() => {
    if (address?.fullAddress) return address.fullAddress;
    if (latitude != null && longitude != null)
      return `${latitude.toFixed(4)}, ${longitude.toFixed(4)}`;
    return "";
  });
  const [stopTracking, setStopTracking] = React.useState(false);
  const [map, setMap] = React.useState<L.Map | null>(null);
  const [debugMode, setDebugMode] = React.useState(options.showDebug ?? false);
  const [debugData, setDebugData] = React.useState<DebugData | null>(null);
  const [gpsBannerVisible, setGpsBannerVisible] = React.useState(false);
  const [isReversingGeocode, setIsReversingGeocode] = React.useState(false);
  const [mapZoomLevel, setMapZoomLevel] = React.useState(() => options.minZoom || 15);

  const debugDataRef = React.useRef<DebugData | null>(null);
  React.useEffect(() => {
    debugDataRef.current = debugData;
  }, [debugData]);

  const isInitialized = React.useRef(false);
  const hasLocation = latitude != null && longitude != null;
  const [isLocked, setIsLockedState] = React.useState(() => hasLocation);
  const setIsLocked = React.useCallback((val: boolean | ((_prev: boolean) => boolean)) => {
    setIsLockedState(val);
  }, []);
  const firstLayerChange = React.useRef(true);

  const mapConfig = React.useMemo(() => {
    const countryFallback = options.countryCode ? getCountryBounds(options.countryCode) : null;
    const fbBounds = countryFallback?.bounds || [
      TUNISIA_BOUNDS.southWest.lat,
      TUNISIA_BOUNDS.southWest.lng,
      TUNISIA_BOUNDS.northEast.lat,
      TUNISIA_BOUNDS.northEast.lng,
    ];
    const fbCenter = countryFallback?.center ||
      options.center || [TUNISIA_CENTER.lat, TUNISIA_CENTER.lng];
    const fbMinZoom = countryFallback?.minZoom || 6;
    const fbDefaultZoom = countryFallback?.defaultZoom || 7;

    const swLat = fbBounds[0];
    const swLng = fbBounds[1];
    const neLat = fbBounds[2];
    const neLng = fbBounds[3];

    return {
      centerLat: options.center?.[0] ?? fbCenter[0],
      centerLng: options.center?.[1] ?? fbCenter[1],
      minZoom: options.minZoom || fbMinZoom,
      maxZoom: options.maxZoom || 18,
      defaultZoom: options.defaultZoom || fbDefaultZoom,
      markerZoom: options.markerZoom || 18,
      boundsSouthWest: [swLat, swLng] as [number, number],
      boundsNorthEast: [neLat, neLng] as [number, number],
      isWithinBounds: (lat: number, lng: number) =>
        lat >= swLat && lat <= neLat && lng >= swLng && lng <= neLng,
    };
  }, [options]);

  const getCoordinates = React.useCallback((): [number, number] => {
    if (latitude != null && longitude != null) return [latitude, longitude];
    return [mapConfig.centerLat, mapConfig.centerLng];
  }, [latitude, longitude, mapConfig.centerLat, mapConfig.centerLng]);

  const { L } = useLeaflet();

  const getMarkerIcon = React.useCallback(() => {
    if (!L) return null;
    return L.divIcon({
      className: "",
      iconAnchor: [16, 32],
      popupAnchor: [0, -32],
      html: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-map-pin size-8 filter drop-shadow-lg" style="color:var(--map-marker-color,#2563eb)"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0" fill="var(--map-marker-fill,#dbeafe)"/><circle cx="12" cy="10" r="3"/></svg>`,
    });
  }, [L]);

  const createDrawMarker = React.useCallback(
    (lat: number, lng: number) => {
      if (!L || !map) return;
      const newMarker = L.marker([lat, lng], { icon: getMarkerIcon()!, draggable: true });
      if (newMarker.dragging) newMarker.dragging.disable();
      map.fire("draw:created", { layer: newMarker, layerType: "marker" });
    },
    [L, map, getMarkerIcon],
  );

  const doReverseGeocode = React.useCallback(
    async (lat: number, lng: number) => {
      if (isReversingGeocode) return;
      setIsReversingGeocode(true);

      const buildEmptyDebug = (): DebugData => ({
        latitude: lat,
        longitude: lng,
        street: "",
        housenumber: "",
        name: "",
        city: "",
        state: "",
        postcode: "",
        country: "",
        countryCode: "",
        district: "",
        locality: "",
        fullAddress: "",
        displayName: "",
        matchedGovernorate: null,
        matchedCity: null,
        geocodingSource: null,
        isWithinTunisia: false,
        countryCodeMatch: false,
        configuredCountry: options.countryCode || "",
        osmId: null,
        osmType: "",
        rawJson: "",
        updatedAt: new Date(),
        overpassStreet: null,
        overpassHighway: null,
        overpassPerpDist: null,
        overpassConfidence: null,
      });

      try {
        const addressData = await reverseGeocode(lat, lng);
        if (!addressData) {
          setDebugData(buildEmptyDebug());
          return;
        }

        const addressLine = [addressData.housenumber, addressData.street || addressData.name]
          .filter(Boolean)
          .join(" ");

        if (addressLine)
          onAddressChange({
            ...addressData,
            fullAddress: addressData.fullAddress || addressLine,
          });
        const label = addressData.fullAddress || addressData.displayName || addressLine || "";
        if (label) setSearchPlace(label);

        setDebugData({
          latitude: lat,
          longitude: lng,
          street: addressData.street,
          housenumber: addressData.housenumber,
          name: addressData.name,
          city: addressData.city,
          state: addressData.state,
          postcode: addressData.postcode,
          country: addressData.country,
          countryCode: addressData.countryCode,
          district: addressData.district,
          locality: addressData.locality,
          fullAddress: addressData.fullAddress,
          displayName: addressData.displayName,
          matchedGovernorate: null,
          matchedCity: addressData.city,
          geocodingSource: addressData.source,
          isWithinTunisia: mapConfig.isWithinBounds(lat, lng),
          countryCodeMatch: options.countryCode
            ? addressData.countryCode === options.countryCode
            : false,
          configuredCountry: options.countryCode || "",
          osmId: addressData.osmId,
          osmType: addressData.osmType,
          rawJson: JSON.stringify(addressData.raw, null, 2),
          updatedAt: new Date(),
          overpassStreet: addressData.overpassStreet?.street ?? null,
          overpassHighway: addressData.overpassStreet?.highway ?? null,
          overpassPerpDist: addressData.overpassStreet?.perpDist ?? null,
          overpassConfidence: addressData.overpassStreet?.confidence ?? null,
        });
      } catch (error) {
        console.warn("[MapSection] Reverse geocoding failed:", error);
        setDebugData(buildEmptyDebug());
      } finally {
        setIsReversingGeocode(false);
      }
    },
    [isReversingGeocode, onAddressChange, options.countryCode, mapConfig],
  );

  const handlePlaceSelect = React.useCallback(
    (feature: GeoJsonFeature) => {
      if (feature?.geometry?.type !== "Point") {
        return;
      }

      const coords = feature.geometry.coordinates;
      const lng = coords[0] ?? 0;
      const lat = coords[1] ?? 0;

      if (!mapConfig.isWithinBounds(lat, lng)) {
        toast.add({
          title:
            "This location is outside the allowed area. Please select a location within the configured bounds.",
          type: "error",
        });
        return;
      }

      const addressLabel = formatAddress(feature.properties ?? {});
      setSearchPlace(addressLabel);
      onLatitudeChange(lat);
      onLongitudeChange(lng);

      if (map) {
        map.setView([lat, lng], mapConfig.markerZoom);
        createDrawMarker(lat, lng);
      }

      doReverseGeocode(lat, lng);
      setIsLocked(true);
    },
    [
      onLatitudeChange,
      onLongitudeChange,
      map,
      createDrawMarker,
      doReverseGeocode,
      mapConfig,
      setIsLocked,
    ],
  );

  const handleLocationFound = React.useCallback(
    (event: { latlng: { lat: number; lng: number } }) => {
      const { lat, lng } = event.latlng;

      if (!mapConfig.isWithinBounds(lat, lng)) {
        setGpsBannerVisible(true);
        toast.add({
          title:
            "Your current location is outside the allowed area. Location tracking is restricted to the configured bounds.",
          type: "error",
        });
        return;
      }

      setStopTracking(true);
      onLatitudeChange(lat);
      onLongitudeChange(lng);
      setTimeout(() => setStopTracking(false), 500);

      if (map) {
        map.setView([lat, lng], mapConfig.markerZoom);
        createDrawMarker(lat, lng);
      }

      doReverseGeocode(lat, lng);
      setIsLocked(true);
    },
    [
      onLatitudeChange,
      onLongitudeChange,
      map,
      createDrawMarker,
      doReverseGeocode,
      mapConfig,
      setIsLocked,
    ],
  );

  const handleGpsPermissionDenied = React.useCallback(() => {
    setGpsBannerVisible(true);
  }, []);

  const handleClearSearch = React.useCallback(() => {
    setSearchPlace("");
    setStopTracking(true);
    setTimeout(() => setStopTracking(false), 100);
  }, []);

  React.useEffect(() => {
    if (!map || !L || isInitialized.current) return;
    if (hasLocation && !mapData) {
      const [lat, lng] = getCoordinates();
      createDrawMarker(lat, lng);
      map.setView([lat, lng], mapConfig.markerZoom);
      // eslint-disable-next-line @eslint-react/web-api-no-leaked-timeout -- one-shot reverse geocode after map init
      setTimeout(() => doReverseGeocode(lat, lng), 0);
    }
    isInitialized.current = true;
  }, [map, L, hasLocation, getCoordinates, createDrawMarker, mapData, doReverseGeocode, mapConfig]);

  React.useEffect(() => {
    setIsLocked(hasLocation); // eslint-disable-line react-hooks/set-state-in-effect -- Sync lock state with location presence
  }, [hasLocation, setIsLocked]);

  React.useEffect(() => {
    if (!map || !L) return;

    const handleCreated = (e: { layer: L.Layer }) => {
      const layer = e.layer;
      if (layer instanceof L.Marker || layer instanceof L.Circle) {
        const latlng = layer.getLatLng();
        const currentDebug = debugDataRef.current;
        const hasAddress = currentDebug?.street || currentDebug?.city;
        const popupContent = `
          <div style="font-family: system-ui, sans-serif; line-height: 1.4; padding: 2px 0;">
            <p style="font-weight: 600; font-size: 13px; margin: 0 0 2px;">${hasAddress ? "Location" : "Coordinates"}</p>
            ${hasAddress ? `<p style="font-size: 12px; color: #666; margin: 0 0 2px;">${[currentDebug.housenumber, currentDebug.street].filter(Boolean).join(" ")}, ${currentDebug.city || ""}</p>` : ""}
            <p style="font-size: 11px; color: #999; margin: 0;">${latlng.lat.toFixed(6)}, ${latlng.lng.toFixed(6)}</p>
          </div>
        `;
        layer.bindPopup(popupContent, { offset: [0, -5], closeButton: true });
        // eslint-disable-next-line @eslint-react/web-api-no-leaked-timeout -- one-shot popup open after creation
        setTimeout(() => layer.openPopup(), 100);
      }
    };

    map.on("pm:create", handleCreated);
    return () => {
      map.off("pm:create", handleCreated);
    };
  }, [map, L]);

  React.useEffect(() => {
    if (!map) return;
    const updateZoom = () => setMapZoomLevel(map.getZoom());
    updateZoom();
    map.on("zoomend", updateZoom);
    return () => {
      map.off("zoomend", updateZoom);
    };
  }, [map]);

  const handleLayersChange = React.useCallback(
    (layers: { getLayers: () => L.Layer[]; toGeoJSON: () => unknown; clearLayers: () => void }) => {
      if (!L || !layers) return;

      if (firstLayerChange.current) {
        firstLayerChange.current = false;
      }

      const layerArray = layers.getLayers();

      if (layerArray.length > 0) {
        const geojson = layers.toGeoJSON();
        onMapDataChange?.(JSON.stringify(geojson));
      } else {
        onMapDataChange?.("");
        setIsLocked(false);
        return;
      }

      const layer = layerArray[0];
      if (!layer) return;
      let lat: number | null = null;
      let lng: number | null = null;

      if (
        typeof (layer as unknown as { getLatLng: () => { lat: number; lng: number } }).getLatLng ===
        "function"
      ) {
        const latlng = (
          layer as unknown as { getLatLng: () => { lat: number; lng: number } }
        ).getLatLng();
        lat = latlng.lat;
        lng = latlng.lng;
      } else if (
        typeof (
          layer as unknown as { getBounds: () => { getCenter: () => { lat: number; lng: number } } }
        ).getBounds === "function"
      ) {
        const bounds = (
          layer as unknown as { getBounds: () => { getCenter: () => { lat: number; lng: number } } }
        ).getBounds();
        const center = bounds.getCenter();
        lat = center.lat;
        lng = center.lng;
      } else if (
        typeof (
          layer as unknown as {
            getLatLngs: () => Array<
              Array<{ lat: number; lng: number }> | { lat: number; lng: number }
            >;
          }
        ).getLatLngs === "function"
      ) {
        const latlngs = (
          layer as unknown as {
            getLatLngs: () => Array<
              Array<{ lat: number; lng: number }> | { lat: number; lng: number }
            >;
          }
        ).getLatLngs();
        const first = Array.isArray(latlngs[0]) ? latlngs[0][0] : latlngs[0];
        if (first && typeof first.lat === "number") {
          lat = first.lat;
          lng = first.lng;
        }
      }

      if (lat !== null && lng !== null) {
        if (mapConfig.isWithinBounds(lat, lng)) {
          onLatitudeChange(lat);
          onLongitudeChange(lng);
          doReverseGeocode(lat, lng);
          setIsLocked(true);
          setTimeout(() => map?.setView([lat, lng], mapConfig.markerZoom), 0);
        } else {
          toast.add({
            title:
              "This location is outside the allowed area. Please place the marker within the configured bounds.",
            type: "error",
          });
          layers.clearLayers();
          onMapDataChange?.("");
          onLatitudeChange(null);
          onLongitudeChange(null);
        }
      }
    },
    [
      onLatitudeChange,
      onLongitudeChange,
      onMapDataChange,
      L,
      doReverseGeocode,
      map,
      mapConfig,
      setIsLocked,
    ],
  );

  const handleDeleteLocation = React.useCallback(() => {
    onLatitudeChange(null);
    onLongitudeChange(null);
    onAddressChange(null);
    onMapZoomChange(null);
    onMapDataChange?.("");
    setSearchPlace("");
    setDebugData(null);
  }, [onLatitudeChange, onLongitudeChange, onAddressChange, onMapZoomChange, onMapDataChange]);

  const handleToolActivate = React.useCallback(
    (mode: string | null) => {
      if (mode) {
        setIsLocked(false);
        if (map && hasLocation) {
          const [lat, lng] = getCoordinates();
          setTimeout(() => map.setView([lat, lng], mapConfig.markerZoom), 0);
        }
      } else {
        if (hasLocation && map) {
          const [lat, lng] = getCoordinates();
          setIsLocked(true);
          setTimeout(() => map.setView([lat, lng], mapConfig.markerZoom), 0);
        }
      }
    },
    [map, getCoordinates, mapConfig, setIsLocked, hasLocation],
  );

  const effectiveCenter = getCoordinates();
  const effectiveZoom = hasLocation ? mapConfig.markerZoom : mapConfig.defaultZoom;

  return (
    <Card className={className}>
      <CardHeader className="border-b border-border">
        <CardTitle className="flex items-center gap-2 text-foreground">
          <MapPin className="h-5 w-5 text-foreground" />
          {title}
        </CardTitle>
        <CardDescription>{description}</CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        {gpsBannerVisible && (
          <Alert variant="destructive" className="border-destructive/50">
            <AlertTriangleIcon className="size-4" />
            <AlertDescription className="flex items-center justify-between gap-2">
              <span>
                GPS location is unavailable. Please enable location services in your browser
                settings to use current location tracking.
              </span>
              <button
                type="button"
                onClick={() => setGpsBannerVisible(false)}
                className="text-sm font-medium underline underline-offset-2 hover:no-underline shrink-0"
              >
                Dismiss
              </button>
            </AlertDescription>
          </Alert>
        )}

        <div className="relative h-96 w-full overflow-hidden rounded-lg border border-border bg-slate-50 dark:bg-slate-900">
          <Map
            ref={(instance: unknown) => setMap(instance as L.Map | null)}
            center={effectiveCenter}
            zoom={effectiveZoom}
            minZoom={isLocked ? mapConfig.minZoom : mapConfig.defaultZoom}
            maxZoom={mapConfig.maxZoom}
            maxBounds={[
              [mapConfig.boundsSouthWest[0], mapConfig.boundsSouthWest[1]],
              [mapConfig.boundsNorthEast[0], mapConfig.boundsNorthEast[1]],
            ]}
            className="h-full w-full z-0"
          >
            <MapLayers defaultTileLayer="Default">
              <MapLockController
                isLocked={isLocked}
                minZoom={mapConfig.minZoom}
                defaultZoom={mapConfig.defaultZoom}
                swLat={mapConfig.boundsSouthWest[0]}
                swLng={mapConfig.boundsSouthWest[1]}
                neLat={mapConfig.boundsNorthEast[0]}
                neLng={mapConfig.boundsNorthEast[1]}
              />
              <MapTileLayer
                name="Default"
                darkUrl="https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}.png"
              />
              <MapTileLayer
                name="Satellite"
                url="https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}"
                attribution="&copy; Esri"
                darkUrl=""
                darkAttribution=""
              />

              <div className="absolute top-1 right-1 z-[1010] pointer-events-auto">
                <Button
                  type="button"
                  size="icon-sm"
                  variant={debugMode ? "default" : "secondary"}
                  onClick={() => setDebugMode((prev) => !prev)}
                  aria-label={debugMode ? "Disable debug mode" : "Enable debug mode"}
                  className="border cursor-pointer shadow-lg"
                >
                  <BugIcon className="size-4" />
                </Button>
              </div>

              <MapSearchControl
                value={searchPlace}
                onChange={setSearchPlace}
                onClear={handleClearSearch}
                onSelect={handlePlaceSelect}
                bbox={
                  options.searchBbox || [
                    mapConfig.boundsSouthWest[1],
                    mapConfig.boundsSouthWest[0],
                    mapConfig.boundsNorthEast[1],
                    mapConfig.boundsNorthEast[0],
                  ]
                }
                lang={options.searchLang}
                position="top-2 left-1/2 -translate-x-1/2"
                className="shadow-2xl"
              />

              <MapControls
                onLocationFound={handleLocationFound}
                onLocationError={(error: unknown) => {
                  console.warn(`[MapSection] Location error: ${(error as Error).message}`);
                }}
                onGpsPermissionDenied={handleGpsPermissionDenied}
                stop={stopTracking}
              />
              <MapDrawControl
                onLayersChange={handleLayersChange}
                onDelete={handleDeleteLocation}
                onToolActivate={handleToolActivate}
                position="bottom-1 left-1"
                initialGeoJson={mapData}
                markerIcon={getMarkerIcon()}
              >
                <MapDrawMarker />
                <MapDrawEdit />
                <MapDrawDelete />
                <MapDrawUndo />
              </MapDrawControl>
            </MapLayers>
          </Map>

          {isReversingGeocode && (
            <div className="absolute bottom-1 left-1/2 -translate-x-1/2 z-[1010]">
              <div className="bg-background/90 backdrop-blur-sm border rounded-full px-3 py-1 text-xs text-muted-foreground shadow-sm">
                Detecting address...
              </div>
            </div>
          )}

          {loading && (
            <div className="absolute inset-0 z-[2000] flex items-center justify-center bg-background/80">
              <div className="text-sm text-muted-foreground">Loading...</div>
            </div>
          )}
        </div>

        {debugMode && <MapDebugPanel data={debugData} zoom={mapZoomLevel} />}

        {debugData && !debugMode && (
          <div className="flex items-center gap-2 text-xs text-muted-foreground">
            <MapPin className="size-3" />
            <span>
              {debugData.street || debugData.housenumber
                ? `${debugData.housenumber ? debugData.housenumber + " " : ""}${debugData.street}`
                : ""}
              {debugData.city && `, ${debugData.city}`}
              {debugData.matchedGovernorate && ` (${debugData.matchedGovernorate})`}
              {debugData.postcode && `, ${debugData.postcode}`}
            </span>
            <span className="text-[10px] text-muted-foreground/60">Zoom: {mapZoomLevel}</span>
            {(debugData.street || debugData.city) && (
              <span className="text-[10px] text-muted-foreground/60">&bull; auto-detected</span>
            )}
          </div>
        )}

        {latitude != null && longitude != null && (
          <div className="flex items-center justify-between rounded-md bg-muted/50 px-3 py-2 text-sm">
            <div className="flex items-center gap-2 text-muted-foreground">
              <MapPin className="h-4 w-4" />
              <span>
                {address?.fullAddress || `${latitude.toFixed(6)}, ${longitude.toFixed(6)}`}
              </span>
            </div>
            <Button type="button" variant="ghost" size="sm" onClick={handleDeleteLocation}>
              Clear
            </Button>
          </div>
        )}

        <input type="hidden" name="latitude" value={latitude ?? ""} />
        <input type="hidden" name="longitude" value={longitude ?? ""} />
        <input type="hidden" name="map_zoom" value={mapZoom ?? ""} />

        {errors?.latitude && <p className="text-sm text-destructive">{errors.latitude}</p>}
        {errors?.longitude && <p className="text-sm text-destructive">{errors.longitude}</p>}
      </CardContent>
    </Card>
  );
}
