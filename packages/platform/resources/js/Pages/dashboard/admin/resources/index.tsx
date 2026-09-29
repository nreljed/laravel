import { Head, Link } from "@inertiajs/react";
import { useMemo } from "react";
import AdminLayout from "../../../../Layouts/AdminLayout";
import { ResourceIndex } from "../../../../Components/resources/ResourceIndex";
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "../../../../Components/ui/breadcrumb";
import { toResource, type ResourceRecord, type SerializedResource } from "../../../../lib/resource-schema";

interface ResourceIndexPageProps {
  resource: SerializedResource;
  data: ResourceRecord[];
  resourceKey: string;
}

/**
 * Generic kernel index page — a declared resource + the provider's resolved
 * rows. Brought to life by `ResourceRenderer::renderIndex()`; the route names
 * bound here (create/edit/destroy) are the descriptor's, wired to Ziggy.
 */
export default function ResourceIndexPage({ resource: serialized, data }: ResourceIndexPageProps) {
  const resource = useMemo(() => toResource(serialized), [serialized]);
  const routes = resource.routes;

  return (
    <AdminLayout title={resource.label}>
      <Head title={resource.label} />
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
              <BreadcrumbPage>{resource.label}</BreadcrumbPage>
            </BreadcrumbItem>
          </BreadcrumbList>
        </Breadcrumb>

        {resource.table ? (
          <ResourceIndex
            resource={resource}
            data={data}
            createRoute={routes?.create}
            editRoute={routes?.edit}
            destroyRoute={routes?.destroy}
          />
        ) : (
          <p className="text-sm text-muted-foreground">
            Resource <code className="rounded bg-muted px-1 py-0.5">{resource.key}</code> declares no table.
          </p>
        )}
      </div>
    </AdminLayout>
  );
}