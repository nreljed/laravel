import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { SiteAvatar } from "./SiteAvatar";

describe("SiteAvatar", () => {
  it("renders the author image when a source is provided", () => {
    const { container } = render(<SiteAvatar name="Jane Doe" src="https://example.test/a.jpg" />);

    const img = container.querySelector("img");
    expect(img).not.toBeNull();
    expect(img).toHaveAttribute("src", "https://example.test/a.jpg");
    expect(img).toHaveAttribute("alt", "");
    expect(container.querySelector("span")).toBeNull();
  });

  it("falls back to an initials monogram when there is no image", () => {
    render(<SiteAvatar name="John Doe" />);

    expect(screen.getByText("JD")).toBeInTheDocument();
  });

  it("uses the first two characters for a single-word name", () => {
    render(<SiteAvatar name="nstudio" />);

    expect(screen.getByText("NS")).toBeInTheDocument();
  });

  it("renders a placeholder monogram when the author has no name", () => {
    render(<SiteAvatar name={null} />);

    expect(screen.getByText("?")).toBeInTheDocument();
  });

  it("prefers the image over the monogram even with a name", () => {
    const { container } = render(<SiteAvatar name="Jane Doe" src="https://example.test/a.jpg" />);

    expect(container.querySelector("img")).not.toBeNull();
    expect(screen.queryByText("JD")).toBeNull();
  });
});
