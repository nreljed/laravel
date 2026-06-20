import { Head, router, usePage } from "@inertiajs/react";
import { AlertTriangle, Send } from "lucide-react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Card, CardContent } from "@/Components/ui/card";
import { Button } from "@/Components/ui/button";
import { EmailAccordionSection } from "@/Components/account/sections/EmailAccordionSection";
import { PasswordSection } from "@/Components/account/sections/PasswordSection";
import { NotificationPrefsSection } from "@/Components/account/sections/NotificationPrefsSection";
import { DangerZoneSection } from "@/Components/account/sections/DangerZoneSection";

interface AccountUser {
  id: number;
  name: string;
  display_name: string | null;
  email: string;
  email_verified_at: string | null;
  avatar: string | null;
  user_login: string;
}

interface NotificationTypeConfig {
  label: string;
  description: string;
  default_channels: string[];
  default_enabled: boolean;
  group: string;
  email_template?: string;
}

export default function AccountEdit() {
  const props = usePage().props as unknown as {
    user: AccountUser;
    mustVerifyEmail: boolean;
    status?: string;
    notificationTypes: Record<string, Record<string, NotificationTypeConfig>>;
    preferences: Record<string, { in_app?: boolean; email?: boolean }>;
  };
  const { user: initialUser, mustVerifyEmail, status, notificationTypes, preferences } = props;

  const emailUnverified = mustVerifyEmail && !initialUser.email_verified_at;

  return (
    <AuthenticatedLayout header="Account">
      <Head title="Account" />

      <div className="max-w-2xl space-y-6">
        {emailUnverified && (
          <Card className="border-amber-500/50 bg-amber-50 dark:bg-amber-950/20">
            <CardContent className="flex items-center gap-4 p-4">
              <AlertTriangle className="h-5 w-5 shrink-0 text-amber-600" />
              <div className="flex-1 text-sm">
                <p className="font-medium text-amber-800 dark:text-amber-300">Email not verified</p>
                <p className="text-amber-700 dark:text-amber-400">
                  Please verify your email address to access all features.
                </p>
              </div>
              <Button
                size="sm"
                variant="outline"
                onClick={() => router.post(route("verification.send"), {}, {
                  preserveScroll: true,
                })}
              >
                <Send className="mr-1 h-4 w-4" />
                Resend
              </Button>
            </CardContent>
          </Card>
        )}

        <EmailAccordionSection user={initialUser as unknown as Record<string, unknown>} initialStatus={status} />

        <PasswordSection asAccordion />

        {notificationTypes && Object.keys(notificationTypes).length > 0 && (
          <NotificationPrefsSection
            notificationTypes={notificationTypes}
            preferences={preferences}
          />
        )}

        <DangerZoneSection asAccordion />
      </div>
    </AuthenticatedLayout>
  );
}
