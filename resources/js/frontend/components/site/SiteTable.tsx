import type { ReactNode } from "react";
import { cn } from "cn";

// ── SiteTable ──────────────────────────────────────────────────────────────

interface SiteTableProps {
  className?: string;
  children: ReactNode;
}

export function SiteTable({ className, children }: SiteTableProps) {
  return (
    <div
      className={cn(
        "w-full overflow-x-auto rounded-site-card border border-site-border",
        className,
      )}
    >
      <table className="w-full text-sm">{children}</table>
    </div>
  );
}

export function SiteTableHeader({
  className,
  children,
}: {
  className?: string;
  children: ReactNode;
}) {
  return (
    <thead className={cn("border-b border-site-border bg-site-surface", className)}>
      {children}
    </thead>
  );
}

export function SiteTableBody({
  className,
  children,
}: {
  className?: string;
  children: ReactNode;
}) {
  return <tbody className={cn("divide-y divide-site-border", className)}>{children}</tbody>;
}

export function SiteTableRow({ className, children }: { className?: string; children: ReactNode }) {
  return (
    <tr className={cn("transition-colors hover:bg-site-surface/50", className)}>{children}</tr>
  );
}

export function SiteTableHead({
  className,
  children,
}: {
  className?: string;
  children: ReactNode;
}) {
  return (
    <th
      className={cn(
        "px-site-md py-site-sm text-left text-xs font-semibold uppercase tracking-[0.1em] text-site-ink-faint",
        className,
      )}
    >
      {children}
    </th>
  );
}

export function SiteTableCell({
  className,
  children,
}: {
  className?: string;
  children: ReactNode;
}) {
  return <td className={cn("px-site-md py-site-sm text-site-ink", className)}>{children}</td>;
}
