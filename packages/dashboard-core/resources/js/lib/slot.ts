import * as React from "react";
import { composeRefs } from "@/lib/compose-refs";

function mergeProps(
  slotProps: Record<string, unknown>,
  childProps: Record<string, unknown>,
): Record<string, unknown> {
  const merged: Record<string, unknown> = {};

  for (const key in childProps) {
    if (key === "className") {
      merged.className = [slotProps.className, childProps.className].filter(Boolean).join(" ");
    } else if (
      key.startsWith("on") &&
      typeof childProps[key] === "function" &&
      typeof slotProps[key] === "function"
    ) {
      merged[key] = (..._args: unknown[]) => {
        (slotProps[key] as (..._a: unknown[]) => void)(..._args);
        (childProps[key] as (..._a: unknown[]) => void)(..._args);
      };
    } else {
      merged[key] = childProps[key];
    }
  }

  for (const key in slotProps) {
    if (key === "className" || key === "children" || key === "ref" || key.startsWith("on")) {
      continue;
    }
    if (!Object.prototype.hasOwnProperty.call(merged, key)) {
      merged[key] = slotProps[key];
    }
  }

  return merged;
}

interface SlotProps extends React.HTMLAttributes<HTMLElement> {
  children?: React.ReactNode;
  ref?: React.Ref<HTMLElement>;
}

const SlotComponent = React.forwardRef<HTMLElement, SlotProps>(function Slot(props, ref) {
  const { children, ...slotProps } = props;

  if (!children || !React.isValidElement(children)) {
    return null;
  }

  const child = children as React.ReactElement;
  const childProps = { ...(child.props as Record<string, unknown>) };

  const composedRef = composeRefs(
    ref as React.Ref<unknown>,
    (child as { ref?: React.Ref<unknown> }).ref as React.Ref<unknown>,
  );
  childProps.ref = composedRef;

  const mergedProps = mergeProps(slotProps, childProps);

  return React.cloneElement(
    child as React.ReactElement<Record<string, unknown>>,
    mergedProps as Record<string, unknown>,
  );
});

const Slot = SlotComponent as React.ComponentType<
  React.HTMLAttributes<HTMLElement> & { ref?: React.Ref<HTMLElement> }
>;
export { Slot };
