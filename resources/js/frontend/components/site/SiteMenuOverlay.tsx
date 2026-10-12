import { Link } from "@inertiajs/react";
import { useEffect, useRef } from "react";
import gsap from "gsap";
import { cn } from "cn";
import { useBodyScrollLock } from "./motion/use-body-scroll-lock";

export interface SiteMenuLink {
  number: string;
  label: string;
  href: string;
}

// Brand icons were dropped from lucide-react (1.5x), so the social links ship
// their own minimal glyphs. Paths are simple-icons (CC0).
const socialLinks = [
  {
    label: "Facebook",
    href: "https://facebook.com",
    path: "M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z",
  },
  {
    label: "Instagram",
    href: "https://instagram.com",
    path: "M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z",
  },
  {
    label: "X",
    href: "https://x.com",
    path: "M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z",
  },
];

interface SiteMenuOverlayProps {
  open: boolean;
  onClose: () => void;
  links: SiteMenuLink[];
  brand?: string;
  logo?: string;
}

export function SiteMenuOverlay({ open, onClose, links, brand, logo }: SiteMenuOverlayProps) {
  // Freeze the page while the menu is open — the body can't scroll, but the
  // menu content itself stays scrollable (link list can overflow short
  // viewports). Stopping Lenis is what actually locks the page.
  useBodyScrollLock(open);
  const rootRef = useRef<HTMLDivElement>(null);
  const contentRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const root = rootRef.current;
    const content = contentRef.current;
    if (!root || !content) return;

    const tl = gsap.timeline();
    if (open) {
      tl.fromTo(
        root,
        { height: 0 },
        { height: window.innerHeight, duration: 1, ease: "power4.inOut" },
        0,
      );
      tl.fromTo(
        content,
        { y: 80, autoAlpha: 0, skewY: 6 },
        { y: 0, autoAlpha: 1, skewY: 0, duration: 0.75, ease: "power3.out" },
        0.5,
      );
    } else {
      tl.to(content, { y: 80, autoAlpha: 0, skewY: 6, duration: 0.75, ease: "power2.in" }, 0);
      tl.to(root, { height: 0, duration: 1, ease: "power4.inOut" }, 0.1);
    }

    return () => {
      tl.kill();
      gsap.set(root, { height: 0 });
      gsap.set(content, { autoAlpha: 0 });
    };
  }, [open]);

  return (
    <div
      ref={rootRef}
      aria-hidden={!open}
      className={cn(
        "fixed top-0 right-0 left-0 z-20 h-0 overflow-hidden bg-[#11130f] text-[#edf0e3]",
        open ? "pointer-events-auto" : "pointer-events-none",
      )}
    >
      <div
        ref={contentRef}
        data-lenis-prevent
        className="flex h-full w-full flex-col overflow-y-auto overscroll-contain px-6 pt-28 pb-[8vh] opacity-0 sm:px-10"
      >
        <nav className="mx-auto my-auto flex w-full max-w-site flex-col">
          {links.map((link) => (
            <Link
              key={link.label}
              href={link.href}
              onClick={onClose}
              className="group flex items-baseline gap-6 border-b border-white/15 py-5 sm:gap-8"
            >
              <span className="text-sm text-white/40">{link.number}</span>
              <span className="font-site-display text-[clamp(2.5rem,9vw,7rem)] leading-[0.95] font-bold tracking-[-0.03em] text-[#edf0e3] uppercase transition-all duration-300 group-hover:-skew-x-6 group-hover:italic group-hover:text-site-accent sm:py-0">
                {link.label}
              </span>
            </Link>
          ))}
        </nav>

        {brand || logo ? (
          <div className="mx-auto mt-10 flex w-full max-w-site items-center justify-between gap-4 text-sm text-white/50">
            <span className="flex items-center gap-3">
              {logo ? (
                <img
                  src={logo}
                  alt=""
                  className="h-10 w-10 rounded-full bg-[#edf0e3] object-contain p-1.5"
                />
              ) : null}
              {brand}
            </span>

            <ul className="flex items-center gap-1">
              {socialLinks.map((social) => (
                <li key={social.label}>
                  <a
                    href={social.href}
                    target="_blank"
                    rel="noreferrer noopener"
                    aria-label={social.label}
                    className="flex h-10 w-10 items-center justify-center rounded-full text-[#edf0e3]/60 transition-colors hover:bg-white/10 hover:text-[#edf0e3]"
                  >
                    <svg viewBox="0 0 24 24" aria-hidden="true" className="h-5 w-5 fill-current">
                      <path d={social.path} />
                    </svg>
                  </a>
                </li>
              ))}
            </ul>
          </div>
        ) : null}
      </div>
    </div>
  );
}
