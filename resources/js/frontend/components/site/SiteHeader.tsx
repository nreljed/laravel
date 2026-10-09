import { Link, usePage } from "@inertiajs/react";
import { useEffect, useState } from "react";
import { cn } from "cn";
import { SiteMenuOverlay, type SiteMenuLink } from "./SiteMenuOverlay";
import { ThemeToggle } from "./ThemeToggle";
import { UserMenu } from "./UserMenu";

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

// Shared public-site header. Fixed-height (h-20) bar that is transparent at
// the top of the page and turns to a glass surface once scrolled. Carries the
// three global controls — dark-mode toggle, user control (→ dashboard/login),
// and the Menu pill that opens the full-screen nav overlay — so every public
// page shares one chrome without forking.
export function SiteHeader() {
  const { props } = usePage();
  const brand = (props.brand as { name?: string; logo?: string }) || {};
  const appName = brand.name || "";
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
          "sticky top-0 z-40 h-20 border-b transition-colors duration-300",
          scrolled
            ? "border-site-border bg-site-bg/80 backdrop-blur"
            : "border-transparent bg-transparent",
        )}
      >
        <div className="mx-auto flex h-full w-full max-w-site items-center justify-between px-6 sm:px-10">
          <Link href="/" aria-label={appName || "Home"} className="inline-flex items-center">
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
              className="inline-flex h-[53px] min-w-28 items-center justify-center gap-2 rounded-site-card bg-[#191c16] px-7 text-[#edf0e3] transition-transform duration-200 hover:scale-[1.03]"
            >
              {menuOpen ? "Close" : "Menu"}
            </button>
          </div>
        </div>
      </header>

      <SiteMenuOverlay
        open={menuOpen}
        onClose={() => setMenuOpen(false)}
        links={menuLinks}
        brand={appName}
        logo={brand.logo}
        note="Let's build something that sticks."
      />
    </>
  );
}
