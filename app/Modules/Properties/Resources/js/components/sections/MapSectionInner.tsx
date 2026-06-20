import * as React from "react"
import { useMap } from "react-leaflet"
import { Section } from "@/Components/ui/section"
import { Alert, AlertDescription } from "@/Components/ui/alert"
import { Button } from "@/Components/ui/button"
import {
  Map,
  MapTileLayer,
  MapDrawControl,
  MapDrawMarker,
  MapDrawPolygon,
  MapDrawEdit,
  MapDrawDelete,
  MapDrawUndo,
  useLeaflet,
  MapLayers,
  MapControls,
  MapSearchControl,
} from "@/Components/ui/map"
import { MapDebugPanel } from "@/Components/ui/map-debug-panel"
import type { DebugData } from "@/Components/ui/map-debug-panel"
import { reverseGeocode, TUNISIA_BOUNDS, TUNISIA_CENTER } from "@/lib/geocoding"
import { getCountryBounds } from "@/lib/country-bounds"
import type { AddressData } from "@/lib/geocoding"
import { AlertTriangleIcon, BugIcon, MapPinIcon } from "lucide-react"
import { toast } from "sonner"

interface CityTerm {
  id: number
  name: string
  slug: string
  parent_id?: number
  parent_name?: string
}

interface MatchedCity {
  governorateId: string | null
  governorateName: string | null
  cityId: string | null
  cityName: string | null
}

function formatAddress(properties: Record<string, unknown>) {
  const parts: string[] = []
  if (properties.name) parts.push(properties.name as string)
  if (properties.formatted_address) parts.push(properties.formatted_address as string)
  if (properties.city || properties.locality) parts.push((properties.city || properties.locality) as string)
  if (properties.region || properties.state) parts.push((properties.region || properties.state) as string)
  if (properties.country) parts.push(properties.country as string)
  return parts.join(", ")
}

function normalizeCityName(name: string): string {
  return name
    .toLowerCase()
    .normalize("NFD")
    .replaceAll(/[\u0300-\u036F]/g, "")
    .replaceAll(/[''\u2019]/g, "")
    .replaceAll(/[-_\s]+/g, " ")
    .trim()
}

function matchCity(addressData: AddressData, cities: CityTerm[]): MatchedCity {
  const governorates = cities.filter((c) => !c.parent_id || c.parent_id === 0)
  const allCities = cities.filter((c) => c.parent_id && c.parent_id > 0)
  const normalizedState = normalizeCityName(addressData.state)
  const normalizedCity = normalizeCityName(addressData.city)

  const matchedGovernorate = governorates.find((g) => normalizeCityName(g.name) === normalizedState)
    || governorates.find((g) =>
      normalizedState.includes(normalizeCityName(g.name)) || normalizeCityName(g.name).includes(normalizedState)
    )

  let matchedCity = null
  if (matchedGovernorate) {
    matchedCity = allCities.find((c) => c.parent_id === matchedGovernorate.id && normalizeCityName(c.name) === normalizedCity)
      || allCities.find((c) => normalizeCityName(c.name) === normalizedCity)
  }

  if (!matchedGovernorate) {
    matchedCity = allCities.find((c) => normalizeCityName(c.name) === normalizedCity)
    if (matchedCity) {
      const parentGovernorate = governorates.find((g) => g.id === matchedCity.parent_id)
      if (parentGovernorate) {
        return {
          governorateId: String(parentGovernorate.id),
          governorateName: parentGovernorate.name,
          cityId: String(matchedCity.id),
          cityName: matchedCity.name,
        }
      }
    }
  }

  return {
    governorateId: matchedGovernorate ? String(matchedGovernorate.id) : null,
    governorateName: matchedGovernorate ? matchedGovernorate.name : null,
    cityId: matchedCity ? String(matchedCity.id) : null,
    cityName: matchedCity ? matchedCity.name : null,
  }
}

export interface MapSectionInnerData {
  meta?: {
    address?: string
    locality?: string
    latitude?: string
    longitude?: string
    lat?: string
    lng?: string
    long?: string
    map_data?: unknown
  }
  taxonomies?: {
    property_city?: string
  }
}

export interface MapSettings {
  map_country_code?: string
  map_bounds_south_west_lat?: number
  map_bounds_south_west_lng?: number
  map_bounds_north_east_lat?: number
  map_bounds_north_east_lng?: number
  map_center_lat?: number
  map_center_lng?: number
  map_min_zoom?: number
  map_max_zoom?: number
  map_default_zoom?: number
  map_marker_zoom?: number
  map_debug_show?: boolean
}

