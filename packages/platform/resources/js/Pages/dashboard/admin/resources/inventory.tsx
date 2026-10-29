import { Head, Link } from "@inertiajs/react";
import { Layers } from "lucide-react";
import AdminLayout from "../../../../Layouts/AdminLayout";
import { Badge } from "../../../../Components/ui/badge";
import { Button } from "../../../../Components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "../../../../Components/ui/card";
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "../../../../Components/ui/breadcrumb";
import { resolveResourceRoute, type SerializedResource } from "../../../../lib/resource-schema";

interface ResourceInventoryProps {
  resources: Record<string, SerializedResource>;
}

export default function ResourceInventory({ resources }: ResourceInventoryProps) {
  const entries = Object.values(resources ?? {});

  return (
    <AdminLayout title="Resources">
      <Head title="Resources" />
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
              <BreadcrumbPage>Resources</BreadcrumbPage>
            </BreadcrumbItem>
          </BreadcrumbList>
        </Breadcrumb>

        <div className="flex flex-col md:flex-row gap-8">
          <div className="flex-1 min-w-0 space-y-6">
            <div>
              <div className="flex flex-wrap items-center gap-3">
                <h1 className="text-2xl font-semibold tracking-tight">Admin resources</h1>
                <Badge variant="default" className="uppercase">
                  {entries.length} declared
                </Badge>
              </div>
              <p className="text-sm text-muted-foreground mt-1">
                Every resource declared by an enabled feature. Each entry keeps its routes, controllers,
                and permissions with the owning module — this page only catalogues the descriptors.
              </p>
            </div>

            {entries.length === 0 ? (
              <Card>
                <CardContent className="py-12 text-center text-sm text-muted-foreground">
                  No admin resources are declared yet. A module registers one by returning a
                  <code className="mx-1 rounded bg-muted px-1 py-0.5">ResourceDefinition</code>
                  from its feature provider&apos;s <code>getAdminResources()</code>.
                </CardContent>
              </Card>
            ) : (
              <div className="grid gap-4 sm:grid-cols-2">
                {entries.map((resource) => {
                  const indexUrl = resolveResourceRoute(resource.routes?.index);

                  return (
                    <Card key={resource.key}>
                      <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                          <Layers className="h-4 w-4 text-muted-foreground" />
                          {resource.label}
                        </CardTitle>
                        <CardDescription>
                          <code className="rounded bg-muted px-1 py-0.5 text-xs">{resource.key}</code>
                        </CardDescription>
                      </CardHeader>
                      <CardContent className="space-y-3">
                        {resource.permissions && Object.keys(resource.permissions).length > 0 && (
                          <div className="flex flex-wrap gap-1">
                            {Object.values(resource.permissions)
                              .filter(Boolean)
                              .map((permission) => (
                                <Badge key={permission as string} variant="secondary" className="font-mono text-xs">
                                  {permission as string}
                                </Badge>
                              ))}
                          </div>
                        )}
                        <div className="flex justify-end">
                          {indexUrl ? (
                            <Button size="sm" variant="outline" render={<Link href={indexUrl} />}>
                              Open
                            </Button>
                          ) : (
                            <span className="text-xs text-muted-foreground">No index route declared</span>
                          )}
                        </div>
                      </CardContent>
                    </Card>
                  );
                })}
              </div>
            )}
          </div>
        </div>
      </div>
    </AdminLayout>
  );
}