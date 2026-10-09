import { render } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { gsap, ScrollTrigger, SplitText } from "./gsap";
import { SmoothScroll } from "./SmoothScroll";

describe("site motion kit", () => {
  it("exports the registered GSAP plugins recipes animate with", () => {
    expect(gsap.registerPlugin).toBeTypeOf("function");
    expect(ScrollTrigger).toBeTruthy();
    expect(SplitText).toBeTruthy();
  });

  it("renders children through SmoothScroll", () => {
    const { container } = render(
      <SmoothScroll>
        <div data-testid="motion-child">hi</div>
      </SmoothScroll>,
    );
    expect(container.querySelector('[data-testid="motion-child"]')).not.toBeNull();
  });
});
