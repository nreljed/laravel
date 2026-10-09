import { useEffect } from "react";
import { getActiveLenis } from "./lenis-store";

// Freeze the page behind an overlay. `overflow: hidden` alone only stops native
// scrolling — the active Lenis instance keeps driving the window — so the lock
// also stops/resumes Lenis. Restores whatever inline overflow the page had.
export function useBodyScrollLock(locked: boolean): void {
  useEffect(() => {
    if (!locked) {
      return;
    }

    const html = document.documentElement;
    const body = document.body;
    const lenis = getActiveLenis();

    const previous = {
      htmlOverflow: html.style.overflow,
      bodyOverflow: body.style.overflow,
      htmlOverscroll: html.style.overscrollBehavior,
      bodyOverscroll: body.style.overscrollBehavior,
    };

    lenis?.stop();
    html.style.overflow = "hidden";
    body.style.overflow = "hidden";
    html.style.overscrollBehavior = "none";
    body.style.overscrollBehavior = "none";

    return () => {
      html.style.overflow = previous.htmlOverflow;
      body.style.overflow = previous.bodyOverflow;
      html.style.overscrollBehavior = previous.htmlOverscroll;
      body.style.overscrollBehavior = previous.bodyOverscroll;
      lenis?.start();
    };
  }, [locked]);
}
