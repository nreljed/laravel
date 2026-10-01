import { Link } from "@inertiajs/react";
import { Badge } from "@nreljed/platform/Components/ui/badge";
import { DataTableColumnHeader } from "@nreljed/platform/Components/ui/data-table-column-header";
import {
  defineResource,
  type FieldDefinition,
  type ResourceColumn,
  type ResourceFilter,
} from "@nreljed/platform/lib/resource-schema";
import { Download, Eye, FileEdit, ShoppingBag, TrendingUp, Upload } from "lucide-react";

export interface ProductOption {
  id: number;
  name: string;
}

/**
 * A type alias, not an interface: the kernel's record constraint is
 * `Record<string, unknown>`, and only an alias carries the implicit index
 * signature an `interface` does not.
 */
export type ProductRow = {
  id: number;
  name: string;
  slug: string;
  sku: string | null;
  price: number | null;
  type: string;
  status: string;
  publish_date: string | null;
  unpublish_at: string | null;
  stock_quantity: number;
  low_stock_threshold: number | null;
  track_stock: boolean;
  thumbnail_url: string | null;
  catalog_visibility: string;
  categories: ProductOption[];
  tags: ProductOption[];
  brands: ProductOption[];
  is_featured: boolean;
};

/**
 * How each visibility reads on the staff list.
 *
 * The status column answers "is it published?" and the visibility column answers
 * the completely separate question "is it in the store?". They can disagree in
 * both directions - a published product can be `hidden`, and a `search`-only
 * product is published, buyable and reachable but never appears on a grid - so
 * collapsing them into one badge would hide exactly the state a staff member
 * needs to see.
 */
const VISIBILITY_ROWS: Record<string, { label: string; variant: string; hint: string }> = {
  visible: { label: "Visible", variant: "default", hint: "Listed, searchable and buyable" },
  catalog: { label: "Catalog", variant: "default", hint: "Listed, searchable and buyable" },
  search: {
    label: "Search only",
    variant: "secondary",
    hint: "Reachable and buyable, but never listed",
  },
  hidden: { label: "Hidden", variant: "destructive", hint: "Not in the store at all" },
};

const STATUS_FILTER: ResourceFilter = {
  key: "status",
  title: "Status",
  options: [
    { label: "Draft", value: "draft" },
    { label: "Published", value: "published" },
    { label: "Archived", value: "archived" },
  ],
};

const VISIBILITY_FILTER: ResourceFilter = {
  key: "catalog_visibility",
  title: "In Store",
  options: [
    { label: "Visible", value: "visible" },
    { label: "Catalog", value: "catalog" },
    { label: "Search only", value: "search" },
    { label: "Hidden", value: "hidden" },
  ],
};

const TYPE_OPTIONS = [
  { label: "Simple", value: "simple" },
  { label: "Variable", value: "variable" },
  { label: "Grouped", value: "grouped" },
  { label: "External / Affiliate", value: "external" },
  { label: "Digital", value: "digital" },
  { label: "Service", value: "service" },
];

const STOCK_STATUS_OPTIONS = [
  { label: "In Stock", value: "instock" },
  { label: "Out of Stock", value: "outofstock" },
  { label: "On Backorder", value: "onbackorder" },
];

/** Badges for a relation list, collapsing the tail into a `+n` counter. */
function RelationCell({
  items,
  max,
  maxWidth,
  variant,
}: {
  items: ProductOption[];
  max: number;
  maxWidth: string;
  variant: "secondary" | "outline";
}) {
  if (items.length === 0) {
    return <span className="text-sm text-muted-foreground">—</span>;
  }

  const visible = items.slice(0, max);
  const remainder = items.length - max;

  return (
    <div className="flex flex-wrap gap-1" style={{ maxWidth }}>
      {visible.map((item) => (
        <Badge key={item.id} variant={variant} className="text-[10px] px-1.5 py-0 font-normal">
          {item.name}
        </Badge>
      ))}
      {remainder > 0 && (
        <span className="text-[10px] text-muted-foreground self-center">+{remainder}</span>
      )}
    </div>
  );
}

