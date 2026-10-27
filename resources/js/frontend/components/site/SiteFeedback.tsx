import type { ReactNode } from "react";
import { cn } from "cn";
import { AlertCircle, CheckCircle, Info, XCircle, X } from "lucide-react";

// ── SiteBadge ──────────────────────────────────────────────────────────────

type SiteBadgeVariant = "default" | "accent" | "success" | "danger" | "warning" | "muted";

interface SiteBadgeProps {
  variant?: SiteBadgeVariant;
  className?: string;
  children: ReactNode;
}

const badgeVariants: Record<SiteBadgeVariant, string> = {
  default: "bg-site-surface text-site-ink border border-site-border",
  accent: "bg-site-accent/10 text-site-accent",
  success: "bg-site-accent/10 text-site-accent",
  danger: "bg-site-danger/10 text-site-danger",
  warning: "bg-site-alt-1 text-site-ink",
  muted: "bg-site-ink/5 text-site-ink-soft",
};

export function SiteBadge({ variant = "default", className, children }: SiteBadgeProps) {
  return (
    <span
      className={cn(
        "inline-flex items-center rounded-site-pill px-site-sm py-0.5 text-xs font-medium",
        badgeVariants[variant],
        className,
      )}
    >
      {children}
    </span>
  );
}

// ── SiteAlert ──────────────────────────────────────────────────────────────

type SiteAlertVariant = "info" | "success" | "warning" | "danger";

interface SiteAlertProps {
  variant?: SiteAlertVariant;
  title?: string;
  className?: string;
  children: ReactNode;
  onDismiss?: () => void;
}

const alertConfig: Record<SiteAlertVariant, { icon: typeof Info; classes: string }> = {
  info: { icon: Info, classes: "border-site-secondary/30 bg-site-secondary/5 text-site-ink" },
  success: { icon: CheckCircle, classes: "border-site-accent/30 bg-site-accent/5 text-site-ink" },
  warning: { icon: AlertCircle, classes: "border-site-alt-1 bg-site-alt-1/50 text-site-ink" },
  danger: { icon: XCircle, classes: "border-site-danger/30 bg-site-danger/5 text-site-ink" },
};

export function SiteAlert({
  variant = "info",
  title,
  className,
  children,
  onDismiss,
}: SiteAlertProps) {
  const config = alertConfig[variant];
  const Icon = config.icon;
  return (
    <div
      role="alert"
      className={cn(
        "flex gap-site-md rounded-site-card border p-site-md",
        config.classes,
        className,
      )}
    >
      <Icon className="h-5 w-5 shrink-0 mt-0.5" />
      <div className="flex-1 min-w-0">
        {title && <p className="font-semibold text-site-ink">{title}</p>}
        <div className="text-sm text-site-ink-soft">{children}</div>
      </div>
      {onDismiss && (
        <button
          type="button"
          onClick={onDismiss}
          className="shrink-0 text-site-ink-faint hover:text-site-ink transition-colors"
          aria-label="Dismiss"
        >
          <X className="h-4 w-4" />
        </button>
      )}
    </div>
  );
}

// ── SiteEmptyState ─────────────────────────────────────────────────────────

interface SiteEmptyStateProps {
  icon?: React.ComponentType<{ className?: string }>;
  title: string;
  description?: string;
  action?: ReactNode;
  className?: string;
}

export function SiteEmptyState({
  icon: Icon,
  title,
  description,
  action,
  className,
}: SiteEmptyStateProps) {
  return (
    <div
      className={cn("flex flex-col items-center justify-center py-site-5xl text-center", className)}
    >
      {Icon && (
        <div className="mb-site-md flex h-12 w-12 items-center justify-center rounded-full bg-site-surface text-site-ink-faint">
          <Icon className="h-6 w-6" />
        </div>
      )}
      <h3 className="site-heading-sm text-site-ink">{title}</h3>
      {description && (
        <p className="mt-site-xs max-w-[40ch] text-sm text-site-ink-soft">{description}</p>
      )}
      {action && <div className="mt-site-lg">{action}</div>}
    </div>
  );
}

// ── SiteSkeleton ───────────────────────────────────────────────────────────

interface SiteSkeletonProps {
  className?: string;
  width?: string;
  height?: string;
}

export function SiteSkeleton({ className, width, height }: SiteSkeletonProps) {
  return (
    <div
      className={cn("animate-pulse rounded-site-card bg-site-ink/5", className)}
      style={{ width, height }}
      aria-hidden="true"
    />
  );
}

// ── SiteProgress ───────────────────────────────────────────────────────────

interface SiteProgressProps {
  value: number;
  max?: number;
  className?: string;
  label?: string;
}

export function SiteProgress({ value, max = 100, className, label }: SiteProgressProps) {
  const pct = Math.min(100, Math.max(0, (value / max) * 100));
  return (
    <div className={cn("flex flex-col gap-site-xs", className)}>
      {label && (
        <div className="flex items-center justify-between text-sm">
          <span className="text-site-ink-soft">{label}</span>
          <span className="font-medium text-site-ink tabular-nums">{Math.round(pct)}%</span>
        </div>
      )}
      <div className="h-2 w-full overflow-hidden rounded-full bg-site-ink/10">
        <div
          className="h-full rounded-full bg-site-accent transition-all duration-300"
          style={{ width: `${pct}%` }}
          role="progressbar"
          aria-valuenow={value}
          aria-valuemin={0}
          aria-valuemax={max}
        />
      </div>
    </div>
  );
}
