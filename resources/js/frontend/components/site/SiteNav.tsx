import type { ReactNode } from "react";
import { useState } from "react";
import { cn } from "cn";
import { ChevronRight, Home } from "lucide-react";
import { Link } from "@inertiajs/react";

// ── SiteBreadcrumb ─────────────────────────────────────────────────────────

interface BreadcrumbItem {
  label: string;
  href?: string;
}

interface SiteBreadcrumbProps {
  items: BreadcrumbItem[];
  className?: string;
}

export function SiteBreadcrumb({ items, className }: SiteBreadcrumbProps) {
  return (
    <nav aria-label="Breadcrumb" className={cn("flex items-center gap-site-xs text-sm", className)}>
      <Link
        href={route("home")}
        className="text-site-ink-faint hover:text-site-ink transition-colors"
      >
        <Home className="h-3.5 w-3.5" />
      </Link>
      {items.map((item, i) => (
        <span key={i} className="flex items-center gap-site-xs">
          <ChevronRight className="h-3.5 w-3.5 text-site-ink-faint" />
          {item.href ? (
            <Link
              href={item.href}
              className="text-site-ink-soft hover:text-site-ink transition-colors"
            >
              {item.label}
            </Link>
          ) : (
            <span className="font-medium text-site-ink">{item.label}</span>
          )}
        </span>
      ))}
    </nav>
  );
}

// ── SiteTabs ───────────────────────────────────────────────────────────────

interface SiteTab {
  id: string;
  label: string;
  content: ReactNode;
}

interface SiteTabsProps {
  tabs: SiteTab[];
  defaultTab?: string;
  className?: string;
}

export function SiteTabs({ tabs, defaultTab, className }: SiteTabsProps) {
  const [active, setActive] = useState(defaultTab || tabs[0]?.id);
  const activeTab = tabs.find((t) => t.id === active);

  return (
    <div className={cn("flex flex-col gap-site-md", className)}>
      <div className="flex gap-site-xs border-b border-site-border">
        {tabs.map((tab) => (
          <button
            key={tab.id}
            type="button"
            onClick={() => setActive(tab.id)}
            className={cn(
              "px-site-md py-site-xs text-sm font-medium transition-colors border-b-2 -mb-px",
              active === tab.id
                ? "border-site-accent text-site-ink"
                : "border-transparent text-site-ink-soft hover:text-site-ink",
            )}
            role="tab"
            aria-selected={active === tab.id}
          >
            {tab.label}
          </button>
        ))}
      </div>
      <div role="tabpanel">{activeTab?.content}</div>
    </div>
  );
}
