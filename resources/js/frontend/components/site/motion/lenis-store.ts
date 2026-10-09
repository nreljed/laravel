import type Lenis from "lenis";

// Module-level handle to the page's Lenis instance. Lenis drives the window
// scroll itself and keeps moving it even when `overflow: hidden` is set on
// <html>/<body>, so overlays that need to freeze the page (the site menu) must
// stop/resume the instance rather than rely on overflow alone. SmoothScroll
// owns the lifecycle and registers/unregisters here.
const store: { current: Lenis | null } = { current: null };

export function setActiveLenis(lenis: Lenis | null): void {
  store.current = lenis;
}

export function getActiveLenis(): Lenis | null {
  return store.current;
}