interface MapSectionInnerOptions {
  cities?: CityTerm[]
  mapSettings?: MapSettings
}

interface MapSectionInnerProps {
  data: MapSectionInnerData
  onChange: (_field: string, _value: unknown) => void
  errors?: Record<string, unknown>
  loading?: boolean
  onSubmit?: (_e: React.FormEvent) => void
  options?: MapSectionInnerOptions
}

interface GeoJsonFeature {
  type: string
  geometry?: {
    type: string
    coordinates: number[]
  }
  properties?: Record<string, unknown>
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
  isLocked: boolean
  minZoom: number
  swLat: number
  swLng: number
  neLat: number
  neLng: number
  defaultZoom: number
}) {
  const map = useMap()

  React.useLayoutEffect(() => {
    const handlers: Array<[string, { enable: () => void; disable: () => void } | undefined]> = [
      ["dragging", map.dragging],
      ["scrollWheelZoom", map.scrollWheelZoom],
      ["doubleClickZoom", map.doubleClickZoom],
      ["touchZoom", map.touchZoom],
      ["keyboard", map.keyboard],
      ["boxZoom", map.boxZoom],
    ]

    handlers.forEach(([_name, handler]) => {
      if (!handler) return
      if (isLocked) handler.disable()
      else handler.enable()
    })

    const effectiveMinZoom = isLocked ? minZoom : defaultZoom
    map.setMinZoom(effectiveMinZoom)
    map.setMaxBounds([[swLat, swLng], [neLat, neLng]])
  }, [map, isLocked, minZoom, swLat, swLng, neLat, neLng, defaultZoom])

  return null
}

