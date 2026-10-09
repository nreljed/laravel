import { useEffect, type RefObject } from "react";
import { useGSAP } from "@gsap/react";
import { gsap, SplitText } from "./gsap";

// Reusable canvasly-recipe motion hooks. Each one:
//   - scopes to the caller's root ref (data-site-theme wrapper),
//   - gates on `(prefers-reduced-motion: no-preference)` via gsap.matchMedia
//     so reduced-motion users get static, fully-revealed content, and
//   - tears down cleanly (matchMedia revert + SplitText.revert() — the
//     latter is the important one: SplitText mutates the DOM, and useGSAP's
//     context does NOT undo that).
// Consumers opt in from markup with a data attribute, e.g.
//   <h1 data-fading-chars>…</h1>
//   <h2 data-word-reveal>…</h2>
//   <div data-scale-in>…</div>
//   <img data-parallax> … (wrap the img in an overflow-hidden box)
// Anything missing from the DOM is a no-op, so a page can call every hook
// unconditionally and only the elements that opt in animate.

export interface ScrubConfig {
  /**
  ScrollTrigger `start`, e.g. "top 60%" (default per-hook).
  */
  start?: string;
  /**
  ScrollTrigger `end`, e.g. "center 30%" (default per-hook).
  */
  end?: string;
  /**
  Direct link to scroll: true = tied 1:1, number = smoothed (default per-hook).
  */
  scrub?: boolean | number;
  /**
  Fire once and keep the final state instead of scrubbing (defaults per-hook).
  */
  once?: boolean;
}

// canvasly `fading-chars` (interaction i-e9b799f3): hero title characters
// start at 15% opacity and fill to full as the title scolls through the
// viewport. Recipe: start "top 60%" / end "center 30%", scrub 0.85,
// stagger 0.05, duration 0.64 — cloned verbatim below.
export function useFadingChars(
  scope: RefObject<HTMLElement | null>,
  selector = "[data-fading-chars]",
  config: ScrubConfig & { stagger?: number; duration?: number } = {},
) {
  useGSAP(
    () => {
      const splits = new Set<SplitText>();
      const mm = gsap.matchMedia();

      mm.add("(prefers-reduced-motion: no-preference)", () => {
        const root = scope.current;
        if (!root) return;
        const targets = gsap.utils.toArray<HTMLElement>(selector, root);
        for (const target of targets) {
          if (!target.textContent || !target.textContent.trim()) continue;
          const split = SplitText.create(target, { type: "chars", aria: "auto" });
          splits.add(split);

          gsap.fromTo(
            split.chars,
            { opacity: 0.15 },
            {
              opacity: 1,
              stagger: config.stagger ?? 0.05,
              duration: config.duration ?? 0.64,
              ease: "none",
              scrollTrigger: {
                trigger: target,
                start: config.start ?? "top 60%",
                end: config.end ?? "center 30%",
                scrub: config.scrub ?? 0.85,
              },
            },
          );
        }
      });

      return () => {
        mm.revert();
        for (const split of splits) split.revert();
      };
    },
    { scope },
  );
}

// canvasly `word-reveal`: words rise out of their line mask as the heading
// enters the viewport. Markup must wrap the heading in an overflow-hidden
// container for the mask to read. Cloned to match the fading-chars teardown
// contract (SplitText.revert() in the cleanup).
export function useWordReveal(
  scope: RefObject<HTMLElement | null>,
  selector = "[data-word-reveal]",
  config: ScrubConfig = {},
) {
  useGSAP(
    () => {
      const splits = new Set<SplitText>();
      const mm = gsap.matchMedia();

      mm.add("(prefers-reduced-motion: no-preference)", () => {
        const root = scope.current;
        if (!root) return;
        const targets = gsap.utils.toArray<HTMLElement>(selector, root);
        for (const target of targets) {
          if (!target.textContent || !target.textContent.trim()) continue;
          const split = SplitText.create(target, { type: "words", aria: "auto" });
          splits.add(split);

          gsap.from(split.words, {
            yPercent: 110,
            autoAlpha: 0,
            stagger: 0.04,
            duration: 0.6,
            ease: "power3.out",
            scrollTrigger: {
              trigger: target,
              start: config.start ?? "top 85%",
              end: config.end ?? "top 45%",
              scrub: config.scrub ?? true,
              once: config.once,
            },
          });
        }
      });

      return () => {
        mm.revert();
        for (const split of splits) split.revert();
      };
    },
    { scope },
  );
}

