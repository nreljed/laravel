import { Link, usePage } from "@inertiajs/react";
import { useMemo } from "react";
import { cn } from "cn";

const footerNav = [
  { label: "Home", href: "/" },
  { label: "About", href: route("about") },
  { label: "Services", href: route("services") },
  { label: "Blog", href: route("blog.index") },
  { label: "Contact", href: route("contact") },
];

export function SiteFooter() {
  const { props } = usePage();
  const brand = (props.brand as { name?: string }) || {};
  const appName = brand.name || "";
  const year = useMemo(() => new Date().getFullYear(), []);

  return (
    <footer className="border-t border-site-border bg-site-bg">
      <div className="mx-auto flex w-full max-w-site flex-col items-start justify-between gap-6 px-4 py-10 sm:px-6 md:flex-row md:items-center lg:px-8">
        <div className="flex flex-col gap-1">
          <span className="font-site-display text-base font-bold tracking-tight text-site-ink">
            {appName}
          </span>
          <span className="text-xs text-site-ink-faint">
            © {year} {appName}. All rights reserved.
          </span>
        </div>

        <nav className="flex flex-wrap items-center gap-x-6 gap-y-2">
          {footerNav.map((link) => (
            <Link
              key={link.label}
              href={link.href}
              className={cn("text-sm text-site-ink-soft transition-colors hover:text-site-ink")}
            >
              {link.label}
            </Link>
          ))}
        </nav>
      </div>
    </footer>
  );
}
