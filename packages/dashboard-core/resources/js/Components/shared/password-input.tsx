import * as React from "react";
import { cn } from "@/lib/utils";
import { Link } from "@inertiajs/react";
import { Eye, EyeOff, Info, Check } from "lucide-react";
import {
  InputGroup,
  InputGroupAddon,
  InputGroupButton,
  InputGroupInput,
} from "@/Components/ui/input-group";
import { Tooltip, TooltipContent, TooltipTrigger } from "@/Components/ui/tooltip";
import { Field, FieldLabel, FieldError } from "@/Components/ui/field";

interface ZxcvbnFeedback {
  warning?: string;
  suggestions?: string[];
}

interface ZxcvbnResult {
  score: number;
  feedback: ZxcvbnFeedback;
  guesses?: number;
  guessesLog10?: number;
  calcTime?: string;
  password?: string;
}

type ZxcvbnFn = (_password: string) => ZxcvbnResult;

let zxcvbnInstance: ZxcvbnFn | null = null;
let zxcvbnLoading: Promise<ZxcvbnFn> | null = null;

async function getZxcvbn(): Promise<ZxcvbnFn> {
  if (zxcvbnInstance) return zxcvbnInstance;
  if (zxcvbnLoading) return zxcvbnLoading;

  // eslint-disable-next-line unicorn/no-top-level-assignment-in-function
  zxcvbnLoading = (async () => {
    const [{ ZxcvbnFactory }, zxcvbnCommon, zxcvbnEn] = await Promise.all([
      import("@zxcvbn-ts/core"),
      import("@zxcvbn-ts/language-common"),
      import("@zxcvbn-ts/language-en"),
    ]);

    const zxcvbn = new ZxcvbnFactory({
      translations: zxcvbnEn.translations,
      graphs: zxcvbnCommon.adjacencyGraphs,
      maxLength: 50,
      dictionary: {
        ...zxcvbnCommon.dictionary,
        ...zxcvbnEn.dictionary,
      },
    });

    // eslint-disable-next-line unicorn/no-top-level-assignment-in-function
    zxcvbnInstance = zxcvbn.check.bind(zxcvbn) as unknown as ZxcvbnFn;
    // eslint-disable-next-line unicorn/no-top-level-assignment-in-function
    zxcvbnLoading = null;
    return zxcvbnInstance;
  })();

  return zxcvbnLoading;
}

export function getPasswordStrengthColor(score: number): string {
  if (score <= 1) return "bg-destructive";
  if (score === 2) return "bg-amber-500 dark:bg-amber-400";
  if (score === 3) return "bg-lime-500 dark:bg-lime-400";
  return "bg-green-500 dark:bg-green-400";
}

export function getPasswordStrengthTextColor(score: number): string {
  if (score <= 1) return "text-destructive dark:text-red-400";
  if (score === 2) return "text-amber-600 dark:text-amber-400";
  if (score === 3) return "text-lime-600 dark:text-lime-400";
  return "text-green-600 dark:text-green-400";
}

export function getPasswordStrengthLabel(
  score: number,
  emptyLabel: string = "Password strength",
): string {
  if (score < 0 || score > 4) return emptyLabel;
  const labels = ["Very weak", "Weak", "Fair", "Strong", "Very strong"];
  return labels[score] ?? "";
}

export async function calculatePasswordStrength(password: string): Promise<ZxcvbnResult> {
  if (!password || password.length === 0) {
    return { score: 0, feedback: { warning: undefined } };
  }
  const zxcvbn = await getZxcvbn();
  return zxcvbn(password);
}

interface PasswordInputProps {
  variant?: "login" | "register";
  layout?: "vertical" | "inline";
  newPasswordLabel?: string;
  confirmLabel?: string;
  minLength?: number;
  showStrengthBar?: boolean;
  showInfoIcon?: boolean;
  requireUppercase?: boolean;
  requireLowercase?: boolean;
  requireNumber?: boolean;
  requireSpecial?: boolean;
  disableSameAsCurrent?: boolean;
  newPasswordValue?: string;
  onNewPasswordChange?: React.ChangeEventHandler<HTMLInputElement>;
  newPasswordError?: string;
  confirmValue?: string;
  onConfirmChange?: React.ChangeEventHandler<HTMLInputElement>;
  confirmError?: string;
  currentPasswordValue?: string;
  required?: boolean;
  autoFocus?: boolean;
  placeholder?: string;
  showForgotPasswordLink?: boolean;
  forgotPasswordUrl?: string;
}

