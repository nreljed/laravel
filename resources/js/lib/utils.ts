import { clsx, type ClassValue } from "clsx"
import { twMerge } from "tailwind-merge"

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}

const PHP_DATE_MAP: Record<string, string> = {
  d: "dd", D: "EEE", j: "d", l: "EEEE",
  m: "MM", M: "MMM", F: "MMMM",
  Y: "yyyy", y: "yy",
  a: "aa", A: "AA",
  g: "h", G: "H", h: "hh", H: "HH",
  i: "mm", s: "ss",
};

function phpToJsFormat(php: string): string {
  let js = "";
  for (let i = 0; i < php.length; i++) {
    const c = php[i];
    if (c === "\\" && i + 1 < php.length) {
      js += php[i + 1];
      i++;
    } else if (PHP_DATE_MAP[c]) {
      js += PHP_DATE_MAP[c];
    } else {
      js += c;
    }
  }
  return js;
}

function pad(n: number): string {
  return String(n).padStart(2, "0");
}

function applyJsFormat(date: Date, jsFormat: string): string {
  const map: Record<string, string> = {
    dd: pad(date.getDate()),
    d: String(date.getDate()),
    EEE: ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"][date.getDay()],
    EEEE: ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"][date.getDay()],
    MM: pad(date.getMonth() + 1),
    MMM: ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"][date.getMonth()],
    MMMM: ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"][date.getMonth()],
    yyyy: String(date.getFullYear()),
    yy: String(date.getFullYear()).slice(-2),
    HH: pad(date.getHours()),
    H: String(date.getHours()),
    hh: pad(date.getHours() % 12 || 12),
    h: String(date.getHours() % 12 || 12),
    mm: pad(date.getMinutes()),
    ss: pad(date.getSeconds()),
    aa: date.getHours() < 12 ? "am" : "pm",
    AA: date.getHours() < 12 ? "AM" : "PM",
  };

  let result = jsFormat;
  for (const [key, val] of Object.entries(map)) {
    result = result.replaceAll(key, val);
  }
  return result;
}

export function formatDate(date: Date | string | number | undefined | null, format?: string, fallback?: string): string {
  if (date == null) return fallback || "-";
  const d = typeof date === "object" ? date : new Date(date);
  if (isNaN(d.getTime())) return fallback || "-";
  const jsFormat = format ? phpToJsFormat(format) : "MMM d, yyyy";
  return applyJsFormat(d, jsFormat);
}

export function zodErrorsToMap(
  issues?: Array<{ path: (string | number | symbol)[]; message: string }> | null,
): Record<string, string> {
  const errors: Record<string, string> = {};
  if (!issues) return errors;
  for (const issue of issues) {
    const key = issue.path.join(".");
    if (!errors[key]) {
      errors[key] = issue.message;
    }
  }
  return errors;
}

export function formatDateTime(date: Date | string | number | undefined | null, dateFormat?: string, timeFormat?: string, fallback?: string): string {
  if (date == null) return fallback || "-";
  const d = typeof date === "object" ? date : new Date(date);
  if (isNaN(d.getTime())) return fallback || "-";
  const df = dateFormat ? phpToJsFormat(dateFormat) : "MMM d, yyyy";
  const tf = timeFormat ? phpToJsFormat(timeFormat) : "HH:mm";
  return `${applyJsFormat(d, df)} ${applyJsFormat(d, tf)}`;
}
