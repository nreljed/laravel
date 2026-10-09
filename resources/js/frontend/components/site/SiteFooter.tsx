import { Link, usePage } from "@inertiajs/react";
import { useMemo } from "react";
import { ArrowUp, ArrowUpRight } from "lucide-react";

const exploreLinks = [
  { label: "Home", href: "/" },
  { label: "About", href: "/about" },
  { label: "Services", href: "/services" },
  { label: "Blog", href: "/blog" },
  { label: "Style Guide", href: "/style-guide" },
  { label: "Contact", href: "/contact" },
];

const socialLinks = [
  { label: "Instagram", href: "#" },
  { label: "Twitter", href: "#" },
  { label: "Behance", href: "#" },
  { label: "LinkedIn", href: "#" },
];

const legalLinks = [
  { label: "Privacy Policy", href: "#" },
  { label: "Terms of Service", href: "#" },
  { label: "Licensing", href: "#" },
  { label: "Changelog", href: "#" },
];

// Shared public-site footer. A three-column brand/explore/connect layout with
// a legal bar — distinct from any page content, rendered on every public page
// by PublicLayout.
export function SiteFooter() {
  const { props } = usePage();
  const brand = (props.brand as { name?: string }) || {};
  const appName = brand.name || "";
  const year = useMemo(() => new Date().getFullYear(), []);

  return (
    <footer className="mt-24 border-t border-site-border px-6 pt-16 pb-10 sm:px-10">
      <div className="mx-auto max-w-site">
        <div className="grid gap-12 lg:grid-cols-[1.6fr_1fr_1fr]">
          <div className="max-w-sm">
            <Link
              href={route("home")}
              className="font-site-display text-3xl font-bold tracking-[-0.04em]"
            >
              {appName}
            </Link>
            <p className="mt-4 text-site-ink-soft">
              A creative agency building brands with clarity, strategy, and intention.
            </p>
            <Link
              href={route("contact")}
              className="mt-8 inline-flex items-center gap-2 text-sm font-bold tracking-[0.02em]"
            >
              Start a project
              <ArrowUpRight className="h-4 w-4" />
            </Link>
          </div>

          <nav aria-label="Explore">
            <h3 className="text-xs font-bold tracking-[0.2em] text-site-ink-faint uppercase">
              Explore
            </h3>
            <ul className="mt-5 space-y-3 text-sm">
              {exploreLinks.map((link) => (
                <li key={link.label}>
                  <Link
                    href={link.href}
                    className="text-site-ink-soft transition-colors hover:text-site-ink"
                  >
                    {link.label}
                  </Link>
                </li>
              ))}
            </ul>
          </nav>

          <nav aria-label="Connect">
            <h3 className="text-xs font-bold tracking-[0.2em] text-site-ink-faint uppercase">
              Connect
            </h3>
            <ul className="mt-5 space-y-3 text-sm">
              {socialLinks.map((link) => (
                <li key={link.label}>
                  <a
                    href={link.href}
                    className="inline-flex items-center gap-1.5 text-site-ink-soft transition-colors hover:text-site-ink"
                  >
                    {link.label}
                    <ArrowUpRight className="h-3.5 w-3.5" />
                  </a>
                </li>
              ))}
            </ul>
          </nav>
        </div>

        <div className="mt-16 flex flex-col gap-6 border-t border-site-border pt-8 text-sm text-site-ink-faint sm:flex-row sm:items-center sm:justify-between">
          <p>
            © {year} {appName}. All rights reserved.
          </p>

          <ul className="flex flex-wrap gap-x-6 gap-y-2">
            {legalLinks.map((link) => (
              <li key={link.label}>
                <a href={link.href} className="transition-colors hover:text-site-ink">
                  {link.label}
                </a>
              </li>
            ))}
          </ul>

          <button
            type="button"
            onClick={() => window.scrollTo({ top: 0, behavior: "smooth" })}
            className="inline-flex items-center gap-1.5 font-medium transition-colors hover:text-site-ink"
          >
            Back to top
            <ArrowUp className="h-3.5 w-3.5" />
          </button>
        </div>
      </div>
    </footer>
  );
}
