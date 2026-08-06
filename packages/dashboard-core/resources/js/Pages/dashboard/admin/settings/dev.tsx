import { useEffect } from "react";
import { Head, Link, useForm, usePage } from "@inertiajs/react";
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
import { ShieldAlert, TerminalSquare } from "lucide-react";

interface DeveloperSettingsProps {
  databaseMaintenanceEnabled: boolean;
  isProduction: boolean;
}

export default function DeveloperSettings({
  databaseMaintenanceEnabled,
  isProduction,
}: DeveloperSettingsProps) {
  const flash = usePage().props.flash as Record<string, string> | undefined;

  const { data, setData, post, processing, errors, reset } = useForm({
    enabled: databaseMaintenanceEnabled,
    password: "",
  });

  useEffect(() => {
    if (flash?.status) toast.add({ title: flash.status, type: "success" });
  }, [flash?.status]);

  const save = () => {
    post(route("dashboard.admin.settings.dev.database-maintenance-toggle"), {
      preserveScroll: true,
      onFinish: () => reset("password"),
    });
  };

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
                  Controls access to the destructive database maintenance page (reset / reseed).
                  Changes apply instantly, persist across deploys, require your current password,
                  and are recorded in the audit log.
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="flex items-center justify-between gap-4">
                  <div className="space-y-1">
                    <Label htmlFor="database_maintenance_enabled" className="font-medium">
                      Database Maintenance UI
                    </Label>
                    <p className="text-sm text-muted-foreground">
                      {data.enabled
                        ? "Enabled — Administration → Database Maintenance is accessible."
                        : "Disabled — the page is hidden."}
                    </p>
                  </div>
                  <Switch
                    id="database_maintenance_enabled"
                    checked={data.enabled}
                    onCheckedChange={(checked) => setData("enabled", checked)}
                  />
                </div>

                {isProduction && data.enabled && (
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

                <Field data-invalid={!!errors.password}>
                  <CurrentPasswordInput
                    value={data.password}
                    onChange={(e) => setData("password", e.target.value)}
                    error={errors.password}
                    disabled={processing}
                    showForgotPasswordLink={false}
                    showHelpText={false}
                  />
                </Field>

                <div className="flex justify-end">
                  <Button onClick={save} disabled={processing || data.password.length === 0}>
                    {processing ? "Saving..." : "Save changes"}
                  </Button>
                </div>
              </CardContent>
            </Card>
          </div>
        </div>
      </div>
    </AdminLayout>
  );
}
