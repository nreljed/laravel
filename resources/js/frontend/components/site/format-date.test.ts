import { describe, expect, it } from "vitest";
import { formatSiteDate } from "./format-date";

describe("formatSiteDate", () => {
  it("returns null for an empty value", () => {
    expect(formatSiteDate(null)).toBeNull();
    expect(formatSiteDate(undefined)).toBeNull();
    expect(formatSiteDate("")).toBeNull();
  });

  it("returns null for an unparseable value", () => {
    expect(formatSiteDate("not-a-date")).toBeNull();
  });

  it("defaults to the compact byline format", () => {
    expect(formatSiteDate("2024-01-15T12:00:00")).toBe("Jan 15, 2024");
  });

  it("formats the long editorial form on request", () => {
    expect(formatSiteDate("2024-01-15T12:00:00", "long")).toBe("January 15, 2024");
  });

  it("formats the short form when asked explicitly", () => {
    expect(formatSiteDate("2024-01-15T12:00:00", "short")).toBe("Jan 15, 2024");
  });
});
