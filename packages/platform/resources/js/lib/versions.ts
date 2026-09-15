export function normalizeVersion(value?: string | null): string {
  return (value ?? "").trim().replace(/^v/i, "");
}
