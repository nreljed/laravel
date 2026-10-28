import * as React from "react";

function useLazyRef<T>(fn: () => T): React.MutableRefObject<T> {
  const ref = React.useRef<T>(null as unknown as T);

  if (ref.current === null) {
    ref.current = fn();
  }

  return ref;
}

export { useLazyRef };
