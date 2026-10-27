import type { ReactNode } from "react";
import { cn } from "cn";

// ── SiteContainer ──────────────────────────────────────────────────────────

interface SiteContainerProps {
  size?: "default" | "narrow" | "wide" | "measure" | "display";
  className?: string;
  children: ReactNode;
}

const containerSizes: Record<string, string> = {
  default: "max-w-site",
  narrow: "max-w-3xl",
  wide: "max-w-7xl",
  measure: "max-w-site-measure",
  display: "max-w-site-display",
};

export function SiteContainer({ size = "default", className, children }: SiteContainerProps) {
  return (
    <div
      className={cn("mx-auto w-full px-site-lg sm:px-site-2xl", containerSizes[size], className)}
    >
      {children}
    </div>
  );
}

// ── SiteSection ─────────────────────────────────────────────────────────────

interface SiteSectionProps {
  className?: string;
  children: ReactNode;
  id?: string;
  spacing?: "sm" | "md" | "lg" | "xl";
}

const spacingClasses: Record<string, string> = {
  sm: "py-site-xl",
  md: "py-site-2xl",
  lg: "py-site-3xl",
  xl: "py-site-5xl",
};

export function SiteSection({ className, children, id, spacing = "lg" }: SiteSectionProps) {
  return (
    <section
      id={id}
      className={cn("px-site-lg sm:px-site-2xl", spacingClasses[spacing], className)}
    >
      <div className="mx-auto max-w-site">{children}</div>
    </section>
  );
}
