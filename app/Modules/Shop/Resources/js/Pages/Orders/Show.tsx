import { Head, Link, useForm, router } from "@inertiajs/react";
import { useState, useRef } from "react";
import { toast } from "sonner";
import {
  CheckCircle2, Clock, Pencil, Printer, Undo2,
  Plus, MessageSquare, Trash2, X, FileText, Send,
  Eye, EyeOff,
} from "lucide-react";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/Components/ui/dialog";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/Components/ui/card";
import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
import { Textarea } from "@/Components/ui/textarea";
import { Input } from "@/Components/ui/input";
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
import { Checkbox } from "@/Components/ui/checkbox";

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

interface OrderRefund {
  id: number;
  total: string;
  reason: string | null;
  items: { order_item_id: number; quantity: number; amount: number }[];
  shipping_refund: string;
  created_at: string;
  creator: { id: number; name: string } | null;
}

interface OrderNote {
  id: number;
  content: string;
  is_public: boolean;
  created_at: string;
  user: { id: number; name: string } | null;
}

interface OrderAction {
  id: number;
  action_type: string;
  old_value: string | null;
  new_value: string | null;
  notes: string | null;
  created_at: string;
  user: { id: number; name: string } | null;
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
  total_refunded: string;
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
  refunded_at: string | null;
  refund_amount: string | null;
  refund_notes: string | null;
  created_at: string;
  user: { id: number; name: string; email: string } | null;
  items: OrderItem[];
  actions: OrderAction[];
  refunds: OrderRefund[];
  notes: OrderNote[];
}

interface OrdersShowProps {
  order: Order;
  statuses: string[];
  allowedTransitions: string[];
}

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
    case "trash": return "outline" as const;
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

interface RefundItemEntry {
  order_item_id: number;
  quantity: number;
  amount: number;
  max_quantity: number;
  max_amount: number;
}

