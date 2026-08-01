const isDev = import.meta.env.DEV;

function debugLog(...args: unknown[]): void {
  if (!isDev) {
    return;
  }
  debugLog(...args);
}

export interface OverpassStreetMatch {
  street: string;
  osmId: number;
  highway: string;
  perpDist: number;
  confidence: "high" | "medium" | "low";
}

export interface AddressData {
  street: string;
  housenumber: string;
  city: string;
  state: string;
  postcode: string;
  country: string;
  countryCode: string;
  district: string;
  locality: string;
  name: string;
  fullAddress: string;
  displayName: string;
  source: "overpass" | "photon" | "nominatim";
  osmId: number | null;
  osmType: string;
  raw: Record<string, unknown>;
  overpassStreet?: OverpassStreetMatch;
}

interface PhotonFeature {
  properties: {
    osm_id: number;
    osm_type: string;
    name?: string;
    street?: string;
    housenumber?: string;
    city?: string;
    state?: string;
    postcode?: string;
    country?: string;
    countrycode?: string;
    district?: string;
    locality?: string;
    osm_key?: string;
    osm_value?: string;
    type?: string;
    [key: string]: unknown;
  };
  geometry: {
    coordinates: [number, number];
    type: string;
  };
}

interface NominatimResponse {
  place_id: number;
  licence: string;
  osm_type: string;
  osm_id: number;
  lat: string;
  lon: string;
  display_name: string;
  category: string;
  type: string;
  address: {
    house_number?: string;
    road?: string;
    city?: string;
    town?: string;
    village?: string;
    state?: string;
    postcode?: string;
    country?: string;
    country_code?: string;
    suburb?: string;
    neighbourhood?: string;
    county?: string;
    municipality?: string;
    [key: string]: unknown;
  };
}

export const TUNISIA_BOUNDS = {
  southWest: { lat: 30.2, lng: 7.5 },
  northEast: { lat: 37.5, lng: 11.6 },
  asLeaflet: () =>
    [
      [30.2, 7.5],
      [37.5, 11.6],
    ] as [[number, number], [number, number]],
  asPhotonBbox: () => [7.5, 30.2, 11.6, 37.5] as [number, number, number, number],
};

export const TUNISIA_CENTER = { lat: 34, lng: 9.5 };

export const TUNISIA_PHOTON_BBOX: [number, number, number, number] = [7.5, 30.2, 11.6, 37.5];

export function isWithinTunisia(lat: number, lng: number): boolean {
  return (
    lat >= TUNISIA_BOUNDS.southWest.lat &&
    lat <= TUNISIA_BOUNDS.northEast.lat &&
    lng >= TUNISIA_BOUNDS.southWest.lng &&
    lng <= TUNISIA_BOUNDS.northEast.lng
  );
}

function haversineDistance(lat1: number, lng1: number, lat2: number, lng2: number): number {
  const R = 6371e3;
  const toRad = (d: number) => (d * Math.PI) / 180;
  const dLat = toRad(lat2 - lat1);
  const dLng = toRad(lng2 - lng1);
  const a =
    Math.sin(dLat / 2) ** 2 +
    Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLng / 2) ** 2;
  return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

function pointToSegmentDistance(
  px: number,
  py: number,
  ax: number,
  ay: number,
  bx: number,
  by: number,
): number {
  const abx = bx - ax;
  const aby = by - ay;
  const apx = px - ax;
  const apy = py - ay;
  const ab2 = abx * abx + aby * aby;
  if (ab2 === 0) return Math.hypot(px - ax, py - ay);
  let t = (apx * abx + apy * aby) / ab2;
  t = Math.max(0, Math.min(1, t));
  const cx = ax + t * abx;
  const cy = ay + t * aby;
  return Math.hypot(px - cx, py - cy);
}

