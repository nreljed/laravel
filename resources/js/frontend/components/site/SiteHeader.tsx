import { Link, usePage } from "@inertiajs/react";
import { useEffect, useState } from "react";
import { cn } from "cn";
import { SiteMenuOverlay, type SiteMenuLink } from "./SiteMenuOverlay";
import { ThemeToggle } from "./ThemeToggle";
import { UserMenu } from "./UserMenu";

const navLinks = [
  { label: "Home", href: route("home") },
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

// Shared public-site header. Fixed-height (h-20) bar pinned to the viewport so
// it is always visible; transparent at the top of the page and a glass surface
// once scrolled (or while the menu overlay is open, so it stays legible over
// the menu). Carries the three global controls — the theme-mode icon (light /
// dark / automatic), the user icon → account menu (avatar when signed in), and
// the Menu pill, which is the header's only button — so every public page
// shares one chrome without forking.
export function SiteHeader() {
  const { props } = usePage();
  const brand = (props.brand as { name?: string; logo?: string }) || {};
  const appName = brand.name || "Cost Breaker 360";
  const [menuOpen, setMenuOpen] = useState(false);
  const [scrolled, setScrolled] = useState(false);

  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 24);
    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });
    return () => window.removeEventListener("scroll", onScroll);
  }, []);

  return (
    <>
      <header
        className={cn(
          "fixed inset-x-0 top-0 z-40 h-20 border-b transition-colors duration-300",
          scrolled || menuOpen
            ? "border-site-border bg-site-bg/80 backdrop-blur"
            : "border-transparent bg-transparent",
        )}
      >
        <div className="mx-auto flex h-full w-full max-w-site items-center justify-between px-6 sm:px-10">
          <Link
            href={route("home")}
            aria-label={appName || "Home"}
            className="inline-flex items-center"
          >
            {brand.logo ? (
              <img
                src={brand.logo}
                alt={`${appName} logo`}
                className="h-10 w-10 rounded-full object-contain"
              />
            ) : (
              <span className="font-site-display text-lg font-bold tracking-tight text-site-ink">
                {appName}
              </span>
            )}
          </Link>

          <div className="flex items-center gap-3">
            <ThemeToggle />
            <UserMenu />
            <button
              type="button"
              onClick={() => setMenuOpen((value) => !value)}
              className="inline-flex h-[53px] min-w-28 items-center justify-center gap-2 rounded-site-card bg-site-invert-bg px-7 text-site-invert-ink"
            >
              {menuOpen ? "Close" : "Menu"}
            </button>
          </div>
        </div>
      </header>

      {/* Offsets the fixed header inside the flex column flow. */}
      <div aria-hidden="true" className="h-20 shrink-0" />

      <SiteMenuOverlay
        open={menuOpen}
        onClose={() => setMenuOpen(false)}
        links={menuLinks}
        brand={appName}
        logo={brand.logo}
      />
    </>
  );
}
