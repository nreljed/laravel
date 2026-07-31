import { useState, useEffect } from "react";
import { router } from "@inertiajs/react";
import { toast } from "@/Components/ui/toast";
import { Input } from "@/Components/ui/input";
import { Button } from "@/Components/ui/button";
import { Label } from "@/Components/ui/label";
import { Badge } from "@/Components/ui/badge";
import { Field, FieldError, FieldLabel } from "@/Components/ui/field";
import { SettingsAccordionItem } from "@/Components/shared/settings-accordion";
import { cn } from "@/lib/utils";
import { Mail, CheckCircle2 } from "lucide-react";
import { CurrentPasswordInput } from "@/Components/shared/current-password-input";

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
  mustVerifyEmail: _mustVerifyEmail,
  initialStatus,
  asAccordion: _asAccordion,
  accordionDefaultOpen,
}: EmailSectionProps) {
  const [email, setEmail] = useState("");
  const [currentPassword, setCurrentPassword] = useState("");
  const [loading, setLoading] = useState(false);
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

  return (
    <SettingsAccordionItem
      value="email"
      icon={Mail}
      title="Email Address"
      description="Used for notifications and login"
      defaultOpen={accordionDefaultOpen}
    >
      {emailVerified && success && (
        <div className="mb-4 flex items-center gap-2 text-sm font-medium text-green-600 dark:text-green-500">
          <CheckCircle2 className="h-4 w-4" />
          {success}
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