const COLUMNS: ResourceColumn<ProductRow>[] = [
  {
    name: "id",
    headerCell: ({ column }) => <DataTableColumnHeader column={column} title="ID" />,
    cell: (row) => <span className="text-xs font-mono text-muted-foreground">#{row.id}</span>,
    className: "w-16 hidden sm:table-cell",
  },
  {
    name: "name",
    headerCell: ({ column }) => <DataTableColumnHeader column={column} title="Product" />,
    cell: (row) => (
      <div className="flex items-center gap-3 max-w-xs">
        <div className="h-10 w-10 shrink-0 rounded-md bg-muted flex items-center justify-center overflow-hidden">
          {row.thumbnail_url ? (
            <img src={row.thumbnail_url} alt="" className="h-full w-full object-cover" />
          ) : (
            <ShoppingBag className="h-4 w-4 text-muted-foreground/50" />
          )}
        </div>
        <div className="min-w-0">
          <Link
            href={route("dashboard.shop.products.edit", row.id)}
            className="text-sm font-medium text-foreground hover:underline truncate block"
          >
            {row.name}
          </Link>
          {row.sku && <p className="text-xs text-muted-foreground truncate">SKU: {row.sku}</p>}
          {row.is_featured && (
            <Badge variant="outline" className="text-[10px] px-1 py-0 mt-0.5">
              Featured
            </Badge>
          )}
        </div>
      </div>
    ),
  },
  {
    name: "type",
    headerCell: ({ column }) => <DataTableColumnHeader column={column} title="Type" />,
    cell: (row) => (
      <Badge variant="outline" className="text-xs capitalize">
        {row.type}
      </Badge>
    ),
    className: "hidden md:table-cell",
  },
  {
    name: "categories",
    headerCell: ({ column }) => <DataTableColumnHeader column={column} title="Categories" />,
    cell: (row) => (
      <RelationCell items={row.categories ?? []} max={2} maxWidth="180px" variant="secondary" />
    ),
    className: "hidden lg:table-cell",
    sortable: false,
  },
  {
    name: "tags",
    headerCell: ({ column }) => <DataTableColumnHeader column={column} title="Tags" />,
    cell: (row) => (
      <RelationCell items={row.tags ?? []} max={2} maxWidth="160px" variant="outline" />
    ),
    className: "hidden xl:table-cell",
    sortable: false,
  },
  {
    name: "brands",
    headerCell: ({ column }) => <DataTableColumnHeader column={column} title="Brands" />,
    cell: (row) => {
      if (!row.brands || row.brands.length === 0) {
        return <span className="text-sm text-muted-foreground">—</span>;
      }

      return (
        <div className="flex flex-wrap gap-1 max-w-[160px]">
          {row.brands.map((brand) => (
            <Badge key={brand.id} variant="outline" className="text-[10px] px-1 py-0 font-normal">
              {brand.name}
            </Badge>
          ))}
        </div>
      );
    },
    className: "hidden xl:table-cell",
    sortable: false,
  },
  {
    name: "price",
    headerCell: ({ column }) => <DataTableColumnHeader column={column} title="Price" />,
    cell: (row) => {
      /* A variable product's price lives on its variations, so a null base price
       * there means "priced from a variation", not "unpriced". */
      if (!row.price && row.type === "variable") {
        return <span className="text-sm text-muted-foreground">From €—</span>;
      }

      return (
        <span className="text-sm font-medium tabular-nums">
          {row.price != null ? `€${Number(row.price).toFixed(2)}` : "—"}
        </span>
      );
    },
    className: "hidden md:table-cell",
  },
  {
    id: "stock",
    headerCell: ({ column }) => <DataTableColumnHeader column={column} title="Stock" />,
    cell: (row) => {
      if (!row.track_stock) {
        return <span className="text-sm text-muted-foreground">—</span>;
      }

      const isLow =
        row.low_stock_threshold != null && row.stock_quantity <= row.low_stock_threshold;

      return (
        <div className="flex items-center gap-1.5">
          <span className={`text-sm tabular-nums ${isLow ? "text-destructive font-medium" : ""}`}>
            {row.stock_quantity}
          </span>
          <Link
            href={route("dashboard.shop.products.stock-history", row.id)}
            className="text-muted-foreground hover:text-foreground"
            title="Stock History"
          >
            <TrendingUp className="h-3 w-3" />
          </Link>
        </div>
      );
    },
    className: "hidden lg:table-cell",
  },
  {
    name: "status",
    headerCell: ({ column }) => <DataTableColumnHeader column={column} title="Status" />,
    cell: (row) => {
      const variant =
        row.status === "published" ? "default" : row.status === "draft" ? "secondary" : "outline";
      const cue =
        row.status === "future" && row.publish_date
          ? `scheduled ${new Date(row.publish_date).toLocaleDateString()}`
          : row.status === "published" && row.unpublish_at
            ? `unpublishes ${new Date(row.unpublish_at).toLocaleDateString()}`
            : null;

      return (
        <span className="flex flex-col gap-0.5">
          <Badge variant={variant} className="text-xs capitalize w-fit">
            {row.status}
          </Badge>
          {cue && <span className="text-[10px] text-muted-foreground">{cue}</span>}
        </span>
      );
    },
    className: "hidden lg:table-cell",
  },
  {
    name: "catalog_visibility",
    headerCell: ({ column }) => <DataTableColumnHeader column={column} title="In Store" />,
    cell: (row) => {
      const meta = VISIBILITY_ROWS[row.catalog_visibility] ?? {
        label: row.catalog_visibility,
        variant: "outline",
        hint: "",
      };

      return (
        <span className="flex flex-col gap-0.5" title={meta.hint}>
          <Badge variant={meta.variant as never} className="text-xs w-fit">
            {meta.label}
          </Badge>
          <span className="text-[10px] text-muted-foreground">{meta.hint}</span>
        </span>
      );
    },
    className: "hidden lg:table-cell",
  },
];

