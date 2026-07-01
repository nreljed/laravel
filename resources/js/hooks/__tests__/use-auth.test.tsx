import { describe, it, expect, vi } from "vitest";
import { renderHook } from "@testing-library/react";
import { useAuth, getUserInitials } from "../use-auth";

vi.mock("@inertiajs/react", () => ({
  usePage: vi.fn(),
}));

import type { Page } from "@inertiajs/core";
import { usePage } from "@inertiajs/react";

function createMockPage(props: Record<string, unknown> = {}) {
  return {
    props: {
      auth: { user: null },
      errors: {},
      flash: null,
      ...props,
    },
    component: "",
    url: "/",
    version: null,
  } as unknown as Page;
}

describe("useAuth", () => {
  it("returns logged-out state when no auth", () => {
    vi.mocked(usePage).mockReturnValue(createMockPage());

    const { result } = renderHook(() => useAuth());
    expect(result.current.isLoggedIn).toBe(false);
    expect(result.current.user).toBeNull();
    expect(result.current.isAdmin).toBe(false);
  });

  it("returns user when authenticated", () => {
    vi.mocked(usePage).mockReturnValue(
      createMockPage({
        auth: {
          user: { id: 1, name: "John", email: "john@test.com" },
        },
      }),
    );

    const { result } = renderHook(() => useAuth());
    expect(result.current.isLoggedIn).toBe(true);
    expect(result.current.user?.name).toBe("John");
  });

  it("detects admin from capabilities", () => {
    vi.mocked(usePage).mockReturnValue(
      createMockPage({
        auth: { user: { id: 1, name: "Admin", email: "admin@test.com" } },
        userCapabilities: { isAdmin: true, can: {} },
      }),
    );

    const { result } = renderHook(() => useAuth());
    expect(result.current.isAdmin).toBe(true);
  });
});

describe("getUserInitials", () => {
  it("returns initials from full name", () => {
    expect(getUserInitials("John Doe")).toBe("JD");
  });

  it("returns first two chars for single name", () => {
    expect(getUserInitials("John")).toBe("JO");
  });

  it("returns ? for null/undefined", () => {
    expect(getUserInitials(null)).toBe("?");
    expect(getUserInitials(undefined)).toBe("?");
  });
});
