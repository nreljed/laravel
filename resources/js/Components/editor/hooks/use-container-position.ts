import { useCallback, useRef, type RefObject, type RefCallback } from "react";

/**
 * Position a floating element inside a scrollable container.
 * Converts viewport-relative DOMRect coordinates to container-relative
 * absolute positions, accounting for scroll offset.
 *
 * Use this when the floating element uses `position: absolute` inside
 * a scrollable container (e.g. editor overflow wrapper).
 *
 * For viewport-fixed positioning (e.g. portaled toolbars), use
 * `useFloatingPosition` instead.
 */
export function useContainerPosition(scrollContainerRef: RefObject<HTMLElement | null>) {
  const refObject = useRef<HTMLDivElement>(null);

  const ref: RefCallback<HTMLDivElement> = useCallback((node) => {
    refObject.current = node;
  }, []);

  const positionRelative = useCallback(
    (
      targetRect: DOMRect,
      opts?: {
        anchor?: "top-left" | "top-right" | "top-center" | "bottom-center" | "center";
      },
    ) => {
      const el = refObject.current;
      const container = scrollContainerRef.current;
      if (!el || !container) return;

      const containerRect = container.getBoundingClientRect();
      const anchor = opts?.anchor ?? "top-left";

      let left: number;
      let top: number;

      switch (anchor) {
        case "top-center": {
          left =
            targetRect.left -
            containerRect.left +
            container.scrollLeft +
            targetRect.width / 2 -
            el.offsetWidth / 2;
          top = targetRect.top - containerRect.top + container.scrollTop - el.offsetHeight;
          break;
        }
        case "bottom-center": {
          left =
            targetRect.left -
            containerRect.left +
            container.scrollLeft +
            targetRect.width / 2 -
            el.offsetWidth / 2;
          top = targetRect.bottom - containerRect.top + container.scrollTop;
          break;
        }
        case "center": {
          left =
            targetRect.left -
            containerRect.left +
            container.scrollLeft +
            targetRect.width / 2 -
            el.offsetWidth / 2;
          top =
            targetRect.top -
            containerRect.top +
            container.scrollTop +
            targetRect.height / 2 -
            el.offsetHeight / 2;
          break;
        }
        case "top-right": {
          left = targetRect.right - containerRect.left + container.scrollLeft - el.offsetWidth;
          top = targetRect.top - containerRect.top + container.scrollTop - el.offsetHeight;
          break;
        }
        default: {
          left = targetRect.left - containerRect.left + container.scrollLeft;
          top = targetRect.top - containerRect.top + container.scrollTop;
          break;
        }
      }

      el.style.left = `${left}px`;
      el.style.top = `${top}px`;
    },
    [scrollContainerRef],
  );

  const positionAbove = useCallback(
    (targetRect: DOMRect, offset = 4) => {
      const el = refObject.current;
      const container = scrollContainerRef.current;
      if (!el || !container) return;

      const containerRect = container.getBoundingClientRect();
      const left =
        targetRect.left -
        containerRect.left +
        container.scrollLeft +
        targetRect.width / 2 -
        el.offsetWidth / 2;
      const top =
        targetRect.top - containerRect.top + container.scrollTop - el.offsetHeight - offset;

      el.style.left = `${left}px`;
      el.style.top = `${top}px`;
    },
    [scrollContainerRef],
  );

  const positionAtCenter = useCallback(
    (targetRect: DOMRect) => {
      positionRelative(targetRect, { anchor: "center" });
    },
    [positionRelative],
  );

  return { ref, positionRelative, positionAbove, positionAtCenter };
}
