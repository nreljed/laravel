import type { ReactNode } from "react";
import { useState } from "react";
import { cn } from "cn";
import { X } from "lucide-react";

// ── SiteDialog ─────────────────────────────────────────────────────────────

interface SiteDialogProps {
  open: boolean;
  onClose: () => void;
  title?: string;
  description?: string;
  children: ReactNode;
  className?: string;
}

export function SiteDialog({
  open,
  onClose,
  title,
  description,
  children,
  className,
}: SiteDialogProps) {
  if (!open) return null;
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-site-md">
      <div
        className="fixed inset-0 bg-site-ink/40 backdrop-blur-sm"
        onClick={onClose}
        aria-hidden="true"
      />
      <div
        role="dialog"
        aria-modal="true"
        aria-labelledby={title ? "site-dialog-title" : undefined}
        className={cn(
          "relative z-10 w-full max-w-lg rounded-site-card border border-site-border bg-site-bg-strong p-site-xl shadow-xl",
          className,
        )}
      >
        {title && (
          <h2 id="site-dialog-title" className="site-heading-sm text-site-ink">
            {title}
          </h2>
        )}
        {description && <p className="mt-site-xs text-sm text-site-ink-soft">{description}</p>}
        <div className={cn(title || description ? "mt-site-lg" : "")}>{children}</div>
        <button
          type="button"
          onClick={onClose}
          className="absolute right-site-md top-site-md text-site-ink-faint hover:text-site-ink transition-colors"
          aria-label="Close dialog"
        >
          <X className="h-4 w-4" />
        </button>
      </div>
    </div>
  );
}

// ── SitePopover ────────────────────────────────────────────────────────────

interface SitePopoverProps {
  trigger: ReactNode;
  children: ReactNode;
  className?: string;
}

export function SitePopover({ trigger, children, className }: SitePopoverProps) {
  const [open, setOpen] = useState(false);
  return (
    <div className="relative inline-block">
      <div onClick={() => setOpen(!open)} className="cursor-pointer">
        {trigger}
      </div>
      {open && (
        <>
          <div className="fixed inset-0 z-40" onClick={() => setOpen(false)} aria-hidden="true" />
          <div
            className={cn(
              "absolute z-50 mt-site-xs rounded-site-card border border-site-border bg-site-bg-strong p-site-md shadow-lg",
              className,
            )}
          >
            {children}
          </div>
        </>
      )}
    </div>
  );
}

// ── SiteDropdown ───────────────────────────────────────────────────────────

interface SiteDropdownItem {
  label: string;
  onClick?: () => void;
  href?: string;
  destructive?: boolean;
}

interface SiteDropdownProps {
  trigger: ReactNode;
  items: SiteDropdownItem[];
  className?: string;
}

export function SiteDropdown({ trigger, items, className }: SiteDropdownProps) {
  const [open, setOpen] = useState(false);
  return (
    <div className="relative inline-block">
      <div onClick={() => setOpen(!open)} className="cursor-pointer">
        {trigger}
      </div>
      {open && (
        <>
          <div className="fixed inset-0 z-40" onClick={() => setOpen(false)} aria-hidden="true" />
          <div
            className={cn(
              "absolute z-50 mt-site-xs min-w-[180px] rounded-site-card border border-site-border bg-site-bg-strong py-site-xs shadow-lg",
              className,
            )}
          >
            {items.map((item, i) => (
              <button
                key={i}
                type="button"
                onClick={() => {
                  item.onClick?.();
                  setOpen(false);
                }}
                className={cn(
                  "flex w-full items-center px-site-md py-site-xs text-sm text-site-ink transition-colors hover:bg-site-surface",
                  item.destructive && "text-site-danger hover:bg-site-danger/5",
                )}
              >
                {item.label}
              </button>
            ))}
          </div>
        </>
      )}
    </div>
  );
}

// ── SiteTooltip ─────────────────────────────────────────────────────────────

interface SiteTooltipProps {
  content: string;
  children: ReactNode;
  className?: string;
}

export function SiteTooltip({ content, children, className }: SiteTooltipProps) {
  const [show, setShow] = useState(false);
  return (
    <div
      className="relative inline-block"
      onMouseEnter={() => setShow(true)}
      onMouseLeave={() => setShow(false)}
    >
      {children}
      {show && (
        <div
          className={cn(
            "absolute bottom-full left-1/2 z-50 mb-site-xs -translate-x-1/2 rounded-site-card bg-site-ink px-site-sm py-site-xs text-xs text-site-bg-strong whitespace-nowrap",
            className,
          )}
        >
          {content}
        </div>
      )}
    </div>
  );
}