export default function OrdersShow({ order, statuses, allowedTransitions }: OrdersShowProps) {
  const form = useForm({
    status: order.status,
    notes: order.notes || "",
  });

  const [refundOpen, setRefundOpen] = useState(false);
  const [refundItems, setRefundItems] = useState<RefundItemEntry[]>(
    order.items.map((item) => ({
      order_item_id: item.id,
      quantity: 0,
      amount: 0,
      max_quantity: item.quantity,
      max_amount: parseFloat(item.total),
    })),
  );
  const [refundShipping, setRefundShipping] = useState(0);
  const refundReasonRef = useRef<HTMLTextAreaElement>(null);

  const selectedItems = refundItems.filter((ri) => ri.quantity > 0);
  const refundTotal = selectedItems.reduce((s, ri) => s + ri.amount, 0) + refundShipping;
  const orderTotal = parseFloat(order.total);
  const totalRefunded = parseFloat(order.total_refunded || "0");
  const remainingRefundable = orderTotal - totalRefunded;

  const handleRefund = () => {
    if (selectedItems.length === 0) {
      toast.error("Select at least one item to refund.");
      return;
    }
    if (refundTotal <= 0) {
      toast.error("Refund total must be greater than 0.");
      return;
    }
    if (refundTotal > remainingRefundable) {
      toast.error(`Refund total (${fmtCurrency(refundTotal)}) exceeds remaining refundable (${fmtCurrency(remainingRefundable)}).`);
      return;
    }

    router.post(route("dashboard.shop.orders.refund", order.id), {
      items: selectedItems.map((ri) => ({
        order_item_id: ri.order_item_id,
        quantity: ri.quantity,
        amount: ri.amount,
      })),
      shipping_refund: refundShipping,
      reason: refundReasonRef.current?.value || "",
    }, {
      preserveScroll: true,
      onSuccess: () => {
        toast.success("Refund processed.");
        setRefundOpen(false);
      },
      onError: () => toast.error("Failed to process refund."),
    });
  };

  const handleStatusUpdate = (e: React.FormEvent) => {
    e.preventDefault();
    form.patch(route("dashboard.shop.orders.update-status", order.id), {
      preserveScroll: true,
      onSuccess: () => toast.success("Order status updated."),
      onError: () => toast.error("Failed to update status."),
    });
  };

  const handleConvertFromDraft = () => {
    router.post(route("dashboard.shop.orders.convert-from-draft", order.id), {
      preserveScroll: true,
      onSuccess: () => toast.success("Draft converted to pending."),
      onError: () => toast.error("Failed to convert draft."),
    });
  };

  const [noteContent, setNoteContent] = useState("");
  const [notePublic, setNotePublic] = useState(false);
  const handleAddNote = () => {
    if (!noteContent.trim()) return;
    router.post(route("dashboard.shop.orders.notes.store", order.id), {
      content: noteContent,
      is_public: notePublic,
    }, {
      preserveScroll: true,
      onSuccess: () => {
        toast.success("Note added.");
        setNoteContent("");
        setNotePublic(false);
      },
      onError: () => toast.error("Failed to add note."),
    });
  };

  const handleDeleteNote = (noteId: number) => {
    router.delete(route("dashboard.shop.orders.notes.destroy", [order.id, noteId]), {
      preserveScroll: true,
      onSuccess: () => toast.success("Note deleted."),
      onError: () => toast.error("Failed to delete note."),
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

  const isDraft = order.status === "draft";
  const canRefund = allowedTransitions.includes("refunded") || order.status === "completed";

  return (
    <AuthenticatedLayout header={`Order ${order.order_number}`}>
      <Head title={`Order ${order.order_number}`} />

      {/* Print-only invoice */}
      <div className="hidden print:block space-y-6 p-8">
        <div className="text-center border-b pb-4">
          <h1 className="text-2xl font-bold">INVOICE</h1>
          <p className="text-lg font-mono mt-1">{order.order_number}</p>
          <p className="text-sm text-muted-foreground">{new Date(order.created_at).toLocaleDateString()}</p>
        </div>
        <div className="grid grid-cols-2 gap-8 text-sm">
          <div>
            <p className="font-semibold mb-1">Billing Address</p>
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
          </div>
          <div>
            <p className="font-semibold mb-1">Shipping Address</p>
            {order.shipping_address && (
              <>
                <p>{order.shipping_address.first_name} {order.shipping_address.last_name}</p>
                {order.shipping_address.company && <p>{order.shipping_address.company}</p>}
                <p>{order.shipping_address.address_line1}</p>
                {order.shipping_address.address_line2 && <p>{order.shipping_address.address_line2}</p>}
                <p>{[order.shipping_address.city, order.shipping_address.state, order.shipping_address.postcode].filter(Boolean).join(", ")}</p>
                <p>{order.shipping_address.country}</p>
              </>
            )}
          </div>
        </div>
        <table className="w-full text-sm mt-4">
          <thead>
            <tr className="border-b">
              <th className="text-left py-2">Product</th>
              <th className="text-right py-2">Price</th>
              <th className="text-center py-2">Qty</th>
              <th className="text-right py-2">Total</th>
            </tr>
          </thead>
          <tbody>
            {order.items.map((item) => (
              <tr key={item.id} className="border-b">
                <td className="py-2">{item.product_name}</td>
                <td className="text-right py-2">{fmtCurrency(item.unit_price)}</td>
                <td className="text-center py-2">{item.quantity}</td>
                <td className="text-right py-2">{fmtCurrency(item.total)}</td>
              </tr>
            ))}
          </tbody>
          <tfoot>
            <tr>
              <td colSpan={3} className="text-right py-1 font-semibold">Subtotal</td>
              <td className="text-right py-1">{fmtCurrency(order.subtotal)}</td>
            </tr>
            {Number(order.shipping_total) > 0 && (
              <tr>
                <td colSpan={3} className="text-right py-1">Shipping</td>
                <td className="text-right py-1">{fmtCurrency(order.shipping_total)}</td>
              </tr>
            )}
            <tr>
              <td colSpan={3} className="text-right py-1 text-lg font-bold">Total</td>
              <td className="text-right py-1 text-lg font-bold">{fmtCurrency(order.total)}</td>
            </tr>
          </tfoot>
        </table>
        <div className="text-center text-xs text-muted-foreground pt-4 border-t">
          <p>Payment Status: <span className="capitalize font-medium">{order.payment_status}</span></p>
        </div>
      </div>

      {/* Screen-only content */}
      <div className="print:hidden space-y-6">
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
            {!isDraft && (
              <Button variant="outline" size="sm" asChild>
                <Link href={route("dashboard.shop.orders.edit", order.id)}>
                  <Pencil className="mr-1 h-3 w-3" />
                  Edit
                </Link>
              </Button>
            )}
            <Link href={route("dashboard.shop.orders.invoice", order.id)} className="inline-flex items-center justify-center whitespace-nowrap rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 border border-input bg-background hover:bg-accent hover:text-accent-foreground h-9 px-3">
              <Printer className="mr-1 h-3 w-3" />
              Print Invoice
            </Link>
          </div>
        </div>

        {/* Draft banner */}
        {isDraft && (
          <Card className="border-warning/50 bg-warning/5">
            <CardContent className="flex items-center justify-between py-4">
              <div className="flex items-center gap-3">
                <FileText className="h-5 w-5 text-warning" />
                <div>
                  <p className="font-medium">Draft Order</p>
                  <p className="text-sm text-muted-foreground">This order is a draft. Convert it to process inventory and notify the customer.</p>
                </div>
              </div>
              <Button onClick={handleConvertFromDraft}>
                <Send className="mr-2 h-4 w-4" />
                Convert to Pending
              </Button>
            </CardContent>
          </Card>
        )}

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

            {/* Status update */}
            {!isDraft && (
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
                            {allowedTransitions.map((s) => (
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
            )}

            {/* Order Notes */}
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <MessageSquare className="h-4 w-4" />
                  Order Notes
                </CardTitle>
                <CardDescription>Internal and customer-visible notes.</CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="space-y-3">
                  {order.notes?.length === 0 && (
                    <p className="text-sm text-muted-foreground">No notes yet.</p>
                  )}
                  {order.notes?.map((note) => (
                    <div key={note.id} className="flex gap-3 text-sm border rounded-lg p-3">
                      <div className="flex-1 min-w-0">
                        <div className="flex items-center gap-2 mb-1">
                          {note.is_public ? (
                            <Eye className="h-3 w-3 text-muted-foreground" />
                          ) : (
                            <EyeOff className="h-3 w-3 text-muted-foreground" />
                          )}
                          <span className="font-medium text-xs">{note.user?.name || "System"}</span>
                          <span className="text-xs text-muted-foreground">
                            {new Date(note.created_at).toLocaleString()}
                          </span>
                          <Badge variant="outline" className="text-[10px] px-1.5 py-0">
                            {note.is_public ? "Public" : "Private"}
                          </Badge>
                        </div>
                        <p className="whitespace-pre-wrap">{note.content}</p>
                      </div>
                      <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="shrink-0 h-6 w-6"
                        onClick={() => handleDeleteNote(note.id)}
                      >
                        <Trash2 className="h-3 w-3 text-destructive" />
                      </Button>
                    </div>
                  ))}
                </div>
                <div className="space-y-2 pt-2 border-t">
                  <Textarea
                    rows={3}
                    placeholder="Add a note..."
                    value={noteContent}
                    onChange={(e) => setNoteContent(e.target.value)}
                  />
                  <div className="flex items-center justify-between">
                    <label className="flex items-center gap-2 text-sm cursor-pointer">
                      <Checkbox
                        checked={notePublic}
                        onCheckedChange={(v) => setNotePublic(v === true)}
                      />
                      <span className="text-muted-foreground">Visible to customer</span>
                    </label>
                    <Button type="button" size="sm" onClick={handleAddNote} disabled={!noteContent.trim()}>
                      Add Note
                    </Button>
                  </div>
                </div>
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
                {totalRefunded > 0 && (
                  <div className="flex justify-between text-destructive">
                    <span>Refunded</span>
                    <span>-{fmtCurrency(totalRefunded)}</span>
                  </div>
                )}
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
                {order.payment_status === "pending" && !isDraft && order.status !== "refunded" && (
                  <Button
                    variant="outline"
                    size="sm"
                    className="w-full mt-2"
                    onClick={() => {
                      router.post(route("dashboard.shop.orders.mark-paid", order.id), {
                        preserveScroll: true,
                        onSuccess: () => toast.success("Payment marked as received."),
                        onError: () => toast.error("Failed to mark payment."),
                      });
                    }}
                  >
                    <CheckCircle2 className="mr-2 h-4 w-4" />
                    Mark as Paid
                  </Button>
                )}
                {canRefund && !isDraft && (
                  <Dialog open={refundOpen} onOpenChange={setRefundOpen}>
                    <DialogTrigger asChild>
                      <Button variant="outline" size="sm" className="w-full mt-2 text-destructive">
                        <Undo2 className="mr-2 h-4 w-4" />
                        Issue Refund
                      </Button>
                    </DialogTrigger>
                    <DialogContent className="max-w-xl">
                      <DialogHeader>
                        <DialogTitle>Issue Refund</DialogTitle>
                        <DialogDescription>
                          Select items to refund. Remaining refundable: {fmtCurrency(remainingRefundable)}
                        </DialogDescription>
                      </DialogHeader>
                      <div className="space-y-4 py-2">
                        <Table>
                          <TableHeader>
                            <TableRow>
                              <TableHead className="w-12">Item</TableHead>
                              <TableHead>Product</TableHead>
                              <TableHead className="w-20 text-center">Qty</TableHead>
                              <TableHead className="w-28 text-right">Amount</TableHead>
                            </TableRow>
                          </TableHeader>
                          <TableBody>
                            {refundItems.map((ri, idx) => {
                              const item = order.items.find((i) => i.id === ri.order_item_id);
                              if (!item) return null;
                              const alreadyRefunded = order.refunds?.reduce((sum, r) => {
                                const rItem = r.items?.find((ri) => ri.order_item_id === item.id);
                                return sum + (rItem ? rItem.quantity : 0);
                              }, 0) || 0;
                              const maxQty = item.quantity - alreadyRefunded;
                              const maxAmount = parseFloat(item.total) * (maxQty / item.quantity);

                              return (
                                <TableRow key={item.id}>
                                  <TableCell>
                                    <Checkbox
                                      checked={ri.quantity > 0}
                                      onCheckedChange={(v) => {
                                        const newItems = [...refundItems];
                                        newItems[idx] = {
                                          ...ri,
                                          quantity: v ? maxQty : 0,
                                          amount: v ? maxAmount : 0,
                                        };
                                        setRefundItems(newItems);
                                      }}
                                    />
                                  </TableCell>
                                  <TableCell>
                                    <p className="text-sm font-medium">{item.product_name}</p>
                                    <p className="text-xs text-muted-foreground">
                                      {item.variant_label || ""} x{item.quantity} @ {fmtCurrency(item.unit_price)}
                                    </p>
                                  </TableCell>
                                  <TableCell>
                                    <Input
                                      type="number"
                                      min={0}
                                      max={maxQty}
                                      className="w-16 h-8 text-center mx-auto"
                                      value={ri.quantity}
                                      onChange={(e) => {
                                        const qty = Math.min(parseInt(e.target.value) || 0, maxQty);
                                        const newItems = [...refundItems];
                                        newItems[idx] = {
                                          ...ri,
                                          quantity: qty,
                                          amount: qty > 0 ? maxAmount * (qty / maxQty) : 0,
                                        };
                                        setRefundItems(newItems);
                                      }}
                                      disabled={maxQty === 0}
                                    />
                                  </TableCell>
                                  <TableCell>
                                    <Input
                                      type="number"
                                      step="0.01"
                                      min="0"
                                      max={maxAmount}
                                      className="w-24 h-8 text-right ml-auto"
                                      value={ri.amount.toFixed(2)}
                                      onChange={(e) => {
                                        const amt = Math.min(parseFloat(e.target.value) || 0, maxAmount);
                                        const newItems = [...refundItems];
                                        newItems[idx] = { ...ri, amount: amt, quantity: amt > 0 ? ri.quantity || maxQty : 0 };
                                        setRefundItems(newItems);
                                      }}
                                    />
                                  </TableCell>
                                </TableRow>
                              );
                            })}
                          </TableBody>
                        </Table>

                        <div className="flex items-center gap-4">
                          <div className="flex-1">
                            <Label>Shipping Refund</Label>
                            <Input
                              type="number"
                              step="0.01"
                              min="0"
                              max={parseFloat(order.shipping_total || "0")}
                              value={refundShipping.toFixed(2)}
                              onChange={(e) => setRefundShipping(parseFloat(e.target.value) || 0)}
                              className="mt-1"
                            />
                          </div>
                          <div className="text-right">
                            <p className="text-sm text-muted-foreground">Refund Total</p>
                            <p className="text-lg font-bold text-destructive">{fmtCurrency(refundTotal)}</p>
                          </div>
                        </div>

                        <div className="space-y-1.5">
                          <Label htmlFor="refund_reason">Reason (optional)</Label>
                          <Textarea
                            id="refund_reason"
                            rows={2}
                            placeholder="Reason for refund..."
                            ref={refundReasonRef}
                          />
                        </div>
                      </div>
                      <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setRefundOpen(false)}>
                          Cancel
                        </Button>
                        <Button
                          type="button"
                          variant="destructive"
                          onClick={handleRefund}
                          disabled={selectedItems.length === 0 || refundTotal <= 0}
                        >
                          Issue Refund ({fmtCurrency(refundTotal)})
                        </Button>
                      </DialogFooter>
                    </DialogContent>
                  </Dialog>
                )}
              </CardContent>
            </Card>

            {/* Refund history */}
            {order.refunds?.length > 0 && (
              <Card>
                <CardHeader>
                  <CardTitle className="flex items-center gap-2">
                    <Undo2 className="h-4 w-4" />
                    Refund History
                  </CardTitle>
                </CardHeader>
                <CardContent className="space-y-3">
                  {order.refunds.map((refund) => (
                    <div key={refund.id} className="text-sm border rounded-lg p-3">
                      <div className="flex justify-between items-start">
                        <div>
                          <p className="font-medium text-destructive">
                            -{fmtCurrency(refund.total)}
                          </p>
                          <p className="text-xs text-muted-foreground">
                            {refund.creator?.name && <span>{refund.creator.name} — </span>}
                            {new Date(refund.created_at).toLocaleString()}
                          </p>
                        </div>
                      </div>
                      {refund.reason && (
                        <p className="text-xs text-muted-foreground mt-1 italic">{refund.reason}</p>
                      )}
                      {refund.items && refund.items.length > 0 && (
                        <div className="mt-2 text-xs text-muted-foreground space-y-0.5">
                          {refund.items.map((ri, idx) => {
                            const item = order.items.find((i) => i.id === ri.order_item_id);
                            return (
                              <p key={idx}>
                                {item?.product_name || "Item"} x{ri.quantity} — {fmtCurrency(ri.amount)}
                              </p>
                            );
                          })}
                        </div>
                      )}
                    </div>
                  ))}
                </CardContent>
              </Card>
            )}

            {/* Activity timeline */}
            {order.actions?.length > 0 && (
              <Card>
                <CardHeader>
                  <CardTitle className="flex items-center gap-2">
                    <Clock className="h-4 w-4" />
                    Activity Timeline
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="space-y-3">
                    {order.actions.map((action) => (
                      <div key={action.id} className="flex gap-3 text-sm">
                        <div className="flex flex-col items-center">
                          <div className="h-2 w-2 rounded-full bg-muted-foreground/30 mt-1.5" />
                          {action.id !== order.actions[order.actions.length - 1]?.id && (
                            <div className="w-px flex-1 bg-border mt-1" />
                          )}
                        </div>
                        <div className="flex-1 pb-3">
                          <p className="font-medium capitalize">{action.action_type.replace(/_/g, " ")}</p>
                          <p className="text-xs text-muted-foreground">
                            {action.user?.name && <span>{action.user.name} — </span>}
                            {new Date(action.created_at).toLocaleString()}
                          </p>
                          {action.old_value && action.new_value && (
                            <p className="text-xs text-muted-foreground mt-0.5">
                              {action.old_value} → {action.new_value}
                            </p>
                          )}
                          {action.notes && (
                            <p className="text-xs text-muted-foreground mt-0.5 italic">
                              {action.notes}
                            </p>
                          )}
                        </div>
                      </div>
                    ))}
                  </div>
                </CardContent>
              </Card>
            )}

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
