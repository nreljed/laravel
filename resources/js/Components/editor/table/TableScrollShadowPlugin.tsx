import { useLexicalComposerContext } from "@lexical/react/LexicalComposerContext";
import { isHTMLElement } from "lexical";
import { useEffect } from "react";

const SCROLLABLE_WRAPPER_SELECTOR = "[data-lexical-editor] .overflow-x-auto";
const HAS_SCROLL_SHADOW_RIGHT = "table-scroll-shadow-right";
const HAS_SCROLL_SHADOW_LEFT = "table-scroll-shadow-left";
const HAS_SCROLL_SHADOW_MIDDLE = "table-scroll-shadow-middle";

function updateTableScrollState(element: HTMLElement): void {
  const hasScroll = element.scrollWidth > element.clientWidth;
  const isScrolledToRight = element.scrollLeft + element.clientWidth >= element.scrollWidth - 1;
  const isScrolledToLeft = element.scrollLeft <= 1;

  element.classList.remove(
    HAS_SCROLL_SHADOW_RIGHT,
    HAS_SCROLL_SHADOW_LEFT,
    HAS_SCROLL_SHADOW_MIDDLE,
  );

  if (hasScroll) {
    if (!isScrolledToLeft && !isScrolledToRight) {
      element.classList.add(HAS_SCROLL_SHADOW_MIDDLE);
    } else if (isScrolledToLeft && !isScrolledToRight) {
      element.classList.add(HAS_SCROLL_SHADOW_RIGHT);
    } else if (!isScrolledToLeft && isScrolledToRight) {
      element.classList.add(HAS_SCROLL_SHADOW_LEFT);
    }
  }
}

export default function TableScrollShadowPlugin(): null {
  const [editor] = useLexicalComposerContext();

  useEffect(() => {
    const editorElement = editor.getRootElement();
    if (!editorElement) {
      return;
    }

    const updateAllTableScrollStates = () => {
      const wrappers = editorElement.querySelectorAll<HTMLElement>(SCROLLABLE_WRAPPER_SELECTOR);
      for (const wrapper of wrappers) {
        updateTableScrollState(wrapper);
      }
    };

    updateAllTableScrollStates();

    const observer = new MutationObserver(() => {
      updateAllTableScrollStates();
    });

    observer.observe(editorElement, {
      childList: true,
      subtree: true,
    });

    const resizeObserver = new ResizeObserver(() => {
      updateAllTableScrollStates();
    });

    const scrollHandlers = new Map<HTMLElement, () => void>();

    const addScrollListener = (wrapper: HTMLElement) => {
      if (scrollHandlers.has(wrapper)) {
        return;
      }
      const handler = () => {
        updateTableScrollState(wrapper);
      };
      wrapper.addEventListener("scroll", handler, { passive: true });
      scrollHandlers.set(wrapper, handler);
    };

    const wrappers = editorElement.querySelectorAll<HTMLElement>(SCROLLABLE_WRAPPER_SELECTOR);
    for (const wrapper of wrappers) {
      resizeObserver.observe(wrapper);
      addScrollListener(wrapper);
    }

    const observeNode = (node: HTMLElement) => {
      resizeObserver.observe(node);
      addScrollListener(node);
      updateTableScrollState(node);
    };

    const processAddedNode = (node: HTMLElement) => {
      if (node.matches(SCROLLABLE_WRAPPER_SELECTOR)) {
        observeNode(node);
      }

      const childWrappers = node.querySelectorAll<HTMLElement>(SCROLLABLE_WRAPPER_SELECTOR);
      for (const wrapper of childWrappers) {
        observeNode(wrapper);
      }
    };

    const wrapperObserver = new MutationObserver((mutations) => {
      for (const mutation of mutations) {
        for (const node of mutation.addedNodes) {
          if (isHTMLElement(node)) {
            processAddedNode(node);
          }
        }
      }
    });

    wrapperObserver.observe(editorElement, {
      childList: true,
      subtree: true,
    });

    return () => {
      observer.disconnect();
      resizeObserver.disconnect();
      wrapperObserver.disconnect();

      scrollHandlers.forEach((handler, wrapper) => {
        wrapper.removeEventListener("scroll", handler);
      });
      scrollHandlers.clear();
    };
  }, [editor]);

  return null;
}