export function PasswordInput({
  variant = "login",
  layout = "vertical",
  newPasswordLabel = "New Password",
  confirmLabel = "Confirm Password",
  minLength = 8,
  showStrengthBar = true,
  showInfoIcon = false,
  requireUppercase = false,
  requireLowercase = false,
  requireNumber = false,
  requireSpecial = false,
  disableSameAsCurrent = false,
  newPasswordValue,
  onNewPasswordChange,
  newPasswordError,
  confirmValue,
  onConfirmChange,
  confirmError,
  currentPasswordValue,
  required = false,
  autoFocus,
  placeholder,
  showForgotPasswordLink = true,
  forgotPasswordUrl,
}: PasswordInputProps) {
  const resolvedForgotPasswordUrl =
    forgotPasswordUrl ??
    (() => {
      try {
        return `/forgot-password?redirect_to=${encodeURIComponent(location.href)}`;
      } catch {
        return "/forgot-password";
      }
    })();

  const [showNewPassword, setShowNewPassword] = React.useState(false);
  const [showConfirm, setShowConfirm] = React.useState(false);

  const newPassword = newPasswordValue || "";
  const confirmPassword = confirmValue || "";

  const [strengthResult, setStrengthResult] = React.useState<ZxcvbnResult>({
    score: 0,
    feedback: {},
  });

  React.useEffect(() => {
    let cancelled = false;
    (async () => {
      const result = await calculatePasswordStrength(newPassword);
      if (!cancelled) setStrengthResult(result);
    })();
    return () => {
      cancelled = true;
    };
  }, [newPassword]);

  const currentPassword = currentPasswordValue || "";
  const meetsMinLength = newPassword.length >= minLength;
  const meetsUppercase = requireUppercase ? /[A-Z]/.test(newPassword) : true;
  const meetsLowercase = requireLowercase ? /[a-z]/.test(newPassword) : true;
  const meetsNumber = requireNumber ? /\d/.test(newPassword) : true;
  const meetsSpecial = requireSpecial ? /[@$!%*?&]/.test(newPassword) : true;

  const isSameAsCurrent =
    disableSameAsCurrent &&
    currentPassword.length > 0 &&
    newPassword.length > 0 &&
    newPassword === currentPassword;

  const passwordsMatch = confirmPassword.length > 0 && newPassword === confirmPassword;
  const passwordsDoNotMatch = confirmPassword.length > 0 && newPassword !== confirmPassword;

  const passwordIsValid = newPassword.length >= minLength;

  const labelText = getPasswordStrengthLabel(strengthResult.score);
  const labelColor = getPasswordStrengthTextColor(strengthResult.score);

  const validVariant = variant === "login" || variant === "register" ? variant : "login";

  if (validVariant === "login") {
    return (
      <Field data-invalid={!!newPasswordError}>
        <div className="flex justify-between items-center">
          <FieldLabel htmlFor="password">
            {newPasswordLabel} {required && <span className="text-destructive">*</span>}
          </FieldLabel>
          {showForgotPasswordLink && (
            <Link href={resolvedForgotPasswordUrl} className="text-sm text-primary hover:underline">
              Forgot your password?
            </Link>
          )}
        </div>
        <InputGroup>
          <InputGroupInput
            id="password"
            type={showNewPassword ? "text" : "password"}
            value={newPasswordValue}
            onChange={onNewPasswordChange}
            placeholder={placeholder || "Enter password"}
            required={required}
            autoFocus={autoFocus}
            autoComplete="current-password"
            className={cn(
              "pe-9",
              newPasswordError && "border-destructive focus:border-destructive",
            )}
            aria-invalid={newPasswordError ? "true" : undefined}
          />
          <InputGroupAddon align="inline-end" className="right-1">
            <Tooltip>
              <TooltipTrigger
                render={
                  <InputGroupButton
                    size="icon-xs"
                    onClick={() => setShowNewPassword((p) => !p)}
                    type="button"
                  >
                    {showNewPassword ? (
                      <EyeOff className="size-4.5" />
                    ) : (
                      <Eye className="size-4.5" />
                    )}
                    <span className="sr-only">
                      {showNewPassword ? "Hide password" : "Show password"}
                    </span>
                  </InputGroupButton>
                }
              />
              <TooltipContent side="bottom" sideOffset={4}>
                {showNewPassword ? "Hide password" : "Show password"}
              </TooltipContent>
            </Tooltip>
          </InputGroupAddon>
        </InputGroup>
        {newPasswordError && <FieldError>{newPasswordError}</FieldError>}
      </Field>
    );
  }

  const isInline = layout === "inline";

  return (
    <div className={cn(isInline ? "flex flex-col md:flex-row gap-4 items-start" : "space-y-4")}>
      <Field data-invalid={isSameAsCurrent} className={cn(isInline && "w-full md:flex-1")}>
        <div className="flex justify-between items-center">
          <FieldLabel htmlFor="password">
            {newPasswordLabel} {required && <span className="text-destructive">*</span>}
          </FieldLabel>
          {newPassword.length > 0 && showStrengthBar && (
            <span className={cn("text-sm font-normal", labelColor)}>{labelText}</span>
          )}
        </div>
        <InputGroup>
          <InputGroupInput
            id="password"
            type={showNewPassword ? "text" : "password"}
            value={newPasswordValue}
            onChange={onNewPasswordChange}
            placeholder={placeholder || "Enter password"}
            required={required}
            autoFocus={autoFocus}
            autoComplete="new-password"
            aria-invalid={isSameAsCurrent ? "true" : undefined}
            aria-describedby={isSameAsCurrent ? "password-same-error" : undefined}
            className={cn(
              "pe-16",
              isSameAsCurrent && "border-destructive focus:border-destructive",
            )}
          />
          <InputGroupAddon align="inline-end" className="right-1 gap-1">
            {passwordIsValid && (
              <Tooltip>
                <TooltipTrigger
                  render={
                    <div className="flex items-center text-green-500 dark:text-green-400 cursor-default">
                      <Check className="h-4 w-4" />
                    </div>
                  }
                />
                <TooltipContent side="bottom" sideOffset={4}>
                  Password meets requirements
                </TooltipContent>
              </Tooltip>
            )}
            {showInfoIcon && !passwordIsValid && (
              <Tooltip>
                <TooltipTrigger
                  render={
                    <InputGroupButton
                      size="icon-xs"
                      type="button"
                      className="hover:bg-transparent focus-visible:ring-0"
                    >
                      <Info className="h-4 w-4 text-muted-foreground" />
                    </InputGroupButton>
                  }
                />
                <TooltipContent side="bottom" sideOffset={4} className="text-xs">
                  <div className="space-y-1">
                    <p className="font-medium mb-1">Password recommendations:</p>
                    <div
                      className={cn(
                        meetsMinLength
                          ? "text-green-500 dark:text-green-400"
                          : "text-muted-foreground",
                      )}
                    >
                      {meetsMinLength ? "✓" : "○"} At least {minLength} chars
                    </div>
                    <div
                      className={cn(
                        meetsUppercase
                          ? "text-green-500 dark:text-green-400"
                          : "text-muted-foreground",
                      )}
                    >
                      {meetsUppercase ? "✓" : "○"} Uppercase (recommended)
                    </div>
                    <div
                      className={cn(
                        meetsLowercase
                          ? "text-green-500 dark:text-green-400"
                          : "text-muted-foreground",
                      )}
                    >
                      {meetsLowercase ? "✓" : "○"} Lowercase (recommended)
                    </div>
                    <div
                      className={cn(
                        meetsNumber
                          ? "text-green-500 dark:text-green-400"
                          : "text-muted-foreground",
                      )}
                    >
                      {meetsNumber ? "✓" : "○"} Number (recommended)
                    </div>
                    <div
                      className={cn(
                        meetsSpecial
                          ? "text-green-500 dark:text-green-400"
                          : "text-muted-foreground",
                      )}
                    >
                      {meetsSpecial ? "✓" : "○"} Special (recommended)
                    </div>
                    {disableSameAsCurrent && currentPassword.length > 0 && (
                      <div
                        className={cn(
                          !isSameAsCurrent
                            ? "text-green-500 dark:text-green-400"
                            : "text-muted-foreground",
                        )}
                      >
                        {!isSameAsCurrent ? "✓" : "○"} Different from current
                      </div>
                    )}
                  </div>
                </TooltipContent>
              </Tooltip>
            )}
            <Tooltip>
              <TooltipTrigger
                render={
                  <InputGroupButton
                    size="icon-xs"
                    onClick={() => setShowNewPassword((p) => !p)}
                    type="button"
                  >
                    {showNewPassword ? (
                      <EyeOff className="size-4.5" />
                    ) : (
                      <Eye className="size-4.5" />
                    )}
                    <span className="sr-only">{showNewPassword ? "Hide" : "Show"}</span>
                  </InputGroupButton>
                }
              />
              <TooltipContent side="bottom" sideOffset={4}>
                {showNewPassword ? "Hide" : "Show"} password
              </TooltipContent>
            </Tooltip>
          </InputGroupAddon>
        </InputGroup>

        {showStrengthBar && newPassword.length > 0 && (
          <div className="flex gap-1.5">
            {Array.from({ length: 4 }, (_, i) => (
              <div
                key={i}
                className={cn(
                  "h-2 flex-1 rounded-full",
                  strengthResult.score > i
                    ? getPasswordStrengthColor(strengthResult.score)
                    : "bg-secondary",
                )}
              />
            ))}
          </div>
        )}
        {newPasswordError && <FieldError>{newPasswordError}</FieldError>}
        {isSameAsCurrent && currentPassword.length > 0 && newPassword.length > 0 && (
          <FieldError id="password-same-error">
            Your new password must be different from your current password.
          </FieldError>
        )}
      </Field>

      <Field data-invalid={passwordsDoNotMatch} className={cn(isInline && "w-full md:flex-1")}>
        <div className="flex justify-between items-center">
          <FieldLabel htmlFor="password_confirmation">
            {confirmLabel} {required && <span className="text-destructive">*</span>}
          </FieldLabel>
          {passwordsMatch && confirmPassword.length > 0 && (
            <span className="text-sm text-green-600 dark:text-green-400">Match!</span>
          )}
          {passwordsDoNotMatch && <span className="text-sm text-destructive">Don't match</span>}
        </div>
        <InputGroup>
          <InputGroupInput
            id="password_confirmation"
            type={showConfirm ? "text" : "password"}
            value={confirmValue}
            onChange={onConfirmChange}
            placeholder={placeholder ? `Confirm ${placeholder.toLowerCase()}` : "Confirm password"}
            required={required}
            autoComplete="new-password"
            aria-invalid={passwordsDoNotMatch ? "true" : undefined}
            className={cn(
              "pe-9",
              passwordsDoNotMatch && "border-destructive",
              passwordsMatch && "border-green-500 dark:border-green-400",
            )}
          />
          <InputGroupAddon align="inline-end" className="right-1 gap-1">
            {passwordsMatch && (
              <Tooltip>
                <TooltipTrigger
                  render={
                    <div className="flex items-center text-green-500 dark:text-green-400">
                      <Check className="h-4 w-4" />
                    </div>
                  }
                />
                <TooltipContent side="bottom">Match!</TooltipContent>
              </Tooltip>
            )}
            <Tooltip>
              <TooltipTrigger
                render={
                  <InputGroupButton
                    size="icon-xs"
                    onClick={() => setShowConfirm((p) => !p)}
                    type="button"
                  >
                    {showConfirm ? <EyeOff className="size-4.5" /> : <Eye className="size-4.5" />}
                  </InputGroupButton>
                }
              />
              <TooltipContent side="bottom">{showConfirm ? "Hide" : "Show"}</TooltipContent>
            </Tooltip>
          </InputGroupAddon>
        </InputGroup>
        {confirmError && <FieldError>{confirmError}</FieldError>}
      </Field>
    </div>
  );
}
