import { useEffect, type ReactNode } from "react";
import { SmoothScroll } from "./motion/SmoothScroll";

interface SiteRootProps {
  children: ReactNode;
}

// Project-owned site shell. Injects the theme's webfont (Instrument Sans,
// 400-700 variable) exactly once and wraps the page in
// `data-site-theme="canvasly"` so every `site-*` utility resolves against the
// Canvasly palette for the whole sub-tree. The Dashboard stays on core's
// shadcn tokens — this shell only governs public pages.
export function SiteRoot({ children }: SiteRootProps) {
  useEffect(() => {
    const id = "instrument-sans-font-stylesheet";
    if (document.querySelector(`#${id}`)) return;
    const link = document.createElement("link");
    link.id = id;
    link.rel = "stylesheet";
    link.href =
      "https://fonts.googleapis.com/css2?family=Instrument+Sans:ital,wght@0,400..700;1,400..700&display=swap";
    document.head.appendChild(link);
  }, []);

  return (
    <div
      data-site-theme="canvasly"
      className="flex min-h-screen flex-col bg-site-bg font-site-body text-site-ink"
    >
      <SmoothScroll>{children}</SmoothScroll>
    </div>
  );
}
