import * as React from "react";

import { Badge } from "./badge";
import { cn } from "cn";

const statusStyles = {
  success: "bg-status-success/10 text-status-success border-status-success/30",
  warning: "bg-warning/10 text-warning border-warning/30",
  info: "bg-status-info/10 text-status-info border-status-info/30",
  critical: "bg-status-error/10 text-status-error border-status-error/30",
  destructive: "bg-destructive/10 text-destructive border-destructive/30",
  accentPurple: "bg-accent-purple/10 text-accent-purple border-accent-purple/30",
  accentRose: "bg-accent-rose/10 text-accent-rose border-accent-rose/30",
  accentCyan: "bg-accent-cyan/10 text-accent-cyan border-accent-cyan/30",
  accentPink: "bg-accent-pink/10 text-accent-pink border-accent-pink/30",
} as const;

type StatusVariant = keyof typeof statusStyles;

type StatusBadgeProps = Omit<React.ComponentProps<typeof Badge>, "variant"> & {
  variant: StatusVariant;
};

function StatusBadge({ variant, className, ...props }: StatusBadgeProps) {
  return <Badge variant="outline" className={cn(statusStyles[variant], className)} {...props} />;
}

export { StatusBadge, statusStyles, type StatusVariant };