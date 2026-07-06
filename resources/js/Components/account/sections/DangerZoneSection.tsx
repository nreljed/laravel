import { useState, useEffect, useRef } from "react";
import { router } from "@inertiajs/react";
import { toast } from "sonner";
import { cn } from "@/lib/utils";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import { Button } from "@/Components/ui/button";
import { SettingsAccordionItem } from "@/Components/settings-accordion";
import { CurrentPasswordInput } from "@/Components/ui/current-password-input";
import {
  AlertDialog,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/Components/ui/alert-dialog";
import { AlertTriangle } from "lucide-react";

interface DangerZoneSectionProps {
  asAccordion?: boolean;
}

export function DangerZoneSection({ asAccordion }: DangerZoneSectionProps) {
  const [deleteConfirm, setDeleteConfirm] = useState("");
  const [currentPassword, setCurrentPassword] = useState("");
  const [showDialog, setShowDialog] = useState(false);
  const [loading, setLoading] = useState(false);
  const [countdown, setCountdown] = useState(0);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const countdownRef = useRef<ReturnType<typeof setInterval> | null>(null);

  useEffect(() => {
    return () => {
      if (countdownRef.current) clearInterval(countdownRef.current);
    };
  }, []);

  const handleDelete = async () => {
    if (deleteConfirm !== "delete my account") {
      toast.error('Please type "delete my account" to confirm');
      return;
    }
    if (!currentPassword) {
      toast.error("Please enter your current password");
      return;
    }

    setLoading(true);

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
            onError: (errors) => {
              setErrors(errors as Record<string, string>);
              reject();
            },
          },
        );
      });
    } catch {
      setErrors({ current_password: "Failed to verify password. Please try again." });
      setLoading(false);
      return;
    }

    setLoading(false);
    setCountdown(10);
    countdownRef.current = setInterval(() => {
      setCountdown((prev) => {
        if (prev <= 1) {
          clearInterval(countdownRef.current!);
          countdownRef.current = null;
          setLoading(true);
          router.post(
            route("dashboard.account.destroy"),
            {
              confirmation: "delete my account",
              current_password: currentPassword,
            },
            {
              preserveScroll: true,
              onSuccess: () => {
                setLoading(false);
                setShowDialog(false);
              },
              onError: (errs) => {
                setErrors(errs);
                setLoading(false);
                setShowDialog(true);
              },
            },
          );
          return 0;
        }
        return prev - 1;
      });
    }, 1000);
  };

  const dialogContent = (
    <AlertDialog
      open={showDialog}
      onOpenChange={(open) => {
        setShowDialog(open);
        if (!open) {
          setDeleteConfirm("");
          setCurrentPassword("");
          setCountdown(0);
          setLoading(false);
          setErrors({});
          if (countdownRef.current) {
            clearInterval(countdownRef.current);
            countdownRef.current = null;
          }
        }
      }}
    >
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle className="flex items-center gap-2 text-destructive">
            <AlertTriangle className="h-5 w-5" />
            Delete Account?
          </AlertDialogTitle>
          <AlertDialogDescription className="space-y-4 text-left">
            <p className="text-destructive font-medium">
              This will permanently delete your account and remove all of your data. This action is
              irreversible.
            </p>
            <p className="text-sm text-muted-foreground">
              All your personal data, content, and account information will be permanently deleted.
              This action cannot be undone.
            </p>
            <div>
              <Label htmlFor="delete_confirm">Type "delete my account" to confirm</Label>
              <Input
                id="delete_confirm"
                type="text"
                value={deleteConfirm}
                onChange={(e) => {
                  setDeleteConfirm(e.target.value);
                  setErrors((prev) => ({ ...prev, confirmation: "" }));
                }}
                placeholder="delete my account"
                className={cn("mt-2", errors.confirmation && "border-destructive")}
                disabled={loading || countdown > 0}
              />
              {errors.confirmation && (
                <p className="mt-1 text-xs text-destructive">{errors.confirmation}</p>
              )}
            </div>
            <CurrentPasswordInput
              value={currentPassword}
              onChange={(e) => {
                setCurrentPassword(e.target.value);
                setErrors((prev) => ({ ...prev, current_password: "" }));
              }}
              error={errors.current_password}
              disabled={loading || countdown > 0}
              showForgotPasswordLink={false}
              showHelpText={false}
            />
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel>Cancel</AlertDialogCancel>
          <Button
            variant="destructive"
            onClick={handleDelete}
            disabled={deleteConfirm !== "delete my account" || !currentPassword || countdown > 0}
          >
            {countdown > 0 ? `Delete (${countdown})` : loading ? "Deleting..." : "Delete Account"}
          </Button>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  );

  if (asAccordion) {
    return (
      <SettingsAccordionItem
        value="danger-zone"
        icon={AlertTriangle}
        title="Delete Account"
        description="Once you delete your account, there is no going back. Please be certain."
        variant="destructive"
      >
        <p className="text-sm text-muted-foreground mb-4">
          This will permanently delete your account and remove all of your data from our servers.
          This action is irreversible.
        </p>
        <div className="flex justify-end">
          <Button variant="destructive" onClick={() => setShowDialog(true)}>
            Delete Account
          </Button>
          {dialogContent}
        </div>
      </SettingsAccordionItem>
    );
  }

  return (
    <>
      <Button variant="destructive" onClick={() => setShowDialog(true)}>
        Delete Account
      </Button>
      {dialogContent}
    </>
  );
}

export default DangerZoneSection;