function pointToWayDistance(
  px: number,
  py: number,
  geometry: Array<{ lat: number; lon: number }>,
): number {
  let minDist = Infinity;
  for (let i = 0; i < geometry.length - 1; i++) {
    const a = geometry[i];
    const b = geometry[i + 1];
    if (!a || !b) continue;
    const dist = pointToSegmentDistance(px, py, a.lat, a.lon, b.lat, b.lon);
    if (dist < minDist) minDist = dist;
  }
  return minDist;
}

function safeString(val: unknown): string {
  if (val === null || val === undefined) return "";
  return String(val);
}

const TYPE_PREFERENCE: Record<string, number> = {
  house: 100,
  street: 90,
  locality: 40,
  district: 30,
  city: 20,
  country: 10,
};

function pickBestPhotonFeature(
  features: PhotonFeature[],
  queryLat: number,
  queryLng: number,
): { feature: PhotonFeature; score: number } {
  let best: PhotonFeature = features[0]!;
  let bestScore = -Infinity;

  for (const f of features) {
    const props = f.properties;
    const extent = props.extent as [number, number, number, number] | undefined;
    const featCoords = f.geometry?.coordinates;
    const featType = safeString(props.type);
    const typePref = TYPE_PREFERENCE[featType] ?? 0;

    let score = typePref;

    const featLat = featCoords?.[1];
    const featLng = featCoords?.[0];

    if (featLat != null && featLng != null) {
      const dist = haversineDistance(queryLat, queryLng, featLat, featLng);
      score -= dist;
      if (dist > 500) {
        score -= 1000;
      }
    }

    if (extent) {
      const [minLng, minLat, maxLng, maxLat] = extent;
      const pointInExtent =
        queryLat >= minLat && queryLat <= maxLat && queryLng >= minLng && queryLng <= maxLng;
      if (pointInExtent) {
        score += 200;
      }
    }

    debugLog(
      `[Photon] Candidate: type=${featType}, name=${props.name || "(unnamed)"}, typePref=${typePref}, score=${score}${extent ? ", hasExtent" : ""}${featLat != null ? `, dist=${Math.round(haversineDistance(queryLat, queryLng, featLat, featLng))}m` : ""}`,
    );

    if (score > bestScore) {
      best = f;
      bestScore = score;
    }
  }

  debugLog(
    `[Photon] Selected: type=${best.properties.type}, name=${best.properties.name || "(unnamed)"}, score=${bestScore}`,
  );
  return { feature: best, score: bestScore };
}

async function reverseViaPhoton(
  lat: number,
  lng: number,
  signal?: AbortSignal,
): Promise<AddressData | null> {
  const url = new URL("https://photon.komoot.io/reverse");
  url.searchParams.set("lat", String(lat));
  url.searchParams.set("lon", String(lng));
  url.searchParams.set("limit", "5");
  url.searchParams.set("lang", "fr");

  debugLog(`[Photon] GET ${url.href}`);
  const t0 = performance.now();

  const response = await fetch(url.href, { signal });

  if (!response.ok) {
    throw new Error(`Photon API error: ${response.status}`);
  }

  const data = await response.json();
  const ms = Math.round(performance.now() - t0);
  const features: PhotonFeature[] = data.features;

  debugLog(`[Photon] Response ${response.status} (${ms}ms) — ${features?.length ?? 0} features`);

  if (!features || features.length === 0) {
    console.warn("[Photon] No features found");
    return null;
  }

  const selected = pickBestPhotonFeature(features, lat, lng).feature;
  const props = selected.properties;

  const housenumber = safeString(props.housenumber);
  const street = safeString(props.street);
  const name = safeString(props.name);
  const city = safeString(props.city);
  const state = safeString(props.state);
  const postcode = safeString(props.postcode);
  const country = safeString(props.country);
  const countryCode = safeString(props.countrycode).toLowerCase();
  const district = safeString(props.district);
  const locality = safeString(props.locality);

  const fullParts = [name, housenumber, street, district, locality, city, state, country].filter(
    Boolean,
  );
  const fullAddress = fullParts.join(", ");

  const displayParts = [housenumber, street || name, city, country].filter(Boolean);
  const displayName = displayParts.join(", ");

  const featCoords = selected.geometry?.coordinates;
  const featDist = featCoords
    ? Math.round(haversineDistance(lat, lng, featCoords[1], featCoords[0]))
    : null;

  debugLog(`[Photon] Parsed:`, {
    housenumber,
    street,
    name,
    city,
    state,
    postcode,
    country,
    countryCode,
    district,
    locality,
    osmId: (props.osm_id as number) ?? null,
    osmType: safeString(props.osm_type),
    osmKey: safeString(props.osm_key),
    osmValue: safeString(props.osm_value),
    type: safeString(props.type),
    distanceFromClick: featDist ? `${featDist}m` : "unknown",
  });

  return {
    street,
    housenumber,
    city,
    state,
    postcode,
    country,
    countryCode,
    district,
    locality,
    name,
    fullAddress,
    displayName,
    source: "photon",
    osmId: (props.osm_id as number) ?? null,
    osmType: safeString(props.osm_type),
    raw: props as Record<string, unknown>,
  };
}

