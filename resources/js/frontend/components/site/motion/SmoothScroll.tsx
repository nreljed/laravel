import { useGSAP } from "@gsap/react";
import Lenis from "lenis";
import type { PropsWithChildren } from "react";
import { gsap, ScrollTrigger } from "./gsap";

// canvasly scroll: Lenis smooth-scrolling driven on the GSAP ticker so the
// smoothed scroll position and ScrollTrigger scrubs share one clock. Lenis
// forwards every scroll step to ScrollTrigger.update, which is the standard
// glue that keeps `scrub` timelines in step with the page.
//
// The Lenis instance stays private by design — there is no `window.lenis`
// handle to drive. For debugging, `window.scrollTo` + a dispatched `scroll`
// event is the supported path (Lenis emits on native scroll steps too).
export function SmoothScroll({ children }: PropsWithChildren) {
  useGSAP(
    () => {
      const lenis = new Lenis({ autoRaf: false });
      lenis.on("scroll", ScrollTrigger.update);

      const tick = (time: number) => {
        lenis.raf(time * 1000);
      };

      gsap.ticker.add(tick);
      gsap.ticker.lagSmoothing(0);

      return () => {
        gsap.ticker.remove(tick);
        lenis.destroy();
      };
    },
    { dependencies: [] },
  );

  return <>{children}</>;
}
