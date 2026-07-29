import { useState, useCallback } from "react";
import { router } from "@inertiajs/react";
import { cn } from "@/lib/utils";
import { Input } from "@/Components/ui/input";
import { Textarea } from "@/Components/ui/textarea";
import { Button } from "@/Components/ui/button";
import { Field, FieldError, FieldLabel } from "@/Components/ui/field";
import { SettingsAccordionItem } from "@/Components/shared/settings-accordion";
import { CurrentPasswordInput } from "@/Components/shared/current-password-input";
import { AlertTriangle, Trash2, X, CheckCircle2 } from "lucide-react";

interface DangerZoneSectionProps {
  asAccordion?: boolean;
  accordionDefaultOpen?: boolean;
}

type Phase = "form" | "verifying" | "confirmed" | "deleting";

export function DangerZoneSection({
  asAccordion,
  accordionDefaultOpen: _accordionDefaultOpen,
}: DangerZoneSectionProps) {
  const [phase, setPhase] = useState<Phase>("form");
  const [confirmation, setConfirmation] = useState("");
  const [currentPassword, setCurrentPassword] = useState("");
  const [feedback, setFeedback] = useState("");
  const [errors, setErrors] = useState<Record<string, string>>({});

  const reset = useCallback(() => {
    setPhase("form");
    setConfirmation("");
    setCurrentPassword("");
    setFeedback("");
    setErrors({});
  }, []);

  const executeDelete = useCallback(() => {
    setPhase("deleting");
    router.post(
      route("dashboard.account.destroy"),
      {
        confirmation: "delete my account",
        current_password: currentPassword,
        feedback: feedback || undefined,
      },
      {
        preserveScroll: true,
        onError: (errs) => {
          setErrors(errs);
          reset();
        },
      },
    );
  }, [currentPassword, feedback, reset]);

  const handleVerify = useCallback(async () => {
    if (confirmation !== "delete my account") {
      setErrors({ confirmation: 'Please type "delete my account" to confirm.' });
      return;
    }
    if (!currentPassword) {
      setErrors({ current_password: "Please enter your current password." });
      return;
    }

    setPhase("verifying");
    setErrors({});

    try {
      await new Promise<void>((resolve, reject) => {
        router.post(
          route("dashboard.account.verify-delete-password"),
          // eslint-disable-next-line @typescript-eslint/no-explicit-any
          { current_password: currentPassword } as any,
          {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => resolve(),
            onError: (errs) => {
              setErrors(errs as Record<string, string>);
              reject();
            },
          },
        );
      });
    } catch {
      setErrors({ current_password: "Password verification failed. Please try again." });
      setPhase("form");
      return;
    }

    setPhase("confirmed");
  }, [confirmation, currentPassword]);

  const isFormPhase = phase === "form";
  const isVerifying = phase === "verifying";
  const isConfirmed = phase === "confirmed";
  const isDeleting = phase === "deleting";
  const isDisabled = isVerifying || isDeleting;

  const content = (
    <div className="space-y-4">
      {isFormPhase && (
        <>
          <div className="rounded-md bg-destructive/10 p-4 text-sm">
            <div className="flex items-start gap-2">
              <AlertTriangle className="h-4 w-4 text-destructive shrink-0 mt-0.5" />
              <div className="space-y-1">
                <p className="font-medium text-destructive">
                  This action is permanent and cannot be undone.
                </p>
                <p className="text-muted-foreground">
                  Once deleted, the following data will be permanently removed:
                </p>
                <ul className="list-disc list-inside text-muted-foreground mt-1 space-y-0.5">
                  <li>Profile information and avatar</li>
                  <li>Blog posts and content</li>
                  <li>API tokens and sessions</li>
                  <li>Notification preferences and history</li>
                </ul>
              </div>
            </div>
          </div>

          <Field data-invalid={!!errors.confirmation}>
            <FieldLabel htmlFor="delete_confirm">
              Type <span className="font-mono text-destructive">delete my account</span> to confirm
            </FieldLabel>
            <Input
              id="delete_confirm"
              type="text"
              value={confirmation}
              onChange={(e) => {
                setConfirmation(e.target.value);
                if (errors.confirmation) setErrors((prev) => ({ ...prev, confirmation: "" }));
              }}
              placeholder="delete my account"
              className={cn("font-mono", errors.confirmation && "border-destructive")}
              disabled={isDisabled}
              autoComplete="off"
            />
            {errors.confirmation && <FieldError>{errors.confirmation}</FieldError>}
          </Field>

          <Field data-invalid={!!errors.current_password}>
            <CurrentPasswordInput
              value={currentPassword}
              onChange={(e) => {
                setCurrentPassword(e.target.value);
                if (errors.current_password)
                  setErrors((prev) => ({ ...prev, current_password: "" }));
              }}
              error={errors.current_password}
              disabled={isDisabled}
              showForgotPasswordLink={false}
              showHelpText={false}
            />
          </Field>

          <Field>
            <FieldLabel htmlFor="delete_feedback">
              What could we have done better?{" "}
              <span className="text-muted-foreground font-normal">(optional)</span>
            </FieldLabel>
            <Textarea
              id="delete_feedback"
              value={feedback}
              onChange={(e) => setFeedback(e.target.value)}
              placeholder="Your feedback helps us improve..."
              disabled={isDisabled}
              rows={3}
              className="resize-none"
            />
          </Field>

          <div className="flex justify-end pt-2 border-t border-border">
            <Button
              variant="destructive"
              onClick={handleVerify}
              disabled={confirmation !== "delete my account" || !currentPassword || isDisabled}
            >
              <Trash2 className="h-4 w-4 mr-2" />
              {isVerifying ? "Verifying..." : "Continue"}
            </Button>
          </div>
        </>
      )}

      {isConfirmed && (
        <div className="space-y-4">
          <div className="rounded-md bg-destructive/10 p-4">
            <div className="flex items-start gap-2">
              <CheckCircle2 className="h-4 w-4 text-destructive shrink-0 mt-0.5" />
              <div>
                <p className="font-medium text-destructive">Ready to delete your account</p>
                <p className="text-sm text-muted-foreground mt-1">
                  Your identity has been verified. Click{" "}
                  <span className="font-medium">Delete now</span> to permanently remove your account
                  and all associated data.
                </p>
              </div>
            </div>
          </div>

          <div className="flex justify-end gap-3">
            <Button variant="outline" onClick={reset}>
              <X className="h-4 w-4 mr-2" />
              Cancel
            </Button>
            <Button variant="destructive" onClick={executeDelete}>
              <Trash2 className="h-4 w-4 mr-2" />
              Delete now
            </Button>
          </div>
        </div>
      )}

      {isDeleting && (
        <div className="text-center py-4">
          <div className="h-6 w-6 border-2 border-destructive border-t-transparent rounded-full animate-spin mx-auto" />
          <p className="text-sm text-muted-foreground mt-2">Deleting your account...</p>
        </div>
      )}
    </div>
  );

  if (asAccordion) {
    return (
      <SettingsAccordionItem
        value="danger-zone"
        icon={AlertTriangle}
        title="Delete Account"
        description="Permanently delete your account and all associated data"
        variant="destructive"
      >
        {content}
      </SettingsAccordionItem>
    );
  }

  return <div className="space-y-4">{content}</div>;
}

export default DangerZoneSection;
