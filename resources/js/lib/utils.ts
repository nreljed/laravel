import { format } from "date-fns";
import { clsx, type ClassValue } from "clsx";
import { twMerge } from "tailwind-merge";

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}

const PHP_DATE_TOKENS: Record<string, string> = {
  d: "dd",
  D: "EEE",
  j: "d",
  l: "EEEE",
  m: "MM",
  M: "MMM",
  F: "MMMM",
  Y: "yyyy",
  y: "yy",
  a: "aa",
  A: "AA",
  g: "h",
  G: "H",
  h: "hh",
  H: "HH",
  i: "mm",
  s: "ss",
};

function phpToDateFormat(php: string): string {
  let result = "";
  for (let i = 0; i < php.length; i++) {
    const c = php[i]!;
    if (c === "\\" && i + 1 < php.length) {
      result += php[i + 1]!;
      i++;
    } else if (Object.hasOwn(PHP_DATE_TOKENS, c)) {
      result += PHP_DATE_TOKENS[c]!;
    } else {
      result += c;
    }
  }
  return result;
}

export function formatDate(
  date: Date | string | number | undefined | null,
  dateFormat?: string,
  fallback?: string,
): string {
  if (date == null) return fallback || "-";
  const d = typeof date === "object" ? date : new Date(date);
  if (Number.isNaN(d.getTime())) return fallback || "-";
  const fmt = dateFormat ? phpToDateFormat(dateFormat) : "MMM d, yyyy";
  return format(d, fmt);
}

export function zodErrorsToMap(
  issues?: Array<{ path: (string | number | symbol)[]; message: string }> | null,
): Record<string, string> {
  const errors: Record<string, string> = {};
  if (!issues) return errors;
  for (const issue of issues) {
    const key = issue.path.join(".");
    if (!Object.hasOwn(errors, key)) {
      errors[key] = issue.message;
    }
  }
  return errors;
}

export function formatDateTime(
  date: Date | string | number | undefined | null,
  dateFormat?: string,
  timeFormat?: string,
  fallback?: string,
): string {
  if (date == null) return fallback || "-";
  const d = typeof date === "object" ? date : new Date(date);
  if (Number.isNaN(d.getTime())) return fallback || "-";
  const df = dateFormat ? phpToDateFormat(dateFormat) : "MMM d, yyyy";
  const tf = timeFormat ? phpToDateFormat(timeFormat) : "HH:mm";
  return `${format(d, df)} ${format(d, tf)}`;
}
