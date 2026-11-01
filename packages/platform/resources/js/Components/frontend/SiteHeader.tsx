import { Link, usePage } from "@inertiajs/react";
import { useState } from "react";
import { Menu, X } from "lucide-react";
import { Button } from "../ui/button";
import { Separator } from "../ui/separator";
import { BrandLogo } from "./BrandLogo";
import { NavUser } from "../navigation/nav-user";

export function SiteHeader() {
  const { props } = usePage();
  const brand = (props.brand as { logo?: string; favicon?: string; name?: string }) || {};
  const appName = brand.name || "";

  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);

  // The Blog link is only available when the blog module is registered
  // (and its `blog.index` route exists). Guard so consumers without the
  // blog module (e.g. the booking app) don't crash on render.
  const hasBlogRoute = (() => {
    try {
      route("blog.index");
      return true;
    } catch {
      return false;
    }
  })();

  const siteLinks = (
    <>
      <Link
        href={route("home")}
        className="px-3 py-2 text-sm font-medium text-muted-foreground hover:text-foreground hover:bg-accent rounded-md transition-colors"
      >
        Home
      </Link>
      {hasBlogRoute && (
        <Link
          href={route("blog.index")}
          className="px-3 py-2 text-sm font-medium text-muted-foreground hover:text-foreground hover:bg-accent rounded-md transition-colors"
        >
          Blog
        </Link>
      )}
      <Link
        href={route("about")}
        className="px-3 py-2 text-sm font-medium text-muted-foreground hover:text-foreground hover:bg-accent rounded-md transition-colors"
      >
        About
      </Link>
      <Link
        href={route("services")}
        className="px-3 py-2 text-sm font-medium text-muted-foreground hover:text-foreground hover:bg-accent rounded-md transition-colors"
      >
        Services
      </Link>
      <Link
        href={route("contact")}
        className="px-3 py-2 text-sm font-medium text-muted-foreground hover:text-foreground hover:bg-accent rounded-md transition-colors"
      >
        Contact
      </Link>
    </>
  );

  return (
    <nav className="border-b border-border bg-background">
      <div className="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 sm:px-6 lg:px-8">
        <div className="flex items-center gap-6">
          <Link href="/" className="flex items-center gap-2 shrink-0">
            <BrandLogo
              logo={brand.logo || null}
              favicon={brand.favicon || null}
              name={appName}
              size="sm"
              showName
            />
          </Link>
          <div className="hidden sm:flex items-center gap-1">{siteLinks}</div>
        </div>
        <div className="flex items-center gap-2">
          <div className="hidden sm:flex">
            <NavUser variant="basic" />
          </div>
          <Button
            variant="ghost"
            size="icon"
            className="sm:hidden"
            aria-label={mobileMenuOpen ? "Close menu" : "Open menu"}
            aria-expanded={mobileMenuOpen}
            aria-controls="site-mobile-menu"
            onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
          >
            {mobileMenuOpen ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
          </Button>
        </div>
      </div>
      {mobileMenuOpen && (
        <div id="site-mobile-menu" className="border-t border-border sm:hidden">
          <div className="space-y-1 px-4 py-3">
            <Link
              href={route("home")}
              className="block rounded-md px-3 py-2 text-sm font-medium text-foreground hover:bg-accent"
              onClick={() => setMobileMenuOpen(false)}
            >
              Home
            </Link>
            {hasBlogRoute && (
              <Link
                href={route("blog.index")}
                className="block rounded-md px-3 py-2 text-sm font-medium text-muted-foreground hover:text-foreground hover:bg-accent"
                onClick={() => setMobileMenuOpen(false)}
              >
                Blog
              </Link>
            )}
            <Link
              href={route("about")}
              className="block rounded-md px-3 py-2 text-sm font-medium text-muted-foreground hover:text-foreground hover:bg-accent"
              onClick={() => setMobileMenuOpen(false)}
            >
              About
            </Link>
            <Link
              href={route("services")}
              className="block rounded-md px-3 py-2 text-sm font-medium text-muted-foreground hover:text-foreground hover:bg-accent"
              onClick={() => setMobileMenuOpen(false)}
            >
              Services
            </Link>
            <Link
              href={route("contact")}
              className="block rounded-md px-3 py-2 text-sm font-medium text-muted-foreground hover:text-foreground hover:bg-accent"
              onClick={() => setMobileMenuOpen(false)}
            >
              Contact
            </Link>
            <Separator className="my-2" />
            <NavUser variant="basic" />
          </div>
        </div>
      )}
    </nav>
  );
}