export default function MapSectionInner({
  data,
  onChange,
  errors,
  loading = false,
  onSubmit,
  options = {},
}: MapSectionInnerProps) {
  const [searchPlace, setSearchPlace] = React.useState(() => {
    if (data?.meta?.address) {
      const addr = data.meta.address
      if (data?.meta?.locality) return `${addr}, ${data.meta.locality}`
      return addr
    }
    const lat = data?.meta?.latitude || data?.meta?.lat
    const lng = data?.meta?.longitude || data?.meta?.lng || data?.meta?.long
    if (lat && lng) return `Saved location (${Number.parseFloat(lat).toFixed(4)}, ${Number.parseFloat(lng).toFixed(4)})`
    return ""
  })
  const [stopTracking, setStopTracking] = React.useState(false)
  const [map, setMap] = React.useState<L.Map | null>(null)
  const [debugMode, setDebugMode] = React.useState(() => {
    const ms = (options as MapSectionInnerOptions)?.mapSettings
    return ms?.map_debug_show ?? true
  })
  const [debugData, setDebugData] = React.useState<DebugData | null>(null)
  const [gpsBannerVisible, setGpsBannerVisible] = React.useState(false)
  const [isReversingGeocode, setIsReversingGeocode] = React.useState(false)
  const [mapZoom, setMapZoom] = React.useState(() => {
    const ms = (options as MapSectionInnerOptions)?.mapSettings
    return Number(ms?.map_min_zoom) || 15
  })

  const debugDataRef = React.useRef<DebugData | null>(null)
  React.useEffect(() => { debugDataRef.current = debugData }, [debugData])

  const isInitialized = React.useRef(false)
  const hasLocation = !!(data?.meta?.latitude || data?.meta?.longitude)
  const [isLocked, setIsLockedState] = React.useState(() => hasLocation)
  const setIsLocked = React.useCallback((val: boolean | ((_prev: boolean) => boolean)) => { setIsLockedState(val) }, [])
  const firstLayerChange = React.useRef(true)

  const { cities = [], mapSettings = {} } = options as MapSectionInnerOptions

  const mapConfig = React.useMemo(() => {
    const countryFallback = getCountryBounds(mapSettings?.map_country_code || "")
    const fbBounds = countryFallback?.bounds || [TUNISIA_BOUNDS.southWest.lat, TUNISIA_BOUNDS.southWest.lng, TUNISIA_BOUNDS.northEast.lat, TUNISIA_BOUNDS.northEast.lng]
    const fbCenter = countryFallback?.center || [TUNISIA_CENTER.lat, TUNISIA_CENTER.lng]
    const fbMinZoom = countryFallback?.minZoom || 6
    const fbDefaultZoom = countryFallback?.defaultZoom || 7

    const swLat = Number(mapSettings?.map_bounds_south_west_lat) || fbBounds[0]
    const swLng = Number(mapSettings?.map_bounds_south_west_lng) || fbBounds[1]
    const neLat = Number(mapSettings?.map_bounds_north_east_lat) || fbBounds[2]
    const neLng = Number(mapSettings?.map_bounds_north_east_lng) || fbBounds[3]
    return {
      centerLat: Number(mapSettings?.map_center_lat) || fbCenter[0],
      centerLng: Number(mapSettings?.map_center_lng) || fbCenter[1],
      minZoom: Number(mapSettings?.map_min_zoom) || fbMinZoom,
      maxZoom: Number(mapSettings?.map_max_zoom) || 18,
      defaultZoom: Number(mapSettings?.map_default_zoom) || fbDefaultZoom,
      markerZoom: Number(mapSettings?.map_marker_zoom) || 18,
      debugShow: mapSettings?.map_debug_show ?? true,
      boundsSouthWest: [swLat, swLng] as [number, number],
      boundsNorthEast: [neLat, neLng] as [number, number],
      isWithinBounds: (lat: number, lng: number) => lat >= swLat && lat <= neLat && lng >= swLng && lng <= neLng,
    }
  }, [mapSettings])

  const getCoordinates = React.useCallback((): [number, number] => {
    const lat = data?.meta?.latitude || data?.meta?.lat
    const lng = data?.meta?.longitude || data?.meta?.lng || data?.meta?.long
    if (lat && lng) return [Number.parseFloat(lat), Number.parseFloat(lng)]
    return [mapConfig.centerLat, mapConfig.centerLng]
  }, [data?.meta?.latitude, data?.meta?.lat, data?.meta?.longitude, data?.meta?.lng, data?.meta?.long, mapConfig.centerLat, mapConfig.centerLng])

  const handleMetaChange = React.useCallback((field: string, value: unknown) => { onChange(`meta.${field}`, value) }, [onChange])

  const { L } = useLeaflet()

  const getMarkerIcon = React.useCallback(() => {
    if (!L) return null
    return L.divIcon({
      className: "",
      iconAnchor: [16, 32],
      popupAnchor: [0, -32],
      html: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-map-pin size-8 text-blue-600 fill-blue-100 filter drop-shadow-lg"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/></svg>`,
    })
  }, [L])

  const createDrawMarker = React.useCallback((lat: number, lng: number) => {
    if (!L || !map) return
    const newMarker = L.marker([lat, lng], { icon: getMarkerIcon()!, draggable: true })
    if (newMarker.dragging) newMarker.dragging.disable()
    map.fire("draw:created", { layer: newMarker, layerType: "marker" })
  }, [L, map, getMarkerIcon])

  const doReverseGeocode = React.useCallback(async (lat: number, lng: number) => {
    if (isReversingGeocode) return
    setIsReversingGeocode(true)

    const buildEmptyDebug = () => ({
      latitude: lat, longitude: lng, street: "", housenumber: "", name: "", city: "", state: "",
      postcode: "", country: "", countryCode: "", district: "", locality: "", fullAddress: "",
      displayName: "", matchedGovernorate: null, matchedCity: null, geocodingSource: null,
      isWithinTunisia: false, countryCodeMatch: false, configuredCountry: mapSettings?.map_country_code || "",
      osmId: null, osmType: "", rawJson: "", updatedAt: new Date(),
      overpassStreet: null, overpassHighway: null, overpassPerpDist: null, overpassConfidence: null,
    })

    try {
      const addressData = await reverseGeocode(lat, lng)
      if (!addressData) { setDebugData(buildEmptyDebug()); return }

      const matched = matchCity(addressData, cities)

      const addressLine = [addressData.housenumber, addressData.street || addressData.name].filter(Boolean).join(" ")

      if (addressLine) handleMetaChange("address", addressLine)
      if (addressData.postcode) handleMetaChange("zip", addressData.postcode)
      if (addressData.city) handleMetaChange("locality", addressData.city)

      const label = addressData.fullAddress || addressData.displayName || [addressLine, matched.cityName || matched.governorateName || addressData.city].filter(Boolean).join(", ")
      if (label) setSearchPlace(label)

      setDebugData({
        latitude: lat, longitude: lng, street: addressData.street, housenumber: addressData.housenumber,
        name: addressData.name, city: addressData.city, state: addressData.state, postcode: addressData.postcode,
        country: addressData.country, countryCode: addressData.countryCode, district: addressData.district,
        locality: addressData.locality, fullAddress: addressData.fullAddress, displayName: addressData.displayName,
        matchedGovernorate: matched.governorateName, matchedCity: matched.cityName || addressData.city,
        geocodingSource: addressData.source, isWithinTunisia: mapConfig.isWithinBounds(lat, lng),
        countryCodeMatch: addressData.countryCode === mapSettings?.map_country_code,
        configuredCountry: mapSettings?.map_country_code || "",
        osmId: addressData.osmId, osmType: addressData.osmType,
        rawJson: JSON.stringify(addressData.raw, null, 2), updatedAt: new Date(),
        overpassStreet: addressData.overpassStreet?.street ?? null,
        overpassHighway: addressData.overpassStreet?.highway ?? null,
        overpassPerpDist: addressData.overpassStreet?.perpDist ?? null,
        overpassConfidence: addressData.overpassStreet?.confidence ?? null,
      })
    } catch (error) {
      console.warn("[MapSection] Reverse geocoding failed:", error)
      setDebugData({
        latitude: lat, longitude: lng, street: "", housenumber: "", name: "", city: "", state: "",
        postcode: "", country: "", countryCode: "", district: "", locality: "", fullAddress: "",
        displayName: "", matchedGovernorate: null, matchedCity: null, geocodingSource: null,
        isWithinTunisia: false, countryCodeMatch: false, configuredCountry: mapSettings?.map_country_code || "",
        osmId: null, osmType: "", rawJson: "", updatedAt: new Date(),
        overpassStreet: null, overpassHighway: null, overpassPerpDist: null, overpassConfidence: null,
      })
    } finally {
      setIsReversingGeocode(false)
    }
  }, [cities, handleMetaChange, isReversingGeocode, mapSettings, mapConfig])

  const handlePlaceSelect = React.useCallback((feature: GeoJsonFeature) => {
    if (feature?.geometry?.type === "Point") {
      const coords = feature.geometry.coordinates
      const lng = coords[0] ?? 0
      const lat = coords[1] ?? 0

      if (!mapConfig.isWithinBounds(lat, lng)) {
        toast.error("This location is outside the allowed country area. Please select a location within the configured country.")
        return
      }

      const address = formatAddress(feature.properties ?? {})
      setSearchPlace(address)
      handleMetaChange("latitude", lat)
      handleMetaChange("longitude", lng)

      if (map) { map.setView([lat, lng], mapConfig.markerZoom); createDrawMarker(lat, lng) }

      doReverseGeocode(lat, lng)
      setIsLocked(true)
    }
  }, [handleMetaChange, map, createDrawMarker, doReverseGeocode, mapConfig, setIsLocked])

  const handleLocationFound = React.useCallback((event: { latlng: { lat: number; lng: number } }) => {
    const { lat, lng } = event.latlng

    if (!mapConfig.isWithinBounds(lat, lng)) {
      setGpsBannerVisible(true)
      toast.error("Your current location is outside the allowed country area. Location tracking is restricted to the configured country.")
      return
    }

    setStopTracking(true)
    handleMetaChange("latitude", lat)
    handleMetaChange("longitude", lng)
    setTimeout(() => setStopTracking(false), 500)

    if (map) { map.setView([lat, lng], mapConfig.markerZoom); createDrawMarker(lat, lng) }

    doReverseGeocode(lat, lng)
    setIsLocked(true)
  }, [handleMetaChange, map, createDrawMarker, doReverseGeocode, mapConfig, setIsLocked])

  const handleGpsPermissionDenied = React.useCallback(() => { setGpsBannerVisible(true) }, [])

  const handleClearSearch = React.useCallback(() => {
    setSearchPlace("")
    setStopTracking(true)
    setTimeout(() => setStopTracking(false), 100)
  }, [])

  React.useEffect(() => {
    if (!map || !L || isInitialized.current) return
    if (hasLocation && !data?.meta?.map_data) {
      const [lat, lng] = getCoordinates()
      createDrawMarker(lat, lng)
      map.setView([lat, lng], mapConfig.markerZoom)
      setTimeout(() => doReverseGeocode(lat, lng), 0)
    }
    isInitialized.current = true
  }, [map, L, hasLocation, getCoordinates, createDrawMarker, data?.meta?.map_data, doReverseGeocode, mapConfig])

  React.useEffect(() => { setIsLockedState(hasLocation) }, [hasLocation])

  React.useEffect(() => {
    if (!map || !L) return

    const handleCreated = (e: { layer: L.Layer }) => {
      const layer = e.layer
      if (layer instanceof L.Marker || layer instanceof L.Circle) {
        const latlng = layer.getLatLng()
        const currentDebug = debugDataRef.current
        const hasAddress = currentDebug?.street || currentDebug?.city
        const popupContent = `
          <div style="font-family: system-ui, sans-serif; line-height: 1.4; padding: 2px 0;">
            <p style="font-weight: 600; font-size: 13px; margin: 0 0 2px;">${hasAddress ? "Location" : "Coordinates"}</p>
            ${hasAddress ? `<p style="font-size: 12px; color: #666; margin: 0 0 2px;">${[currentDebug.housenumber, currentDebug.street].filter(Boolean).join(" ")}, ${currentDebug.city || ""}</p>` : ""}
            <p style="font-size: 11px; color: #999; margin: 0;">${latlng.lat.toFixed(6)}, ${latlng.lng.toFixed(6)}</p>
          </div>
        `
        layer.bindPopup(popupContent, { offset: [0, -5], closeButton: true })
        setTimeout(() => layer.openPopup(), 100)
      }
    }

    map.on(L.Draw.Event.CREATED, handleCreated)
    return () => { map.off(L.Draw.Event.CREATED, handleCreated) }
  }, [map, L])

  React.useEffect(() => {
    if (!map) return
    const updateZoom = () => setMapZoom(map.getZoom())
    updateZoom()
    map.on("zoomend", updateZoom)
    return () => { map.off("zoomend", updateZoom) }
  }, [map])

  const handleLayersChange = React.useCallback((layers: { getLayers: () => L.Layer[]; toGeoJSON: () => unknown; clearLayers: () => void }) => {
    if (!L || !layers) return

    if (firstLayerChange.current) { firstLayerChange.current = false }

    const layerArray = layers.getLayers()

    if (layerArray.length > 0) {
      const geojson = layers.toGeoJSON()
      handleMetaChange("map_data", JSON.stringify(geojson))
    } else {
      handleMetaChange("map_data", "")
      setIsLocked(false)
      return
    }

    const layer = layerArray[0]
    if (!layer) return
    let lat: number | null = null
    let lng: number | null = null

    if (typeof (layer as unknown as { getLatLng: () => { lat: number; lng: number } }).getLatLng === "function") {
      const latlng = (layer as unknown as { getLatLng: () => { lat: number; lng: number } }).getLatLng()
      lat = latlng.lat; lng = latlng.lng
    } else if (typeof (layer as unknown as { getBounds: () => { getCenter: () => { lat: number; lng: number } } }).getBounds === "function") {
      const bounds = (layer as unknown as { getBounds: () => { getCenter: () => { lat: number; lng: number } } }).getBounds()
      const center = bounds.getCenter()
      lat = center.lat; lng = center.lng
    } else if (typeof (layer as unknown as { getLatLngs: () => Array<{ lat: number; lng: number } | Array<{ lat: number; lng: number }>> }).getLatLngs === "function") {
      const latlngs = (layer as unknown as { getLatLngs: () => Array<{ lat: number; lng: number } | Array<{ lat: number; lng: number }>> }).getLatLngs()
      const first = Array.isArray(latlngs[0]) ? latlngs[0][0] : latlngs[0]
      if (first && typeof first.lat === "number") { lat = first.lat; lng = first.lng }
    }

    if (lat !== null && lng !== null) {
      if (mapConfig.isWithinBounds(lat, lng)) {
        handleMetaChange("latitude", lat)
        handleMetaChange("longitude", lng)
        doReverseGeocode(lat, lng)
        setIsLocked(true)
        setTimeout(() => map?.setView([lat, lng], mapConfig.markerZoom), 0)
      } else {
        toast.error("This location is outside the allowed country area. Please place the marker within the configured country.")
        layers.clearLayers()
        handleMetaChange("map_data", "")
        handleMetaChange("latitude", "")
        handleMetaChange("longitude", "")
      }
    }
  }, [handleMetaChange, L, doReverseGeocode, map, mapConfig, setIsLocked])

  const handleDeleteLocation = React.useCallback(() => {
    handleMetaChange("latitude", "")
    handleMetaChange("longitude", "")
    handleMetaChange("locality", "")
    handleMetaChange("address", "")
    handleMetaChange("zip", "")
    setSearchPlace("")
    setDebugData(null)
  }, [handleMetaChange])

  const handleToolActivate = React.useCallback((mode: string | null) => {
    if (mode) {
      setIsLocked(false)
      if (map && hasLocation) {
        const [lat, lng] = getCoordinates()
        setTimeout(() => map.setView([lat, lng], mapConfig.markerZoom), 0)
      }
    } else {
      if (hasLocation && map) {
        const [lat, lng] = getCoordinates()
        setIsLocked(true)
        setTimeout(() => map.setView([lat, lng], mapConfig.markerZoom), 0)
      }
    }
  }, [map, getCoordinates, mapConfig, setIsLocked, hasLocation])

  return (
    <Section
      title="Location"
      description="Set property location on the map"
      icon={() => null}
      onSubmit={onSubmit}
      loading={loading}
      error={errors?.general}
      fieldErrors={errors}
      submitLabel="Save Location"
    >
      <div className="space-y-4">
        {gpsBannerVisible && (
          <Alert variant="destructive" className="border-destructive/50">
            <AlertTriangleIcon className="size-4" />
            <AlertDescription className="flex items-center justify-between gap-2">
              <span>
                GPS location is unavailable. Please enable location services in your browser settings to use current location tracking.
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

        <div className="relative h-[400px] w-full rounded-lg border overflow-hidden bg-slate-50 dark:bg-slate-900">
          <Map
            ref={(instance: unknown) => setMap(instance as L.Map | null)}
            center={getCoordinates()}
            zoom={hasLocation ? mapConfig.markerZoom : mapConfig.defaultZoom}
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
                url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                darkUrl="https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}.png"
                attribution="&copy; OSM"
                darkAttribution="&copy; OSM"
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
                placeholder="Search location..."
                value={searchPlace}
                onChange={setSearchPlace}
                onClear={handleClearSearch}
                onPlaceSelect={handlePlaceSelect}
                bbox={[
                  mapConfig.boundsSouthWest[1],
                  mapConfig.boundsSouthWest[0],
                  mapConfig.boundsNorthEast[1],
                  mapConfig.boundsNorthEast[0],
                ]}
                lang="fr"
                position="top-2 left-1/2 -translate-x-1/2"
                className="shadow-2xl"
              />

              <MapControls
                onLocationFound={handleLocationFound}
                onLocationError={(error: unknown) => { console.warn(`[MapSection] Location error: ${(error as Error).message}`) }}
                onGpsPermissionDenied={handleGpsPermissionDenied}
                stop={stopTracking}
                className=""
              />
              <MapDrawControl
                onLayersChange={handleLayersChange}
                onDelete={handleDeleteLocation}
                onToolActivate={handleToolActivate}
                position="bottom-1 left-1"
                initialGeoJson={data?.meta?.map_data}
                markerIcon={getMarkerIcon()}
              >
                <MapDrawMarker className="" />
                <MapDrawPolygon className="" />
                <MapDrawEdit className="" />
                <MapDrawDelete className="" />
                <MapDrawUndo className="" />
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

        {debugMode && <MapDebugPanel data={debugData} zoom={mapZoom} className="" />}

        {debugData && !debugMode && (
          <div className="flex items-center gap-2 text-xs text-muted-foreground">
            <MapPinIcon className="size-3" />
            <span>
              {debugData.street || debugData.housenumber
                ? `${debugData.housenumber ? debugData.housenumber + " " : ""}${debugData.street}`
                : ""}
              {debugData.city && `, ${debugData.city}`}
              {debugData.matchedGovernorate && ` (${debugData.matchedGovernorate})`}
              {debugData.postcode && `, ${debugData.postcode}`}
            </span>
            <span className="text-[10px] text-muted-foreground/60">Zoom: {mapZoom}</span>
            {(debugData.street || debugData.city) && (
              <span className="text-[10px] text-muted-foreground/60">&bull; auto-detected</span>
            )}
          </div>
        )}
      </div>
    </Section>
  )
}
