import type { InputHTMLAttributes, TextareaHTMLAttributes } from "react";
import { forwardRef } from "react";
import { cn } from "cn";

// ── SiteInput ──────────────────────────────────────────────────────────────

interface SiteInputProps extends InputHTMLAttributes<HTMLInputElement> {
  error?: string;
  label?: string;
}

export const SiteInput = forwardRef<HTMLInputElement, SiteInputProps>(
  ({ className, error, label, id, ...props }, ref) => {
    const inputId =
      id || (label ? `site-input-${label.toLowerCase().replaceAll(/\s+/g, "-")}` : undefined);
    return (
      <div className="flex flex-col gap-site-xs">
        {label && (
          <label htmlFor={inputId} className="text-sm font-medium text-site-ink">
            {label}
          </label>
        )}
        <input
          ref={ref}
          id={inputId}
          className={cn(
            "h-11 w-full rounded-site-card border border-site-border bg-site-surface px-site-md text-sm text-site-ink",
            "placeholder:text-site-ink-faint",
            "transition-colors duration-200",
            "focus:border-site-accent focus:outline-none focus:ring-[3px] focus:ring-site-accent/20",
            "disabled:cursor-not-allowed disabled:opacity-50",
            error && "border-site-danger focus:border-site-danger focus:ring-site-danger/20",
            className,
          )}
          aria-invalid={!!error}
          aria-describedby={error ? `${inputId}-error` : undefined}
          {...props}
        />
        {error && (
          <p id={`${inputId}-error`} className="text-sm text-site-danger" role="alert">
            {error}
          </p>
        )}
      </div>
    );
  },
);
SiteInput.displayName = "SiteInput";

// ── SiteTextarea ───────────────────────────────────────────────────────────

interface SiteTextareaProps extends TextareaHTMLAttributes<HTMLTextAreaElement> {
  error?: string;
  label?: string;
}

export const SiteTextarea = forwardRef<HTMLTextAreaElement, SiteTextareaProps>(
  ({ className, error, label, id, ...props }, ref) => {
    const textareaId =
      id || (label ? `site-textarea-${label.toLowerCase().replaceAll(/\s+/g, "-")}` : undefined);
    return (
      <div className="flex flex-col gap-site-xs">
        {label && (
          <label htmlFor={textareaId} className="text-sm font-medium text-site-ink">
            {label}
          </label>
        )}
        <textarea
          ref={ref}
          id={textareaId}
          className={cn(
            "min-h-[100px] w-full rounded-site-card border border-site-border bg-site-surface px-site-md py-site-sm text-sm text-site-ink",
            "placeholder:text-site-ink-faint",
            "transition-colors duration-200",
            "focus:border-site-accent focus:outline-none focus:ring-[3px] focus:ring-site-accent/20",
            "disabled:cursor-not-allowed disabled:opacity-50",
            "resize-y",
            error && "border-site-danger focus:border-site-danger focus:ring-site-danger/20",
            className,
          )}
          aria-invalid={!!error}
          aria-describedby={error ? `${textareaId}-error` : undefined}
          {...props}
        />
        {error && (
          <p id={`${textareaId}-error`} className="text-sm text-site-danger" role="alert">
            {error}
          </p>
        )}
      </div>
    );
  },
);
SiteTextarea.displayName = "SiteTextarea";

// ── SiteCheckbox ───────────────────────────────────────────────────────────

interface SiteCheckboxProps extends Omit<InputHTMLAttributes<HTMLInputElement>, "type"> {
  label?: string;
  error?: string;
}

export function SiteCheckbox({ className, label, error, id, ...props }: SiteCheckboxProps) {
  const checkboxId =
    id || (label ? `site-checkbox-${label.toLowerCase().replaceAll(/\s+/g, "-")}` : undefined);
  return (
    <div className="flex flex-col gap-site-xs">
      <label className="flex items-center gap-site-sm text-sm text-site-ink cursor-pointer">
        <input
          type="checkbox"
          id={checkboxId}
          className={cn(
            "h-4 w-4 rounded border-site-border text-site-accent",
            "focus:ring-[3px] focus:ring-site-accent/20 focus:ring-offset-0",
            "disabled:cursor-not-allowed disabled:opacity-50",
            className,
          )}
          aria-invalid={!!error}
          {...props}
        />
        {label && <span>{label}</span>}
      </label>
      {error && (
        <p className="text-sm text-site-danger" role="alert">
          {error}
        </p>
      )}
    </div>
  );
}

// ── SiteRadio ──────────────────────────────────────────────────────────────

interface SiteRadioProps extends Omit<InputHTMLAttributes<HTMLInputElement>, "type"> {
  label?: string;
}

export function SiteRadio({ className, label, id, ...props }: SiteRadioProps) {
  const radioId =
    id || (label ? `site-radio-${label.toLowerCase().replaceAll(/\s+/g, "-")}` : undefined);
  return (
    <label className="flex items-center gap-site-sm text-sm text-site-ink cursor-pointer">
      <input
        type="radio"
        id={radioId}
        className={cn(
          "h-4 w-4 border-site-border text-site-accent",
          "focus:ring-[3px] focus:ring-site-accent/20 focus:ring-offset-0",
          "disabled:cursor-not-allowed disabled:opacity-50",
          className,
        )}
        {...props}
      />
      {label && <span>{label}</span>}
    </label>
  );
}

// ── SiteSelect ─────────────────────────────────────────────────────────────

interface SiteSelectProps extends React.SelectHTMLAttributes<HTMLSelectElement> {
  error?: string;
  label?: string;
  options?: { value: string; label: string }[];
}

export const SiteSelect = forwardRef<HTMLSelectElement, SiteSelectProps>(
  ({ className, error, label, id, options, children, ...props }, ref) => {
    const selectId =
      id || (label ? `site-select-${label.toLowerCase().replaceAll(/\s+/g, "-")}` : undefined);
    return (
      <div className="flex flex-col gap-site-xs">
        {label && (
          <label htmlFor={selectId} className="text-sm font-medium text-site-ink">
            {label}
          </label>
        )}
        <select
          ref={ref}
          id={selectId}
          className={cn(
            "h-11 w-full rounded-site-card border border-site-border bg-site-surface px-site-md text-sm text-site-ink",
            "transition-colors duration-200",
            "focus:border-site-accent focus:outline-none focus:ring-[3px] focus:ring-site-accent/20",
            "disabled:cursor-not-allowed disabled:opacity-50",
            error && "border-site-danger focus:border-site-danger focus:ring-site-danger/20",
            className,
          )}
          aria-invalid={!!error}
          {...props}
        >
          {options
            ? options.map((opt) => (
                <option key={opt.value} value={opt.value}>
                  {opt.label}
                </option>
              ))
            : children}
        </select>
        {error && (
          <p className="text-sm text-site-danger" role="alert">
            {error}
          </p>
        )}
      </div>
    );
  },
);
SiteSelect.displayName = "SiteSelect";
