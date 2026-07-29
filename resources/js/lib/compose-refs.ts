import * as React from "react";

function setRef(
  ref: React.Ref<unknown>,
  value: React.RefCallback<unknown> | React.MutableRefObject<unknown> | null,
) {
  if (typeof ref === "function") {
    return ref(value as never);
  }
  if (ref !== null && ref !== undefined) {
    (ref as React.MutableRefObject<unknown>).current = value;
  }
}

function composeRefs(...refs: React.Ref<unknown>[]) {
  return (node: unknown) => {
    let hasCleanup = false;
    const cleanups = refs.map((ref) => {
      const cleanup = setRef(ref, node as never);
      if (!hasCleanup && typeof cleanup === "function") {
        hasCleanup = true;
      }
      return cleanup;
    });
    if (hasCleanup) {
      return () => {
        for (const [i, cleanup] of cleanups.entries()) {
          const ref = refs[i];
          if (typeof cleanup === "function") {
            cleanup();
          } else if (ref !== undefined) {
            setRef(ref, null);
          }
        }
      };
    }
  };
}

function useComposedRefs(...refs: React.Ref<unknown>[]) {
  // eslint-disable-next-line react-hooks/use-memo, react-hooks/exhaustive-deps
  return React.useMemo(() => composeRefs(...refs), [...refs]);
}

export { composeRefs, useComposedRefs };
