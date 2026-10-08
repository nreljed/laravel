import { Link, usePage } from "@inertiajs/react";
import { useState } from "react";
import { cn } from "cn";
import { SiteMenuOverlay, type SiteMenuLink } from "./SiteMenuOverlay";

const navLinks = [
  { label: "Home", href: "/" },
  { label: "About", href: route("about") },
  { label: "Services", href: route("services") },
  { label: "Blog", href: route("blog.index") },
  { label: "Style Guide", href: route("styleguide") },
  { label: "Contact", href: route("contact") },
];

const menuLinks: SiteMenuLink[] = navLinks.map((link, index) => ({
  number: String(index + 1).padStart(2, "0"),
  label: link.label,
  href: link.href,
}));

interface SiteHeaderProps {
  active?: string;
}

export function SiteHeader({ active }: SiteHeaderProps) {
  const { props } = usePage();
  const brand = (props.brand as { name?: string; logo?: string }) || {};
  const appName = brand.name || "";
  const [menuOpen, setMenuOpen] = useState(false);

  return (
    <>
      <header className="sticky top-0 z-40 border-b border-site-border bg-site-bg/90 backdrop-blur">
        <div className="mx-auto flex w-full max-w-site items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
          <Link
            href="/"
            className="font-site-display text-lg font-bold tracking-tight text-site-ink"
          >
            {appName}
          </Link>

          <nav className="hidden items-center gap-7 md:flex">
            {navLinks.map((link) => (
              <Link
                key={link.label}
                href={link.href}
                className={cn(
                  "text-sm font-medium transition-colors",
                  active === link.label
                    ? "text-site-ink"
                    : "text-site-ink-soft hover:text-site-ink",
                )}
              >
                {link.label}
              </Link>
            ))}

            <Link
              href={route("register")}
              className={cn(
                "inline-flex h-[42px] items-center rounded-site-pill bg-site-ink px-6 text-sm font-medium text-site-bg-strong transition-colors hover:bg-site-ink/90",
              )}
            >
              Get Started
            </Link>
          </nav>

          <button
            type="button"
            onClick={() => setMenuOpen((value) => !value)}
            className={cn(
              "inline-flex h-[42px] items-center justify-center gap-2 rounded-site-pill border border-site-border bg-site-ink px-5 text-sm font-medium text-site-bg-strong md:hidden",
            )}
          >
            {menuOpen ? "Close" : "Menu"}
          </button>
        </div>
      </header>

      <SiteMenuOverlay
        open={menuOpen}
        onClose={() => setMenuOpen(false)}
        links={menuLinks}
        brand={appName}
      />
    </>
  );
}
