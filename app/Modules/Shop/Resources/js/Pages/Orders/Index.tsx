import { Head, Link, router } from "@inertiajs/react";
import { Search, X, Eye, ShoppingCart, Users, Download } from "lucide-react";
import { useState } from "react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Button } from "@/Components/ui/button";
import { Badge } from "@/Components/ui/badge";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/Components/ui/tabs";
import { PageHeader, FilterBar, PaginationLinks, TableList } from "../../components/templates";

interface OrderItem {
  id: number;
  product_name: string;
  product_sku: string | null;
  quantity: number;
  unit_price: string;
  total: string;
}

interface Order {
  id: number;
  order_number: string;
  status: string;
  subtotal: string;
  tax_total: string;
  shipping_total: string;
  discount_total: string;
  total: string;
  currency: string;
  payment_status: string;
  customer_name: string | null;
  customer_email: string | null;
  created_at: string;
  user: { id: number; name: string; email: string } | null;
  items: OrderItem[];
}

interface Customer {
  id: number;
  name: string;
  email: string;
  orders_count: number;
  total_spent: number;
  created_at: string;
}

interface OrdersIndexProps {
  orders?: { data: Order[]; current_page: number; last_page: number; links: { url: string | null; label: string; active: boolean }[] };
  customers?: { data: Customer[]; current_page: number; last_page: number; links: { url: string | null; label: string; active: boolean }[] };
  filters: { search?: string; status?: string };
}

const ORDER_STATUSES = [
  { value: "draft", label: "Draft" },
  { value: "pending", label: "Pending" },
  { value: "on-hold", label: "On Hold" },
  { value: "confirmed", label: "Confirmed" },
  { value: "processing", label: "Processing" },
  { value: "completed", label: "Completed" },
  { value: "cancelled", label: "Cancelled" },
  { value: "refunded", label: "Refunded" },
  { value: "failed", label: "Failed" },
];

function statusBadgeVariant(status: string) {
  switch (status) {
    case "completed": return "default" as const;
    case "processing": return "secondary" as const;
    case "pending": return "outline" as const;
    case "confirmed": return "secondary" as const;
    case "draft": return "outline" as const;
    case "on-hold": return "warning" as const;
    case "cancelled": return "destructive" as const;
    case "refunded": return "destructive" as const;
    case "failed": return "destructive" as const;
    default: return "secondary" as const;
  }
}

function paymentBadgeVariant(status: string) {
  switch (status) {
    case "paid": return "default" as const;
    case "pending": return "outline" as const;
    case "failed": return "destructive" as const;
    case "refunded": return "secondary" as const;
    default: return "secondary" as const;
  }
}

