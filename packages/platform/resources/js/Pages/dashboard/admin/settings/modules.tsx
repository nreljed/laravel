import { Head, Link, useForm } from "@inertiajs/react";
import AdminLayout from "../../../../Layouts/AdminLayout";
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "../../../../Components/ui/breadcrumb";
import { AdminSettingsNav } from "../../../../Components/settings/AdminSettingsNav";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "../../../../Components/ui/card";
import { Badge } from "../../../../Components/ui/badge";
import { Button } from "../../../../Components/ui/button";
import { Switch } from "../../../../Components/ui/switch";
import { Alert, AlertDescription, AlertTitle } from "../../../../Components/ui/alert";
import { Puzzle, AlertCircle, CheckCircle2 } from "lucide-react";

interface ModuleItem {
  slug: string;
  name: string;
  description: string | null;
  version: string;
  package: string | null;
  dependencies: string[];
  enabled: boolean;
  has_settings: boolean;
  settings_route: string | null;
}

interface MissingDependency {
  provider: string;
  dependency: string;
  reason: string;
}

interface ModulesSettingsProps {
  modules: ModuleItem[];
  missingDependencies: MissingDependency[];
}

export default function ModulesSettings({
  modules,
  missingDependencies,
}: ModulesSettingsProps) {
  const { data, setData, put, processing, errors } = useForm<{ modules: Record<string, boolean> }>({
    modules: Object.fromEntries((modules ?? []).map((m) => [m.slug, m.enabled])),
  });

  const save = () => {
    put(route("dashboard.admin.settings.modules.update"), {
      preserveScroll: true,
      preserveState: true,
    });
  };

  return (
    <AdminLayout title="Settings">
      <Head title="Modules" />
      <div className="space-y-6">
        <Breadcrumb>
          <BreadcrumbList>
            <BreadcrumbItem>
              <Link href={route("dashboard.index")} className="transition-colors hover:text-foreground">
                Dashboard
              </Link>
            </BreadcrumbItem>
            <BreadcrumbSeparator />
            <BreadcrumbItem>
              <BreadcrumbPage>Modules</BreadcrumbPage>
            </BreadcrumbItem>
          </BreadcrumbList>
        </Breadcrumb>
        <div className="flex flex-col md:flex-row gap-8">
          <AdminSettingsNav />
          <div className="flex-1 min-w-0 space-y-6">
            <div>
              <div className="flex flex-wrap items-center gap-3">
                <h1 className="text-2xl font-semibold tracking-tight">Modules</h1>
                <Badge variant="default" className="uppercase">
                  {modules.length} installed
                </Badge>
              </div>
              <p className="text-sm text-muted-foreground mt-1">
                Enable or disable application features. Disabling a feature removes its routes,
                navigation, settings, and permissions. Data is preserved and the feature can be
                re-enabled non-destructively.
              </p>
            </div>

            {missingDependencies.length > 0 && (
              <Alert variant="destructive">
                <AlertCircle />
                <AlertTitle>Broken feature dependencies</AlertTitle>
                <AlertDescription>
                  {missingDependencies.map((dep) => (
                    <div key={`${dep.provider}-${dep.dependency}`} className="mt-1">
                      <span className="font-medium">{dep.provider}</span> requires{" "}
                      <span className="font-medium">{dep.dependency}</span> — {dep.reason}
                    </div>
                  ))}
                </AlertDescription>
              </Alert>
            )}

            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <Puzzle className="h-4 w-4 text-muted-foreground" />
                  Installed Features
                </CardTitle>
                <CardDescription>
                  Changes apply instantly and are recorded in the audit log.
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-3">
                {modules.map((mod) => {
                  const hasDep = missingDependencies.some((d) => d.provider === mod.slug);
                  return (
                    <div key={mod.slug} className="flex items-center justify-between gap-4 rounded-lg border border-border p-4">
                      <div className="space-y-1 flex-1 mr-4">
                        <p className="text-sm font-medium leading-none flex items-center gap-2">
                          {mod.name}
                          <span className="text-xs text-muted-foreground ml-auto">{mod.version}</span>
                        </p>
                        {mod.description && (
                          <p className="text-xs text-muted-foreground">{mod.description}</p>
                        )}
                        {mod.dependencies.length > 0 && (
                          <p className="text-xs text-muted-foreground mt-1">
                            Requires: {mod.dependencies.join(", ")}
                          </p>
                        )}
                        {mod.package && <p className="text-xs text-muted-foreground">{mod.package}</p>}
                        {hasDep && (
                          <p className="flex items-center gap-1 mt-1 text-xs text-amber-600 dark:text-amber-400">
                            <AlertCircle className="h-3 w-3" /> Missing or disabled dependency
                          </p>
                        )}
                        {mod.enabled && !hasDep && (
                          <p className="flex items-center gap-1 mt-1 text-xs text-green-600 dark:text-green-400">
                            <CheckCircle2 className="h-3 w-3" /> Enabled
                          </p>
                        )}
                        {!mod.enabled && (
                          <p className="flex items-center gap-1 mt-1 text-xs text-muted-foreground">
                            Disabled
                          </p>
                        )}
                      </div>
                      <Switch
                        id={`module-${mod.slug}`}
                        checked={data.modules[mod.slug] ?? false}
                        onCheckedChange={(checked) =>
                          setData("modules", { ...data.modules, [mod.slug]: checked })
                        }
                        disabled={processing}
                      />
                    </div>
                  );
                })}

                {Object.keys(errors).length > 0 && (
                  <p className="text-sm text-destructive">{Object.values(errors)[0]}</p>
                )}

                <div className="flex justify-end pt-2">
                  <Button onClick={save} disabled={processing}>
                    {processing ? "Saving..." : "Save Module Settings"}
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
