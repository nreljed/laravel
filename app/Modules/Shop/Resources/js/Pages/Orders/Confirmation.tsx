import { Head, Link } from "@inertiajs/react";
import { CheckCircle, Package, ArrowRight, CreditCard, Info } from "lucide-react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { useAuth } from "@/hooks/use-auth";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Button } from "@/Components/ui/button";
import { Separator } from "@/Components/ui/separator";

interface OrderItem {
  id: number;
  product_name: string;
  quantity: number;
  unit_price: number;
  subtotal: number;
  variant_label: string | null;
}

interface Order {
  id: number;
  order_number: string;
  status: string;
  total: number;
  subtotal: number;
  shipping_total: number;
  items: OrderItem[];
  billing_address: Record<string, string> | null;
  shipping_address: Record<string, string> | null;
  payment_method: string;
  created_at: string;
}

interface Props {
  order: Order;
}

export default function Confirmation({ order }: Props) {
  const { isLoggedIn } = useAuth();

  const content = (
    <div className="mx-auto max-w-2xl space-y-6">
      <div className="text-center space-y-2">
        <CheckCircle className="mx-auto h-12 w-12 text-green-500" />
        <h1 className="text-2xl font-bold">Order Confirmed!</h1>
        <p className="text-muted-foreground">
          Thank you for your order. Your order number is <strong className="text-foreground">{order.order_number}</strong>.
        </p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Package className="h-4 w-4" />
            Order Summary
          </CardTitle>
        </CardHeader>
        <CardContent className="space-y-3">
          {order.items.map((item) => (
            <div key={item.id} className="flex justify-between text-sm">
              <span className="truncate pr-2">
                {item.product_name}
                {item.variant_label && <span className="text-muted-foreground"> ({item.variant_label})</span>}
                {" × "}{item.quantity}
              </span>
              <span className="tabular-nums shrink-0">
                {new Intl.NumberFormat("en-US", { style: "currency", currency: "EUR" }).format(item.subtotal)}
              </span>
            </div>
          ))}
          <Separator />
          <div className="flex justify-between text-sm">
            <span className="text-muted-foreground">Subtotal</span>
            <span className="tabular-nums">{new Intl.NumberFormat("en-US", { style: "currency", currency: "EUR" }).format(Number(order.subtotal))}</span>
          </div>
          <div className="flex justify-between text-sm">
            <span className="text-muted-foreground">Shipping</span>
            <span className="tabular-nums">{Number(order.shipping_total) === 0 ? "Free" : new Intl.NumberFormat("en-US", { style: "currency", currency: "EUR" }).format(Number(order.shipping_total))}</span>
          </div>
          <Separator />
          <div className="flex justify-between font-medium text-base">
            <span>Total</span>
            <span className="tabular-nums">{new Intl.NumberFormat("en-US", { style: "currency", currency: "EUR" }).format(Number(order.total))}</span>
          </div>
        </CardContent>
      </Card>

      {order.payment_method === "manual" && (
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <CreditCard className="h-4 w-4" />
              Payment Instructions
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-2 text-sm">
            <div className="flex gap-2 text-muted-foreground">
              <Info className="h-4 w-4 shrink-0 mt-0.5" />
              <p>Your order has been placed and is pending payment. Please transfer the total amount to complete your order. Your order will be processed once payment is confirmed by our team.</p>
            </div>
          </CardContent>
        </Card>
      )}

      <div className="grid grid-cols-2 gap-4">
        <Card>
          <CardHeader>
            <CardTitle className="text-sm">Billing Address</CardTitle>
          </CardHeader>
          <CardContent className="text-sm text-muted-foreground space-y-0.5">
            {order.billing_address && (
              <>
                <p>{order.billing_address.first_name} {order.billing_address.last_name}</p>
                {order.billing_address.company && <p>{order.billing_address.company}</p>}
                <p>{order.billing_address.address_line1}</p>
                {order.billing_address.address_line2 && <p>{order.billing_address.address_line2}</p>}
                <p>{[order.billing_address.city, order.billing_address.state, order.billing_address.postcode].filter(Boolean).join(", ")}</p>
                <p>{order.billing_address.country}</p>
                {order.billing_address.phone && <p>{order.billing_address.phone}</p>}
              </>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="text-sm">Shipping Address</CardTitle>
          </CardHeader>
          <CardContent className="text-sm text-muted-foreground space-y-0.5">
            {order.shipping_address && (
              <>
                <p>{order.shipping_address.first_name} {order.shipping_address.last_name}</p>
                {order.shipping_address.company && <p>{order.shipping_address.company}</p>}
                <p>{order.shipping_address.address_line1}</p>
                {order.shipping_address.address_line2 && <p>{order.shipping_address.address_line2}</p>}
                <p>{[order.shipping_address.city, order.shipping_address.state, order.shipping_address.postcode].filter(Boolean).join(", ")}</p>
                <p>{order.shipping_address.country}</p>
                {order.shipping_address.phone && <p>{order.shipping_address.phone}</p>}
              </>
            )}
          </CardContent>
        </Card>
      </div>

      <div className="flex justify-center">
        <Button asChild>
          <Link href={isLoggedIn ? route("dashboard.shop.orders.show", order.id) : "/"}>
            {isLoggedIn ? "View Order Details" : "Continue Shopping"}
            <ArrowRight className="ml-2 h-4 w-4" />
          </Link>
        </Button>
      </div>
    </div>
  );

  if (isLoggedIn) {
    return (
      <AuthenticatedLayout header="Order Confirmation">
        <Head title="Order Confirmation" />
        {content}
      </AuthenticatedLayout>
    );
  }

  return (
    <>
      <Head title="Order Confirmation" />
      <div className="min-h-screen bg-background p-4 md:p-8">
        <div className="mx-auto max-w-2xl">
          {content}
        </div>
      </div>
    </>
  );
}
