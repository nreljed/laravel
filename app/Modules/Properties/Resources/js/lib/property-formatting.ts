export interface TermItem {
  id: number;
  name: string;
  group?: string;
  icon?: string;
  children?: TermItem[];
}

export interface GalleryImage {
  id: number;
  url: string;
  thumbnail_url: string;
  alt_text: string | null;
  caption: string | null;
  is_featured: boolean;
  sort_order: number;
}

export interface PropertyData {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  address: string | null;
  city: string | null;
  state: string | null;
  zip: string | null;
  country: string | null;
  latitude: number | null;
  longitude: number | null;
  price: number | null;
  currency: string;
  surface: number | null;
  surface_unit: string | null;
  status: string;
  featured: boolean;
  video_url: string | null;
  virtual_tour_url: string | null;
  types: TermItem[];
  categories: TermItem[];
  features: TermItem[];
  images: GalleryImage[];
  featuredImage: GalleryImage | null;
  metadata: Record<string, unknown> | null;
}

export interface PropertyListItem {
  id: number;
  name: string;
  slug: string;
  city: string | null;
  state: string | null;
  price: number | null;
  price_formatted: string;
  surface: number | null;
  surface_formatted: string;
  status: string;
  featured: boolean;
  user?: { id: number; name: string };
}

export function getStatusColor(status: string): "default" | "secondary" | "destructive" | "outline" {
  switch (status) {
    case "published": return "default";
    case "draft": return "secondary";
    case "archived": return "outline";
    default: return "secondary";
  }
}

export const RENTAL_PERIOD_OPTIONS = [
  { value: "day", label: "Per Day" },
  { value: "week", label: "Per Week" },
  { value: "month", label: "Per Month" },
  { value: "year", label: "Per Year" },
]

export const CURRENCY_OPTIONS = [
  { value: "USD", label: "US Dollar ($)", symbol: "$" },
  { value: "EUR", label: "Euro (€)", symbol: "€" },
  { value: "TND", label: "Tunisian Dinar (د.ت)", symbol: "د.ت" },
  { value: "GBP", label: "British Pound (£)", symbol: "£" },
  { value: "SAR", label: "Saudi Riyal (ر.س)", symbol: "ر.س" },
  { value: "AED", label: "UAE Dirham (د.إ)", symbol: "د.إ" },
]

export const SURFACE_UNIT_OPTIONS = [
  { value: "sq_ft", label: "Square Feet (ft²)" },
  { value: "sq_m", label: "Square Meters (m²)" },
]

export const CURRENCY_SYMBOLS: Record<string, string> = {
  USD: "$", EUR: "€", TND: "د.ت", GBP: "£", SAR: "ر.س", AED: "د.إ",
  MAD: "د.م.", DZD: "DA", EGP: "E£", QAR: "ر.ق", KWD: "د.ك", BHD: ".د.ب",
  OMR: "ر.ع", JOD: "د.أ", LYD: "ل.د", SDG: "ج.س.", SYP: "ل.س", IQD: "ع.د",
  IRR: "﷼", TRY: "₺",
}

export const SURFACE_UNITS: Record<string, string> = {
  sq_ft: "ft²", sq_m: "m²", sq_yd: "yd²", sq_meter: "㎡", sq_feet: "sq ft",
  acres: "ac", hectares: "ha",
}

export function getCurrencySymbol(currencyCode: string): string {
  return CURRENCY_SYMBOLS[currencyCode] || currencyCode
}

export function getSurfaceUnit(unitCode: string): string {
  return SURFACE_UNITS[unitCode] || unitCode
}

export function formatPrice(value: number | string, settings: {
  property_default_currency?: string
  property_currency_position?: "before" | "after"
  property_thousands_separator?: string
  property_decimal_separator?: string
  property_price_empty_text?: string
} = {}): string {
  const num = Number(value)
  if (isNaN(num) || num === 0) return settings.property_price_empty_text || ""
  const currency = settings.property_default_currency || "USD"
  const position = settings.property_currency_position || "before"
  const symbol = CURRENCY_SYMBOLS[currency] ?? currency
  const thousandsSep = settings.property_thousands_separator || ","
  const decimalSep = settings.property_decimal_separator || "."
  let formatted = num % 1 === 0 ? num.toString() : num.toFixed(2)
  if (decimalSep !== ".") formatted = formatted.replace(".", decimalSep)
  if (thousandsSep && num >= 1000) {
    const parts = formatted.split(decimalSep)
    if (parts[0]) parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousandsSep)
    formatted = parts.join(decimalSep)
  }
  return position === "before" ? symbol + formatted : formatted + " " + symbol
}

export function formatSurface(value: number | string, settings: {
  property_surface_unit?: string
  property_thousands_separator?: string
  property_decimal_separator?: string
} = {}): string {
  const num = Number(value)
  if (isNaN(num) || num === 0) return ""
  const unit = settings.property_surface_unit || "sq_m"
  const unitLabel = SURFACE_UNITS[unit] ?? unit
  const thousandsSep = settings.property_thousands_separator || ","
  const decimalSep = settings.property_decimal_separator || "."
  let formatted = num % 1 === 0 ? num.toString() : num.toFixed(2)
  if (decimalSep !== ".") formatted = formatted.replace(".", decimalSep)
  if (thousandsSep && num >= 1000) {
    const parts = formatted.split(decimalSep)
    if (parts[0]) parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousandsSep)
    formatted = parts.join(decimalSep)
  }
  return formatted + " " + unitLabel
}
