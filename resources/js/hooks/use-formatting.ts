import { useMemo, useCallback } from "react";
import { usePage } from "@inertiajs/react";

export interface FormattingSettings {
  default_currency: string;
  currency_position: string;
  price_empty_text: string;
  surface_unit: string;
  thousands_separator: string;
  decimal_separator: string;
  date_format: string;
  time_format: string;
  start_of_week: string;
  timezone: string;
}

export interface FormattingOptions {
  currency?: string;
  position?: string;
  millions_sep?: string;
  thousands_sep?: string;
  decimal_sep?: string;
  empty_text?: string;
  unit?: string;
}

interface UseFormattingReturn {
  settings: FormattingSettings;
  formatPrice: (_value: number | string, _overrides?: FormattingOptions) => string;
  formatSurface: (_value: number | string, _overrides?: FormattingOptions) => string;
  formatNumber: (_value: number | string, _thousandsSep?: string, _decimalSep?: string) => string;
  getCurrencySymbol: (_currencyCode?: string) => string;
  getSurfaceUnit: (_unitCode?: string) => string;
  currencyOptions: Array<{ value: string; label: string; symbol: string }>;
  surfaceUnitOptions: Array<{ value: string; label: string }>;
}

const CURRENCY_SYMBOLS: Record<string, string> = {
  USD: "$",
  EUR: "€",
  GBP: "£",
  TND: "د.ت",
  MAD: "د.م.",
  DZD: "DA",
  EGP: "E£",
  SAR: "ر.س",
  AED: "د.إ",
  QAR: "ر.ق",
  KWD: "د.ك",
  BHD: ".د.ب",
  OMR: "ر.ع",
  JOD: "د.أ",
  LYD: "ل.د",
  SDG: "ج.س.",
  SYP: "ل.س",
  IQD: "ع.د",
  IRR: "﷼",
  TRY: "₺",
};

const SURFACE_UNITS: Record<string, string> = {
  sq_ft: "ft²",
  sq_m: "m²",
  sq_yd: "yd²",
  sq_meter: "㎡",
  sq_feet: "sq ft",
  acres: "ac",
  hectares: "ha",
};

const CURRENCY_OPTIONS: Array<{ value: string; label: string; symbol: string }> = [
  { value: "USD", label: "US Dollar ($)", symbol: "$" },
  { value: "EUR", label: "Euro (€)", symbol: "€" },
  { value: "GBP", label: "British Pound (£)", symbol: "£" },
  { value: "TND", label: "Tunisian Dinar (د.ت)", symbol: "د.ت" },
  { value: "MAD", label: "Moroccan Dirham (د.م.)", symbol: "د.م." },
  { value: "DZD", label: "Algerian Dinar (DA)", symbol: "DA" },
  { value: "EGP", label: "Egyptian Pound (E£)", symbol: "E£" },
  { value: "SAR", label: "Saudi Riyal (ر.س)", symbol: "ر.س" },
  { value: "AED", label: "UAE Dirham (د.إ)", symbol: "د.إ" },
  { value: "QAR", label: "Qatari Riyal (ر.ق)", symbol: "ر.ق" },
  { value: "KWD", label: "Kuwaiti Dinar (د.ك)", symbol: "د.ك" },
  { value: "BHD", label: "Bahraini Dinar (.د.ب)", symbol: ".د.ب" },
  { value: "OMR", label: "Omani Rial (ر.ع)", symbol: "ر.ع" },
  { value: "JOD", label: "Jordanian Dinar (د.أ)", symbol: "د.أ" },
  { value: "LYD", label: "Libyan Dinar (ل.د)", symbol: "ل.د" },
  { value: "SDG", label: "Sudanese Pound (ج.س.)", symbol: "ج.س." },
  { value: "SYP", label: "Syrian Pound (ل.س)", symbol: "ل.س" },
  { value: "IQD", label: "Iraqi Dinar (ع.د)", symbol: "ع.د" },
  { value: "IRR", label: "Iranian Rial (﷼)", symbol: "﷼" },
  { value: "TRY", label: "Turkish Lira (₺)", symbol: "₺" },
];

const SURFACE_UNIT_OPTIONS: Array<{ value: string; label: string }> = [
  { value: "sq_ft", label: "Square Feet (ft²)" },
  { value: "sq_m", label: "Square Meters (m²)" },
  { value: "sq_yd", label: "Square Yards (yd²)" },
  { value: "sq_meter", label: "Square Meters (㎡)" },
  { value: "sq_feet", label: "Square Feet (sq ft)" },
  { value: "acres", label: "Acres (ac)" },
  { value: "hectares", label: "Hectares (ha)" },
];

export { CURRENCY_SYMBOLS, SURFACE_UNITS, CURRENCY_OPTIONS, SURFACE_UNIT_OPTIONS };

