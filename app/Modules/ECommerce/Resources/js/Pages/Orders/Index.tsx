import { Head, Link, router } from "@inertiajs/react";
import { Search, X, Eye, ShoppingCart, Users } from "lucide-react";
import { useState } from "react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Button } from "@/Components/ui/button";
import { Card, CardContent } from "@/Components/ui/card";
import { Badge } from "@/Components/ui/badge";
import { Input } from "@/Components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/Components/ui/select";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/Components/ui/table";
import {
  Pagination,
  PaginationContent,
  PaginationItem,
  PaginationLink,
  PaginationNext,
  PaginationPrevious,
} from "@/Components/ui/pagination";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/Components/ui/tabs";
import { Empty, EmptyHeader, EmptyTitle, EmptyDescription, EmptyMedia } from "@/Components/ui/empty";

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

const ORDER_STATUSES = ["pending", "confirmed", "processing", "completed", "cancelled", "refunded"];

function statusBadgeVariant(status: string) {
  switch (status) {
    case "completed": return "default" as const;
    case "processing": return "secondary" as const;
    case "pending": return "outline" as const;
    case "confirmed": return "secondary" as const;
    case "cancelled": return "destructive" as const;
    case "refunded": return "destructive" as const;
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
      ? "dashboard.ecommerce.orders.index"
      : "dashboard.ecommerce.customers.index";
    router.get(route(routeName), params, { preserveState: true, replace: true });
  };

  const clearFilters = () => {
    setSearch("");
    router.get(
      hasOrdersData
        ? route("dashboard.ecommerce.orders.index")
        : route("dashboard.ecommerce.customers.index"),
      {},
      { preserveState: true, replace: true },
    );
  };

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    applyFilter("search", search);
  };

  const hasFilters = filters.search || filters.status;

  return (
    <AuthenticatedLayout header="Orders">
      <Head title="Orders" />

      <div className="space-y-6">
        <h2 className="text-lg font-semibold">Orders & Customers</h2>

        <Tabs defaultValue={hasOrdersData ? "orders" : "customers"}>
          <TabsList>
            <TabsTrigger value="orders" asChild>
              <Link href={route("dashboard.ecommerce.orders.index")} preserveState>
                Orders
              </Link>
            </TabsTrigger>
            <TabsTrigger value="customers" asChild>
              <Link href={route("dashboard.ecommerce.customers.index")} preserveState>
                Customers
              </Link>
            </TabsTrigger>
          </TabsList>

          <TabsContent value="orders" className="mt-6 space-y-4">
            <div className="flex flex-wrap items-center gap-3">
              <form onSubmit={handleSearch} className="flex items-center gap-2">
                <div className="relative">
                  <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                  <Input
                    placeholder="Search orders..."
                    value={hasOrdersData ? search : ""}
                    onChange={(e) => setSearch(e.target.value)}
                    className="w-60 pl-8"
                  />
                </div>
                <Button type="submit" variant="secondary" size="sm">Search</Button>
              </form>

              <Select
                value={filters.status || ""}
                onValueChange={(v) => applyFilter("status", v)}
              >
                <SelectTrigger className="w-40">
                  <SelectValue placeholder="All statuses" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="">All statuses</SelectItem>
                  {ORDER_STATUSES.map((s) => (
                    <SelectItem key={s} value={s}>{s.charAt(0).toUpperCase() + s.slice(1)}</SelectItem>
                  ))}
                </SelectContent>
              </Select>

              {hasFilters && (
                <Button variant="ghost" size="sm" onClick={clearFilters}>
                  <X className="mr-1 h-3 w-3" /> Clear
                </Button>
              )}
            </div>

            {orders && orders.data.length === 0 ? (
              <Empty>
                <EmptyHeader>
                  <EmptyMedia variant="icon"><ShoppingCart /></EmptyMedia>
                  <EmptyTitle>No orders found</EmptyTitle>
                  <EmptyDescription>No orders match your criteria. Try adjusting your filters.</EmptyDescription>
                </EmptyHeader>
              </Empty>
            ) : orders ? (
              <>
                <Card>
                  <CardContent className="p-0">
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead>Order</TableHead>
                          <TableHead>Customer</TableHead>
                          <TableHead>Status</TableHead>
                          <TableHead>Payment</TableHead>
                          <TableHead className="text-right">Total</TableHead>
                          <TableHead>Date</TableHead>
                          <TableHead className="w-12"></TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {orders.data.map((order) => (
                          <TableRow key={order.id}>
                            <TableCell className="font-mono text-xs font-medium">{order.order_number}</TableCell>
                            <TableCell>
                              <p className="text-sm font-medium">{order.customer_name || order.user?.name || "—"}</p>
                              <p className="text-xs text-muted-foreground">{order.customer_email || order.user?.email}</p>
                            </TableCell>
                            <TableCell>
                              <Badge variant={statusBadgeVariant(order.status)} className="text-xs">
                                {order.status}
                              </Badge>
                            </TableCell>
                            <TableCell>
                              <Badge variant={paymentBadgeVariant(order.payment_status)} className="text-xs">
                                {order.payment_status}
                              </Badge>
                            </TableCell>
                            <TableCell className="text-right font-medium">
                              {new Intl.NumberFormat("en-US", { style: "currency", currency: order.currency || "EUR" }).format(Number(order.total))}
                            </TableCell>
                            <TableCell className="text-xs text-muted-foreground">
                              {new Date(order.created_at).toLocaleDateString()}
                            </TableCell>
                            <TableCell>
                              <Link href={route("dashboard.ecommerce.orders.show", order.id)}>
                                <Button variant="ghost" size="icon-sm">
                                  <Eye className="h-3.5 w-3.5" />
                                </Button>
                              </Link>
                            </TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  </CardContent>
                </Card>

                {orders.last_page > 1 && (
                  <Pagination>
                    <PaginationContent>
                      {orders.links.map((link, i) => {
                        if (link.label.includes("Previous")) {
                          return <PaginationItem key={i}><PaginationPrevious href={link.url ?? "#"} /></PaginationItem>;
                        }
                        if (link.label.includes("Next")) {
                          return <PaginationItem key={i}><PaginationNext href={link.url ?? "#"} /></PaginationItem>;
                        }
                        return (
                          <PaginationItem key={i}>
                            <PaginationLink href={link.url ?? "#"} isActive={link.active}>{link.label}</PaginationLink>
                          </PaginationItem>
                        );
                      })}
                    </PaginationContent>
                  </Pagination>
                )}
              </>
            ) : null}
          </TabsContent>

          <TabsContent value="customers" className="mt-6 space-y-4">
            <div className="flex flex-wrap items-center gap-3">
              <form onSubmit={(e) => { e.preventDefault(); applyFilter("search", search); }} className="flex items-center gap-2">
                <div className="relative">
                  <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                  <Input
                    placeholder="Search customers..."
                    value={!hasOrdersData ? search : ""}
                    onChange={(e) => setSearch(e.target.value)}
                    className="w-60 pl-8"
                  />
                </div>
                <Button type="submit" variant="secondary" size="sm">Search</Button>
              </form>
            </div>

            {customers && customers.data.length === 0 ? (
              <Empty>
                <EmptyHeader>
                  <EmptyMedia variant="icon"><Users /></EmptyMedia>
                  <EmptyTitle>No customers yet</EmptyTitle>
                  <EmptyDescription>Customers will appear here once they place their first order.</EmptyDescription>
                </EmptyHeader>
              </Empty>
            ) : customers ? (
              <>
                <Card>
                  <CardContent className="p-0">
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead>Name</TableHead>
                          <TableHead>Email</TableHead>
                          <TableHead className="text-center">Orders</TableHead>
                          <TableHead className="text-right">Total Spent</TableHead>
                          <TableHead>Customer Since</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {customers.data.map((customer) => (
                          <TableRow key={customer.id}>
                            <TableCell className="font-medium">{customer.name}</TableCell>
                            <TableCell className="text-muted-foreground">{customer.email}</TableCell>
                            <TableCell className="text-center">{customer.orders_count}</TableCell>
                            <TableCell className="text-right font-medium">
                              {new Intl.NumberFormat("en-US", { style: "currency", currency: "EUR" }).format(customer.total_spent)}
                            </TableCell>
                            <TableCell className="text-xs text-muted-foreground">
                              {new Date(customer.created_at).toLocaleDateString()}
                            </TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  </CardContent>
                </Card>

                {customers.last_page > 1 && (
                  <Pagination>
                    <PaginationContent>
                      {customers.links.map((link, i) => {
                        if (link.label.includes("Previous")) {
                          return <PaginationItem key={i}><PaginationPrevious href={link.url ?? "#"} /></PaginationItem>;
                        }
                        if (link.label.includes("Next")) {
                          return <PaginationItem key={i}><PaginationNext href={link.url ?? "#"} /></PaginationItem>;
                        }
                        return (
                          <PaginationItem key={i}>
                            <PaginationLink href={link.url ?? "#"} isActive={link.active}>{link.label}</PaginationLink>
                          </PaginationItem>
                        );
                      })}
                    </PaginationContent>
                  </Pagination>
                )}
              </>
            ) : null}
          </TabsContent>
        </Tabs>
      </div>
    </AuthenticatedLayout>
  );
}