interface OverpassWayElement {
  type: string;
  id: number;
  tags?: {
    name?: string;
    highway?: string;
    [key: string]: unknown;
  };
  geometry: Array<{ lat: number; lon: number }>;
}

interface OverpassResponse {
  elements: OverpassWayElement[];
}

const OVERPASS_STREET_SEARCH_RADIUS = 20;
const OVERPASS_PERP_DIST_HIGH = 5;
const OVERPASS_PERP_DIST_MEDIUM = 12;

async function reverseViaOverpass(
  lat: number,
  lng: number,
  signal?: AbortSignal,
): Promise<OverpassStreetMatch | null> {
  const query = `[out:json];(way(around:${OVERPASS_STREET_SEARCH_RADIUS},${lat},${lng})["highway"];);out geom tags;`;
  const url = new URL("https://overpass-api.de/api/interpreter");
  url.searchParams.set("data", query);

  const t0 = performance.now();

  debugLog(`[Overpass] GET ${url.href}`);

  const response = await fetch(url.href, { signal });

  if (!response.ok) {
    throw new Error(
      `Overpass API error: ${response.status}${response.status === 429 ? " (rate limited)" : ""}`,
    );
  }

  const ms = Math.round(performance.now() - t0);
  const data: OverpassResponse = await response.json();
  debugLog(`[Overpass] Response ${response.status} (${ms}ms) — ${data.elements?.length ?? 0} ways`);

  if (!data.elements || data.elements.length === 0) {
    console.warn("[Overpass] No highway ways found near point");
    return null;
  }

  const scored: Array<{
    element: OverpassWayElement;
    perpDist: number;
    name: string;
    highway: string;
  }> = [];

  for (const el of data.elements) {
    if (!el.geometry || el.geometry.length < 2) continue;
    const tags = el.tags;
    if (!tags || !tags.highway || !tags.name) continue;

    const perpDist = pointToWayDistance(lat, lng, el.geometry);
    scored.push({ element: el, perpDist, name: tags.name, highway: tags.highway });
  }

  if (scored.length === 0) {
    console.warn("[Overpass] No named highway ways found");
    return null;
  }

  scored.sort((a, b) => a.perpDist - b.perpDist);
  const best = scored[0]!;

  const confidence: "high" | "medium" | "low" =
    best.perpDist <= OVERPASS_PERP_DIST_HIGH
      ? "high"
      : best.perpDist <= OVERPASS_PERP_DIST_MEDIUM
        ? "medium"
        : "low";

  debugLog(
    `[Overpass] Best match: ${best.name} (highway=${best.highway}, perpDist=${best.perpDist.toFixed(1)}m, confidence=${confidence})`,
  );

  if (scored.length > 1) {
    for (const [i, s] of scored.slice(1).entries()) {
      debugLog(`[Overpass]   #${i + 2}: ${s.name} (perpDist=${s.perpDist.toFixed(1)}m)`);
    }
  }

  return {
    street: best.name,
    osmId: best.element.id,
    highway: best.highway,
    perpDist: best.perpDist,
    confidence,
  };
}

