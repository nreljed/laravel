import { useCallback, useEffect, useState } from "react";
import { Head, Link, router, usePage } from "@inertiajs/react";
import AdminLayout from "../../../Layouts/AdminLayout";
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "../../../Components/ui/breadcrumb";
import { Card, CardContent, CardHeader, CardTitle } from "../../../Components/ui/card";

import { Badge } from "../../../Components/ui/badge";
import { Button } from "../../../Components/ui/button";
import { Input } from "../../../Components/ui/input";
import { Checkbox } from "../../../Components/ui/checkbox";
import { Field, FieldError, FieldLabel, FieldDescription } from "../../../Components/ui/field";
import { Alert, AlertDescription, AlertTitle } from "../../../Components/ui/alert";
import { Separator } from "../../../Components/ui/separator";
import { CurrentPasswordInput } from "../../../Components/shared/current-password-input";
import { SettingsAccordion } from "../../../Components/shared/settings-accordion";
import { toast } from "../../../Components/ui/toast";
import {
  AlertTriangle,
  Clock,
  HardDrive,
  Info,
  Layers,
  Lock,
  RefreshCw,
  RotateCcw,
  ShieldAlert,
  Table2,
  UserRound,
} from "lucide-react";

const GROUP_DESCRIPTIONS: Record<string, string> = {
  roles: "Re-creates all core roles and their permission assignments. Safe to re-run.",
  settings:
    "Restores the default site settings (general, registration, security, email, ...). Safe to re-run.",
  demo_users: "Re-creates the demo role users (editor, author, user, admin). Safe to re-run.",
  blog: "Re-creates the demo blog posts, categories and tags. May overwrite existing demo content.",
};

interface ReseedGroup {
  key: string;
  label: string;
  destructive: boolean;
}

interface DatabaseMaintenanceStatus {
  enabled: boolean;
  environment: string;
  is_production: boolean;
  database_driver: string;
  table_count: number;
  locked: boolean;
  lock_path: string;
  media_disk_path: string;
  last_reset_at: string | null;
  last_reset_by: string | null;
  reseed_groups: ReseedGroup[];
}

interface DatabaseMaintenanceProps {
  status: DatabaseMaintenanceStatus;
}

interface FormErrors {
  confirmation?: string;
  password?: string;
  purge_media?: string;
  groups?: string;
}

function ConfirmField({
  id,
  expected,
  value,
  onChange,
  disabled,
  error,
}: {
  id: string;
  expected: string;
  value: string;
  onChange: (_value: string) => void;
  disabled?: boolean;
  error?: string;
}) {
  return (
    <Field data-invalid={!!error}>
      <FieldLabel htmlFor={id}>
        Type <span className="font-mono text-destructive">{expected}</span> to confirm
      </FieldLabel>
      <Input
        id={id}
        type="text"
        value={value}
        onChange={(e) => {
          onChange(e.target.value);
        }}
        placeholder={expected}
        className="font-mono"
        disabled={disabled}
        autoComplete="off"
      />
      {error && <FieldError>{error}</FieldError>}
    </Field>
  );
}

