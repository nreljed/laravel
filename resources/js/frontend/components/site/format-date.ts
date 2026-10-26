// One date formatter for the public site. Pinned to `en-US` so the rendered
// string never depends on the server or browser locale — a value formatted in
// PHP-rendered HTML and re-rendered on the client must match, and `11/09/2026`
// is ambiguous between locales. `short` is the compact byline form (blog card),
// `long` the editorial masthead form (article header).
export type SiteDateFormat = "short" | "long";

export function formatSiteDate(
  value?: string | null,
  format: SiteDateFormat = "short",
): string | null {
  if (!value) {
    return null;
  }

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) {
    return null;
  }

  return date.toLocaleDateString("en-US", {
    year: "numeric",
    month: format === "long" ? "long" : "short",
    day: "numeric",
  });
}
