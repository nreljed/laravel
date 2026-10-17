import { Link } from "@inertiajs/react";
import { cn } from "cn";

interface PageLink {
  url: string | null;
  label: string;
  active: boolean;
}

interface SitePaginationProps {
  links: PageLink[];
}

// Site-token pagination for the public blog archive. Same `links` shape as
// the platform's shared PaginationLinks, styled with the site kit.
export function SitePagination({ links }: SitePaginationProps) {
  if (links.length === 0) return null;

  return (
    <nav aria-label="Pagination" className="flex flex-wrap items-center justify-center gap-site-xs">
      {links.map((link, i) => {
        const isPrevious = link.label.includes("Previous");
        const isNext = link.label.includes("Next");

        if (isPrevious || !link.url) {
          return (
            <span
              key={i}
              aria-hidden={isPrevious}
              className={cn(
                "inline-flex h-10 items-center rounded-site-pill px-site-md text-sm text-site-ink-faint",
                isPrevious && "border border-site-border bg-site-surface text-site-ink-soft",
              )}
            >
              {link.label.replaceAll(/&laquo;|&raquo;/g, "")}
            </span>
          );
        }

        if (isNext) {
          return (
            <Link
              key={i}
              href={link.url}
              className="inline-flex h-10 items-center rounded-site-pill border border-site-border bg-site-surface px-site-md text-sm text-site-ink-soft transition-colors hover:border-site-ink hover:text-site-ink"
            >
              {link.label.replaceAll(/&laquo;|&raquo;/g, "")}
            </Link>
          );
        }

        if (link.active) {
          return (
            <span
              key={i}
              aria-current="page"
              className="inline-flex h-10 items-center rounded-site-pill bg-site-ink px-site-md text-sm font-medium text-site-bg-strong"
            >
              {link.label}
            </span>
          );
        }

        return (
          <Link
            key={i}
            href={link.url}
            className="inline-flex h-10 items-center rounded-site-pill border border-site-border bg-site-surface px-site-md text-sm text-site-ink-soft transition-colors hover:border-site-ink hover:text-site-ink"
          >
            {link.label}
          </Link>
        );
      })}
    </nav>
  );
}
