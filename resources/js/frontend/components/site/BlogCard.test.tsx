import { render, screen } from "@testing-library/react";
import type { ReactNode } from "react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { BlogCard } from "./BlogCard";

vi.mock("@inertiajs/react", () => ({
  Link: ({
    href = "#",
    className,
    children,
  }: {
    href?: string;
    className?: string;
    children?: ReactNode;
  }) => (
    <a href={href} className={className}>
      {children}
    </a>
  ),
}));

const basePost = {
  id: 1,
  title: "Break down your cloud spend",
  slug: "cloud-spend",
  excerpt: "A practical guide to unit economics.",
  featured_image: "https://example.test/cover.jpg",
  published_at: "2024-01-15T12:00:00",
  user: { name: "Nadia Rejeb", avatar_url: null as string | null },
  category: { name: "FinOps" },
};

describe("BlogCard", () => {
  beforeEach(() => {
    Object.defineProperty(globalThis, "route", {
      configurable: true,
      writable: true,
      value: (_name: string, param: string | number = "") => `/blog/${param}`,
    });
  });

  it("renders the title, category, excerpt and byline", () => {
    render(<BlogCard post={basePost} />);

    expect(screen.getByText("Break down your cloud spend")).toBeInTheDocument();
    expect(screen.getByText("FinOps")).toBeInTheDocument();
    expect(screen.getByText("A practical guide to unit economics.")).toBeInTheDocument();
    expect(screen.getByText("Nadia Rejeb")).toBeInTheDocument();
    expect(screen.getByText("Jan 15, 2024")).toBeInTheDocument();
  });

  it("links the card to the post", () => {
    render(<BlogCard post={basePost} />);

    expect(screen.getByRole("link")).toHaveAttribute("href", "/blog/cloud-spend");
  });

  it("falls back to an initials monogram when the author has no avatar", () => {
    render(<BlogCard post={basePost} />);

    expect(screen.getByText("NR")).toBeInTheDocument();
  });

  it("renders the author avatar image when one is provided", () => {
    const { container } = render(
      <BlogCard
        post={{
          ...basePost,
          user: { name: "Nadia Rejeb", avatar_url: "https://example.test/a.jpg" },
        }}
      />,
    );

    expect(container.querySelector("img[src='https://example.test/a.jpg']")).not.toBeNull();
    expect(screen.queryByText("NR")).toBeNull();
  });

  it("omits the date when the post is not published", () => {
    render(<BlogCard post={{ ...basePost, published_at: null }} />);

    expect(screen.queryByText("Jan 15, 2024")).toBeNull();
  });
});
