import { Link } from "@inertiajs/react";
import { useEffect, useRef } from "react";
import gsap from "gsap";
import { cn } from "cn";

export interface SiteMenuLink {
  number: string;
  label: string;
  href: string;
}

interface SiteMenuOverlayProps {
  open: boolean;
  onClose: () => void;
  links: SiteMenuLink[];
  brand?: string;
  logo?: string;
  note?: string;
}

export function SiteMenuOverlay({ open, onClose, links, brand, logo, note }: SiteMenuOverlayProps) {
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

  // Lock page scroll while the menu is open — only the menu content
  // itself scrolls (the link list can overflow short viewports).
  useEffect(() => {
    if (!open) return;
    const html = document.documentElement;
    const body = document.body;
    const { overflow: prevHtml, overflowY: prevHtmlY } = html.style;
    const { overflow: prevBody, overflowY: prevBodyY } = body.style;
    html.style.overflow = "hidden";
    html.style.overflowY = "hidden";
    body.style.overflow = "hidden";
    body.style.overflowY = "hidden";
    return () => {
      html.style.overflow = prevHtml;
      html.style.overflowY = prevHtmlY;
      body.style.overflow = prevBody;
      body.style.overflowY = prevBodyY;
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
        className="flex h-full w-full flex-col overflow-y-auto overscroll-contain px-6 py-[8vh] opacity-0 sm:px-10"
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

        {brand || logo || note ? (
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
            {note ? <span>{note}</span> : null}
          </div>
        ) : null}
      </div>
    </div>
  );
}