// canvasly `text-fill`: a reading-progress fill — the whole block fades from
// a faint fill to full ink as it scrolls through the viewport.
export function useTextFill(
  scope: RefObject<HTMLElement | null>,
  selector = "[data-text-fill]",
  config: ScrubConfig = {},
) {
  useGSAP(
    () => {
      const mm = gsap.matchMedia();

      mm.add("(prefers-reduced-motion: no-preference)", () => {
        const root = scope.current;
        if (!root) return;
        const targets = gsap.utils.toArray<HTMLElement>(selector, root);
        for (const target of targets) {
          gsap.fromTo(
            target,
            { autoAlpha: 0.35 },
            {
              autoAlpha: 1,
              ease: "none",
              scrollTrigger: {
                trigger: target,
                start: config.start ?? "top 80%",
                end: config.end ?? "top 40%",
                scrub: config.scrub ?? 0.6,
                once: config.once,
              },
            },
          );
        }
      });
    },
    { scope },
  );
}

// canvasly `scale-in`: scale + fade a block in as it crossess the viewport
// (used for the statement/footer titles the template grows from the bottom
// of the page).
export function useScaleIn(
  scope: RefObject<HTMLElement | null>,
  selector = "[data-scale-in]",
  config: ScrubConfig = {},
) {
  useGSAP(
    () => {
      const mm = gsap.matchMedia();

      mm.add("(prefers-reduced-motion: no-preference)", () => {
        const root = scope.current;
        if (!root) return;
        const targets = gsap.utils.toArray<HTMLElement>(selector, root);
        for (const target of targets) {
          gsap.fromTo(
            target,
            { scale: 0.8, autoAlpha: 0 },
            {
              scale: 1,
              autoAlpha: 1,
              ease: "power1.out",
              scrollTrigger: {
                trigger: target,
                start: config.start ?? "top 85%",
                end: config.end ?? "top 45%",
                scrub: config.scrub ?? true,
                once: config.once,
              },
            },
          );
        }
      });
    },
    { scope },
  );
}

// canvasly `parallax`: the image inside an overflow-hidden wrap drifts ±6%
// relative to the scroll. Mirror of the inline recipe Home.tsx uses for its
// work grid.
export function useParallax(scope: RefObject<HTMLElement | null>, selector = "[data-parallax]") {
  useGSAP(
    () => {
      const mm = gsap.matchMedia();

      mm.add("(prefers-reduced-motion: no-preference)", () => {
        const root = scope.current;
        if (!root) return;
        const wraps = gsap.utils.toArray<HTMLElement>(selector, root);
        for (const wrap of wraps) {
          const img = wrap.querySelector("img");
          if (!img) continue;
          gsap.fromTo(
            img,
            { yPercent: -6 },
            {
              yPercent: 6,
              ease: "none",
              scrollTrigger: {
                trigger: wrap,
                start: "top bottom",
                end: "bottom top",
                scrub: true,
              },
            },
          );
        }
      });
    },
    { scope },
  );
}

// canvasly spotlight: on pointermove over the ref, writes the cursor position
// to --site-cursor-x/y so `.site-spotlight::after` (site.css) can track the
// radial glow behind the CTA text.
export function useCursorSpotlight(ref: RefObject<HTMLElement | null>) {
  useEffect(() => {
    const el = ref.current;
    if (!el) return;

    const onMove = (event: PointerEvent) => {
      const rect = el.getBoundingClientRect();
      el.style.setProperty("--site-cursor-x", `${event.clientX - rect.left}px`);
      el.style.setProperty("--site-cursor-y", `${event.clientY - rect.top}px`);
    };

    el.addEventListener("pointermove", onMove);
    return () => el.removeEventListener("pointermove", onMove);
  }, [ref]);
}
