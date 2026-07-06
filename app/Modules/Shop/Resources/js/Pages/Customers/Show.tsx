import { Head, Link } from "@inertiajs/react";
import { ArrowLeft, ShoppingCart, CreditCard, Package, CheckCircle } from "lucide-react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
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

interface CustomerStats {
  total_orders: number;
  total_spent: number;
  pending_orders: number;
  completed_orders: number;
}

interface Customer {
  id: number;
  name: string;
  email: string;
  created_at: string;
  stats: CustomerStats;
}

interface Order {
  id: number;
  order_number: string;
  status: string;
  total: string;
  currency: string;
  created_at: string;
  payment_status: string;
}

interface Props {
  customer: Customer;
  orders: { data: Order[]; current_page: number; last_page: number; links: { url: string | null; label: string; active: boolean }[] };
}

function statusBadgeVariant(status: string) {
  switch (status) {
    case "completed": return "default" as const;
    case "processing": return "secondary" as const;
    case "pending": return "outline" as const;
    case "cancelled": return "destructive" as const;
    default: return "secondary" as const;
  }
}

export default function CustomerShow({ customer, orders }: Props) {
  return (
    <AuthenticatedLayout header={customer.name}>
      <Head title={`Customer: ${customer.name}`} />

      <div className="space-y-6">
        <Link href={route("dashboard.shop.customers.index")}>
          <Button variant="ghost" size="sm">
            <ArrowLeft className="mr-1 h-4 w-4" /> Back to Customers
          </Button>
        </Link>

        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <Card>
            <CardHeader className="flex flex-row items-center justify-between pb-2">
              <CardTitle className="text-sm font-medium">Total Orders</CardTitle>
              <ShoppingCart className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{customer.stats.total_orders}</div>
            </CardContent>
          </Card>
          <Card>
            <CardHeader className="flex flex-row items-center justify-between pb-2">
              <CardTitle className="text-sm font-medium">Total Spent</CardTitle>
              <CreditCard className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">
                {new Intl.NumberFormat("en-US", { style: "currency", currency: "EUR" }).format(customer.stats.total_spent)}
              </div>
            </CardContent>
          </Card>
          <Card>
            <CardHeader className="flex flex-row items-center justify-between pb-2">
              <CardTitle className="text-sm font-medium">Pending</CardTitle>
              <Package className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{customer.stats.pending_orders}</div>
            </CardContent>
          </Card>
          <Card>
            <CardHeader className="flex flex-row items-center justify-between pb-2">
              <CardTitle className="text-sm font-medium">Completed</CardTitle>
              <CheckCircle className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{customer.stats.completed_orders}</div>
            </CardContent>
          </Card>
        </div>

        <div>
          <h3 className="text-base font-semibold mb-3">Contact</h3>
          <Card>
            <CardContent className="pt-6 space-y-1">
              <p className="text-sm"><span className="text-muted-foreground">Name:</span> {customer.name}</p>
              <p className="text-sm"><span className="text-muted-foreground">Email:</span> {customer.email}</p>
              <p className="text-sm"><span className="text-muted-foreground">Customer since:</span> {new Date(customer.created_at).toLocaleDateString()}</p>
            </CardContent>
          </Card>
        </div>

        <div>
          <h3 className="text-base font-semibold mb-3">Order History</h3>
          {orders.data.length === 0 ? (
            <Card>
              <CardContent className="py-8 text-center text-sm text-muted-foreground">
                No orders yet.
              </CardContent>
            </Card>
          ) : (
            <>
              <Card>
                <CardContent className="p-0">
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Order</TableHead>
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
                            <Badge variant={statusBadgeVariant(order.status)} className="text-xs">{order.status}</Badge>
                          </TableCell>
                          <TableCell className="text-xs text-muted-foreground">{order.payment_status}</TableCell>
                          <TableCell className="text-right font-medium">
                            {new Intl.NumberFormat("en-US", { style: "currency", currency: order.currency || "EUR" }).format(Number(order.total))}
                          </TableCell>
                          <TableCell className="text-xs text-muted-foreground">
                            {new Date(order.created_at).toLocaleDateString()}
                          </TableCell>
                          <TableCell>
                            <Link href={route("dashboard.shop.orders.show", order.id)}>
                              <Button variant="ghost" size="icon-sm">
                                <Package className="h-3.5 w-3.5" />
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
          )}
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