/**
 * The bulk picker actions post `category_ids`/`tag_ids` and the controller
 * requires them, so the field carries the page's own options rather than an
 * `optionsUrl` the kernel would have to fetch - the index already has them.
 */
function pickerField(name: string, legend: string, options: ProductOption[]): FieldDefinition {
  return {
    name,
    type: "checkboxList",
    label: legend,
    required: true,
    options: options.map((option) => ({ label: option.name, value: option.id })),
  };
}

export interface ProductResourceOptions {
  categories: ProductOption[];
  tags: ProductOption[];
}

/**
 * The shop product list, declared rather than hand-built.
 *
 * Column payloads are named for the controller's validator (`type_value`,
 * `category_ids`, `tag_ids`, `stock_status_value`, `price_value`) so the kernel
 * can post `{action, selected_ids, ...values}` straight to
 * `dashboard.shop.products.bulk` with no translation layer in the page.
 */
export function defineProductResource({ categories, tags }: ProductResourceOptions) {
  return defineResource<ProductRow>({
    key: "shop/products",
    label: "Products",
    singular: "Product",
    table: {
      columns: COLUMNS,
      searchKey: "name",
      searchPlaceholder: "Search products...",
      filters: [STATUS_FILTER, VISIBILITY_FILTER],
      bulkEndpoint: "dashboard.shop.products.bulk",
      headerActions: [
        {
          label: "Export",
          route: route("dashboard.shop.products.export"),
          method: "get",
          icon: Download,
        },
        {
          label: "Import",
          route: route("dashboard.shop.products.import"),
          method: "post",
          fileInput: true,
          accept: ".csv",
          icon: Upload,
        },
      ],
      bulkActions: [
        { label: "Publish", value: "publish", show: (s) => s.every((st) => st !== "published") },
        { label: "Set to Draft", value: "draft", show: (s) => s.some((st) => st !== "draft") },
        { label: "Archive", value: "archive" },
        {
          label: "Mark as Featured",
          value: "featured",
          show: (s) => s.some((st) => st !== "featured"),
        },
        { label: "Unmark as Featured", value: "unfeatured" },
        {
          label: "Change Type...",
          value: "type",
          dialogTitle: "Change Product Type",
          fields: [
            {
              name: "type_value",
              type: "select",
              label: "Product Type",
              required: true,
              options: TYPE_OPTIONS,
              defaultValue: "simple",
            },
          ],
        },
        {
          label: "Add Categories...",
          value: "add_category",
          dialogTitle: "Add Categories",
          fields: [pickerField("category_ids", "Categories to add", categories)],
        },
        {
          label: "Remove Categories...",
          value: "remove_category",
          dialogTitle: "Remove Categories",
          fields: [pickerField("category_ids", "Categories to remove", categories)],
        },
        {
          label: "Add Tags...",
          value: "add_tag",
          dialogTitle: "Add Tags",
          fields: [pickerField("tag_ids", "Tags to add", tags)],
        },
        {
          label: "Remove Tags...",
          value: "remove_tag",
          dialogTitle: "Remove Tags",
          fields: [pickerField("tag_ids", "Tags to remove", tags)],
        },
        {
          label: "Set Stock Status...",
          value: "stock_status",
          dialogTitle: "Set Stock Status",
          fields: [
            {
              name: "stock_status_value",
              type: "select",
              label: "Stock Status",
              required: true,
              options: STOCK_STATUS_OPTIONS,
              defaultValue: "instock",
            },
          ],
        },
        {
          label: "Set Price...",
          value: "set_price",
          dialogTitle: "Set Price",
          fields: [
            {
              name: "price_value",
              type: "number",
              label: "Price",
              required: true,
              placeholder: "0.00",
            },
          ],
        },
        { label: "Delete selected", value: "delete", destructive: true },
      ],
      rowActions: [
        {
          label: "Publish",
          icon: Eye,
          route: (row) => route("dashboard.shop.products.update-status", row.id),
          method: "patch",
          confirm: "Publish this product?",
          show: (row) => ["draft", "archived", "future"].includes(row.status),
        },
        {
          label: "Make Draft",
          icon: FileEdit,
          route: (row) => route("dashboard.shop.products.update-status", row.id),
          method: "patch",
          confirm: "Change this product to draft?",
          show: (row) => row.status === "published",
        },
      ],
      emptyState: (
        <div className="flex flex-col items-center justify-center gap-3 py-10 text-center">
          <ShoppingBag className="size-6 text-muted-foreground/50" />
          <div className="space-y-1">
            <p className="text-sm font-medium">No products found</p>
            <p className="text-sm text-muted-foreground">
              Create your first product to get started
            </p>
          </div>
          <Link href={route("dashboard.shop.products.create")}>
            <span className="inline-flex h-9 items-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground">
              Create your first product
            </span>
          </Link>
        </div>
      ),
    },
    routes: {
      create: route("dashboard.shop.products.create"),
      edit: (row: ProductRow) => route("dashboard.shop.products.edit", row.id),
      destroy: (row: ProductRow) => route("dashboard.shop.products.destroy", row.id),
    },
  });
}
