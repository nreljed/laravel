import { useState, useEffect } from "react";
import { router } from "@inertiajs/react";
import { toast } from "sonner";
import { Input } from "@/Components/ui/input";
import { Button } from "@/Components/ui/button";
import { Label } from "@/Components/ui/label";
import { Badge } from "@/Components/ui/badge";
import { SettingsAccordionItem } from "@/Components/ui/settings-accordion";
import { Mail, CheckCircle2 } from "lucide-react";
import { CurrentPasswordInput } from "@/Components/ui/current-password-input";

interface EmailAccordionSectionProps {
  user: Record<string, unknown>;
  initialStatus?: string;
}

export function EmailAccordionSection({ user, initialStatus }: EmailAccordionSectionProps) {
  const [email, setEmail] = useState("");
  const [currentPassword, setCurrentPassword] = useState("");
  const [loading, setLoading] = useState(false);
  const [success, setSuccess] = useState(initialStatus || "");

  const currentEmail = (user.email as string) || "";
  const emailVerified = !!user.email_verified_at;

  useEffect(() => {
    if (initialStatus) setSuccess(initialStatus);
  }, [initialStatus]);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (loading) return;
    setLoading(true);
    setSuccess("");

    router.post(route("account.update-email"), { email, current_password: currentPassword }, {
      preserveScroll: true,
      onSuccess: () => {
        toast.success("Email updated successfully!");
        setSuccess("Email updated successfully!");
        setEmail("");
        setCurrentPassword("");
        setLoading(false);
      },
      onError: (errs) => {
        const msg = (errs as Record<string, string>)?.email || "Failed to update email";
        toast.error(msg);
        setLoading(false);
      },
    });
  };

  return (
    <SettingsAccordionItem
      value="email"
      icon={Mail}
      title="Email Address"
      description="Used for notifications and login"
      defaultOpen
    >
      {emailVerified && success && (
        <div className="mb-4 flex items-center gap-2 text-sm font-medium text-green-600 dark:text-green-500">
          <CheckCircle2 className="h-4 w-4" />
          {success}
        </div>
      )}
      <div className="rounded-lg bg-muted/50 p-3 text-sm mb-4">
        <span className="text-muted-foreground">Current: </span>
        <span className="font-medium">{currentEmail}</span>
        <Badge className="ml-2 bg-green-50 text-green-700 dark:bg-green-950 dark:text-green-300">
          {emailVerified ? "Verified" : "Unverified"}
        </Badge>
      </div>
      <form onSubmit={handleSubmit} className="space-y-4">
        <div className="grid gap-2">
          <Label htmlFor="new_email">New Email Address</Label>
          <Input
            id="new_email"
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            disabled={loading}
          />
        </div>
        <CurrentPasswordInput
          value={currentPassword}
          onChange={(e) => setCurrentPassword(e.target.value)}
          disabled={loading}
          showForgotPasswordLink={false}
          showHelpText={false}
        />
        <div className="flex justify-end pt-2">
          <Button type="submit" disabled={loading || !currentPassword}>
            {loading ? "Updating..." : "Update Email"}
          </Button>
        </div>
      </form>
    </SettingsAccordionItem>
  );
}

export default EmailAccordionSection;
