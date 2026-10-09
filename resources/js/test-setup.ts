import "@testing-library/jest-dom/vitest";

// jsdom does not implement window.matchMedia (jsdom issue #907), which GSAP's
// ScrollTrigger.matchMedia and gsap.matchMedia() call at import time. Install
// a minimal non-matching stub before application modules load so the motion
// kit can register without guard-rail hacks in production code. `matches:
// false` also keeps scroll animations inert under test (reduced-motion path),
// which is exactly what a unit test wants.
if (typeof window !== "undefined" && !globalThis.matchMedia) {
  Object.defineProperty(globalThis, "matchMedia", {
    writable: true,
    value: (query: string) => ({
      matches: false,
      media: query,
      onchange: null,
      addListener: () => {},
      removeListener: () => {},
      addEventListener: () => {},
      removeEventListener: () => {},
      dispatchEvent: () => false,
    }),
  });
}

// jsdom does not implement ResizeObserver either, which Lenis' Dimensions
// class constructs on init. Install an inert stub so SmoothScroll can mount
// under test without the smooth-scroll behaviour being exercised.
if (typeof window !== "undefined" && !globalThis.ResizeObserver) {
  class ResizeObserverStub {
    observe(): void {}
    unobserve(): void {}
    disconnect(): void {}
  }

  Object.defineProperty(globalThis, "ResizeObserver", {
    writable: true,
    value: ResizeObserverStub,
  });
}
