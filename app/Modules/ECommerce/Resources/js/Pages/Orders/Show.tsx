import { Head, useForm } from "@inertiajs/react";
import { toast } from "sonner";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/Components/ui/card";
import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
import { Textarea } from "@/Components/ui/textarea";
import { Label } from "@/Components/ui/label";
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
import { Separator } from "@/Components/ui/separator";

interface OrderItem {
  id: number;
  product_id: number | null;
  product_name: string;
  product_sku: string | null;
  unit_price: string;
  quantity: number;
  subtotal: string;
  tax_total: string;
  total: string;
  variant_label: string | null;
  image_url: string | null;
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
  payment_method: string | null;
  payment_status: string;
  notes: string | null;
  customer_name: string | null;
  customer_email: string | null;
  customer_phone: string | null;
  billing_address: Record<string, string> | null;
  shipping_address: Record<string, string> | null;
  paid_at: string | null;
  shipped_at: string | null;
  created_at: string;
  user: { id: number; name: string; email: string } | null;
  items: OrderItem[];
}

interface OrdersShowProps {
  order: Order;
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

export default function OrdersShow({ order }: OrdersShowProps) {
  const form = useForm({
    status: order.status,
    notes: order.notes || "",
  });

  const handleStatusUpdate = (e: React.FormEvent) => {
    e.preventDefault();
    form.patch(route("dashboard.ecommerce.orders.update-status", order.id), {
      preserveScroll: true,
      onSuccess: () => toast.success("Order status updated."),
      onError: () => toast.error("Failed to update status."),
    });
  };

  const fmtCurrency = (val: string | number) => {
    return new Intl.NumberFormat("en-US", {
      style: "currency",
      currency: order.currency || "EUR",
    }).format(Number(val));
  };

  const addressLines = (addr: Record<string, string> | null) => {
    if (!addr) return ["—"];
    return [
      addr.line1,
      addr.line2,
      [addr.city, addr.state, addr.zip].filter(Boolean).join(", "),
      addr.country,
    ].filter(Boolean);
  };

  return (
    <AuthenticatedLayout header={`Order ${order.order_number}`}>
      <Head title={`Order ${order.order_number}`} />

      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h2 className="text-lg font-semibold font-mono">{order.order_number}</h2>
            <p className="text-sm text-muted-foreground">
              Placed on {new Date(order.created_at).toLocaleString()}
            </p>
          </div>
          <div className="flex items-center gap-2">
            <Badge variant={statusBadgeVariant(order.status)} className="text-sm px-3 py-1">
              {order.status}
            </Badge>
            <Badge variant={paymentBadgeVariant(order.payment_status)} className="text-sm px-3 py-1">
              {order.payment_status}
            </Badge>
          </div>
        </div>

        <div className="grid gap-6 lg:grid-cols-3">
          <div className="lg:col-span-2 space-y-6">
            <Card>
              <CardHeader>
                <CardTitle>Order Items</CardTitle>
              </CardHeader>
              <CardContent className="p-0">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Product</TableHead>
                      <TableHead>SKU</TableHead>
                      <TableHead className="text-center">Qty</TableHead>
                      <TableHead className="text-right">Price</TableHead>
                      <TableHead className="text-right">Total</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {order.items.map((item) => (
                      <TableRow key={item.id}>
                        <TableCell>
                          <div className="flex items-center gap-3">
                            {item.image_url && (
                              <img src={item.image_url} alt="" className="w-10 h-10 rounded object-cover" />
                            )}
                            <div>
                              <p className="text-sm font-medium">{item.product_name}</p>
                              {item.variant_label && (
                                <p className="text-xs text-muted-foreground">{item.variant_label}</p>
                              )}
                            </div>
                          </div>
                        </TableCell>
                        <TableCell className="text-xs text-muted-foreground font-mono">
                          {item.product_sku || "—"}
                        </TableCell>
                        <TableCell className="text-center">{item.quantity}</TableCell>
                        <TableCell className="text-right">{fmtCurrency(item.unit_price)}</TableCell>
                        <TableCell className="text-right font-medium">{fmtCurrency(item.total)}</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>Update Status</CardTitle>
                <CardDescription>Change the order status or add internal notes.</CardDescription>
              </CardHeader>
              <CardContent>
                <form onSubmit={handleStatusUpdate} className="space-y-4">
                  <div className="grid grid-cols-2 gap-4">
                    <div className="grid gap-2">
                      <Label htmlFor="status">Status</Label>
                      <Select
                        value={form.data.status}
                        onValueChange={(v) => form.setData("status", v)}
                      >
                        <SelectTrigger id="status">
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          {ORDER_STATUSES.map((s) => (
                            <SelectItem key={s} value={s}>
                              {s.charAt(0).toUpperCase() + s.slice(1)}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                      {form.errors.status && <p className="text-sm text-destructive">{form.errors.status}</p>}
                    </div>
                  </div>
                  <div className="grid gap-2">
                    <Label htmlFor="notes">Internal Notes</Label>
                    <Textarea
                      id="notes"
                      rows={3}
                      value={form.data.notes}
                      onChange={(e) => form.setData("notes", e.target.value)}
                    />
                    {form.errors.notes && <p className="text-sm text-destructive">{form.errors.notes}</p>}
                  </div>
                  <Button type="submit" disabled={form.processing}>
                    {form.processing ? "Updating..." : "Update Status"}
                  </Button>
                </form>
              </CardContent>
            </Card>
          </div>

          <div className="space-y-6">
            <Card>
              <CardHeader>
                <CardTitle>Customer</CardTitle>
              </CardHeader>
              <CardContent className="space-y-2 text-sm">
                <p className="font-medium">{order.customer_name || order.user?.name || "—"}</p>
                <p className="text-muted-foreground">{order.customer_email || order.user?.email}</p>
                {order.customer_phone && <p className="text-muted-foreground">{order.customer_phone}</p>}
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>Order Summary</CardTitle>
              </CardHeader>
              <CardContent className="space-y-2 text-sm">
                <div className="flex justify-between">
                  <span className="text-muted-foreground">Subtotal</span>
                  <span>{fmtCurrency(order.subtotal)}</span>
                </div>
                {Number(order.shipping_total) > 0 && (
                  <div className="flex justify-between">
                    <span className="text-muted-foreground">Shipping</span>
                    <span>{fmtCurrency(order.shipping_total)}</span>
                  </div>
                )}
                {Number(order.tax_total) > 0 && (
                  <div className="flex justify-between">
                    <span className="text-muted-foreground">Tax</span>
                    <span>{fmtCurrency(order.tax_total)}</span>
                  </div>
                )}
                {Number(order.discount_total) > 0 && (
                  <div className="flex justify-between text-destructive">
                    <span>Discount</span>
                    <span>-{fmtCurrency(order.discount_total)}</span>
                  </div>
                )}
                <Separator />
                <div className="flex justify-between font-medium text-base">
                  <span>Total</span>
                  <span>{fmtCurrency(order.total)}</span>
                </div>
                <Separator />
                <div className="flex justify-between">
                  <span className="text-muted-foreground">Payment</span>
                  <span className="capitalize">{order.payment_method || order.payment_status}</span>
                </div>
                {order.paid_at && (
                  <div className="flex justify-between">
                    <span className="text-muted-foreground">Paid at</span>
                    <span>{new Date(order.paid_at).toLocaleString()}</span>
                  </div>
                )}
              </CardContent>
            </Card>

            {order.billing_address && (
              <Card>
                <CardHeader>
                  <CardTitle>Billing Address</CardTitle>
                </CardHeader>
                <CardContent className="text-sm text-muted-foreground space-y-0.5">
                  {addressLines(order.billing_address).map((line, i) => (
                    <p key={i}>{line}</p>
                  ))}
                </CardContent>
              </Card>
            )}

            {order.shipping_address && (
              <Card>
                <CardHeader>
                  <CardTitle>Shipping Address</CardTitle>
                </CardHeader>
                <CardContent className="text-sm text-muted-foreground space-y-0.5">
                  {addressLines(order.shipping_address).map((line, i) => (
                    <p key={i}>{line}</p>
                  ))}
                </CardContent>
              </Card>
            )}
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
