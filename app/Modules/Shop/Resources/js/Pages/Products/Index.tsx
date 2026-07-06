import { useCallback, useMemo, useRef, useState } from "react"
import type { ColumnDef } from "@tanstack/react-table"
import { usePage, router, Link } from "@inertiajs/react"
import { Head } from "@inertiajs/react"
import { toast } from "sonner"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import { DataTable } from "@/Components/ui/data-table"
import { DataTableColumnHeader } from "@/Components/ui/data-table-column-header"
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "@/Components/ui/empty"
import { Button } from "@/Components/ui/button"
import { Badge } from "@/Components/ui/badge"
import { ActionCell } from "@/Components/shared/action-cell"
import { Input } from "@/Components/ui/input"
import { Label } from "@/Components/ui/label"
import { ShoppingBag, Plus, Download, Upload, TrendingUp } from "lucide-react"
import {
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from "@/Components/ui/dialog"
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from "@/Components/ui/select"

interface Category {
  id: number
  name: string
}

interface TagOrBrand {
  id: number
  name: string
}

interface ProductRow {
  id: number
  name: string
  slug: string
  sku: string | null
  price: number | null
  type: string
  status: string
  stock_quantity: number
  low_stock_threshold: number | null
  track_stock: boolean
  thumbnail_url: string | null
  categories: Category[]
  tags: TagOrBrand[]
  brands: TagOrBrand[]
  is_featured: boolean
}

export default function ProductsIndex() {
  const { props } = usePage<{
    products: ProductRow[]
    categories: Category[]
    tags: { id: number; name: string }[]
    nonce?: string
  }>()

  const products = props.products || []
  const nonce = props.nonce || ""
  const fileInputRef = useRef<HTMLInputElement>(null)

  const handleImport = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0]
    if (!file) return
    const formData = new FormData()
    formData.append("file", file)
    router.post(route("dashboard.shop.products.import"), formData, {
      preserveScroll: true,
      onSuccess: () => { toast.success("Import completed.") },
      onError: () => { toast.error("Import failed.") },
    })
    e.target.value = ""
  }

  const columns = useMemo(() => {
    const cols: ColumnDef<ProductRow>[] = [
      {
        accessorKey: "id",
        header: ({ column }) => <DataTableColumnHeader column={column} title="ID" />,
        cell: ({ row }) => <span className="text-xs font-mono text-muted-foreground">#{row.original.id}</span>,
        className: "w-16 hidden sm:table-cell",
      },
      {
        accessorKey: "name",
        header: ({ column }) => <DataTableColumnHeader column={column} title="Product" />,
        cell: ({ row }) => (
          <div className="flex items-center gap-3 max-w-xs">
            <div className="h-10 w-10 shrink-0 rounded-md bg-muted flex items-center justify-center overflow-hidden">
              {row.original.thumbnail_url ? (
                <img src={row.original.thumbnail_url} alt="" className="h-full w-full object-cover" />
              ) : (
                <ShoppingBag className="h-4 w-4 text-muted-foreground/50" />
              )}
            </div>
            <div className="min-w-0">
              <Link
                href={route("dashboard.shop.products.edit", row.original.id)}
                className="text-sm font-medium text-foreground hover:underline truncate block"
              >
                {row.original.name}
              </Link>
              {row.original.sku && (
                <p className="text-xs text-muted-foreground truncate">SKU: {row.original.sku}</p>
              )}
              {row.original.is_featured && (
                <Badge variant="outline" className="text-[10px] px-1 py-0 mt-0.5">Featured</Badge>
              )}
            </div>
          </div>
        ),
      },
      {
        accessorKey: "type",
        header: ({ column }) => <DataTableColumnHeader column={column} title="Type" />,
        cell: ({ row }) => (
          <Badge variant="outline" className="text-xs capitalize">
            {row.original.type}
          </Badge>
        ),
        className: "hidden md:table-cell",
      },
      {
        id: "categories",
        header: ({ column }) => <DataTableColumnHeader column={column} title="Categories" />,
        cell: ({ row }) => {
          const cats = row.original.categories || [];
          if (cats.length === 0) return <span className="text-sm text-muted-foreground">—</span>;
          const visible = cats.slice(0, 2);
          const remainder = cats.length - 2;
          return (
            <div className="flex flex-wrap gap-1 max-w-[180px]">
              {visible.map((c) => (
                <Badge key={c.id} variant="secondary" className="text-[10px] px-1.5 py-0 font-normal">{c.name}</Badge>
              ))}
              {remainder > 0 && <span className="text-[10px] text-muted-foreground self-center">+{remainder}</span>}
            </div>
          );
        },
        className: "hidden lg:table-cell",
        enableSorting: false,
      },
      {
        id: "tags",
        header: ({ column }) => <DataTableColumnHeader column={column} title="Tags" />,
        cell: ({ row }) => {
          const t = row.original.tags || [];
          if (t.length === 0) return <span className="text-sm text-muted-foreground">—</span>;
          const visible = t.slice(0, 2);
          const remainder = t.length - 2;
          return (
            <div className="flex flex-wrap gap-1 max-w-[160px]">
              {visible.map((tag) => (
                <Badge key={tag.id} variant="outline" className="text-[10px] px-1.5 py-0 font-normal">{tag.name}</Badge>
              ))}
              {remainder > 0 && <span className="text-[10px] text-muted-foreground self-center">+{remainder}</span>}
            </div>
          );
        },
        className: "hidden xl:table-cell",
        enableSorting: false,
      },
      {
        id: "brands",
        header: ({ column }) => <DataTableColumnHeader column={column} title="Brands" />,
        cell: ({ row }) => {
          const b = row.original.brands || [];
          if (b.length === 0) return <span className="text-sm text-muted-foreground">—</span>;
          return (
            <div className="flex flex-wrap gap-1 max-w-[160px]">
              {b.map((brand) => (
                <Badge key={brand.id} variant="outline" className="text-[10px] px-1.5 py-0 font-normal">{brand.name}</Badge>
              ))}
            </div>
          );
        },
        className: "hidden xl:table-cell",
        enableSorting: false,
      },
      {
        accessorKey: "price",
        header: ({ column }) => <DataTableColumnHeader column={column} title="Price" />,
        cell: ({ row }) => {
          const p = row.original.price
          if (!p && row.original.type === "variable") return <span className="text-sm text-muted-foreground">From €—</span>
          return <span className="text-sm font-medium tabular-nums">{p != null ? `€${Number(p).toFixed(2)}` : "—"}</span>
        },
        className: "hidden md:table-cell",
      },
      {
        id: "stock",
        header: ({ column }) => <DataTableColumnHeader column={column} title="Stock" />,
        cell: ({ row }) => {
          const p = row.original
          if (!p.track_stock) return <span className="text-sm text-muted-foreground">—</span>
          const isLow = p.low_stock_threshold != null && p.stock_quantity <= p.low_stock_threshold
          return (
            <div className="flex items-center gap-1.5">
              <span className={`text-sm tabular-nums ${isLow ? "text-destructive font-medium" : ""}`}>
                {p.stock_quantity}
              </span>
              {p.track_stock && (
                <Link
                  href={route("dashboard.shop.products.stock-history", p.id)}
                  className="text-muted-foreground hover:text-foreground"
                  title="Stock History"
                >
                  <TrendingUp className="h-3 w-3" />
                </Link>
              )}
            </div>
          )
        },
        className: "hidden lg:table-cell",
      },
      {
        accessorKey: "status",
        header: ({ column }) => <DataTableColumnHeader column={column} title="Status" />,
        cell: ({ row }) => {
          const s = row.original.status
          const variant = s === "published" ? "default" : s === "draft" ? "secondary" : "outline"
          return <Badge variant={variant} className="text-xs capitalize">{s}</Badge>
        },
        className: "hidden lg:table-cell",
      },
      {
        id: "actions",
        cell: ({ row }) => (
          <ActionCell
            item={row.original as unknown as import("@/Components/shared/action-cell").ActionItem}
            baseRoute={route("dashboard.shop.products.index").replace(/\/+$/, "")}
            itemLabel="Product"
            nonce={nonce}
          />
        ),
        enableSorting: false,
      },
    ]
    return cols
  }, [nonce])

  const [bulkActionSelection, setBulkActionSelection] = useState<{ ids: number[]; action: string } | null>(null);

  const [bulkTypeValue, setBulkTypeValue] = useState("simple");
  const [bulkCategoryIds, setBulkCategoryIds] = useState<number[]>([]);
  const [bulkTagIds, setBulkTagIds] = useState<number[]>([]);
  const [bulkStockStatus, setBulkStockStatus] = useState("instock");
  const [bulkPriceValue, setBulkPriceValue] = useState("");

  const handleBulkAction = useCallback((action: string, selectedIds: number[]) => {
    const needsInput = ["type", "add_category", "remove_category", "add_tag", "remove_tag", "stock_status", "set_price"];
    if (needsInput.includes(action)) {
      setBulkActionSelection({ ids: selectedIds, action });
      return;
    }
    router.post(route("dashboard.shop.products.bulk"), {
      action,
      selected_ids: selectedIds,
      _wpnonce: nonce,
    }, {
      onSuccess: () => {
        toast.success("Bulk action completed.")
        router.reload({ preserveScroll: true })
      },
      onError: () => toast.error("Failed to perform bulk action."),
    })
  }, [nonce])

  const executeBulkWithValue = useCallback(() => {
    if (!bulkActionSelection) return;
    const { ids, action } = bulkActionSelection;
    const data: Record<string, unknown> = {
      action,
      selected_ids: ids,
      _wpnonce: nonce,
    };
    if (action === "type") data.type_value = bulkTypeValue;
    if (action === "add_category" || action === "remove_category") data.category_ids = bulkCategoryIds;
    if (action === "add_tag" || action === "remove_tag") data.tag_ids = bulkTagIds;
    if (action === "stock_status") data.stock_status_value = bulkStockStatus;
    if (action === "set_price") data.price_value = bulkPriceValue;

    router.post(route("dashboard.shop.products.bulk"), data, {
      onSuccess: () => {
        toast.success("Bulk action completed.");
        setBulkActionSelection(null);
        router.reload({ preserveScroll: true });
      },
      onError: () => toast.error("Failed to perform bulk action."),
    });
  }, [bulkActionSelection, bulkTypeValue, bulkCategoryIds, bulkTagIds, bulkStockStatus, bulkPriceValue, nonce]);

  const bulkActionDialogTitle = useMemo(() => {
    if (!bulkActionSelection) return "";
    const labels: Record<string, string> = {
      type: "Change Product Type",
      add_category: "Add Categories",
      remove_category: "Remove Categories",
      add_tag: "Add Tags",
      remove_tag: "Remove Tags",
      stock_status: "Set Stock Status",
      set_price: "Set Price",
    };
    return labels[bulkActionSelection.action] || "Bulk Action";
  }, [bulkActionSelection]);

  const header = (
    <div className="flex items-center justify-between">
      <div>
        <h2 className="text-3xl font-bold tracking-tight text-foreground">Products</h2>
        <p className="text-muted-foreground">Manage your product catalog</p>
      </div>
      <div className="flex items-center gap-2">
        <a href={route("dashboard.shop.products.export")}>
          <Button variant="outline"><Download className="mr-1 h-4 w-4" /> Export</Button>
        </a>
        <Button variant="outline" onClick={() => fileInputRef.current?.click()}>
          <Upload className="mr-1 h-4 w-4" /> Import
        </Button>
        <input ref={fileInputRef} type="file" accept=".csv" className="hidden" onChange={handleImport} />
        <Link href={route("dashboard.shop.products.create")}>
          <Button><Plus className="mr-2 h-4 w-4" /> Add Product</Button>
        </Link>
      </div>
    </div>
  )

  if (products.length === 0) {
    return (
      <AuthenticatedLayout header="Products">
        <Head title="Products" />
        <div className="space-y-6">
          {header}
          <Empty>
            <EmptyHeader>
              <EmptyMedia variant="icon"><ShoppingBag /></EmptyMedia>
              <EmptyTitle>No products found</EmptyTitle>
              <EmptyDescription>Create your first product to get started</EmptyDescription>
            </EmptyHeader>
            <EmptyContent>
              <Link href={route("dashboard.shop.products.create")}>
                <Button><Plus className="mr-2 h-4 w-4" /> Create your first product</Button>
              </Link>
            </EmptyContent>
          </Empty>
        </div>
      </AuthenticatedLayout>
    )
  }

  return (
    <AuthenticatedLayout header="Products">
      <Head title="Products" />
      <div className="space-y-6">
        {header}
        <DataTable
          columns={columns}
          data={products}
          searchKey="name"
          searchPlaceholder="Search products..."
          filterKey="status"
          filterTitle="Status"
          filterOptions={[
            { label: "Draft", value: "draft" },
            { label: "Published", value: "published" },
            { label: "Archived", value: "archived" },
          ]}
          bulkActions={[
            { label: "Publish", value: "publish", show: (s) => s.every((st) => st !== "published") },
            { label: "Set to Draft", value: "draft", show: (s) => s.some((st) => st !== "draft") },
            { label: "Archive", value: "archive" },
            { label: "Mark as Featured", value: "featured", show: (s) => s.some((st) => st !== "featured") },
            { label: "Unmark as Featured", value: "unfeatured" },
            { label: "Change Type...", value: "type" },
            { label: "Add Categories...", value: "add_category" },
            { label: "Remove Categories...", value: "remove_category" },
            { label: "Add Tags...", value: "add_tag" },
            { label: "Remove Tags...", value: "remove_tag" },
            { label: "Set Stock Status...", value: "stock_status" },
            { label: "Set Price...", value: "set_price" },
            { label: "Delete selected", value: "delete", destructive: true },
          ]}
          onBulkAction={handleBulkAction}
        />

        {/* Bulk Action Input Dialog */}
        <Dialog open={!!bulkActionSelection} onOpenChange={(o) => { if (!o) setBulkActionSelection(null); }}>
          <DialogContent className="sm:max-w-md">
            <DialogHeader>
              <DialogTitle>{bulkActionDialogTitle}</DialogTitle>
              <DialogDescription>
                {bulkActionSelection?.ids.length} product(s) selected.
              </DialogDescription>
            </DialogHeader>

            {bulkActionSelection?.action === "type" && (
              <div className="grid gap-2">
                <Label>Product Type</Label>
                <Select value={bulkTypeValue} onValueChange={setBulkTypeValue}>
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="simple">Simple</SelectItem>
                    <SelectItem value="variable">Variable</SelectItem>
                    <SelectItem value="grouped">Grouped</SelectItem>
                    <SelectItem value="external">External / Affiliate</SelectItem>
                    <SelectItem value="digital">Digital</SelectItem>
                    <SelectItem value="service">Service</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            )}

            {bulkActionSelection?.action === "add_category" && (
              <p className="text-sm text-muted-foreground">Categories will be added via the category management page.</p>
            )}

            {bulkActionSelection?.action === "remove_category" && (
              <p className="text-sm text-muted-foreground">Categories will be removed via the category management page.</p>
            )}

            {bulkActionSelection?.action === "add_tag" && (
              <p className="text-sm text-muted-foreground">Tags will be added via the tag management page.</p>
            )}

            {bulkActionSelection?.action === "remove_tag" && (
              <p className="text-sm text-muted-foreground">Tags will be removed via the tag management page.</p>
            )}

            {bulkActionSelection?.action === "stock_status" && (
              <div className="grid gap-2">
                <Label>Stock Status</Label>
                <Select value={bulkStockStatus} onValueChange={setBulkStockStatus}>
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="instock">In Stock</SelectItem>
                    <SelectItem value="outofstock">Out of Stock</SelectItem>
                    <SelectItem value="onbackorder">On Backorder</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            )}

            {bulkActionSelection?.action === "set_price" && (
              <div className="grid gap-2">
                <Label>Price</Label>
                <Input type="number" step="0.01" min="0" value={bulkPriceValue}
                  onChange={(e) => setBulkPriceValue(e.target.value)} placeholder="0.00" />
              </div>
            )}

            <DialogFooter>
              <Button variant="outline" onClick={() => setBulkActionSelection(null)}>Cancel</Button>
              <Button onClick={executeBulkWithValue}>Apply</Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      </div>
    </AuthenticatedLayout>
  )
}
