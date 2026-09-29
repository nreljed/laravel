import { Head, Link } from "@inertiajs/react";
import { useMemo } from "react";
import AdminLayout from "../../../../Layouts/AdminLayout";
import { ResourceForm } from "../../../../Components/resources/ResourceForm";
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "../../../../Components/ui/breadcrumb";
import {
  resolveResourceRoute,
  toResource,
  type ResourceRecord,
  type SerializedResource,
} from "../../../../lib/resource-schema";

interface ResourceFormPageProps {
  resource: SerializedResource;
  record?: ResourceRecord | null;
  formMethod?: "POST" | "PUT" | "PATCH" | "post" | "put" | "patch";
  formAction: string;
  resourceKey: string;
}

/**
 * Generic kernel form page — create (POST) when `record` is absent, edit
 * (PUT) when present. Brought to life by `ResourceRenderer::renderForm()`;
 * the provider passes the verb and URL it wants the form to submit to.
 */
export default function ResourceFormPage({
  resource: serialized,
  record,
  formMethod = "POST",
  formAction,
}: ResourceFormPageProps) {
  const resource = useMemo(() => toResource(serialized), [serialized]);
  const cancelRoute = resolveResourceRoute(resource.routes?.index);
  const method = formMethod.toLowerCase() as "post" | "put" | "patch";
  const editing = record !== null && record !== undefined;

  return (
    <AdminLayout title={editing ? `Edit ${resource.singular ?? resource.label}` : `New ${resource.singular ?? resource.label}`}>
      <Head title={editing ? `Edit ${resource.singular ?? resource.label}` : `New ${resource.singular ?? resource.label}`} />
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
              <BreadcrumbPage>{editing ? `Edit ${resource.singular ?? resource.label}` : `New ${resource.singular ?? resource.label}`}</BreadcrumbPage>
            </BreadcrumbItem>
          </BreadcrumbList>
        </Breadcrumb>

        {resource.form ? (
          <ResourceForm
            resource={resource}
            record={record ?? undefined}
            method={method}
            action={formAction}
            cancelRoute={cancelRoute}
          />
        ) : (
          <p className="text-sm text-muted-foreground">
            Resource <code className="rounded bg-muted px-1 py-0.5">{resource.key}</code> declares no form.
          </p>
        )}
      </div>
    </AdminLayout>
  );
}