async function reverseViaNominatim(
  lat: number,
  lng: number,
  signal?: AbortSignal,
): Promise<AddressData | null> {
  const url = new URL("https://nominatim.openstreetmap.org/reverse");
  url.searchParams.set("format", "jsonv2");
  url.searchParams.set("lat", String(lat));
  url.searchParams.set("lon", String(lng));
  url.searchParams.set("addressdetails", "1");

  debugLog(`[Nominatim] GET ${url.href}`);
  const t0 = performance.now();

  const response = await fetch(url.href, {
    signal,
    headers: {
      "User-Agent": "CRM-Dashboard/1.0",
      "Accept-Language": "fr, en;q=0.5",
    },
  });

  if (!response.ok) {
    throw new Error(`Nominatim API error: ${response.status}`);
  }

  const data: NominatimResponse = await response.json();
  const ms = Math.round(performance.now() - t0);

  debugLog(
    `[Nominatim] Response ${response.status} (${ms}ms)`,
    data.display_name ? `— ${data.display_name}` : "",
  );

  if (!data || !data.address) {
    console.warn("[Nominatim] No address in response");
    return null;
  }

  const addr = data.address;

  const housenumber = safeString(addr.house_number);
  const street = safeString(addr.road);
  const name = safeString(addr.road);
  const city = safeString(addr.city || addr.town || addr.village || "");
  const state = safeString(addr.state);
  const postcode = safeString(addr.postcode);
  const country = safeString(addr.country);
  const countryCode = safeString(addr.country_code).toLowerCase();
  const district = safeString(addr.suburb || addr.neighbourhood || "");
  const locality = safeString(addr.municipality || addr.county || "");

  const fullAddress =
    data.display_name ||
    [name, housenumber, street, district, locality, city, state, country]
      .filter(Boolean)
      .join(", ");

  const displayParts = [housenumber, street || name, city, country].filter(Boolean);
  const displayName = displayParts.join(", ");

  const respLat = Number(data.lat);
  const respLng = Number(data.lon);
  const featDist =
    !Number.isNaN(respLat) && !Number.isNaN(respLng)
      ? Math.round(haversineDistance(lat, lng, respLat, respLng))
      : null;

  debugLog(`[Nominatim] Parsed:`, {
    housenumber,
    street,
    name,
    city,
    state,
    postcode,
    country,
    countryCode,
    district,
    locality,
    osmId: data.osm_id,
    osmType: data.osm_type,
    category: data.category,
    type: data.type,
    distanceFromClick: featDist ? `${featDist}m` : "unknown",
  });

  return {
    street,
    housenumber,
    city,
    state,
    postcode,
    country,
    countryCode,
    district,
    locality,
    name,
    fullAddress,
    displayName,
    source: "nominatim",
    osmId: data.osm_id,
    osmType: data.osm_type,
    raw: addr as Record<string, unknown>,
  };
}

