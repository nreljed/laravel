import { useCallback, useRef } from "react";

/**
 * Shared positioning logic for floating elements inside the editor.
 * Positions above the target when possible, falls back to below.
 * Clamps horizontally to the viewport.
 */
export function useFloatingPosition(offset = 8) {
  const ref = useRef<HTMLDivElement>(null);

  const positionAbove = useCallback(
    (targetRect: DOMRect) => {
      const el = ref.current;
      if (!el) return;
      const elHeight = el.offsetHeight;
      const viewportWidth = window.innerWidth;

      const aboveTop = targetRect.top - elHeight - offset;
      const belowTop = targetRect.bottom + offset;
      const top = aboveTop >= 0 ? aboveTop : belowTop;

      const idealLeft = targetRect.left + targetRect.width / 2 - el.offsetWidth / 2;
      const left = Math.max(8, Math.min(idealLeft, viewportWidth - el.offsetWidth - 8));

      el.style.top = `${top}px`;
      el.style.left = `${left}px`;
    },
    [offset],
  );

  const positionAtRect = useCallback(
    (targetRect: DOMRect, opts?: { anchor?: "top-left" | "top-right" | "center" }) => {
      const el = ref.current;
      if (!el) return;
      const viewportWidth = window.innerWidth;
      const anchor = opts?.anchor ?? "top-left";

      let left: number;
      let top: number;

      switch (anchor) {
        case "top-right": {
          left = targetRect.right - el.offsetWidth;
          top = targetRect.top - el.offsetHeight - offset;
          break;
        }
        case "center": {
          left = targetRect.left + targetRect.width / 2 - el.offsetWidth / 2;
          top = targetRect.top + targetRect.height / 2 - el.offsetHeight / 2;
          break;
        }
        default: {
          left = targetRect.left;
          top = targetRect.top - el.offsetHeight - offset;
          break;
        }
      }

      left = Math.max(8, Math.min(left, viewportWidth - el.offsetWidth - 8));
      el.style.top = `${top}px`;
      el.style.left = `${left}px`;
    },
    [offset],
  );

  return { ref, positionAbove, positionAtRect };
}
