import { useCallback, useMemo } from "react";
import { Head, router, usePage } from "@inertiajs/react";
import type { PageProps } from "@inertiajs/core";
import { toast } from "@nreljed/platform/Components/ui/toast";
import AuthenticatedLayout from "@nreljed/platform/Layouts/AuthenticatedLayout";
import { ResourceIndex } from "@nreljed/platform/Components/resources/ResourceIndex";
import type { ResourceRowAction } from "@nreljed/platform/lib/resource-schema";
import {
  defineProductResource,
  type ProductOption,
  type ProductRow,
} from "../../resources/productResource";

/**
 * The product list is declared in `resources/productResource.ts` and rendered
 * by the kernel's generic `ResourceIndex`.
 *
 * What stays here is only what a declaration cannot know: the verb, the payload
 * envelope and the toast copy. The columns, the bulk-input dialog, the filter
 * facets and the row dropdown all come from the declaration.
 */
export default function ProductsIndex() {
  const { props } = usePage<
    PageProps & {
      products: ProductRow[];
      categories: ProductOption[];
      tags: ProductOption[];
    }
  >();

  const products = props.products || [];

  const resource = useMemo(
    () => defineProductResource({ categories: props.categories ?? [], tags: props.tags ?? [] }),
    [props.categories, props.tags],
  );

  /**
   * A bulk action that collects fields posts the collected values alongside the
   * action and the selection. The fields are named for the controller's own
   * validator (`type_value`, `category_ids`, `tag_ids`, `stock_status_value`,
   * `price_value`), so the values need no renaming here.
   */
  const sendBulk = useCallback(
    (action: string, ids: (number | string)[], values: Record<string, unknown> = {}) => {
      router.post(
        route("dashboard.shop.products.bulk"),
        { action, selected_ids: ids, ...values },
        {
          onSuccess: () => {
            toast.add({ title: "Bulk action completed.", type: "success" });
            router.reload();
          },
          onError: () => toast.add({ title: "Failed to perform bulk action.", type: "error" }),
        },
      );
    },
    [],
  );

  const onBulkAction = useCallback(
    (action: string, ids: (number | string)[]) => sendBulk(action, ids),
    [sendBulk],
  );

  const onBulkSubmit = useCallback(
    (action: string, ids: (number | string)[], values: Record<string, unknown>) =>
      sendBulk(action, ids, values),
    [sendBulk],
  );

  /**
   * The kernel owns the confirm dialogs and the per-row gating, so this handler
   * only has to answer "what does this row action actually send?" for the two
   * verbs a declaration cannot express: DELETE the product, or PATCH its status.
   *
   * The status transition is read off the row rather than off the action label.
   * The two row actions are gated so that only one is ever offered - publish
   * while the product is not published, make draft once it is - which makes the
   * row's own status the discriminator, with no string matching to keep in sync
   * with the menu.
   */
  const onRowAction = useCallback((action: ResourceRowAction<ProductRow>, row: ProductRow) => {
    if (action.destructive) {
      router.delete(route("dashboard.shop.products.destroy", row.id), {
        preserveScroll: true,
        onSuccess: () => toast.add({ title: "Product deleted.", type: "success" }),
        onError: () => toast.add({ title: "Failed to delete product.", type: "error" }),
      });

      return;
    }

    if (!action.route) return;

    const status = row.status === "published" ? "draft" : "published";

    router.patch(
      action.route(row),
      { status },
      {
        preserveScroll: true,
        onSuccess: () =>
          toast.add({
            title: status === "published" ? "Product published." : "Product moved to draft.",
            type: "success",
          }),
        onError: () => toast.add({ title: "Failed to update product status.", type: "error" }),
      },
    );
  }, []);

  /** Export is a plain navigation: the CSV comes back as a download. */
  const onHeaderAction = useCallback((action: { route: string; method: "get" | "post" }) => {
    if (action.method === "get") {
      window.location.href = action.route;

      return;
    }

    router.post(action.route, {}, { preserveScroll: true });
  }, []);

  /**
   * Import posts multipart `file`. The CSRF token rides along in the header the
   * kernel sets, which a bare `router.post(FormData)` would otherwise not send.
   */
  const onHeaderUpload = useCallback((action: { route: string; file: File }) => {
    const formData = new FormData();

    formData.append("file", action.file);

    router.post(action.route, formData, {
      preserveScroll: true,
      onSuccess: () => toast.add({ title: "Import completed.", type: "success" }),
      onError: () => toast.add({ title: "Import failed.", type: "error" }),
    });
  }, []);

  return (
    <AuthenticatedLayout header="Products">
      <Head title="Products" />
      <ResourceIndex
        resource={resource}
        data={products}
        createRoute={resource.routes?.create}
        editRoute={resource.routes?.edit}
        destroyRoute={resource.routes?.destroy}
        onRowAction={onRowAction}
        onBulkAction={onBulkAction}
        onBulkSubmit={onBulkSubmit}
        onHeaderAction={onHeaderAction}
        onHeaderUpload={onHeaderUpload}
      />
    </AuthenticatedLayout>
  );
}