export async function reverseGeocode(
  lat: number,
  lng: number,
  options?: { timeout?: number; signal?: AbortSignal },
): Promise<AddressData | null> {
  if (!isWithinTunisia(lat, lng)) {
    if (Math.abs(lat) > 1 || Math.abs(lng) > 1) {
      console.warn(`[Geocode] Coordinates (${lat}, ${lng}) outside Tunisia bounds`);
    }
    return null;
  }

  const timeout = options?.timeout ?? 7000;
  const controller = new AbortController();
  const timeoutId = setTimeout(() => controller.abort(), timeout);

  const combinedSignal = options?.signal
    ? combineAbortSignals(options.signal, controller.signal)
    : controller.signal;

  const OVERPASS_TIMEOUT = 1500;
  const overpassController = new AbortController();
  const overpassTimeoutId = setTimeout(() => {
    if (!overpassController.signal.aborted) {
      overpassController.abort();
    }
  }, OVERPASS_TIMEOUT);
  const overpassSignal = combineAbortSignals(combinedSignal, overpassController.signal);

  try {
    const [overpassResult, photonResult] = await Promise.allSettled([
      reverseViaOverpass(lat, lng, overpassSignal),
      reverseViaPhoton(lat, lng, combinedSignal),
    ]);
    clearTimeout(overpassTimeoutId);

    const overpass = overpassResult.status === "fulfilled" ? overpassResult.value : null;
    const photon = photonResult.status === "fulfilled" ? photonResult.value : null;

    if (overpass?.confidence === "high" || overpass?.confidence === "medium") {
      debugLog(
        `[Geocode] Overpass matched street: ${overpass.street} (${overpass.perpDist.toFixed(1)}m, ${overpass.confidence})`,
      );

      if (photon) {
        const merged: AddressData = {
          ...photon,
          street: overpass.street,
          name: overpass.street,
          source: "overpass",
          osmId: overpass.osmId,
          osmType: "way",
          fullAddress: [
            overpass.street,
            photon.housenumber,
            photon.district,
            photon.locality,
            photon.city,
            photon.state,
            photon.country,
          ]
            .filter(Boolean)
            .join(", "),
          displayName: [photon.housenumber, overpass.street, photon.city, photon.country]
            .filter(Boolean)
            .join(", "),
          overpassStreet: overpass,
        };
        return merged;
      }

      const fallback: AddressData = {
        street: overpass.street,
        housenumber: "",
        city: "",
        state: "",
        postcode: "",
        country: "",
        countryCode: "",
        district: "",
        locality: "",
        name: overpass.street,
        fullAddress: overpass.street,
        displayName: overpass.street,
        source: "overpass",
        osmId: overpass.osmId,
        osmType: "way",
        raw: {},
        overpassStreet: overpass,
      };
      return fallback;
    }

    if (photon) {
      return photon;
    }

    if (overpass) {
      console.warn(
        `[Geocode] Overpass matched ${overpass.street} but confidence is ${overpass.confidence} (${overpass.perpDist.toFixed(1)}m)`,
      );
      const fallback: AddressData = {
        street: overpass.street,
        housenumber: "",
        city: "",
        state: "",
        postcode: "",
        country: "",
        countryCode: "",
        district: "",
        locality: "",
        name: overpass.street,
        fullAddress: overpass.street,
        displayName: overpass.street,
        source: "overpass",
        osmId: overpass.osmId,
        osmType: "way",
        raw: {},
        overpassStreet: overpass,
      };
      return fallback;
    }

    if (photonResult.status === "rejected") {
      console.warn("[Geocode] Photon failed:", photonResult.reason);
    }
    if (overpassResult.status === "rejected") {
      console.warn("[Geocode] Overpass failed:", overpassResult.reason);
    }

    console.warn("[Geocode] Overpass and Photon both failed, trying Nominatim");
    try {
      const result = await reverseViaNominatim(lat, lng, combinedSignal);
      if (result) {
        return result;
      }
      console.warn("[Geocode] Nominatim also returned no data");
    } catch (error) {
      console.warn("[Geocode] Nominatim also failed:", error);
    }

    return null;
  } finally {
    clearTimeout(timeoutId);
  }
}

function combineAbortSignals(...signals: AbortSignal[]): AbortSignal {
  const controller = new AbortController();

  for (const signal of signals) {
    if (signal.aborted) {
      controller.abort(signal.reason);
      return controller.signal;
    }
    signal.addEventListener("abort", () => controller.abort(signal.reason), { once: true });
  }

  return controller.signal;
}
