import { useCallback, useEffect, useState } from "react";
import { Head, Link, router, usePage } from "@inertiajs/react";
import AdminLayout from "@/Layouts/AdminLayout";
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "@/Components/ui/breadcrumb";
import { AdminSettingsNav } from "@/Components/settings/AdminSettingsNav";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/Components/ui/card";
import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
import { Switch } from "@/Components/ui/switch";
import { Label } from "@/Components/ui/label";
import { Field } from "@/Components/ui/field";
import { Alert, AlertDescription, AlertTitle } from "@/Components/ui/alert";
import { CurrentPasswordInput } from "@/Components/shared/current-password-input";
import { toast } from "@/Components/ui/toast";
import { Database, Info, ShieldAlert, ShieldCheck, TerminalSquare } from "lucide-react";

interface DeveloperSettingsProps {
  databaseMaintenanceEnabled: boolean;
  isProduction: boolean;
}

interface ToggleErrors {
  enabled?: string;
  password?: string;
}

export default function DeveloperSettings({
  databaseMaintenanceEnabled,
  isProduction,
}: DeveloperSettingsProps) {
  const flash = usePage().props.flash as Record<string, string> | undefined;

  const [enabled, setEnabled] = useState(databaseMaintenanceEnabled);
  const [password, setPassword] = useState("");
  const [processing, setProcessing] = useState(false);
  const [errors, setErrors] = useState<ToggleErrors>({});

  useEffect(() => {
    if (flash?.status) toast.add({ title: flash.status, type: "success" });
  }, [flash?.status]);

  const save = useCallback(() => {
    if (processing) return;
    setProcessing(true);
    setErrors({});
    router.post(
      route("dashboard.admin.settings.dev.database-maintenance-toggle"),
      {
        enabled,
        password,
      },
      {
        preserveScroll: true,
        onError: (errs) => {
          setErrors(errs as ToggleErrors);
          setProcessing(false);
        },
        onFinish: () => {
          setProcessing(false);
          setPassword("");
        },
      },
    );
  }, [processing, enabled, password]);

  return (
    <AdminLayout title="Settings">
      <Head title="Developer Settings" />
      <div className="space-y-6">
        <Breadcrumb>
          <BreadcrumbList>
            <BreadcrumbItem>
              <Link href={route("dashboard")} className="transition-colors hover:text-foreground">
                Dashboard
              </Link>
            </BreadcrumbItem>
            <BreadcrumbSeparator />
            <BreadcrumbItem>
              <BreadcrumbPage>Developer Settings</BreadcrumbPage>
            </BreadcrumbItem>
          </BreadcrumbList>
        </Breadcrumb>
        <div className="flex flex-col md:flex-row gap-8">
          <AdminSettingsNav />
          <div className="flex-1 min-w-0 space-y-6">
            <div>
              <div className="flex flex-wrap items-center gap-3">
                <h1 className="text-2xl font-semibold tracking-tight">Developer Settings</h1>
                <Badge variant={isProduction ? "destructive" : "default"} className="uppercase">
                  {isProduction ? "production" : "non-production"}
                </Badge>
              </div>
              <p className="text-sm text-muted-foreground mt-1">
                Developer tooling and safety switches for this installation.
              </p>
            </div>

            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <TerminalSquare className="h-4 w-4 text-muted-foreground" />
                  Database Maintenance UI
                </CardTitle>
                <CardDescription>
                  Toggle the destructive database maintenance page (reset / reseed) on or off. It is
                  hidden by default in production and must be explicitly enabled.
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                {isProduction && enabled && (
                  <Alert variant="destructive">
                    <ShieldAlert />
                    <AlertTitle>Danger zone enabled in production</AlertTitle>
                    <AlertDescription>
                      The Database Maintenance page can wipe and reseed the production database.
                      Keep this disabled unless you are actively performing maintenance, and disable
                      it again afterwards.
                    </AlertDescription>
                  </Alert>
                )}

                <div className="flex items-center justify-between rounded-lg border p-4">
                  <div className="space-y-1">
                    <div className="flex items-center gap-2">
                      <Database className="h-4 w-4 text-muted-foreground" />
                      <Label htmlFor="database_maintenance_enabled" className="font-medium">
                        Database Maintenance UI
                      </Label>
                    </div>
                    <p className="text-sm text-muted-foreground">
                      When enabled, Administration → Database Maintenance becomes accessible.
                    </p>
                  </div>
                  <Switch
                    id="database_maintenance_enabled"
                    checked={enabled}
                    onCheckedChange={(c) => {
                      setEnabled(c);
                      if (errors.enabled) setErrors((prev) => ({ ...prev, enabled: undefined }));
                    }}
                  />
                </div>

                <Field data-invalid={!!errors.password}>
                  <CurrentPasswordInput
                    value={password}
                    onChange={(e) => {
                      setPassword(e.target.value);
                      if (errors.password) setErrors((prev) => ({ ...prev, password: undefined }));
                    }}
                    error={errors.password}
                    disabled={processing}
                    showForgotPasswordLink={false}
                    showHelpText={false}
                  />
                </Field>

                <Alert>
                  <Info />
                  <AlertTitle>How it works</AlertTitle>
                  <AlertDescription>
                    The change applies instantly and persists across deploys. Disable the toggle
                    again when maintenance is finished.
                  </AlertDescription>
                </Alert>

                <div className="flex justify-end">
                  <Button onClick={save} disabled={processing || password.length === 0}>
                    {processing ? "Saving..." : "Save changes"}
                  </Button>
                </div>
              </CardContent>
            </Card>

            <Alert>
              <ShieldCheck />
              <AlertTitle>Requires admin credentials</AlertTitle>
              <AlertDescription>
                Changing this switch re-requires your current password, and is recorded in the audit
                log.
              </AlertDescription>
            </Alert>
          </div>
        </div>
      </div>
    </AdminLayout>
  );
}
