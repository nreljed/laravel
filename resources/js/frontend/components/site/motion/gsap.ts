import { gsap as gsapRuntime } from "gsap";
import { ScrollTrigger as ScrollTriggerPlugin } from "gsap/ScrollTrigger";
import { SplitText as SplitTextPlugin } from "gsap/SplitText";

// The motion kit's recipes animate with GSAP. Register the plugins it
// needs up front so consumers (SmoothScroll, the use* hooks, inline page
// effects) never have to.
// eslint-disable-next-line unicorn/no-top-level-side-effects -- registering once here is this module's entire purpose
gsapRuntime.registerPlugin(ScrollTriggerPlugin, SplitTextPlugin);

export { gsap } from "gsap";
export { ScrollTrigger } from "gsap/ScrollTrigger";
export { SplitText } from "gsap/SplitText";
