import { describe, it, expect } from "vitest";
import { cn, formatDate, formatDateTime, zodErrorsToMap } from "../utils";

describe("cn", () => {
  it("merges class names", () => {
    expect(cn("foo", "bar")).toBe("foo bar");
  });

  it("handles conditional classes", () => {
    expect(cn("base", false, "visible")).toBe("base visible");
  });

  it("merges tailwind classes correctly", () => {
    expect(cn("px-4 py-2", "px-6")).toBe("py-2 px-6");
  });

  it("handles undefined values", () => {
    expect(cn("foo", undefined, "bar")).toBe("foo bar");
  });
});

describe("formatDate", () => {
  it("returns fallback for null/undefined", () => {
    expect(formatDate(null)).toBe("-");
    expect(formatDate(undefined)).toBe("-");
  });

  it("formats a Date object", () => {
    const d = new Date(2024, 0, 15);
    expect(formatDate(d)).toBe("Jan 15, 2024");
  });

  it("formats an ISO string", () => {
    expect(formatDate("2024-06-15T10:30:00")).toBe("Jun 15, 2024");
  });

  it("formats a timestamp", () => {
    expect(formatDate(1_705_276_800_000)).toBe("Jan 15, 2024");
  });

  it("applies custom PHP date format", () => {
    const d = new Date(2024, 0, 15);
    expect(formatDate(d, "Y-m-d")).toBe("2024-01-15");
  });

  it("returns custom fallback for invalid dates", () => {
    expect(formatDate("invalid", undefined, "N/A")).toBe("N/A");
  });
});

describe("formatDateTime", () => {
  it("returns fallback for null", () => {
    expect(formatDateTime(null)).toBe("-");
  });

  it("formats date and time", () => {
    const d = new Date(2024, 0, 15, 14, 30, 0);
    const result = formatDateTime(d, "Y-m-d", "H:i");
    expect(result).toBe("2024-01-15 14:30");
  });
});

describe("zodErrorsToMap", () => {
  it("converts Zod issues to a flat map", () => {
    const issues = [
      { path: ["email"], message: "Invalid email" },
      { path: ["password"], message: "Too short" },
    ];
    expect(zodErrorsToMap(issues)).toEqual({
      email: "Invalid email",
      password: "Too short",
    });
  });

  it("handles nested paths", () => {
    const issues = [{ path: ["user", "address", "city"], message: "Required" }];
    expect(zodErrorsToMap(issues)).toEqual({ "user.address.city": "Required" });
  });

  it("returns empty object for null/undefined", () => {
    expect(zodErrorsToMap(null)).toEqual({});
    expect(zodErrorsToMap(undefined)).toEqual({});
  });

  it("keeps first error for duplicate paths", () => {
    const issues = [
      { path: ["email"], message: "First error" },
      { path: ["email"], message: "Second error" },
    ];
    expect(zodErrorsToMap(issues)).toEqual({ email: "First error" });
  });
});
