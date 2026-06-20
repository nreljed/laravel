import { useCallback, useMemo } from "react"
import type { ColumnDef } from "@tanstack/react-table"
import { usePage, router } from "@inertiajs/react"
import { Head } from "@inertiajs/react"
import { toast } from "sonner"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import { DataTable } from "@/Components/ui/data-table"
import { DataTableColumnHeader } from "@/Components/ui/data-table-column-header"
import { Button } from "@/Components/ui/button"
import { Badge } from "@/Components/ui/badge"
import { ActionCell } from "@/Components/shared/action-cell"
import { PropertyMobileDialog } from "../components/PropertyMobileDialog"
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from "@/Components/ui/breadcrumb"
import { Plus, Building2 } from "lucide-react"
import { formatPrice, formatSurface } from "../lib/property-formatting"

interface PropertyRow {
  id: number
  title: string
  thumbnail_url?: string | null
  bookable?: boolean
  date: string
  status: string
  publish_date?: string | null
  taxonomies?: {
    status?: string
    type?: string
    city?: string
  }
  meta?: {
    price?: string
    surface?: string
  }
}

export default function PropertiesIndex() {
  const { props } = usePage<{
    properties?: PropertyRow[]
    nonce?: string
    settings?: Record<string, string>
    dateFormat?: string
    timeFormat?: string
  }>()

  const properties = props.properties || []
  const nonce = props.nonce || ""
  const settings = props.settings || {}

  const columns = useMemo(() => {
    const cols: ColumnDef<PropertyRow>[] = [
      {
        accessorKey: "title",
        header: ({ column }) => <DataTableColumnHeader column={column} title="Title" />,
        cell: ({ row }) => (
          <span className="font-medium text-foreground whitespace-normal line-clamp-2" title={row.original.title}>
            {row.original.title}
          </span>
        ),
      },
      {
        id: "more",
        header: "",
        cell: ({ row }) => (
          <div className="lg:hidden">
            <PropertyMobileDialog property={row.original} settings={settings} />
          </div>
        ),
        enableSorting: false,
      },
      {
        accessorKey: "date",
        header: ({ column }) => <DataTableColumnHeader column={column} title="Date" />,
        cell: ({ row }) => <span className="text-foreground whitespace-nowrap">{row.original.date}</span>,
        className: "hidden md:table-cell",
      },
      {
        id: "type",
        header: ({ column }) => <DataTableColumnHeader column={column} title="Type" />,
        cell: ({ row }) => (
          <span className="text-foreground truncate max-w-[100px]" title={row.original.taxonomies?.type || "-"}>
            {row.original.taxonomies?.type || "-"}
          </span>
        ),
        className: "hidden lg:table-cell",
      },
      {
        id: "city",
        header: ({ column }) => <DataTableColumnHeader column={column} title="City" />,
        cell: ({ row }) => (
          <span className="text-foreground truncate max-w-[150px]" title={row.original.taxonomies?.city || "-"}>
            {row.original.taxonomies?.city || "-"}
          </span>
        ),
        className: "hidden md:table-cell",
      },
      {
        id: "price",
        header: ({ column }) => <DataTableColumnHeader column={column} title="Price" />,
        cell: ({ row }) => {
          const price = row.original.meta?.price
          if (!price) return <span className="text-foreground">-</span>
          return <span className="text-foreground whitespace-nowrap">{formatPrice(price, {
            property_default_currency: settings?.property_default_currency || "USD",
            property_currency_position: "before",
            property_thousands_separator: settings?.property_thousands_separator || ",",
            property_decimal_separator: settings?.property_decimal_separator || ".",
          })}</span>
        },
        className: "hidden md:table-cell",
      },
      {
        id: "surface",
        header: ({ column }) => <DataTableColumnHeader column={column} title="Surface" />,
        cell: ({ row }) => {
          const surface = row.original.meta?.surface
          if (!surface) return <span className="text-foreground">-</span>
          return <span className="text-foreground whitespace-nowrap">{formatSurface(surface, {
            property_surface_unit: settings?.property_surface_unit || "sq_m",
            property_thousands_separator: settings?.property_thousands_separator || ",",
            property_decimal_separator: settings?.property_decimal_separator || ".",
          })}</span>
        },
        className: "hidden md:table-cell",
      },
      {
        accessorKey: "status",
        header: ({ column }) => <DataTableColumnHeader column={column} title="Status" />,
        cell: ({ row }) => {
          const s = row.original.status
          const variant = s === "publish" ? "default" : s === "draft" ? "secondary" : "outline"
          return (
            <Badge variant={variant}>
              {s === "publish" ? "Published" : s === "draft" ? "Draft" : s}
            </Badge>
          )
        },
        className: "hidden lg:table-cell",
      },
      {
        id: "actions",
        cell: ({ row }) => (
          <ActionCell
            item={row.original as unknown as import("@/Components/shared/action-cell").ActionItem}
            baseRoute={route("dashboard.properties.index").replace(/\/+$/, "")}
            itemLabel="Property"
            nonce={nonce}
          />
        ),
        enableSorting: false,
      },
    ]
    return cols
  }, [nonce, settings])

  const handleBulkAction = useCallback((action: string, selectedIds: (number | string)[]) => {
    router.post(route("dashboard.properties.bulk"), {
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

  if (properties.length === 0) {
    return (
      <AuthenticatedLayout header="Properties">
        <Head title="Properties" />
        <div className="space-y-6">
          <div className="flex items-center justify-between">
            <div>
              <h2 className="text-3xl font-bold tracking-tight text-foreground">Properties</h2>
              <p className="text-muted-foreground">Manage your real estate properties</p>
            </div>
            <Button onClick={() => router.visit(route("dashboard.properties.create"))}>
              <Plus className="mr-2 h-4 w-4" />
              Add Property
            </Button>
          </div>
          <div className="flex flex-col items-center justify-center py-12 border border-border rounded-md">
            <Building2 className="h-12 w-12 text-muted-foreground mb-4" />
            <p className="text-lg text-foreground mb-2">No properties found</p>
            <p className="text-muted-foreground mb-4">Create your first property to get started</p>
            <Button onClick={() => router.visit(route("dashboard.properties.create"))}>
              <Plus className="mr-2 h-4 w-4" />
              Create your first property
            </Button>
          </div>
        </div>
      </AuthenticatedLayout>
    )
  }

  return (
    <AuthenticatedLayout header="Properties">
      <Head title="Properties" />
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h2 className="text-3xl font-bold tracking-tight text-foreground">Properties</h2>
            <p className="text-muted-foreground">Manage your real estate properties</p>
          </div>
          <Button onClick={() => router.visit(route("dashboard.properties.create"))}>
            <Plus className="mr-2 h-4 w-4" />
            Add Property
          </Button>
        </div>
        <DataTable
          columns={columns}
          data={properties}
          searchKey="title"
          searchPlaceholder="Search properties..."
          onBulkAction={handleBulkAction}
        />
      </div>
    </AuthenticatedLayout>
  )
}
