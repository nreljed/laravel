import * as React from "react";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/Components/ui/card";
import { Button } from "@/Components/ui/button";
import { Field, FieldDescription, FieldError, FieldLabel } from "@/Components/ui/field";
import { SettingsAccordion } from "@/Components/shared/settings-accordion";
import { cn } from "@/lib/utils";

function Section({
  children,
  title,
  description,
  icon: Icon,
  onSubmit,
  loading = false,
  submitLabel = "Save Changes",
  submitVariant = "default",
  disabled = false,
  error,
  fieldErrors = {},
  success,
  successMessage = "Changes saved successfully",
  className = "",
  columns = 1,
  asAccordion = false,
  accordionValue,
  accordionDefaultOpen = false,
  accordionPersist = false,
}: {
  children: React.ReactNode;
  title: string;
  description?: string;
  icon?: React.ComponentType<{ className?: string }>;
  onSubmit?: (_e: React.FormEvent) => void;
  loading?: boolean;
  submitLabel?: string;
  submitVariant?: "default" | "destructive" | "outline" | "secondary" | "ghost" | "link";
  disabled?: boolean;
  error?: string;
  fieldErrors?: Record<string, string[]>;
  success?: boolean;
  successMessage?: string;
  className?: string;
  columns?: number;
  asAccordion?: boolean;
  accordionValue?: string;
  accordionDefaultOpen?: boolean;
  accordionPersist?: boolean;
}) {
  const hasError = !!error || Object.keys(fieldErrors).length > 0;
  const showSuccess = success && successMessage;

  const firstFieldError = Object.values(fieldErrors).find((v) => Array.isArray(v) && v.length > 0);
  const bannerError =
    error || (hasError ? firstFieldError?.[0] || "Please fix the errors below." : null);

  const formContent = (
    <div className={`grid gap-4 ${columns === 2 ? "grid-cols-1 md:grid-cols-2" : ""}`}>
      {children}
    </div>
  );

  const errorDisplay =
    hasError && bannerError ? (
      <div className="rounded-md bg-destructive/10 p-3 text-sm text-destructive">{bannerError}</div>
    ) : null;

  const successDisplay =
    showSuccess && !hasError ? (
      <div className="flex items-center gap-2 text-sm text-green-600 dark:text-green-500">
        <svg className="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
        </svg>
        <span>{successMessage}</span>
      </div>
    ) : null;

  const buttonArea = onSubmit ? (
    <div className="mt-4 pt-4 border-t border-border flex justify-end">
      <Button type="submit" variant={submitVariant} disabled={disabled || loading}>
        {loading ? "Saving..." : submitLabel}
      </Button>
    </div>
  ) : null;

  if (asAccordion) {
    const derivedValue = accordionValue || title.toLowerCase().replaceAll(/[^a-z0-9]+/g, "-");

    const content = (
      <div className="space-y-4">
        {errorDisplay}
        {successDisplay}
        {onSubmit ? (
          <form onSubmit={onSubmit}>
            {formContent}
            {buttonArea}
          </form>
        ) : (
          formContent
        )}
      </div>
    );

    return (
      <div className={className}>
        <SettingsAccordion
          items={[
            {
              value: derivedValue,
              icon: Icon,
              title,
              description,
              content,
              defaultOpen: accordionDefaultOpen,
              persist: accordionPersist,
            },
          ]}
        />
      </div>
    );
  }

  return (
    <Card className={cn("overflow-hidden", className)}>
      <CardHeader className="border-b border-border">
        <CardTitle className="flex items-center gap-2 text-foreground">
          {Icon && <Icon className="h-5 w-5 text-foreground" />}
          {title}
        </CardTitle>
        {description && <CardDescription>{description}</CardDescription>}
      </CardHeader>

      {onSubmit ? (
        <form onSubmit={onSubmit}>
          <CardContent>
            {errorDisplay}
            {successDisplay}
            {formContent}
            {buttonArea}
          </CardContent>
        </form>
      ) : (
        <CardContent>{formContent}</CardContent>
      )}
    </Card>
  );
}

function SectionField({
  children,
  label,
  htmlFor,
  error,
  required = false,
  className = "",
  description,
}: {
  children: React.ReactNode;
  label?: string;
  htmlFor?: string;
  error?: string;
  required?: boolean;
  className?: string;
  description?: string;
}) {
  const childrenWithProps = React.isValidElement(children)
    ? React.cloneElement(children as React.ReactElement<React.HTMLAttributes<HTMLElement>>, {
        "aria-invalid": !!error,
        ...(htmlFor && { id: htmlFor }),
      })
    : children;

  return (
    <Field className={className}>
      {label && (
        <FieldLabel htmlFor={htmlFor}>
          {label}
          {required && <span className="text-destructive ml-1">*</span>}
        </FieldLabel>
      )}
      {childrenWithProps}
      {description && <FieldDescription>{description}</FieldDescription>}
      {error && <FieldError>{error}</FieldError>}
    </Field>
  );
}

export { Section, SectionField };
