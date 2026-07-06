import { Head, Link } from "@inertiajs/react";
import { Package, ArrowRight, Clock, CreditCard } from "lucide-react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Badge } from "@/Components/ui/badge";
import { Empty, EmptyHeader, EmptyTitle, EmptyDescription } from "@/Components/ui/empty";
import { Button } from "@/Components/ui/button";

interface Order {
  id: number;
  order_number: string;
  status: string;
  total: number;
  items_count: number;
  created_at: string;
  created_at_relative: string;
}

interface Props {
  orders: {
    data: Order[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
  };
}

const statusBadge: Record<string, string> = {
  pending: "bg-yellow-100 text-yellow-800",
  confirmed: "bg-blue-100 text-blue-800",
  processing: "bg-indigo-100 text-indigo-800",
  completed: "bg-green-100 text-green-800",
  cancelled: "bg-red-100 text-red-800",
  refunded: "bg-purple-100 text-purple-800",
};

export default function MyOrdersIndex({ orders }: Props) {
  return (
    <AuthenticatedLayout header="My Orders">
      <Head title="My Orders" />

      {orders.data.length === 0 ? (
        <Card>
          <CardContent className="py-16">
            <Empty>
              <EmptyHeader>
                <Package className="mx-auto h-12 w-12 text-muted-foreground" />
                <EmptyTitle>No orders yet</EmptyTitle>
                <EmptyDescription>When you place an order, it will appear here.</EmptyDescription>
              </EmptyHeader>
              <div className="mt-6">
                <Link href={route("store.index")}>
                  <Button>
                    <Package className="mr-2 h-4 w-4" />
                    Start Shopping
                  </Button>
                </Link>
              </div>
            </Empty>
          </CardContent>
        </Card>
      ) : (
        <div className="space-y-4">
          {orders.data.map((order) => (
            <Link key={order.id} href={route("dashboard.my.orders.show", order.id)}>
              <Card className="hover:shadow-md transition-shadow cursor-pointer">
                <CardContent className="flex items-center justify-between p-4">
                  <div className="space-y-1">
                    <div className="flex items-center gap-2">
                      <span className="font-semibold">{order.order_number}</span>
                      <Badge className={statusBadge[order.status] || "bg-gray-100 text-gray-800"}>
                        {order.status}
                      </Badge>
                    </div>
                    <div className="flex items-center gap-3 text-sm text-muted-foreground">
                      <span className="flex items-center gap-1">
                        <Clock className="h-3.5 w-3.5" />
                        {order.created_at_relative}
                      </span>
                      <span className="flex items-center gap-1">
                        <Package className="h-3.5 w-3.5" />
                        {order.items_count} item{order.items_count !== 1 ? "s" : ""}
                      </span>
                    </div>
                  </div>
                  <div className="flex items-center gap-3">
                    <span className="font-semibold tabular-nums">
                      {new Intl.NumberFormat("en-US", { style: "currency", currency: "EUR" }).format(order.total)}
                    </span>
                    <ArrowRight className="h-4 w-4 text-muted-foreground" />
                  </div>
                </CardContent>
              </Card>
            </Link>
          ))}

          {orders.last_page > 1 && (
            <div className="flex justify-center gap-2 mt-6">
              {orders.prev_page_url && (
                <Link href={orders.prev_page_url}>
                  <Button variant="outline" size="sm">Previous</Button>
                </Link>
              )}
              {orders.next_page_url && (
                <Link href={orders.next_page_url}>
                  <Button variant="outline" size="sm">Next</Button>
                </Link>
              )}
            </div>
          )}
        </div>
      )}
    </AuthenticatedLayout>
  );
}
