import type { LucideIcon } from "lucide-react";
import type { ReactNode } from "react";
import { Link } from "@inertiajs/react";
import { cn } from "cn";

interface SiteCardGridProps {
  className?: string;
  children: ReactNode;
}

// Spaced card grid for public pages: real gaps between discrete cards. Never a
// painted hairline plate (`gap-px` over `bg-site-border`) — that only reads as a
// grid when every row is full, and leaks its background into empty cells
// otherwise. Column counts come in via className (`sm:grid-cols-2 lg:grid-cols-3`).
export function SiteCardGrid({ className, children }: SiteCardGridProps) {
  return <div className={cn("grid gap-site-lg", className)}>{children}</div>;
}

interface SiteCardProps {
  href?: string;
  reveal?: boolean;
  className?: string;
  children: ReactNode;
}

// A discrete card: bordered, rounded surface on the site tokens. Static by
// design — no hover tint, no image zoom — so a grid of them stays calm. Renders
// a link when `href` is given.
export function SiteCard({ href, reveal, className, children }: SiteCardProps) {
  const classes = cn(
    "flex flex-col overflow-hidden rounded-site-card border border-site-border bg-site-surface",
    className,
  );

  if (href) {
    return (
      <Link href={href} className={classes} data-reveal={reveal ? "" : undefined}>
        {children}
      </Link>
    );
  }

  return (
    <div className={classes} data-reveal={reveal ? "" : undefined}>
      {children}
    </div>
  );
}

type SiteCardTitleTag = "h2" | "h3" | "h4";

interface SiteCardTitleProps {
  as?: SiteCardTitleTag;
  className?: string;
  children: ReactNode;
}

// Card titles take the `site-heading-sm` token (H5, 20→25.6px) — one step below
// the section h3 (`site-title-sm`) and deliberately smaller than a "small card
// title" H4. Sized for a card, not for a page section.
export function SiteCardTitle({ as: Tag = "h3", className, children }: SiteCardTitleProps) {
  return <Tag className={cn("site-heading-sm", className)}>{children}</Tag>;
}

interface SiteFeatureCardProps {
  index?: string;
  icon?: LucideIcon;
  title: string;
  value?: string;
  reveal?: boolean;
  className?: string;
  children: ReactNode;
}

// Numbered feature card (Home / Services / About / Contact): an index + optional
// icon row, the title, an optional value line, then the body.
export function SiteFeatureCard({
  index,
  icon: Icon,
  title,
  value,
  reveal,
  className,
  children,
}: SiteFeatureCardProps) {
  return (
    <SiteCard reveal={reveal} className={cn("p-site-xl sm:p-site-2xl", className)}>
      {(index || Icon) && (
        <div className="flex items-center justify-between">
          {index && (
            <span className="font-site-display text-sm font-bold text-site-accent">{index}</span>
          )}
          {Icon && <Icon className="h-5 w-5 text-site-ink-faint" />}
        </div>
      )}
      <SiteCardTitle className="mt-site-lg">{title}</SiteCardTitle>
      {value && <p className="mt-site-xs text-sm font-semibold text-site-ink">{value}</p>}
      <p className="mt-site-sm leading-relaxed text-site-ink-soft">{children}</p>
    </SiteCard>
  );
}
