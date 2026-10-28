import { useState, useEffect } from "react";
import { router } from "@inertiajs/react";
import { toast } from "../../ui/toast";
import { Input } from "../../ui/input";
import { Button } from "../../ui/button";
import { Label } from "../../ui/label";
import { Badge } from "../../ui/badge";
import { Field, FieldError, FieldLabel } from "../../ui/field";
import { SettingsAccordionItem } from "../../shared/settings-accordion";
import { cn } from "../../../lib/utils";
import { Mail, CheckCircle2, AlertTriangle } from "lucide-react";
import { CurrentPasswordInput } from "../../shared/current-password-input";

interface EmailSectionProps {
  user?: Record<string, unknown>;
  email?: string;
  mustVerifyEmail?: boolean;
  initialStatus?: string;
  asAccordion?: boolean;
  accordionDefaultOpen?: boolean;
}

export function EmailSection({
  user: userProp,
  email: emailProp,
  mustVerifyEmail = false,
  initialStatus,
  asAccordion: _asAccordion,
  accordionDefaultOpen,
}: EmailSectionProps) {
  const [email, setEmail] = useState("");
  const [currentPassword, setCurrentPassword] = useState("");
  const [loading, setLoading] = useState(false);
  const [verificationLoading, setVerificationLoading] = useState(false);
  const [success, setSuccess] = useState(initialStatus || "");
  const [errors, setErrors] = useState<Record<string, string>>({});

  const user = userProp || {};
  const currentEmail = emailProp || (user.email as string) || "";
  const emailVerified = !!user.email_verified_at;

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect
    if (initialStatus) setSuccess(initialStatus);
  }, [initialStatus]);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (loading) return;

    const newErrors: Record<string, string> = {};

    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      newErrors.email = "Please enter a valid email address.";
    }
    if (!currentPassword) {
      newErrors.current_password = "Current password is required.";
    }

    if (Object.keys(newErrors).length > 0) {
      setErrors(newErrors);
      return;
    }

    setLoading(true);
    setErrors({});
    setSuccess("");

    router.post(
      route("dashboard.account.email"),
      { email, current_password: currentPassword },
      {
        preserveScroll: true,
        onSuccess: () => {
          toast.add({ title: "Email updated successfully!", type: "success" });
          setSuccess("Email updated successfully!");
          setEmail("");
          setCurrentPassword("");
          setLoading(false);
        },
        onError: (errs) => {
          const e = errs as Record<string, string>;
          setErrors(e);
          const msg = e.email || e.current_password || "Failed to update email";
          toast.add({ title: msg, type: "error" });
          setLoading(false);
        },
      },
    );
  };

  const handleResendVerification = () => {
    if (verificationLoading) return;
    setVerificationLoading(true);

    router.post(
      route("verification.send"),
      {},
      {
        preserveScroll: true,
        onSuccess: () => {
          toast.add({ title: "Verification link sent!", type: "success" });
          setSuccess("verification-link-sent");
          setVerificationLoading(false);
        },
        onError: () => {
          toast.add({
            title: "Failed to send verification link. Please try again.",
            type: "error",
          });
          setVerificationLoading(false);
        },
      },
    );
  };

  return (
    <SettingsAccordionItem
      value="email"
      icon={Mail}
      title="Email Address"
      description="Used for notifications and login"
      defaultOpen={accordionDefaultOpen}
    >
      {mustVerifyEmail && !emailVerified && (
        <div className="mb-4 flex flex-col gap-3 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300">
          <div className="flex items-center gap-2 font-medium">
            <AlertTriangle className="h-4 w-4" />
            Your email address is not verified
          </div>
          <div className="flex flex-wrap items-center justify-between gap-2">
            <p>Some account features require a verified email address.</p>
            <Button
              type="button"
              size="sm"
              variant="outline"
              onClick={handleResendVerification}
              disabled={verificationLoading}
            >
              {verificationLoading ? "Sending..." : "Resend verification link"}
            </Button>
          </div>
        </div>
      )}
      {success && (
        <div className="mb-4 flex items-center gap-2 text-sm font-medium text-green-600 dark:text-green-500">
          <CheckCircle2 className="h-4 w-4" />
          {success === "verification-link-sent"
            ? "A new verification link has been sent to your email address."
            : success}
        </div>
      )}
      <Field>
        <FieldLabel htmlFor="accordion_current_email">
          Current Email{" "}
          <Badge
            className={cn(
              "ml-auto",
              emailVerified
                ? "bg-green-50 text-green-700 dark:bg-green-950 dark:text-green-300"
                : "bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300",
            )}
          >
            {emailVerified ? "Verified" : "Unverified"}
          </Badge>
        </FieldLabel>
        <Input
          id="accordion_current_email"
          type="email"
          value={currentEmail}
          disabled
          className="bg-muted cursor-not-allowed"
        />
      </Field>
      <form onSubmit={handleSubmit} className="space-y-4">
        <Field data-invalid={!!errors.email}>
          <Label htmlFor="new_email">New Email Address</Label>
          <Input
            id="new_email"
            type="email"
            value={email}
            onChange={(e) => {
              setEmail(e.target.value);
              if (errors.email)
                setErrors((prev) => {
                  const next = { ...prev };
                  delete next.email;
                  return next;
                });
            }}
            disabled={loading}
            aria-invalid={!!errors.email}
            aria-describedby={errors.email ? "email-error" : undefined}
          />
          {errors.email && <FieldError id="email-error">{errors.email}</FieldError>}
        </Field>
        <CurrentPasswordInput
          value={currentPassword}
          onChange={(e) => {
            setCurrentPassword(e.target.value);
            if (errors.current_password)
              setErrors((prev) => {
                const next = { ...prev };
                delete next.current_password;
                return next;
              });
          }}
          error={errors.current_password}
          disabled={loading}
          showForgotPasswordLink={false}
          showHelpText={false}
        />
        <div className="flex justify-end pt-2">
          <Button type="submit" disabled={loading || !currentPassword || !email}>
            {loading ? "Updating..." : "Update Email"}
          </Button>
        </div>
      </form>
    </SettingsAccordionItem>
  );
}

export default EmailSection;
