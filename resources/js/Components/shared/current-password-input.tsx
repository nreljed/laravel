import * as React from "react";
import { Input } from "@/Components/ui/input";
import { Field, FieldLabel, FieldError, FieldDescription } from "@/Components/ui/field";
import { Eye, EyeOff } from "lucide-react";
import { Link } from "@inertiajs/react";

interface CurrentPasswordInputProps {
  value?: string;
  onChange?: React.ChangeEventHandler<HTMLInputElement>;
  onBlur?: React.FocusEventHandler<HTMLInputElement>;
  error?: string | string[] | null;
  disabled?: boolean;
  id?: string;
  label?: string;
  placeholder?: string;
  helpText?: string;
  showHelpText?: boolean;
  showForgotPasswordLink?: boolean;
  forgotPasswordRedirectTo?: string | null;
}

export function CurrentPasswordInput({
  value,
  onChange,
  onBlur,
  error = null,
  disabled = false,
  id = "current_password",
  label = "Current Password",
  placeholder = "Enter your current password",
  helpText = "Your current password is required for security verification.",
  showHelpText = true,
  showForgotPasswordLink = true,
  forgotPasswordRedirectTo,
}: CurrentPasswordInputProps) {
  const forgotPasswordUrl = React.useMemo(() => {
    let url = "/forgot-password";
    const redirectTo =
      forgotPasswordRedirectTo ??
      (() => {
        try {
          return location.href;
        } catch {
          return null;
        }
      })();
    if (redirectTo) {
      const separator = url.includes("?") ? "&" : "?";
      url += `${separator}redirect_to=${encodeURIComponent(redirectTo)}`;
    }
    return url;
  }, [forgotPasswordRedirectTo]);

  const [showPassword, setShowPassword] = React.useState(false);

  const errorArray = error ? (Array.isArray(error) ? error : [error]) : [];
  const hasError = errorArray.length > 0;

  return (
    <Field data-invalid={hasError}>
      <div className="flex justify-between items-center">
        <FieldLabel htmlFor={id}>
          {label} <span className="text-destructive">*</span>
        </FieldLabel>
        {showForgotPasswordLink && (
          <Link href={forgotPasswordUrl} className="text-sm text-primary hover:underline">
            Forgot your password?
          </Link>
        )}
      </div>
      <div className="relative">
        <Input
          id={id}
          type={showPassword ? "text" : "password"}
          value={value}
          onChange={onChange}
          onBlur={onBlur}
          placeholder={placeholder}
          disabled={disabled}
          aria-invalid={hasError ? "true" : undefined}
          aria-describedby={hasError ? `${id}-error` : undefined}
          className={
            hasError
              ? "border-red-500 focus:border-red-500 dark:border-red-400 dark:focus:border-red-400 pr-10"
              : "pr-10"
          }
          autoComplete="off"
        />
        <button
          type="button"
          onClick={() => setShowPassword(!showPassword)}
          className="absolute right-2 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
          aria-label={showPassword ? "Hide password" : "Show password"}
        >
          {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
        </button>
      </div>
      {hasError && (
        <FieldError id={`${id}-error`} role="alert">
          {errorArray.map((err, idx) => (
            <span key={idx}>{err}</span>
          ))}
        </FieldError>
      )}
      {showHelpText && !hasError && <FieldDescription>{helpText}</FieldDescription>}
    </Field>
  );
}

export default CurrentPasswordInput;
