import { useGSAP } from "@gsap/react";
import Lenis from "lenis";
import type { PropsWithChildren } from "react";
import { gsap, ScrollTrigger } from "./gsap";
import { setActiveLenis } from "./lenis-store";

// canvasly scroll: Lenis smooth-scrolling driven on the GSAP ticker so the
// smoothed scroll position and ScrollTrigger scrubs share one clock. Lenis
// forwards every scroll step to ScrollTrigger.update, which is the standard
// glue that keeps `scrub` timelines in step with the page.
//
// The Lenis instance stays private by design — there is no `window.lenis`
// handle to drive. It is exposed through `lenis-store` (module-level) so page
// overlays can stop/resume scrolling; programmatic control otherwise goes
// through `window.scrollTo` + a dispatched `scroll` event, which Lenis mirrors.
export function SmoothScroll({ children }: PropsWithChildren) {
  useGSAP(
    () => {
      const lenis = new Lenis({ autoRaf: false });
      setActiveLenis(lenis);
      lenis.on("scroll", ScrollTrigger.update);

      const tick = (time: number) => {
        lenis.raf(time * 1000);
      };

      gsap.ticker.add(tick);
      gsap.ticker.lagSmoothing(0);

      return () => {
        gsap.ticker.remove(tick);
        setActiveLenis(null);
        lenis.destroy();
      };
    },
    { dependencies: [] },
  );

  return <>{children}</>;
}
