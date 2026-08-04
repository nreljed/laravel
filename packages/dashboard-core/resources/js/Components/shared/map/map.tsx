// @ts-nocheck
import { cn } from "@/lib/utils";
import { Button, buttonVariants } from "@/Components/ui/button";
import { ButtonGroup } from "@/Components/ui/button-group";
import {
  DropdownMenu,
  DropdownMenuGroup,
  DropdownMenuCheckboxItem,
  DropdownMenuContent,
  DropdownMenuLabel,
  DropdownMenuRadioGroup,
  DropdownMenuRadioItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/Components/ui/dropdown-menu";
import { PlaceAutocomplete } from "./place-autocomplete";
import "leaflet.fullscreen/dist/Control.FullScreen.css";
import "leaflet.markercluster/dist/MarkerCluster.css";
import "leaflet.markercluster/dist/MarkerCluster.Default.css";
import "leaflet/dist/leaflet.css";
import "@geoman-io/leaflet-geoman-free/dist/leaflet-geoman.css";
import L from "leaflet";
import leafletFullscreen from "leaflet.fullscreen";
import "@geoman-io/leaflet-geoman-free";

if (L.Control && !L.Control.FullScreen) {
  L.Control.FullScreen = leafletFullscreen.default || leafletFullscreen;
}
import {
  CircleIcon,
  LayersIcon,
  LoaderCircleIcon,
  MapPinIcon,
  MaximizeIcon,
  MinimizeIcon,
  MinusIcon,
  NavigationIcon,
  PenLineIcon,
  PlusIcon,
  SquareIcon,
  Trash2Icon,
  TriangleIcon,
  Undo2Icon,
  WaypointsIcon,
} from "lucide-react";
import React, {
  Suspense,
  createContext,
  lazy,
  useContext,
  useEffect,
  useRef,
  useState,
} from "react";
import { renderToString } from "react-dom/server";
import {
  MapContainer as LeafletMapContainer,
  TileLayer as LeafletTileLayer,
  Marker as LeafletMarker,
  Popup as LeafletPopup,
  Tooltip as LeafletTooltip,
  Circle as LeafletCircle,
  CircleMarker as LeafletCircleMarker,
  Polyline as LeafletPolyline,
  Polygon as LeafletPolygon,
  Rectangle as LeafletRectangle,
  LayerGroup as LeafletLayerGroup,
  FeatureGroup as LeafletFeatureGroup,
  useMap,
  useMapEvents,
} from "react-leaflet";
import { useDarkMode } from "@/hooks/use-dark-mode";

function createLazyComponent(factory) {
  const LazyComponent = lazy(factory);
  const Component = (props) => {
    const [isMounted, setIsMounted] = useState(false);
    useEffect(() => {
      const timer = setTimeout(() => setIsMounted(true), 0);
      return () => clearTimeout(timer);
    }, []);
    if (!isMounted) return null;
    return (
      <Suspense>
        <LazyComponent {...props} />
      </Suspense>
    );
  };
  Component.displayName = "LazyComponent";
  return Component;
}
const LeafletMarkerClusterGroup = createLazyComponent(async () => {
  const mod = await import("react-leaflet-cluster");
  return { default: mod.default };
});
const Map = React.forwardRef(
  (
    {
      zoom = 15,
      minZoom,
      maxZoom = 18,
      maxBounds,
      maxBoundsViscosity,
      dragging = true,
      scrollWheelZoom = true,
      doubleClickZoom = true,
      touchZoom = true,
      keyboard = true,
      boxZoom = true,
      className,
      children,
      ...props
    },
    ref,
  ) => {
    return (
      <LeafletMapContainer
        ref={ref}
        zoom={zoom}
        minZoom={minZoom}
        maxZoom={maxZoom}
        maxBounds={maxBounds}
        maxBoundsViscosity={maxBoundsViscosity}
        dragging={dragging}
        scrollWheelZoom={scrollWheelZoom}
        doubleClickZoom={doubleClickZoom}
        touchZoom={touchZoom}
        keyboard={keyboard}
        boxZoom={boxZoom}
        attributionControl={false}
        zoomControl={false}
        className={cn("size-full min-h-96 flex-1 rounded-md", className)}
        {...props}
      >
        {children}
      </LeafletMapContainer>
    );
  },
);
Map.displayName = "Map";
const MapLayersContext = createContext(null);
function useMapLayersContext() {
  const context = useContext(MapLayersContext);
  if (!context) {
    throw new Error("useMapLayersContext must be used within a MapLayers provider");
  }
  return context;
}
function MapTileLayer({ name = "Default", url, attribution, darkUrl, darkAttribution, ...props }) {
  const map = useMap();
  if (map.attributionControl) {
    map.attributionControl.setPrefix("");
  }
  const context = useContext(MapLayersContext);
  const DEFAULT_URL = "https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}.png";
  const DEFAULT_DARK_URL = "https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}.png";
  const isDark = useDarkMode();
  const resolvedUrl = isDark ? (darkUrl ?? url ?? DEFAULT_DARK_URL) : (url ?? DEFAULT_URL);
  const resolvedAttribution =
    isDark && darkAttribution
      ? darkAttribution
      : (attribution ??
        '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>, &copy; <a href="https://carto.com/attributions">CARTO</a>');
  useEffect(() => {
    if (context) {
      context.registerTileLayer({
        name,
        url: resolvedUrl,
        attribution: resolvedAttribution,
      });
    }
  }, [context, name, url, attribution, resolvedUrl, resolvedAttribution]);
  if (context && context.selectedTileLayer !== name) {
    return null;
  }
  return (
    <LeafletTileLayer
      key={resolvedUrl}
      url={resolvedUrl}
      attribution={resolvedAttribution}
      {...props}
    />
  );
}
function MapLayerGroup({ name, disabled, ...props }) {
  const context = useMapLayersContext();
  useEffect(() => {
    if (context) {
      context.registerLayerGroup({ name, disabled });
    }
  }, [context, name, disabled]);
  if (context && !context.activeLayerGroups.includes(name)) {
    return null;
  }
  return <LeafletLayerGroup {...props} />;
}
function MapFeatureGroup({ name, disabled, ...props }) {
  const context = useMapLayersContext();
  useEffect(() => {
    if (context) {
      context.registerLayerGroup({ name, disabled });
    }
  }, [context, name, disabled]);
  if (context && !context.activeLayerGroups.includes(name)) {
    return null;
  }
  return <LeafletFeatureGroup {...props} />;
}
function MapLayers({ defaultTileLayer, defaultLayerGroups = [], ...props }) {
  const [tileLayers, setTileLayers] = useState([]);
  const [selectedTileLayer, setSelectedTileLayer] = useState(defaultTileLayer || "");
  const [layerGroups, setLayerGroups] = useState([]);
  const [activeLayerGroups, setActiveLayerGroups] = useState(defaultLayerGroups);
  function registerTileLayer(tileLayer) {
    setTileLayers((prevTileLayers) => {
      if (prevTileLayers.some((layer) => layer.name === tileLayer.name)) return prevTileLayers;
      return [...prevTileLayers, tileLayer];
    });
  }
  function registerLayerGroup(layerGroup) {
    setLayerGroups((prevLayerGroups) => {
      if (prevLayerGroups.some((group) => group.name === layerGroup.name)) return prevLayerGroups;
      return [...prevLayerGroups, layerGroup];
    });
  }
  useEffect(() => {
    if (
      defaultTileLayer &&
      tileLayers.length > 0 &&
      tileLayers.every((tileLayer) => tileLayer.name !== defaultTileLayer)
    ) {
      throw new Error(
        `Invalid defaultTileLayer "${defaultTileLayer}" provided to MapLayers. It must match a MapTileLayer's name prop.`,
      );
    }
    if (!selectedTileLayer && tileLayers.length > 0) {
      const validDefaultValue =
        defaultTileLayer && tileLayers.some((layer) => layer.name === defaultTileLayer)
          ? defaultTileLayer
          : tileLayers[0].name;
      // eslint-disable-next-line @eslint-react/web-api-no-leaked-timeout -- one-shot derived default state sync
      setTimeout(() => setSelectedTileLayer(validDefaultValue), 0);
    }
    if (
      defaultLayerGroups.length > 0 &&
      layerGroups.length > 0 &&
      defaultLayerGroups.some((name) => layerGroups.every((group) => group.name !== name))
    ) {
      throw new Error(
        `Invalid defaultLayerGroups value provided to MapLayers. All names must match a MapLayerGroup's name prop.`,
      );
    }
  }, [tileLayers, defaultTileLayer, selectedTileLayer, layerGroups, defaultLayerGroups]);
  return (
    <MapLayersContext
      value={{
        registerTileLayer,
        tileLayers,
        selectedTileLayer,
        setSelectedTileLayer,
        registerLayerGroup,
        layerGroups,
        activeLayerGroups,
        setActiveLayerGroups,
      }}
      {...props}
    />
  );
}
function MapLayersControl({
  tileLayersLabel = "Map Type",
  layerGroupsLabel = "Layers",
  position = "top-1 right-1",
  className,
  ...props
}) {
  const layersContext = useMapLayersContext();
  if (!layersContext) throw new Error("MapLayersControl must be used within MapLayers");
  const {
    tileLayers,
    selectedTileLayer,
    setSelectedTileLayer,
    layerGroups,
    activeLayerGroups,
    setActiveLayerGroups,
  } = layersContext;
  if (tileLayers.length === 0 && layerGroups.length === 0) return null;
  function handleLayerGroupToggle(name, checked) {
    setActiveLayerGroups(
      checked
        ? [...activeLayerGroups, name]
        : activeLayerGroups.filter((groupName) => groupName !== name),
    );
  }
  const showTileLayersDropdown = tileLayers.length > 1;
  const showLayerGroupsDropdown = layerGroups.length > 0;
  if (!showTileLayersDropdown && !showLayerGroupsDropdown) return null;
  return (
    <MapControlContainer className={cn(position, className)}>
      <DropdownMenu>
        <DropdownMenuTrigger
          onPointerDown={(e) => {
            e.stopPropagation();
          }}
          type="button"
          aria-label="Select layers"
          title="Select layers"
          className={cn(buttonVariants({ variant: "secondary", size: "icon-sm" }), "border")}
          {...props}
        >
          <LayersIcon className="size-4" />
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" sideOffset={8}>
          {showTileLayersDropdown && (
            <DropdownMenuGroup>
              <DropdownMenuLabel>{tileLayersLabel}</DropdownMenuLabel>
              <DropdownMenuRadioGroup
                value={selectedTileLayer}
                onValueChange={setSelectedTileLayer}
              >
                {tileLayers.map((tileLayer) => (
                  <DropdownMenuRadioItem key={tileLayer.name} value={tileLayer.name}>
                    {tileLayer.name}
                  </DropdownMenuRadioItem>
                ))}
              </DropdownMenuRadioGroup>
            </DropdownMenuGroup>
          )}
          {showTileLayersDropdown && showLayerGroupsDropdown && <DropdownMenuSeparator />}
          {showLayerGroupsDropdown && (
            <DropdownMenuGroup>
              <DropdownMenuLabel>{layerGroupsLabel}</DropdownMenuLabel>
              {layerGroups.map((layerGroup) => (
                <DropdownMenuCheckboxItem
                  key={layerGroup.name}
                  checked={activeLayerGroups.includes(layerGroup.name)}
                  disabled={layerGroup.disabled}
                  onCheckedChange={(checked) => handleLayerGroupToggle(layerGroup.name, checked)}
                >
                  {layerGroup.name}
                </DropdownMenuCheckboxItem>
              ))}
            </DropdownMenuGroup>
          )}
        </DropdownMenuContent>
      </DropdownMenu>
    </MapControlContainer>
  );
}
function MapMarker({
  icon,
  iconAnchor,
  className = "bg-transparent! border-none!",
  popupAnchor,
  tooltipAnchor,
  ...props
}) {
  const { L } = useLeaflet();
  if (!L) return null;
  const effectiveAnchor = iconAnchor || [12, 24];
  const markerIcon = icon ?? <MapDefaultMarkerIcon />;
  return (
    <LeafletMarker
      icon={L.divIcon({
        html: renderToString(markerIcon),
        iconAnchor: effectiveAnchor,
        className,
        popupAnchor: popupAnchor || [0, -32],
        ...(tooltipAnchor && { tooltipAnchor }),
      })}
      riseOnHover
      {...props}
    />
  );
}
function MapDefaultMarkerIcon() {
  return <MapPinIcon className="size-6 text-primary fill-primary/10" />;
}
function MapMarkerClusterGroup({
  polygonOptions = { className: "fill-foreground stroke-foreground stroke-2" },
  spiderLegPolylineOptions = { className: "fill-foreground stroke-foreground stroke-2" },
  icon,
  ...props
}) {
  const { L } = useLeaflet();
  if (!L) return null;
  const iconCreateFunction = icon
    ? (cluster) => {
        const markerCount = cluster.getChildCount();
        const iconNode = icon(markerCount);
        return L.divIcon({ html: renderToString(iconNode) });
      }
    : void 0;
  return (
    <LeafletMarkerClusterGroup
      polygonOptions={polygonOptions}
      spiderLegPolylineOptions={spiderLegPolylineOptions}
      iconCreateFunction={iconCreateFunction}
      {...props}
    />
  );
}
function MapCircle({ className, ...props }) {
  return (
    <LeafletCircle
      className={cn("fill-foreground stroke-foreground stroke-2", className)}
      {...props}
    />
  );
}
function MapCircleMarker({ className, ...props }) {
  return (
    <LeafletCircleMarker
      className={cn("fill-foreground stroke-foreground stroke-2", className)}
      {...props}
    />
  );
}
function MapPolyline({ className, ...props }) {
  return (
    <LeafletPolyline
      className={cn("fill-foreground stroke-foreground stroke-2", className)}
      {...props}
    />
  );
}
function MapPolygon({ className, ...props }) {
  return (
    <LeafletPolygon
      className={cn("fill-foreground stroke-foreground stroke-2", className)}
      {...props}
    />
  );
}
function MapRectangle({ className, ...props }) {
  return (
    <LeafletRectangle
      className={cn("fill-foreground stroke-foreground stroke-2", className)}
      {...props}
    />
  );
}
function MapPopup({ className, ...props }) {
  return (
    <LeafletPopup
      className={cn(
        "animate-in fade-in-0 zoom-in-95 data-[state=open]:slide-in-from-bottom-2",
        className,
      )}
      offset={[0, -5]}
      {...props}
    />
  );
}
function MapTooltip({ className, children, side = "top", sideOffset = 15, ...props }) {
  const ARROW_POSITION_CLASSES = {
    top: "bottom-0.5 left-1/2 -translate-x-1/2 translate-y-1/2",
    bottom: "top-0.5 left-1/2 -translate-x-1/2 -translate-y-1/2",
    left: "right-0.5 top-1/2 translate-x-1/2 -translate-y-1/2",
    right: "left-0.5 top-1/2 -translate-x-1/2 -translate-y-1/2",
  };
  const DEFAULT_OFFSET = {
    top: [0, -sideOffset],
    bottom: [0, sideOffset],
    left: [-sideOffset, 0],
    right: [sideOffset, 0],
  };
  return (
    <LeafletTooltip
      className={cn(
        "pointer-events-none animate-in fade-in-0 zoom-in-95 fade-out-0 zoom-out-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2 relative z-50 w-fit text-xs text-balance transition-opacity",
        className,
      )}
      data-side={side}
      direction={side}
      offset={DEFAULT_OFFSET[side]}
      opacity={1}
      {...props}
    >
      {children}
      <div
        className={cn(
          "bg-foreground fill-foreground absolute z-50 size-2.5 rotate-45 rounded-[2px]",
          ARROW_POSITION_CLASSES[side],
        )}
      />
    </LeafletTooltip>
  );
}
function MapZoomControl({ position = "top-1 left-1", className, ...props }) {
  const map = useMap();
  const [zoomLevel, setZoomLevel] = useState(map.getZoom());
  useMapEvents({
    zoomend: () => {
      setZoomLevel(map.getZoom());
    },
  });
  return (
    <MapControlContainer className={cn(position, className)}>
      <ButtonGroup orientation="vertical" aria-label="Zoom controls" {...props}>
        <Button
          type="button"
          size="icon-sm"
          variant="secondary"
          aria-label="Zoom in"
          title="Zoom in"
          className="border"
          disabled={zoomLevel >= map.getMaxZoom()}
          onClick={() => map.zoomIn()}
        >
          <PlusIcon />
        </Button>
        <Button
          type="button"
          size="icon-sm"
          variant="secondary"
          aria-label="Zoom out"
          title="Zoom out"
          className="border"
          disabled={zoomLevel <= map.getMinZoom()}
          onClick={() => map.zoomOut()}
        >
          <MinusIcon />
        </Button>
      </ButtonGroup>
    </MapControlContainer>
  );
}
function MapFullscreenControl({ position = "top-1 right-1", className, ...props }) {
  const map = useMap();
  const [isFullscreen, setIsFullscreen] = useState(false);
  const { L } = useLeaflet();
  useEffect(() => {
    if (!L) return;
    const fullscreenControl = new L.Control.FullScreen();
    fullscreenControl.addTo(map);
    const container = fullscreenControl.getContainer();
    if (container) container.style.display = "none";
    const handleEnter = () => setIsFullscreen(true);
    const handleExit = () => setIsFullscreen(false);
    map.on("enterFullscreen", handleEnter);
    map.on("exitFullscreen", handleExit);
    return () => {
      fullscreenControl.remove();
      map.off("enterFullscreen", handleEnter);
      map.off("exitFullscreen", handleExit);
    };
  }, [L, map]);
  return (
    <MapControlContainer className={cn(position, className)}>
      <Button
        type="button"
        size="icon-sm"
        variant="secondary"
        onClick={() => map.toggleFullscreen()}
        aria-label={isFullscreen ? "Exit fullscreen" : "Enter fullscreen"}
        title={isFullscreen ? "Exit fullscreen" : "Enter fullscreen"}
        className="border"
        {...props}
      >
        {isFullscreen ? <MinimizeIcon /> : <MaximizeIcon />}
      </Button>
    </MapControlContainer>
  );
}
function MapLocateControl({
  watch = false,
  onLocationFound,
  onLocationError,
  onGpsPermissionDenied,
  position = "right-1 bottom-1",
  className,
  stop = false,
  ...props
}) {
  const map = useMap();
  const [isLocatingDebounced, setIsLocating, isLocating] = useDebounceLoadingState(200);
  const [location, setLocation] = useState(null);
  const onFoundRef = useRef(null);
  const onErrorRef = useRef(null);
  const stopLocatingRef = useRef(null);
  function startLocating() {
    if (onFoundRef.current || onErrorRef.current) stopLocating();
    setIsLocating(true);
    const locateOptions = {
      setView: true,
      maxZoom: map.getMaxZoom(),
      watch,
      enableHighAccuracy: true,
      timeout: 15e3,
      maximumAge: 0,
      drawCircle: false,
      drawMarker: false,
    };
    const onFound = (e) => {
      setLocation(e.latlng);
      setIsLocating(false);
      onLocationFound?.(e);
      if (!watch) {
        map.off("locationfound", onFound);
        if (onErrorRef.current) map.off("locationerror", onErrorRef.current);
        onFoundRef.current = null;
        onErrorRef.current = null;
      }
    };
    const onError = (e) => {
      setLocation(null);
      setIsLocating(false);
      onLocationError?.(e);
      if (e.code === 1) onGpsPermissionDenied?.(e);
      map.off("locationfound", onFound);
      map.off("locationerror", onError);
      onFoundRef.current = null;
      onErrorRef.current = null;
    };
    onFoundRef.current = onFound;
    onErrorRef.current = onError;
    map.on("locationfound", onFound);
    map.on("locationerror", onError);
    map.locate(locateOptions);
  }
  function stopLocating() {
    map.stopLocate();
    if (onFoundRef.current) {
      map.off("locationfound", onFoundRef.current);
      onFoundRef.current = null;
    }
    if (onErrorRef.current) {
      map.off("locationerror", onErrorRef.current);
      onErrorRef.current = null;
    }
    setLocation(null);
    setIsLocating(false);
  }
  stopLocatingRef.current = stopLocating;
  useEffect(() => {
    if (stop) stopLocatingRef.current();
  }, [stop]);
  useEffect(() => {
    return () => {
      stopLocatingRef.current();
    };
  }, []);
  return (
    <MapControlContainer className={cn(position, className)}>
      <Button
        type="button"
        size="icon-sm"
        variant={location ? "default" : "secondary"}
        onClick={() => {
          if (isLocating) return;
          if (location) stopLocating();
          else startLocating();
        }}
        disabled={isLocating}
        title={isLocatingDebounced ? "Locating..." : location ? "Stop tracking" : "Track location"}
        aria-label={
          isLocatingDebounced
            ? "Locating..."
            : location
              ? "Stop location tracking"
              : "Start location tracking"
        }
        className="border"
        {...props}
      >
        {isLocatingDebounced ? (
          <LoaderCircleIcon className="animate-spin" />
        ) : (
          <NavigationIcon className={cn(location && "fill-current")} />
        )}
      </Button>
    </MapControlContainer>
  );
}
function MapSearchControl({
  position = "top-1 left-1",
  className,
  value,
  onChange,
  onClear,
  onSelect,
  ...props
}) {
  return (
    <MapControlContainer className={cn("z-[1005]", position, className)}>
      <PlaceAutocomplete
        className="w-72 md:w-[450px]"
        value={value}
        onChange={onChange}
        onClear={onClear}
        onPlaceSelect={onSelect}
        {...props}
      />
    </MapControlContainer>
  );
}
function MapControls({
  position = "bottom-1 right-1",
  className,
  tileLayersLabel = "Map Type",
  layerGroupsLabel = "Layers",
  watch = false,
  onLocationFound,
  onLocationError,
  onGpsPermissionDenied,
  stop = false,
  ...props
}) {
  const map = useMap();
  const [zoomLevel, setZoomLevel] = useState(map.getZoom());
  const layersContext = useContext(MapLayersContext);
  const [isLocatingDebounced, setIsLocating, isLocating] = useDebounceLoadingState(200);
  const [location, setLocation] = useState(null);
  const onFoundRef = useRef(null);
  const onErrorRef = useRef(null);
  const stopLocatingRef = useRef(null);

  useMapEvents({
    zoomend: () => {
      setZoomLevel(map.getZoom());
    },
  });

  function startLocating() {
    if (onFoundRef.current || onErrorRef.current) stopLocating();
    setIsLocating(true);
    const locateOptions = {
      setView: true,
      maxZoom: map.getMaxZoom(),
      watch,
      enableHighAccuracy: true,
      timeout: 15e3,
      maximumAge: 0,
      drawCircle: false,
      drawMarker: false,
    };
    const onFound = (e) => {
      setLocation(e.latlng);
      setIsLocating(false);
      onLocationFound?.(e);
      if (!watch) {
        map.off("locationfound", onFound);
        if (onErrorRef.current) map.off("locationerror", onErrorRef.current);
        onFoundRef.current = null;
        onErrorRef.current = null;
      }
    };
    const onError = (e) => {
      setLocation(null);
      setIsLocating(false);
      onLocationError?.(e);
      if (e.code === 1) onGpsPermissionDenied?.(e);
      map.off("locationfound", onFound);
      map.off("locationerror", onError);
      onFoundRef.current = null;
      onErrorRef.current = null;
    };
    onFoundRef.current = onFound;
    onErrorRef.current = onError;
    map.on("locationfound", onFound);
    map.on("locationerror", onError);
    map.locate(locateOptions);
  }
  function stopLocating() {
    map.stopLocate();
    if (onFoundRef.current) {
      map.off("locationfound", onFoundRef.current);
      onFoundRef.current = null;
    }
    if (onErrorRef.current) {
      map.off("locationerror", onErrorRef.current);
      onErrorRef.current = null;
    }
    setLocation(null);
    setIsLocating(false);
  }
  stopLocatingRef.current = stopLocating;
  useEffect(() => {
    if (stop) stopLocatingRef.current();
  }, [stop]);
  useEffect(() => {
    return () => {
      stopLocatingRef.current();
    };
  }, []);

  const showTileLayersDropdown = layersContext?.tileLayers?.length > 1;
  const showLayerGroupsDropdown = (layersContext?.layerGroups?.length ?? 0) > 0;
  const hasLayers = showTileLayersDropdown || showLayerGroupsDropdown;

  function handleLayerGroupToggle(name, checked) {
    if (!layersContext) return;
    layersContext.setActiveLayerGroups(
      checked
        ? [...layersContext.activeLayerGroups, name]
        : layersContext.activeLayerGroups.filter((groupName) => groupName !== name),
    );
  }

  return (
    <MapControlContainer className={cn("flex flex-col gap-0.5", position, className)} {...props}>
      <ButtonGroup orientation="vertical" aria-label="Zoom controls">
        <Button
          type="button"
          size="icon-sm"
          variant="secondary"
          aria-label="Zoom in"
          title="Zoom in"
          className="border"
          disabled={zoomLevel >= map.getMaxZoom()}
          onClick={() => map.zoomIn()}
        >
          <PlusIcon />
        </Button>
        <Button
          type="button"
          size="icon-sm"
          variant="secondary"
          aria-label="Zoom out"
          title="Zoom out"
          className="border"
          disabled={zoomLevel <= map.getMinZoom()}
          onClick={() => map.zoomOut()}
        >
          <MinusIcon />
        </Button>
      </ButtonGroup>
      {hasLayers && (
        <DropdownMenu>
          <DropdownMenuTrigger
            onPointerDown={(e) => e.stopPropagation()}
            type="button"
            aria-label="Select layers"
            title="Select layers"
            className={cn(buttonVariants({ variant: "secondary", size: "icon-sm" }), "border")}
          >
            <LayersIcon className="size-4" />
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end" sideOffset={8}>
            {showTileLayersDropdown && (
              <DropdownMenuGroup>
                <DropdownMenuLabel>{tileLayersLabel}</DropdownMenuLabel>
                <DropdownMenuRadioGroup
                  value={layersContext.selectedTileLayer}
                  onValueChange={layersContext.setSelectedTileLayer}
                >
                  {layersContext.tileLayers.map((tileLayer) => (
                    <DropdownMenuRadioItem key={tileLayer.name} value={tileLayer.name}>
                      {tileLayer.name}
                    </DropdownMenuRadioItem>
                  ))}
                </DropdownMenuRadioGroup>
              </DropdownMenuGroup>
            )}
            {showTileLayersDropdown && showLayerGroupsDropdown && <DropdownMenuSeparator />}
            {showLayerGroupsDropdown && (
              <DropdownMenuGroup>
                <DropdownMenuLabel>{layerGroupsLabel}</DropdownMenuLabel>
                {layersContext.layerGroups.map((layerGroup) => (
                  <DropdownMenuCheckboxItem
                    key={layerGroup.name}
                    checked={layersContext.activeLayerGroups.includes(layerGroup.name)}
                    disabled={layerGroup.disabled}
                    onCheckedChange={(checked) => handleLayerGroupToggle(layerGroup.name, checked)}
                  >
                    {layerGroup.name}
                  </DropdownMenuCheckboxItem>
                ))}
              </DropdownMenuGroup>
            )}
          </DropdownMenuContent>
        </DropdownMenu>
      )}
      <Button
        type="button"
        size="icon-sm"
        variant={location ? "default" : "secondary"}
        onClick={() => {
          if (isLocating) return;
          if (location) stopLocating();
          else startLocating();
        }}
        disabled={isLocating}
        title={isLocatingDebounced ? "Locating..." : location ? "Stop tracking" : "Track location"}
        aria-label={
          isLocatingDebounced
            ? "Locating..."
            : location
              ? "Stop location tracking"
              : "Start location tracking"
        }
        className="border"
      >
        {isLocatingDebounced ? (
          <LoaderCircleIcon className="animate-spin" />
        ) : (
          <NavigationIcon className={cn(location && "fill-current")} />
        )}
      </Button>
    </MapControlContainer>
  );
}
const MapDrawContext = createContext(null);
function useMapDrawContext() {
  return useContext(MapDrawContext);
}
function MapDrawControl({
  onLayersChange,
  onDelete,
  onToolActivate,
  position = "bottom-1 left-1",
  className,
  initialGeoJson,
  markerIcon,
  editable = true,
  ...props
}) {
  const { L } = useLeaflet();
  const map = useMap();
  const featureGroupRef = useRef(null);
  const editControlRef = useRef(null);
  const deleteControlRef = useRef(null);
  const handleDrawCreatedRef = useRef(null);
  const handleDrawEditedOrDeletedRef = useRef(null);
  const snapshotRef = useRef(null);
  const snapshotActiveRef = useRef(false);
  const [activeMode, setActiveMode] = useState(null);
  const [layersCount, setLayersCount] = useState(0);
  const [hasOnlyMarkers, setHasOnlyMarkers] = useState(false);
  const [featureGroupInstance, setFeatureGroupInstance] = useState(null);
  const syncFeatureGroupState = React.useCallback(
    function syncFeatureGroupState({ removeDuplicates = false, enforceSingle = false } = {}) {
      if (!L || !featureGroupRef.current) {
        setLayersCount(0);
        setHasOnlyMarkers(false);
        return;
      }
      const group = featureGroupRef.current;
      if (removeDuplicates) {
        const seen = new Set();
        const duplicates = [];
        group.eachLayer((layer) => {
          if (!(layer instanceof L.Marker)) return;
          const { lat, lng } = layer.getLatLng();
          const key = `${lat.toFixed(6)}:${lng.toFixed(6)}`;
          if (seen.has(key)) duplicates.push(layer);
          else seen.add(key);
        });
        for (const layer of duplicates) group.removeLayer(layer);
      }
      if (enforceSingle) {
        const layers = group.getLayers();
        if (layers.length > 1) {
          const remaining = layers.slice(1);
          for (const layer of remaining) group.removeLayer(layer);
        }
      }
      const layers = group.getLayers();
      setLayersCount(layers.length);
      setHasOnlyMarkers(layers.length > 0 && layers.every((layer) => layer instanceof L.Marker));
    },
    [L],
  );
  function handleDrawCreated(layer) {
    if (!L || !featureGroupRef.current) return;
    featureGroupRef.current.clearLayers();
    featureGroupRef.current.addLayer(layer);
    syncFeatureGroupState({ removeDuplicates: true, enforceSingle: true });
    onLayersChange?.(featureGroupRef.current);
    setActiveMode(null);
  }
  function handleDrawEditedOrDeleted() {
    if (!featureGroupRef.current) return;
    syncFeatureGroupState({ removeDuplicates: true, enforceSingle: true });
    onLayersChange?.(featureGroupRef.current);
  }
  useEffect(() => {
    handleDrawCreatedRef.current = handleDrawCreated;
    handleDrawEditedOrDeletedRef.current = handleDrawEditedOrDeleted;
  });
  function clearLayers() {
    if (!featureGroupRef.current) return;
    featureGroupRef.current.clearLayers();
    syncFeatureGroupState();
    onLayersChange?.(featureGroupRef.current);
    onDelete?.();
    setActiveMode(null);
  }
  useEffect(() => {
    if (!map) return;
    const createdHandler = (e) => handleDrawCreatedRef.current(e.layer);
    const editedHandler = () => handleDrawEditedOrDeletedRef.current();
    const dragHandler = () => handleDrawEditedOrDeletedRef.current();
    map.on("pm:create", createdHandler);
    map.on("pm:edit", editedHandler);
    map.on("pm:remove", editedHandler);
    map.on("pm:dragend", dragHandler);
    return () => {
      map.off("pm:create", createdHandler);
      map.off("pm:edit", editedHandler);
      map.off("pm:remove", editedHandler);
      map.off("pm:dragend", dragHandler);
    };
  }, [map]);

  useEffect(() => {
    if (editable || activeMode === null) {
      return;
    }

    const timer = setTimeout(() => {
      setActiveMode(null);
    }, 0);
    return () => clearTimeout(timer);
  }, [editable, activeMode]);

  useEffect(() => {
    if (activeMode === "edit" && featureGroupRef.current && !snapshotActiveRef.current) {
      snapshotRef.current = featureGroupRef.current.toGeoJSON();
      snapshotActiveRef.current = true;
    }
    if (activeMode !== "edit") snapshotActiveRef.current = false;
  }, [activeMode]);

  function undoChanges() {
    if (!L || !featureGroupRef.current || !snapshotRef.current) return;
    const group = featureGroupRef.current;
    group.clearLayers();
    L.geoJSON(snapshotRef.current, {
      pointToLayer: (feature, latlng) => {
        const marker = L.marker(latlng, {
          icon: markerIcon || L.Icon.Default.prototype,
          draggable: true,
        });
        if (marker.dragging) marker.dragging.disable();
        return marker;
      },
      onEachFeature: (feature, layer) => {
        group.addLayer(layer);
      },
    });
    syncFeatureGroupState({ removeDuplicates: true, enforceSingle: true });
    onLayersChange?.(group);
    setActiveMode(null);
  }

  const isInitialized = useRef(false);
  useEffect(() => {
    if (!L || !map || !initialGeoJson || isInitialized.current || !featureGroupRef.current) return;
    try {
      const geoData =
        typeof initialGeoJson === "string" ? JSON.parse(initialGeoJson) : initialGeoJson;
      if (geoData && geoData.features && geoData.features.length > 0) {
        L.geoJSON(geoData, {
          pointToLayer: (feature, latlng) => {
            const marker = L.marker(latlng, {
              icon: markerIcon || L.Icon.Default.prototype,
              draggable: true,
            });
            if (marker.dragging) marker.dragging.disable();
            return marker;
          },
          onEachFeature: (feature, layer) => {
            featureGroupRef.current.addLayer(layer);
          },
        });
        syncFeatureGroupState({ removeDuplicates: true, enforceSingle: true });
        onLayersChange?.(featureGroupRef.current);
        isInitialized.current = true;
      }
    } catch (error) {
      console.warn("MapDrawControl: Failed to load initialGeoJson", error);
    }
  }, [L, map, initialGeoJson, markerIcon, onLayersChange, syncFeatureGroupState]);

  useEffect(() => {
    if (!map) return;
    const container = map.getContainer();
    container.classList.toggle("map-is-editing", activeMode === "edit");
    return () => container.classList.remove("map-is-editing");
  }, [map, activeMode]);

  useEffect(() => {
    onToolActivate?.(activeMode);
  }, [onToolActivate, activeMode]);

  const { children, ...buttonGroupProps } = props;
  return (
    <MapDrawContext
      value={{
        featureGroup: featureGroupInstance,
        activeMode,
        setActiveMode,
        editControlRef,
        deleteControlRef,
        layersCount,
        hasOnlyMarkers,
        clearLayers,
        undoChanges,
        editable,
      }}
    >
      <LeafletFeatureGroup
        ref={(instance) => {
          featureGroupRef.current = instance;
          setFeatureGroupInstance(instance);
        }}
      />
      <MapControlContainer className={cn(position, className)}>
        <ButtonGroup orientation="vertical" {...buttonGroupProps}>
          {children}
        </ButtonGroup>
      </MapControlContainer>
    </MapDrawContext>
  );
}
function MapDrawShapeButton({ shape, options, className, children, ...props }) {
  const drawContext = useMapDrawContext();
  if (!drawContext) throw new Error("MapDrawShapeButton must be used within MapDrawControl");
  const map = useMap();
  const optionsRef = useRef(options);
  useEffect(() => {
    optionsRef.current = options;
  }, [options]);
  const { activeMode, setActiveMode, layersCount, editable } = drawContext;
  const isActive = activeMode === shape;
  useEffect(() => {
    if (!map || !isActive) {
      map.pm?.disableDraw?.();
      return;
    }
    if (!editable) return;
    map.pm.enableDraw(shape, { snappable: false, ...optionsRef.current });
    return () => {
      map.pm.disableDraw();
    };
  }, [map, isActive, shape, editable]);
  function handleClick() {
    if (!editable) return;
    setActiveMode(isActive ? null : shape);
  }
  return (
    <Button
      type="button"
      size="icon-sm"
      aria-label={`Draw ${shape}`}
      title={`Draw ${shape}`}
      className={cn("border", className)}
      variant={isActive ? "default" : "secondary"}
      disabled={!editable || activeMode === "edit" || activeMode === "delete" || layersCount >= 1}
      onClick={handleClick}
      {...props}
    >
      {children}
    </Button>
  );
}
function MapDrawMarker({ ...props }) {
  const markerIcon = useMapPinIcon();
  return (
    <MapDrawShapeButton
      shape="Marker"
      options={{ markerStyle: markerIcon ? { icon: markerIcon } : undefined, ...props }}
    >
      <MapPinIcon />
    </MapDrawShapeButton>
  );
}
function MapDrawPolyline({
  showLength = false,
  shapeOptions = { color: "var(--color-primary)", opacity: 1, weight: 2 },
  ...props
}) {
  return (
    <MapDrawShapeButton
      shape="Line"
      options={{ pathOptions: shapeOptions, showMeasurements: showLength, ...props }}
    >
      <WaypointsIcon />
    </MapDrawShapeButton>
  );
}
function MapDrawCircle({
  showRadius = false,
  shapeOptions = { color: "var(--color-primary)", opacity: 1, weight: 2 },
  ...props
}) {
  return (
    <MapDrawShapeButton
      shape="Circle"
      options={{ pathOptions: shapeOptions, showMeasurements: showRadius, ...props }}
    >
      <CircleIcon />
    </MapDrawShapeButton>
  );
}
function MapDrawRectangle({
  showArea = false,
  shapeOptions = { color: "var(--color-primary)", opacity: 1, weight: 2 },
  ...props
}) {
  return (
    <MapDrawShapeButton
      shape="Rectangle"
      options={{ pathOptions: shapeOptions, showMeasurements: showArea, ...props }}
    >
      <SquareIcon />
    </MapDrawShapeButton>
  );
}
function MapDrawPolygon({
  showArea = false,
  shapeOptions = { color: "var(--color-primary)", opacity: 1, weight: 2 },
  ...props
}) {
  return (
    <MapDrawShapeButton
      shape="Polygon"
      options={{ pathOptions: shapeOptions, showMeasurements: showArea, ...props }}
    >
      <TriangleIcon />
    </MapDrawShapeButton>
  );
}
function MapDrawActionButton({
  drawAction,
  createDrawTool,
  controlRef,
  className,
  children,
  ...props
}) {
  const drawContext = useMapDrawContext();
  if (!drawContext) throw new Error("MapDrawActionButton must be used within MapDrawControl");
  const { L } = useLeaflet();
  const map = useMap();
  const { featureGroup, activeMode, setActiveMode, layersCount, editable, hasOnlyMarkers } =
    drawContext;
  const isActive = activeMode === drawAction;
  const hasFeatures = layersCount > 0;
  const shouldUseDrawControl = !(drawAction === "edit" && hasOnlyMarkers);
  useEffect(() => {
    if (!L || !featureGroup || !isActive || !editable || !shouldUseDrawControl) {
      controlRef.current?.disable?.();
      controlRef.current = null;
      return;
    }
    const control = createDrawTool(map, featureGroup);
    control.enable?.();
    controlRef.current = control;
    return () => {
      control.disable?.();
      controlRef.current = null;
    };
  }, [L, map, isActive, featureGroup, createDrawTool, editable, shouldUseDrawControl, controlRef]);
  function handleClick() {
    if (!editable) return;
    if (isActive) controlRef.current?.save?.();
    setActiveMode(isActive ? null : drawAction);
  }
  return (
    <Button
      type="button"
      size="icon-sm"
      aria-label={`${drawAction === "edit" ? "Edit" : "Remove"} shapes`}
      title={`${drawAction === "edit" ? "Edit" : "Remove"} shapes`}
      variant={isActive ? "default" : "secondary"}
      disabled={!editable || !hasFeatures}
      onClick={handleClick}
      className={cn("border", className)}
      {...props}
    >
      {children}
    </Button>
  );
}
function MapDrawEdit({ ...props }) {
  const drawContext = useMapDrawContext();
  if (!drawContext) throw new Error("MapDrawEdit must be used within MapDrawControl");
  const { featureGroup, hasOnlyMarkers } = drawContext;
  const createEditTool = (map, group) => {
    const activeFeatureGroup = group || featureGroup;
    if (!activeFeatureGroup || hasOnlyMarkers)
      return { enable: () => {}, disable: () => {}, save: () => {} };
    return {
      enable: () => activeFeatureGroup.pm.enable({ ...props }),
      disable: () => activeFeatureGroup.pm.disable(),
      save: () => activeFeatureGroup.pm.disable(),
    };
  };
  return (
    <MapDrawActionButton
      drawAction="edit"
      controlRef={drawContext.editControlRef}
      createDrawTool={createEditTool}
    >
      <PenLineIcon />
    </MapDrawActionButton>
  );
}
function MapDrawDelete({ className, ...props }) {
  const drawContext = useMapDrawContext();
  if (!drawContext) throw new Error("MapDrawDelete must be used within MapDrawControl");
  const { clearLayers, layersCount, editable } = drawContext;
  const hasFeatures = layersCount > 0;
  return (
    <Button
      type="button"
      size="icon-sm"
      aria-label="Clear map"
      title="Clear map"
      variant="secondary"
      disabled={!editable || !hasFeatures}
      onClick={clearLayers}
      className={cn("border", className)}
      {...props}
    >
      <Trash2Icon />
    </Button>
  );
}
function MapDrawUndo({ className, ...props }) {
  const drawContext = useMapDrawContext();
  if (!drawContext) throw new Error("MapDrawUndo must be used within MapDrawControl");
  const { activeMode, layersCount, editable, undoChanges } = drawContext;
  const isInEditMode = activeMode === "edit";
  const isInDeleteMode = activeMode === "delete";
  const isActive = editable && (isInEditMode || isInDeleteMode) && layersCount > 0;
  function handleUndo() {
    if (!editable) return;
    undoChanges();
  }
  return (
    <Button
      type="button"
      size="icon-sm"
      variant="secondary"
      aria-label={`Undo ${activeMode}`}
      title={`Undo ${activeMode}`}
      onClick={handleUndo}
      disabled={!isActive}
      className={cn("border", className)}
      {...props}
    >
      <Undo2Icon />
    </Button>
  );
}
function MapControlContainer({ className, ...props }) {
  const { L } = useLeaflet();
  const containerRef = useRef(null);
  useEffect(() => {
    if (!L || !containerRef.current) return;
    const element = containerRef.current;
    L.DomEvent.disableClickPropagation(element);
    L.DomEvent.disableScrollPropagation(element);
  }, [L]);
  return (
    <div
      ref={containerRef}
      className={cn("absolute z-[1010] size-fit pointer-events-auto", className)}
      {...props}
    />
  );
}
function useMapPinIcon() {
  const { L } = useLeaflet();
  if (!L) return null;
  return L.divIcon({
    className: "",
    iconAnchor: [16, 32],
    popupAnchor: [0, -32],
    html: renderToString(
      <MapPinIcon
        className="size-8 filter drop-shadow-lg"
        style={{
          color: "var(--map-marker-color,#2563eb)",
          fill: "var(--map-marker-fill,#dbeafe)",
        }}
      />,
    ),
  });
}
function useLeaflet() {
  return { L };
}
function useDebounceLoadingState(delay = 200) {
  const [isLoading, setIsLoading] = useState(false);
  const [showLoading, setShowLoading] = useState(false);
  const timeoutRef = useRef(null);
  useEffect(() => {
    if (isLoading) {
      timeoutRef.current = setTimeout(() => {
        setShowLoading(true);
      }, delay);
    } else {
      if (timeoutRef.current) {
        clearTimeout(timeoutRef.current);
        timeoutRef.current = null;
      }
      const timer = setTimeout(() => setShowLoading(false), 0);
      timeoutRef.current = timer;
    }
    return () => {
      if (timeoutRef.current) clearTimeout(timeoutRef.current);
    };
  }, [isLoading, delay]);
  return [showLoading, setIsLoading, isLoading];
}
export {
  Map,
  MapCircle,
  MapCircleMarker,
  MapControlContainer,
  MapControls,
  MapDrawCircle,
  MapDrawControl,
  MapDrawDelete,
  MapDrawEdit,
  MapDrawMarker,
  MapDrawPolygon,
  MapDrawPolyline,
  MapDrawRectangle,
  MapDrawUndo,
  MapFeatureGroup,
  MapFullscreenControl,
  MapLayerGroup,
  MapLayers,
  MapLayersControl,
  MapLocateControl,
  MapMarker,
  MapMarkerClusterGroup,
  MapPolygon,
  MapPolyline,
  MapPopup,
  MapRectangle,
  MapSearchControl,
  MapTileLayer,
  MapTooltip,
  MapZoomControl,
  useLeaflet,
};
