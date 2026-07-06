import { Link } from "@inertiajs/react";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Badge } from "@/Components/ui/badge";
import { ShoppingCart } from "lucide-react";

interface RecentOrder {
  id: number;
  order_number: string;
  customer_name: string;
  total: number;
  status: string;
  items_count: number;
  created_at: string;
}

interface RecentOrdersTableProps {
  orders: RecentOrder[];
}

const statusColors: Record<string, string> = {
  pending: "bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400",
  confirmed: "bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400",
  processing: "bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-400",
  completed: "bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400",
  cancelled: "bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400",
  refunded: "bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400",
};

const currency = new Intl.NumberFormat("en-US", { style: "currency", currency: "EUR" });

export default function RecentOrdersTable({ orders }: RecentOrdersTableProps) {
  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <ShoppingCart className="h-4 w-4" />
          Recent Orders
        </CardTitle>
      </CardHeader>
      <CardContent className="p-0">
        {orders.length === 0 ? (
          <div className="py-8 text-center text-sm text-muted-foreground">No orders yet.</div>
        ) : (
          <div className="divide-y">
            {orders.map((order) => (
              <div key={order.id} className="flex items-center justify-between px-6 py-3 text-sm">
                <div className="flex items-center gap-3 min-w-0">
                  <Link
                    href={route("dashboard.shop.orders.edit", order.id)}
                    className="font-medium hover:underline truncate"
                  >
                    {order.order_number}
                  </Link>
                  <span className="text-muted-foreground truncate hidden sm:inline">
                    {order.customer_name}
                  </span>
                </div>
                <div className="flex items-center gap-3 shrink-0">
                  <span className="text-muted-foreground text-xs">{order.items_count} items</span>
                  <span className="font-medium tabular-nums">{currency.format(order.total)}</span>
                  <Badge
                    variant="outline"
                    className={`text-xs capitalize ${statusColors[order.status] ?? ""}`}
                  >
                    {order.status}
                  </Badge>
                  <span className="text-xs text-muted-foreground hidden lg:inline">
                    {order.created_at}
                  </span>
                </div>
              </div>
            ))}
          </div>
        )}
      </CardContent>
    </Card>
  );
}