function buildNumber(value: number | string, thousandsSep: string, decimalSep: string): string {
  const num = Number(value);
  if (Number.isNaN(num)) return "";
  let formatted = num.toFixed(2);
  if (decimalSep !== ".") {
    formatted = formatted.split(".").join(decimalSep);
  }
  if (thousandsSep && num >= 1000) {
    const parts = formatted.split(decimalSep);
    if (parts[0]) {
      parts[0] = parts[0].replaceAll(/\B(?=(\d{3})+(?!\d))/g, () => thousandsSep);
    }
    formatted = parts.join(decimalSep);
  }
  if (Number.isSafeInteger(num)) {
    const suffix = decimalSep + "00";
    if (formatted.endsWith(suffix)) {
      formatted = formatted.slice(0, -3);
    }
  }
  return formatted;
}

function buildPrice(value: number | string, options: FormattingOptions): string {
  const num = Number(value);
  const emptyText = options.empty_text ?? "";
  if (num === 0 || Number.isNaN(num)) {
    return emptyText || "";
  }
  const currency = options.currency || "USD";
  const position = options.position || "before";
  const symbol = CURRENCY_SYMBOLS[currency] ?? currency;
  const formatted = buildNumber(value, options.thousands_sep || ",", options.decimal_sep || ".");
  return position === "before" ? symbol + formatted : formatted + " " + symbol;
}

function buildSurface(value: number | string, options: FormattingOptions): string {
  const num = Number(value);
  if (num === 0 || Number.isNaN(num)) return "";
  const unit = options.unit || "sq_ft";
  const unitLabel = SURFACE_UNITS[unit] ?? unit;
  const formatted = buildNumber(value, options.thousands_sep || ",", options.decimal_sep || ".");
  return formatted + " " + unitLabel;
}

export function formatPriceStatic(value: number | string, options: FormattingOptions): string {
  return buildPrice(value, options);
}

export function formatSurfaceStatic(value: number | string, options: FormattingOptions): string {
  const unitPatterns = ["ft²", "m²", "yd²", "ac", "ha", "ft&sup2;", "m&sup2;", "yd&sup2;"];
  if (typeof value === "string") {
    for (const pattern of unitPatterns) {
      if (value.includes(pattern)) return value;
    }
    if (/^[0-9,]+(\.[0-9]+)?\s+[a-zA-Z²&;]+$/.test(value.trim())) {
      return value;
    }
  }
  return buildSurface(value, options);
}

export function useFormatting(): UseFormattingReturn {
  const { props } = usePage();
  const raw = (props as Record<string, unknown>).formattingSettings as
    FormattingSettings | undefined;

  const settings: FormattingSettings = useMemo(
    () => ({
      default_currency: raw?.default_currency || "USD",
      currency_position: raw?.currency_position || "before",
      price_empty_text: raw?.price_empty_text || "",
      surface_unit: raw?.surface_unit || "sq_ft",
      thousands_separator: raw?.thousands_separator || ",",
      decimal_separator: raw?.decimal_separator || ".",
      date_format: raw?.date_format || "Y-m-d",
      time_format: raw?.time_format || "H:i",
      start_of_week: raw?.start_of_week || "0",
      timezone: raw?.timezone || "UTC",
    }),
    [raw],
  );

  const formatNumber = useCallback(
    (value: number | string, thousandsSep?: string, decimalSep?: string): string =>
      buildNumber(
        value,
        thousandsSep || settings.thousands_separator,
        decimalSep || settings.decimal_separator,
      ),
    [settings.thousands_separator, settings.decimal_separator],
  );

  const formatPrice = useCallback(
    (value: number | string, overrides?: FormattingOptions): string =>
      buildPrice(value, {
        currency: overrides?.currency || settings.default_currency,
        position: overrides?.position || settings.currency_position,
        thousands_sep: overrides?.thousands_sep || settings.thousands_separator,
        decimal_sep: overrides?.decimal_sep || settings.decimal_separator,
        empty_text: overrides?.empty_text || settings.price_empty_text,
      }),
    [
      settings.default_currency,
      settings.currency_position,
      settings.thousands_separator,
      settings.decimal_separator,
      settings.price_empty_text,
    ],
  );

  const formatSurface = useCallback(
    (value: number | string, overrides?: FormattingOptions): string =>
      buildSurface(value, {
        unit: overrides?.unit || settings.surface_unit,
        thousands_sep: overrides?.thousands_sep || settings.thousands_separator,
        decimal_sep: overrides?.decimal_sep || settings.decimal_separator,
      }),
    [settings.surface_unit, settings.thousands_separator, settings.decimal_separator],
  );

  const getCurrencySymbol = useCallback(
    (currencyCode?: string): string => {
      const code = currencyCode || settings.default_currency || "USD";
      return CURRENCY_SYMBOLS[code] ?? code;
    },
    [settings.default_currency],
  );

  const getSurfaceUnit = useCallback(
    (unitCode?: string): string => {
      const code = unitCode || settings.surface_unit || "sq_ft";
      return SURFACE_UNITS[code] ?? code;
    },
    [settings.surface_unit],
  );

  return {
    settings,
    formatPrice,
    formatSurface,
    formatNumber,
    getCurrencySymbol,
    getSurfaceUnit,
    currencyOptions: CURRENCY_OPTIONS,
    surfaceUnitOptions: SURFACE_UNIT_OPTIONS,
  };
}

export default useFormatting;
