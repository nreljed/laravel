import { Head, Link } from "@inertiajs/react";
import { ArrowLeft, Package, CreditCard, MapPin, Truck, Receipt } from "lucide-react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
import { Separator } from "@/Components/ui/separator";

interface OrderItem {
  id: number;
  product_name: string;
  product_sku: string | null;
  unit_price: number;
  quantity: number;
  total: number;
  image_url: string | null;
  variant_label: string | null;
}

interface OrderAction {
  id: number;
  action: string;
  description: string;
  created_at: string;
}

interface Props {
  order: {
    id: number;
    order_number: string;
    status: string;
    subtotal: number;
    discount_total: number;
    shipping_total: number;
    tax_total: number;
    total: number;
    payment_method: string;
    payment_status: string;
    billing_address: Record<string, string> | null;
    shipping_address: Record<string, string> | null;
    customer_name: string;
    customer_email: string;
    paid_at: string | null;
    shipped_at: string | null;
    created_at: string;
    items: OrderItem[];
    actions: OrderAction[];
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

function AddressDisplay({ label, address }: { label: string; address: Record<string, string> | null }) {
  if (!address) return null;
  const lines = [
    address.first_name && address.last_name ? `${address.first_name} ${address.last_name}` : null,
    address.company,
    address.address_line1,
    address.address_line2,
    address.city && address.state ? `${address.city}, ${address.state}` : address.city,
    address.postcode,
    address.country,
    address.phone,
  ].filter(Boolean);

  return (
    <div>
      <h4 className="text-sm font-medium text-muted-foreground mb-1">{label}</h4>
      {lines.map((line, i) => (
        <p key={i} className="text-sm">{line}</p>
      ))}
    </div>
  );
}

export default function MyOrdersShow({ order }: Props) {
  const formatCurrency = (val: number) =>
    new Intl.NumberFormat("en-US", { style: "currency", currency: "EUR" }).format(val);

  return (
    <AuthenticatedLayout
      header={
        <div className="flex items-center gap-3">
          <Link href={route("dashboard.my.orders.index")}>
            <Button variant="ghost" size="icon-sm">
              <ArrowLeft className="h-4 w-4" />
            </Button>
          </Link>
          <span>Order {order.order_number}</span>
        </div>
      }
    >
      <Head title={`Order ${order.order_number}`} />

      <div className="space-y-6 max-w-3xl">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2">
            <h2 className="text-lg font-semibold">{order.order_number}</h2>
            <Badge className={statusBadge[order.status] || "bg-gray-100 text-gray-800"}>
              {order.status}
            </Badge>
          </div>
          <span className="text-sm text-muted-foreground">
            Placed {new Date(order.created_at).toLocaleDateString()}
          </span>
        </div>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2 text-base">
              <Package className="h-4 w-4" /> Items
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="divide-y">
              {order.items.map((item) => (
                <div key={item.id} className="flex items-center gap-3 py-3 first:pt-0 last:pb-0">
                  {item.image_url && (
                    <img src={item.image_url} alt={item.product_name} className="h-14 w-14 rounded-md object-cover shrink-0" />
                  )}
                  <div className="flex-1 min-w-0">
                    <p className="font-medium text-sm truncate">{item.product_name}</p>
                    {item.variant_label && (
                      <p className="text-xs text-muted-foreground">{item.variant_label}</p>
                    )}
                    {item.product_sku && (
                      <p className="text-xs text-muted-foreground">SKU: {item.product_sku}</p>
                    )}
                    <p className="text-xs text-muted-foreground">Qty: {item.quantity}</p>
                  </div>
                  <p className="text-sm font-medium tabular-nums">{formatCurrency(item.total)}</p>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>

        <div className="grid gap-4 sm:grid-cols-2">
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2 text-base">
                <MapPin className="h-4 w-4" /> Shipping Address
              </CardTitle>
            </CardHeader>
            <CardContent>
              <AddressDisplay label="" address={order.shipping_address} />
            </CardContent>
          </Card>
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2 text-base">
                <Receipt className="h-4 w-4" /> Billing Address
              </CardTitle>
            </CardHeader>
            <CardContent>
              <AddressDisplay label="" address={order.billing_address} />
            </CardContent>
          </Card>
        </div>

        <Card>
          <CardHeader>
            <CardTitle className="text-base">Order Summary</CardTitle>
          </CardHeader>
          <CardContent className="space-y-2">
            <div className="flex justify-between text-sm">
              <span className="text-muted-foreground">Subtotal</span>
              <span className="tabular-nums">{formatCurrency(order.subtotal)}</span>
            </div>
            {order.discount_total > 0 && (
              <div className="flex justify-between text-sm">
                <span className="text-muted-foreground">Discount</span>
                <span className="tabular-nums text-destructive">-{formatCurrency(order.discount_total)}</span>
              </div>
            )}
            <div className="flex justify-between text-sm">
              <span className="text-muted-foreground">Shipping</span>
              <span className="tabular-nums">{order.shipping_total > 0 ? formatCurrency(order.shipping_total) : "Free"}</span>
            </div>
            <div className="flex justify-between text-sm">
              <span className="text-muted-foreground">Tax</span>
              <span className="tabular-nums">{formatCurrency(order.tax_total)}</span>
            </div>
            <Separator />
            <div className="flex justify-between font-semibold">
              <span>Total</span>
              <span className="tabular-nums">{formatCurrency(order.total)}</span>
            </div>
          </CardContent>
        </Card>

        {order.actions && order.actions.length > 0 && (
          <Card>
            <CardHeader>
              <CardTitle className="text-base">Order Timeline</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="space-y-3">
                {order.actions.map((action) => (
                  <div key={action.id} className="flex items-start gap-3">
                    <div className="mt-1 h-2 w-2 rounded-full bg-primary shrink-0" />
                    <div>
                      <p className="text-sm font-medium capitalize">{action.action}</p>
                      {action.description && (
                        <p className="text-xs text-muted-foreground">{action.description}</p>
                      )}
                      <p className="text-xs text-muted-foreground">{new Date(action.created_at).toLocaleString()}</p>
                    </div>
                  </div>
                ))}
              </div>
            </CardContent>
          </Card>
        )}

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2 text-base">
              <CreditCard className="h-4 w-4" /> Payment
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-1 text-sm">
            <p><span className="text-muted-foreground">Method:</span> {order.payment_method === "stripe" ? "Credit Card (Stripe)" : "Offline / Manual"}</p>
            <p><span className="text-muted-foreground">Status:</span> {order.payment_status}</p>
          </CardContent>
        </Card>
      </div>
    </AuthenticatedLayout>
  );
}