export default function DatabaseMaintenance({ status }: DatabaseMaintenanceProps) {
  const flash = usePage().props.flash as Record<string, string> | undefined;

  const [resetConfirmation, setResetConfirmation] = useState("");
  const [resetPassword, setResetPassword] = useState("");
  const [purgeMedia, setPurgeMedia] = useState(false);
  const [reseedConfirmation, setReseedConfirmation] = useState("");
  const [reseedPassword, setReseedPassword] = useState("");
  const [selectedGroups, setSelectedGroups] = useState<string[]>(() =>
    status.reseed_groups.filter((group) => !group.destructive).map((group) => group.key),
  );
  const [processing, setProcessing] = useState<"reset" | "reseed" | null>(null);
  const [resetErrors, setResetErrors] = useState<FormErrors>({});
  const [reseedErrors, setReseedErrors] = useState<FormErrors>({});

  useEffect(() => {
    if (flash?.success) toast.add({ title: flash.success, type: "success" });
    if (flash?.error) toast.add({ title: flash.error, type: "error" });
  }, [flash?.success, flash?.error]);

  const runReset = useCallback(() => {
    if (processing) return;
    setProcessing("reset");
    setResetErrors({});
    router.post(
      route("dashboard.admin.database.reset"),
      {
        confirmation: resetConfirmation,
        password: resetPassword,
        purge_media: purgeMedia,
      },
      {
        preserveScroll: true,
        onError: (errs) => {
          setResetErrors(errs as FormErrors);
          setProcessing(null);
        },
        onFinish: () => setProcessing(null),
      },
    );
  }, [processing, resetConfirmation, resetPassword, purgeMedia]);

  const runReseed = useCallback(() => {
    if (processing) return;
    setProcessing("reseed");
    setReseedErrors({});
    router.post(
      route("dashboard.admin.database.reseed"),
      {
        confirmation: reseedConfirmation,
        password: reseedPassword,
        groups: selectedGroups,
      },
      {
        preserveScroll: true,
        onError: (errs) => {
          setReseedErrors(errs as FormErrors);
          setProcessing(null);
        },
        onFinish: () => setProcessing(null),
      },
    );
  }, [processing, reseedConfirmation, reseedPassword, selectedGroups]);

  const toggleGroup = useCallback((key: string) => {
    setSelectedGroups((prev) =>
      prev.includes(key) ? prev.filter((group) => group !== key) : [...prev, key],
    );
  }, []);

  const resetReady = resetConfirmation === "RESET" && resetPassword.length > 0;
  const reseedReady =
    reseedConfirmation === "RESEED" && reseedPassword.length > 0 && selectedGroups.length > 0;
  const isProcessing = processing !== null;
  const isResetLocked = status.locked || isProcessing || !status.enabled;

  return (
    <AdminLayout title="Database Maintenance">
      <Head title="Database Maintenance" />
      <div className="space-y-6">
        <Breadcrumb>
          <BreadcrumbList>
            <BreadcrumbItem>
              <Link
                href={route("dashboard.index")}
                className="transition-colors hover:text-foreground"
              >
                Dashboard
              </Link>
            </BreadcrumbItem>
            <BreadcrumbSeparator />
            <BreadcrumbItem>
              <BreadcrumbPage>Database Maintenance</BreadcrumbPage>
            </BreadcrumbItem>
          </BreadcrumbList>
        </Breadcrumb>

        <div>
          <div className="flex flex-wrap items-center gap-3">
            <h1 className="text-2xl font-semibold tracking-tight">Database Maintenance</h1>
            <Badge variant={status.is_production ? "destructive" : "default"} className="uppercase">
              {status.environment}
            </Badge>
            {status.locked && (
              <Badge variant="secondary">
                <Lock className="h-3 w-3 mr-1" />
                Locked
              </Badge>
            )}
          </div>
          <p className="text-sm text-muted-foreground mt-1">
            Destructive operations to reset or reseed the application database.
          </p>
        </div>

        {!status.enabled && (
          <Alert>
            <Info />
            <AlertTitle>Database maintenance UI is disabled</AlertTitle>
            <AlertDescription>
              The reset and reseed tools are currently disabled. Go to{" "}
              <Link
                href={route("dashboard.admin.settings.dev")}
                className="underline underline-offset-2"
              >
                Settings → Developer
              </Link>{" "}
              and enable the <span className="font-mono">Database Maintenance UI</span> toggle to
              access these tools.
            </AlertDescription>
          </Alert>
        )}

        {status.locked && (
          <Alert variant="destructive">
            <Lock />
            <AlertTitle>Operation in progress</AlertTitle>
            <AlertDescription>
              Another database maintenance operation is currently running. Wait a few minutes before
              trying again. Stale lock file: <span className="font-mono">{status.lock_path}</span>
            </AlertDescription>
          </Alert>
        )}

        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="flex items-center gap-2 text-sm font-medium">
                <Layers className="h-4 w-4 text-muted-foreground" />
                Database
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-1 text-sm">
              <div className="flex justify-between">
                <span className="text-muted-foreground">Driver</span>
                <span className="font-mono">{status.database_driver}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-muted-foreground">Tables</span>
                <span>{status.table_count}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-muted-foreground">Environment</span>
                <span className="font-mono">{status.environment}</span>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="flex items-center gap-2 text-sm font-medium">
                <Clock className="h-4 w-4 text-muted-foreground" />
                Last Reset
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-1 text-sm">
              {status.last_reset_at ? (
                <>
                  <div className="flex justify-between">
                    <span className="text-muted-foreground">When</span>
                    <span>{new Date(status.last_reset_at).toLocaleString()}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-muted-foreground">By</span>
                    <span className="inline-flex items-center gap-1 truncate">
                      <UserRound className="h-3 w-3" />
                      {status.last_reset_by ?? "Unknown"}
                    </span>
                  </div>
                </>
              ) : (
                <p className="text-muted-foreground">No reset has been recorded yet.</p>
              )}
            </CardContent>
          </Card>

          <Card className="sm:col-span-2 lg:col-span-1">
            <CardHeader className="pb-2">
              <CardTitle className="flex items-center gap-2 text-sm font-medium">
                <HardDrive className="h-4 w-4 text-muted-foreground" />
                Media Storage
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-1 text-sm">
              <p className="text-muted-foreground">Public uploads directory</p>
              <p className="font-mono text-xs break-all">{status.media_disk_path}</p>
            </CardContent>
          </Card>
        </div>

        <SettingsAccordion
          items={[
            {
              value: "reset",
              variant: "destructive",
              icon: AlertTriangle,
              title: "Reset Database",
              description:
                "Drop every table, re-run migrations, then re-create the admin account plus core roles, permissions and settings. Demo users and blog content are not seeded — use the reseed tool to restore them. All data is permanently lost.",
              content: (
                <>
                  <Alert variant="destructive">
                    <ShieldAlert />
                    <AlertTitle>This action is destructive and cannot be undone</AlertTitle>
                    <AlertDescription>
                      The operation wipes the database and re-creates it from scratch. It relies on
                      your host-level backups — no automated snapshot is taken beforehand. Your
                      current session is invalidated and you will be redirected to the login page.
                    </AlertDescription>
                  </Alert>

                  <div className="space-y-1.5 text-sm">
                    <p className="font-medium">What a reset does</p>
                    <ul className="list-disc pl-5 space-y-1 text-muted-foreground">
                      <li>
                        Drops every table and re-runs all migrations (
                        <span className="font-mono">migrate:fresh</span>).
                      </li>
                      <li>Re-creates the core roles and permissions.</li>
                      <li>Restores the default site settings.</li>
                      <li>
                        Re-creates the admin account from{" "}
                        <span className="font-mono">ADMIN_EMAIL</span> /{" "}
                        <span className="font-mono">ADMIN_PASSWORD</span>.
                      </li>
                      <li>Wipes database references to all content (users, posts, media).</li>
                      <li>
                        Only deletes the actual media files if the purge option below is checked.
                      </li>
                      <li>
                        Demo users and blog content are <strong>not</strong> seeded — restore them
                        with the Reseed accordion.
                      </li>
                    </ul>
                  </div>

                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <ConfirmField
                      id="db_reset_confirm"
                      expected="RESET"
                      value={resetConfirmation}
                      onChange={(v) => {
                        setResetConfirmation(v);
                        if (resetErrors.confirmation)
                          setResetErrors((prev) => ({ ...prev, confirmation: undefined }));
                      }}
                      disabled={isResetLocked}
                      error={resetErrors.confirmation}
                    />
                    <Field data-invalid={!!resetErrors.password}>
                      <CurrentPasswordInput
                        value={resetPassword}
                        onChange={(e) => {
                          setResetPassword(e.target.value);
                          if (resetErrors.password)
                            setResetErrors((prev) => ({ ...prev, password: undefined }));
                        }}
                        error={resetErrors.password}
                        disabled={isResetLocked}
                        showForgotPasswordLink={false}
                        showHelpText={false}
                      />
                    </Field>
                  </div>

                  <Field>
                    <div className="flex items-start gap-2">
                      <Checkbox
                        id="db_reset_purge"
                        checked={purgeMedia}
                        onCheckedChange={(checked) => setPurgeMedia(checked === true)}
                        disabled={isResetLocked}
                      />
                      <div className="space-y-0.5">
                        <FieldLabel htmlFor="db_reset_purge">
                          Delete uploaded media files
                        </FieldLabel>
                        <FieldDescription>
                          Also empty every folder under{" "}
                          <span className="font-mono text-xs">{status.media_disk_path}</span>{" "}
                          (avatars, logos, posts, favicons). Stored database references are wiped by
                          the reset regardless.
                        </FieldDescription>
                      </div>
                    </div>
                  </Field>

                  <Separator />

                  <div className="flex justify-end">
                    <Button
                      variant="destructive"
                      onClick={runReset}
                      disabled={!resetReady || isResetLocked}
                    >
                      <RotateCcw className="h-4 w-4 mr-2" />
                      {processing === "reset" ? "Resetting..." : "Reset database"}
                    </Button>
                  </div>
                </>
              ),
            },
            {
              value: "reseed",
              icon: RefreshCw,
              title: "Reseed Database",
              description:
                "Re-run selected seeders without dropping any tables. Existing data is kept; seeders must be idempotent to avoid duplicate rows.",
              content: (
                <>
                  <Alert>
                    <Info />
                    <AlertTitle>Non-destructive</AlertTitle>
                    <AlertDescription>
                      No tables are dropped and no media is deleted. Only the selected seeder
                      classes are executed again.
                    </AlertDescription>
                  </Alert>

                  <div className="space-y-1.5 text-sm">
                    <p className="font-medium">What each choice does</p>
                    <ul className="list-disc pl-5 space-y-1 text-muted-foreground">
                      {status.reseed_groups.map((group) => (
                        <li key={group.key}>
                          <span className="font-medium text-foreground">{group.label}</span> —{" "}
                          {GROUP_DESCRIPTIONS[group.key] ??
                            (group.destructive
                              ? "Re-running may overwrite existing content."
                              : "Re-running is safe and idempotent.")}
                        </li>
                      ))}
                    </ul>
                  </div>

                  <Field>
                    <FieldLabel>Seeders to re-run</FieldLabel>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                      {status.reseed_groups.map((group) => (
                        <div
                          key={group.key}
                          className="flex items-start gap-2 rounded-md border p-3"
                        >
                          <Checkbox
                            id={`db_reseed_group_${group.key}`}
                            checked={selectedGroups.includes(group.key)}
                            onCheckedChange={() => toggleGroup(group.key)}
                            disabled={isProcessing || !status.enabled}
                          />
                          <div className="space-y-0.5">
                            <FieldLabel htmlFor={`db_reseed_group_${group.key}`}>
                              {group.label}
                              {group.destructive && (
                                <Badge variant="destructive" className="ml-2">
                                  destructive
                                </Badge>
                              )}
                            </FieldLabel>
                            <FieldDescription>
                              {GROUP_DESCRIPTIONS[group.key] ??
                                (group.destructive
                                  ? "Re-running may overwrite existing content."
                                  : "Re-running is safe and idempotent.")}
                            </FieldDescription>
                          </div>
                        </div>
                      ))}
                    </div>
                    {reseedErrors.groups && <FieldError>{reseedErrors.groups}</FieldError>}
                  </Field>

                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <ConfirmField
                      id="db_reseed_confirm"
                      expected="RESEED"
                      value={reseedConfirmation}
                      onChange={(v) => {
                        setReseedConfirmation(v);
                        if (reseedErrors.confirmation)
                          setReseedErrors((prev) => ({ ...prev, confirmation: undefined }));
                      }}
                      disabled={isProcessing}
                      error={reseedErrors.confirmation}
                    />
                    <Field data-invalid={!!reseedErrors.password}>
                      <CurrentPasswordInput
                        value={reseedPassword}
                        onChange={(e) => {
                          setReseedPassword(e.target.value);
                          if (reseedErrors.password)
                            setReseedErrors((prev) => ({ ...prev, password: undefined }));
                        }}
                        error={reseedErrors.password}
                        disabled={isProcessing}
                        showForgotPasswordLink={false}
                        showHelpText={false}
                      />
                    </Field>
                  </div>

                  <Separator />

                  <div className="flex justify-end">
                    <Button
                      variant="outline"
                      onClick={runReseed}
                      disabled={!reseedReady || isProcessing || !status.enabled}
                    >
                      <RefreshCw className="h-4 w-4 mr-2" />
                      {processing === "reseed" ? "Reseeding..." : "Reseed database"}
                    </Button>
                  </div>
                </>
              ),
            },
          ]}
        />

        <Alert>
          <Table2 />
          <AlertTitle>How to enable this page</AlertTitle>
          <AlertDescription>
            The UI is gated by the <span className="font-mono">database_maintenance_enabled</span>{" "}
            setting. It defaults to enabled everywhere except production. Use Settings → Developer →
            Database Maintenance UI (password-protected) to toggle it on, including in production.
            When the setting is absent, the{" "}
            <span className="font-mono">DB_MAINTENANCE_ENABLED</span> environment variable applies
            instead.
          </AlertDescription>
        </Alert>
      </div>
    </AdminLayout>
  );
}
