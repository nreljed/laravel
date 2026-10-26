import { fireEvent, render, screen } from "@testing-library/react";
import type { ReactNode } from "react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import Contact from "./Contact";

const { useFormMock, postMock, resetMock, pageProps } = vi.hoisted(() => {
  // `SiteHeader` (reached through the site barrel) calls `route()` at module
  // scope, so the Ziggy global has to exist before Contact.tsx is imported.
  Object.defineProperty(globalThis, "route", {
    configurable: true,
    writable: true,
    value: (name: string) => (name === "contact.store" ? "/contact" : `/${name}`),
  });

  return {
    useFormMock: vi.fn(),
    postMock: vi.fn(),
    resetMock: vi.fn(),
    pageProps: { flash: {} as { status?: string } },
  };
});

vi.mock("@inertiajs/react", () => ({
  useForm: (...args: unknown[]) => useFormMock(...args),
  usePage: () => ({ props: pageProps }),
  Link: ({
    href,
    className,
    children,
  }: {
    href: string;
    className?: string;
    children?: ReactNode;
  }) => (
    <a href={href} className={className}>
      {children}
    </a>
  ),
  Head: ({ title }: { title?: string }) => <title>{title}</title>,
}));

vi.mock("../../layouts/PublicLayout", () => ({
  default: ({ children }: { children: ReactNode }) => <div>{children}</div>,
}));

vi.mock("@nreljed/platform/Components/ui/toast", () => ({
  toast: { add: vi.fn() },
}));

const emptyData = {
  name: "",
  email: "",
  subject: "",
  message: "",
  website: "",
};

function mockForm(overrides: Record<string, unknown> = {}) {
  useFormMock.mockReturnValue({
    data: { ...emptyData },
    setData: vi.fn(),
    post: postMock,
    processing: false,
    errors: {},
    reset: resetMock,
    ...overrides,
  });
}

function fillAndSubmit() {
  fireEvent.change(screen.getByLabelText(/your name/i), { target: { value: "Ada Lovelace" } });
  fireEvent.change(screen.getByLabelText(/email address/i), {
    target: { value: "ada@example.com" },
  });
  fireEvent.change(screen.getByLabelText(/^subject/i), {
    target: { value: "Billing question" },
  });
  fireEvent.change(screen.getByLabelText(/message/i), {
    target: { value: "How do I export last quarter costs?" },
  });

  fireEvent.click(screen.getByRole("button", { name: /send message/i }));
}

describe("Contact page", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    pageProps.flash = {};

    mockForm();
    postMock.mockImplementation((_url: string, options?: { onSuccess?: () => void }) => {
      options?.onSuccess?.();
    });
  });

  it("renders every form field with a matching label", () => {
    render(<Contact />);

    expect(screen.getByLabelText(/your name/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/email address/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/subject/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/message/i)).toBeInTheDocument();
  });

  it("renders the honeypot field hidden from assistive tech and the tab order", () => {
    render(<Contact />);

    const honeypot = screen.getByLabelText(/leave this field empty/i);

    expect(honeypot).toHaveAttribute("name", "website");
    expect(honeypot).toHaveAttribute("tabindex", "-1");
    expect(honeypot.closest("[aria-hidden='true']")).not.toBeNull();
  });

  it("wires every field to the Inertia form state", () => {
    const setData = vi.fn();
    mockForm({ setData });

    render(<Contact />);

    fireEvent.change(screen.getByLabelText(/your name/i), { target: { value: "Ada Lovelace" } });
    fireEvent.change(screen.getByLabelText(/email address/i), {
      target: { value: "ada@example.com" },
    });
    fireEvent.change(screen.getByLabelText(/^subject/i), {
      target: { value: "Billing question" },
    });
    fireEvent.change(screen.getByLabelText(/message/i), {
      target: { value: "A long enough body" },
    });
    fireEvent.change(screen.getByLabelText(/leave this field empty/i), {
      target: { value: "https://spam.example" },
    });

    expect(setData).toHaveBeenCalledWith("name", "Ada Lovelace");
    expect(setData).toHaveBeenCalledWith("email", "ada@example.com");
    expect(setData).toHaveBeenCalledWith("subject", "Billing question");
    expect(setData).toHaveBeenCalledWith("message", "A long enough body");
    expect(setData).toHaveBeenCalledWith("website", "https://spam.example");
  });

  it("posts to the contact route and resets the form on success", () => {
    render(<Contact />);

    fillAndSubmit();

    expect(postMock).toHaveBeenCalledTimes(1);
    expect(postMock).toHaveBeenCalledWith(
      "/contact",
      expect.objectContaining({ preserveScroll: true }),
    );
    expect(resetMock).toHaveBeenCalled();
  });

  it("shows the confirmation panel after a successful submit", () => {
    render(<Contact />);

    fillAndSubmit();

    expect(screen.getByText(/message received/i)).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: /send message/i })).toBeNull();

    fireEvent.click(screen.getByRole("button", { name: /send another message/i }));
    expect(screen.getByRole("button", { name: /send message/i })).toBeInTheDocument();
  });

  it("renders server-side validation errors under the matching field", () => {
    mockForm({
      errors: {
        name: "The name field is required.",
        message: "Please tell us a little more (at least 10 characters).",
      },
    });

    render(<Contact />);

    expect(screen.getByText("The name field is required.")).toBeInTheDocument();
    expect(
      screen.getByText("Please tell us a little more (at least 10 characters)."),
    ).toBeInTheDocument();
    expect(screen.getByLabelText(/your name/i)).toHaveAttribute("aria-invalid", "true");
  });

  it("shows the confirmation panel when the page is revisited with a flash status", () => {
    pageProps.flash = { status: "contact-sent" };

    render(<Contact />);

    expect(screen.getByText(/message received/i)).toBeInTheDocument();
  });
});