export default function OrdersIndex({ orders, customers, filters }: OrdersIndexProps) {
  const hasOrdersData = !!orders;
  const [search, setSearch] = useState(filters.search || "");

  const applyFilter = (key: string, value: string) => {
    const params = { ...filters, [key]: value || undefined };
    const routeName = hasOrdersData
      ? "dashboard.shop.orders.index"
      : "dashboard.shop.customers.index";
    router.get(route(routeName), params, { preserveState: true, replace: true });
  };

  const clearFilters = () => {
    setSearch("");
    router.get(
      hasOrdersData
        ? route("dashboard.shop.orders.index")
        : route("dashboard.shop.customers.index"),
      {},
      { preserveState: true, replace: true },
    );
  };

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    applyFilter("search", search);
  };

  const hasFilters = !!(filters.search || filters.status);

  return (
    <AuthenticatedLayout header="Orders">
      <Head title="Orders" />

      <div className="space-y-6">
        <PageHeader title="Orders & Customers" />

        <Tabs defaultValue={hasOrdersData ? "orders" : "customers"}>
          <TabsList>
            <TabsTrigger value="orders" asChild>
              <Link href={route("dashboard.shop.orders.index")} preserveState>
                Orders
              </Link>
            </TabsTrigger>
            <TabsTrigger value="customers" asChild>
              <Link href={route("dashboard.shop.customers.index")} preserveState>
                Customers
              </Link>
            </TabsTrigger>
          </TabsList>

          <TabsContent value="orders" className="mt-6 space-y-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
              <FilterBar
                searchPlaceholder="Search orders..."
                searchValue={hasOrdersData ? search : ""}
                onSearchChange={setSearch}
                onSubmit={handleSearch}
                filters={[
                  { key: "status", label: "Status", placeholder: "All statuses", options: ORDER_STATUSES },
                ]}
                filterValues={filters}
                onFilterChange={applyFilter}
                onClear={clearFilters}
                showClear={hasFilters}
              />
              <a href={route("dashboard.shop.orders.export")}>
                <Button variant="outline" size="sm">
                  <Download className="mr-1 h-3 w-3" /> Export
                </Button>
              </a>
            </div>

            {orders && (
              <>
                <TableList
                  columns={[
                    {
                      key: "order",
                      header: "Order",
                      cell: (o: Order) => <span className="font-mono text-xs font-medium">{o.order_number}</span>,
                    },
                    {
                      key: "customer",
                      header: "Customer",
                      cell: (o: Order) => (
                        <>
                          <p className="text-sm font-medium">{o.customer_name || o.user?.name || "—"}</p>
                          <p className="text-xs text-muted-foreground">{o.customer_email || o.user?.email}</p>
                        </>
                      ),
                    },
                    {
                      key: "status",
                      header: "Status",
                      cell: (o: Order) => (
                        <Badge variant={statusBadgeVariant(o.status)} className="text-xs">{o.status}</Badge>
                      ),
                    },
                    {
                      key: "payment",
                      header: "Payment",
                      cell: (o: Order) => (
                        <Badge variant={paymentBadgeVariant(o.payment_status)} className="text-xs">{o.payment_status}</Badge>
                      ),
                    },
                    {
                      key: "total",
                      header: "Total",
                      className: "text-right",
                      cell: (o: Order) => (
                        <span className="font-medium">
                          {new Intl.NumberFormat("en-US", { style: "currency", currency: o.currency || "EUR" }).format(Number(o.total))}
                        </span>
                      ),
                    },
                    {
                      key: "date",
                      header: "Date",
                      cell: (o: Order) => (
                        <span className="text-xs text-muted-foreground">{new Date(o.created_at).toLocaleDateString()}</span>
                      ),
                    },
                    {
                      key: "actions",
                      header: "",
                      className: "w-12",
                      cell: (o: Order) => (
                        <Link href={route("dashboard.shop.orders.show", o.id)}>
                          <Button variant="ghost" size="icon-sm"><Eye className="h-3.5 w-3.5" /></Button>
                        </Link>
                      ),
                    },
                  ]}
                  data={orders.data}
                  emptyTitle="No orders found"
                  emptyDescription="No orders match your criteria. Try adjusting your filters."
                  emptyIcon={<ShoppingCart />}
                />

                <PaginationLinks links={orders.links} />
              </>
            )}
          </TabsContent>

          <TabsContent value="customers" className="mt-6 space-y-4">
            <FilterBar
              searchPlaceholder="Search customers..."
              searchValue={!hasOrdersData ? search : ""}
              onSearchChange={setSearch}
              onSubmit={(e) => { e.preventDefault(); applyFilter("search", search); }}
              filters={[]}
              filterValues={filters}
              onFilterChange={applyFilter}
              onClear={clearFilters}
              showClear={!!filters.search}
            />

            {customers && (
              <>
                <TableList
                  columns={[
                    {
                      key: "name",
                      header: "Name",
                      cell: (c: Customer) => <span className="font-medium">{c.name}</span>,
                    },
                    {
                      key: "email",
                      header: "Email",
                      cell: (c: Customer) => <span className="text-muted-foreground">{c.email}</span>,
                    },
                    {
                      key: "orders",
                      header: "Orders",
                      className: "text-center",
                      cell: (c: Customer) => <span>{c.orders_count}</span>,
                    },
                    {
                      key: "total_spent",
                      header: "Total Spent",
                      className: "text-right",
                      cell: (c: Customer) => (
                        <span className="font-medium">
                          {new Intl.NumberFormat("en-US", { style: "currency", currency: "EUR" }).format(c.total_spent)}
                        </span>
                      ),
                    },
                    {
                      key: "created_at",
                      header: "Customer Since",
                      cell: (c: Customer) => (
                        <span className="text-xs text-muted-foreground">{new Date(c.created_at).toLocaleDateString()}</span>
                      ),
                    },
                  ]}
                  data={customers.data}
                  emptyTitle="No customers yet"
                  emptyDescription="Customers will appear here once they place their first order."
                  emptyIcon={<Users />}
                />

                <PaginationLinks links={customers.links} />
              </>
            )}
          </TabsContent>
        </Tabs>
      </div>
    </AuthenticatedLayout>
  );
}
