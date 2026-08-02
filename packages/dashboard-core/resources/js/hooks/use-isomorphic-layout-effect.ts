import * as React from "react";

const useIsomorphicLayoutEffect: typeof React.useLayoutEffect =
  typeof window !== "undefined" ? React.useLayoutEffect : React.useEffect;

export { useIsomorphicLayoutEffect };
