import { Head, Link, router } from "@inertiajs/react";
import { useCallback, useMemo } from "react";
import AdminLayout from "../../../../Layouts/AdminLayout";
import { ResourceIndex } from "../../../../Components/resources/ResourceIndex";
import { toast } from "../../../../Components/ui/toast";
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
 *
 * Bulk and header actions are wired the same declarative way: the descriptor
 * names the endpoint (`table.bulkEndpoint`) and the kernel owns the envelope
 * (`action` + `selected_ids` + the action's own field values). A feature whose
 * bulk controller expects a different envelope keeps its own page — but the
 * resources that only need a plain POST get it without one.
 */
export default function ResourceIndexPage({ resource: serialized, data }: ResourceIndexPageProps) {
  const resource = useMemo(() => toResource(serialized), [serialized]);
  const routes = resource.routes;
  const bulkEndpoint = resource.table?.bulkEndpoint;

  /** Resolve a declared route *name* to a URL, tolerating a pre-resolved one. */
  const resolve = useCallback(
    (nameOrUrl: string): string =>
      nameOrUrl.startsWith("/") || /^https?:\/\//i.test(nameOrUrl) ? nameOrUrl : route(nameOrUrl),
    [],
  );

  const handleBulkAction = useCallback(
    (action: string, ids: (number | string)[]) => {
      if (!bulkEndpoint) return;

      router.post(resolve(bulkEndpoint), { action, selected_ids: ids }, {
        preserveScroll: true,
        onSuccess: () => toast.add({ title: "Bulk action completed.", type: "success" }),
        onError: () => toast.add({ title: "Failed to perform bulk action.", type: "error" }),
      });
    },
    [bulkEndpoint, resolve],
  );

  const handleBulkSubmit = useCallback(
    (action: string, ids: (number | string)[], values: Record<string, unknown>) => {
      if (!bulkEndpoint) return;

      router.post(resolve(bulkEndpoint), { action, selected_ids: ids, ...values }, {
        preserveScroll: true,
        onSuccess: () => toast.add({ title: "Bulk action completed.", type: "success" }),
        onError: () => toast.add({ title: "Failed to perform bulk action.", type: "error" }),
      });
    },
    [bulkEndpoint, resolve],
  );

  const handleHeaderAction = useCallback(
    (action: { label: string; route: string; method: "get" | "post" }) => {
      const url = resolve(action.route);

      if (action.method === "post") {
        router.post(url, {}, {
          preserveScroll: true,
          onSuccess: () => toast.add({ title: `${action.label} completed.`, type: "success" }),
          onError: () => toast.add({ title: `${action.label} failed.`, type: "error" }),
        });

        return;
      }

      // A GET export is a download, not a page transition — a full navigation
      // keeps the browser's download handling instead of Inertia swapping the
      // whole admin layout out from under the user.
      window.location.href = url;
    },
    [resolve],
  );

  const handleHeaderUpload = useCallback(
    (action: { label: string; route: string; file: File }) => {
      const formData = new FormData();
      formData.append("file", action.file);

      router.post(resolve(action.route), formData, {
        preserveScroll: true,
        onSuccess: () => toast.add({ title: `${action.label} completed.`, type: "success" }),
        onError: () => toast.add({ title: `${action.label} failed.`, type: "error" }),
      });
    },
    [resolve],
  );

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
            /* `toResource` bound edit/destroy to the record param the
             *descriptor declares (defaulting to {record}); `create` is still a
             * route *name*, so Ziggy resolves it here. */
            createRoute={routes?.create ? route(routes.create) : undefined}
            editRoute={routes?.edit}
            destroyRoute={routes?.destroy}
            onBulkAction={bulkEndpoint ? handleBulkAction : undefined}
            onBulkSubmit={bulkEndpoint ? handleBulkSubmit : undefined}
            onHeaderAction={handleHeaderAction}
            onHeaderUpload={handleHeaderUpload}
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
