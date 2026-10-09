import { cn } from "cn";

interface SitePillProps {
  href?: string;
  className?: string;
  children: React.ReactNode;
}

// Site-token pill used for category/tag chips on the public site. Renders a
// link when `href` is provided, otherwise a static span.
export function SitePill({ href, className, children }: SitePillProps) {
  const classes = cn(
    "inline-flex items-center rounded-site-pill border border-site-border bg-site-surface px-4 py-1.5 text-sm text-site-ink-soft",
    className,
  );

  if (href) {
    return (
      <a
        href={href}
        className={cn(classes, "transition-colors hover:border-site-ink hover:text-site-ink")}
      >
        {children}
      </a>
    );
  }

  return <span className={classes}>{children}</span>;
}
