import { Head, Link, router } from "@inertiajs/react";
import { Trash2, ShoppingCart, Minus, Plus, ArrowRight, Ticket, X, Package, AlertTriangle, CheckCircle2 } from "lucide-react";
import { useState } from "react";
import { toast } from "@nreljed/platform/Components/ui/toast";
import StoreLayout from "../../Layouts/StoreLayout";
import { useCurrency } from "../../lib/currency";
import { Button } from "@nreljed/platform/Components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@nreljed/platform/Components/ui/card";
import { Input } from "@nreljed/platform/Components/ui/input";
import { Separator } from "@nreljed/platform/Components/ui/separator";
import { Empty, EmptyHeader, EmptyTitle, EmptyDescription } from "@nreljed/platform/Components/ui/empty";

interface CartItem {
  id: number;
  product_id: number;
  quantity: number;
  unit_price: string;
  variant_label: string | null;
  /**
   * Null once the product row is gone — a soft delete, or a basket that
   * outlived its catalogue entry. The backend returns `null` rather than
   * dropping the line, so the shopper can still remove it.
   */
  product: {
    id: number;
    name: string;
    slug: string;
    price: string;
    compare_price: string | null;
    sku: string | null;
    thumbnail_url: string | null;
    stock_quantity: number | null;
    track_stock: boolean;
    is_in_stock: boolean;
  } | null;
  /** Can this line still be bought? False once withdrawn, sold out or unpublished. */
  available: boolean;
}

interface AppliedCoupon {
  coupon_id: number;
  code: string;
  type: string;
  discount?: number;
}

interface CartData {
  id: number;
  items: CartItem[];
  subtotal: number;
  total: number;
  coupon_code: string | null;
  discount: number;
  applied_coupons: AppliedCoupon[] | null;
}

interface Props {
  cart: CartData;
}

export default function StorefrontCart({ cart }: Props) {
  const { money } = useCurrency();
  const [updating, setUpdating] = useState<Record<number, boolean>>({});
  const [removingUnavailable, setRemovingUnavailable] = useState(false);
  const [couponCode, setCouponCode] = useState("");
  const [couponApplying, setCouponApplying] = useState(false);

  const appliedCoupons = cart.applied_coupons ?? [];

  // Withdrawn or deleted lines are kept and flagged rather than dropped, so the
  // shopper can see what is wrong and remove it. The backend refuses to sell
  // them; this keeps the page from pretending the basket is fine.
  const unavailableItems = cart.items.filter((item) => !item.available);

  const applyCoupon = async () => {
    if (!couponCode.trim() || couponApplying) return;
    setCouponApplying(true);
    try {
      const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
      const res = await fetch(route("store.cart.apply-coupon"), {
        method: "POST",
        headers: { "Content-Type": "application/json", "X-CSRF-TOKEN": token },
        body: JSON.stringify({ code: couponCode.trim() }),
      });
      if (!res.ok) {
        const err = await res.json();
        toast.add({ title: err.coupon?.[0] || err.error || "Failed to apply coupon.", type: "error" });
        return;
      }
      setCouponCode("");
      router.reload({ only: ["cart"] });
      toast.add({ title: "Coupon applied!", type: "success" });
    } catch {
      toast.add({ title: "Failed to apply coupon.", type: "error" });
    } finally {
      setCouponApplying(false);
    }
  };

  const removeCoupon = async (code: string) => {
    setCouponApplying(true);
    try {
      const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
      await fetch(route("store.cart.remove-coupon"), {
        method: "POST",
        headers: { "Content-Type": "application/json", "X-CSRF-TOKEN": token },
        body: JSON.stringify({ code }),
      });
      router.reload({ only: ["cart"] });
      toast.add({ title: `Coupon "${code}" removed.`, type: "success" });
    } catch {
      toast.add({ title: "Failed to remove coupon.", type: "error" });
    } finally {
      setCouponApplying(false);
    }
  };

  const reloadCart = () => router.reload({ only: ["cart"] });

  const getCsrfToken = () =>
    document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";

  const updateQuantity = async (itemId: number, newQty: number) => {
    if (newQty < 0) return;
    setUpdating((prev) => ({ ...prev, [itemId]: true }));
    try {
      const res = await fetch(route("store.cart.update-item", itemId), {
        method: "PUT",
        headers: { "Content-Type": "application/json", "X-CSRF-TOKEN": getCsrfToken() },
        body: JSON.stringify({ quantity: newQty }),
      });
      if (res.ok) reloadCart();
    } finally {
      setUpdating((prev) => ({ ...prev, [itemId]: false }));
    }
  };

  const removeItem = async (itemId: number) => {
    try {
      await fetch(route("store.cart.remove-item", itemId), {
        method: "DELETE",
        headers: { "Content-Type": "application/json", "X-CSRF-TOKEN": getCsrfToken() },
      });
      reloadCart();
    } catch {}
  };

  /**
   * Clear every withdrawn line in one go.
   *
   * The calls are awaited in sequence rather than fired together: the cart is
   * session-scoped, so parallel deletes race each other's read of it and the
   * last one to land wins with the others' lines still attached.
   */
  const removeUnavailableItems = async () => {
    setRemovingUnavailable(true);
    try {
      for (const item of unavailableItems) {
        await fetch(route("store.cart.remove-item", item.id), {
          method: "DELETE",
          headers: { "Content-Type": "application/json", "X-CSRF-TOKEN": getCsrfToken() },
        });
      }
      reloadCart();
    } finally {
      setRemovingUnavailable(false);
    }
  };

  return (
    <StoreLayout>
      <Head title="Shopping Cart">
        <meta name="robots" content="noindex, nofollow" />
      </Head>
      <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <div className="flex items-center justify-between mb-6">
          <h1 className="text-2xl font-bold tracking-tight">Shopping Cart</h1>
          {cart.items.length > 0 && (
            <Link href={route("store.index")} className="text-sm text-muted-foreground hover:text-foreground underline underline-offset-4">
              Continue Shopping
            </Link>
          )}
        </div>

        {cart.items.length === 0 ? (
          <Card>
            <CardContent className="py-16">
              <Empty>
                <EmptyHeader>
                  <ShoppingCart className="mx-auto h-12 w-12 text-muted-foreground" />
                  <EmptyTitle>Your cart is empty</EmptyTitle>
                  <EmptyDescription>Browse products and add items to your cart.</EmptyDescription>
                </EmptyHeader>
                <div className="mt-6">
                  <Link href={route("store.index")}>
                    <Button>
                      <Package className="mr-2 h-4 w-4" />
                      Browse Products
                    </Button>
                  </Link>
                </div>
              </Empty>
            </CardContent>
          </Card>
        ) : (
          <div className="grid gap-6 lg:grid-cols-3">
            <div className="lg:col-span-2 space-y-4">
              {cart.items.map((item) => (
                <Card key={item.id} className={item.available ? "hover:bg-muted/50 transition-colors" : "border-destructive/40 bg-destructive/5"}>
                  <CardContent className="flex items-start gap-4 p-4">
                    {item.product?.thumbnail_url && (
                      <img
                        src={item.product.thumbnail_url}
                        alt={item.product.name}
                        className="h-20 w-20 rounded-md object-cover shrink-0"
                      />
                    )}
                    <div className="flex-1 min-w-0">
                      <span className="font-medium truncate block">{item.product?.name ?? "Product no longer available"}</span>
                      {item.variant_label && (
                        <p className="text-xs text-muted-foreground mt-0.5">{item.variant_label}</p>
                      )}
                      {!item.available && (
                        <p className="inline-flex items-center gap-1 text-xs text-destructive mt-1">
                          <AlertTriangle className="h-3 w-3" />
                          {item.product
                            ? "No longer available — please remove it to continue"
                            : "This product has been removed from the store"}
                        </p>
                      )}
                      {item.available && item.product && (
                        <div className="flex items-center gap-2 mt-1">
                          {item.product.track_stock && (
                            item.product.is_in_stock ? (
                              item.product.stock_quantity !== null && item.product.stock_quantity <= 5 ? (
                                <span className="inline-flex items-center gap-1 text-xs text-amber-600">
                                  <AlertTriangle className="h-3 w-3" />
                                  Only {item.product.stock_quantity} left
                                </span>
                              ) : (
                                <span className="inline-flex items-center gap-1 text-xs text-green-600">
                                  <CheckCircle2 className="h-3 w-3" />
                                  In stock
                                </span>
                              )
                            ) : (
                              <span className="inline-flex items-center gap-1 text-xs text-destructive">
                                <AlertTriangle className="h-3 w-3" />
                                Out of stock
                              </span>
                            )
                          )}
                        </div>
                      )}
                      {item.product?.sku && (
                        <p className="text-xs text-muted-foreground mt-0.5">SKU: {item.product.sku}</p>
                      )}
                      <p className="text-sm font-medium mt-1">
                        {money(Number(item.unit_price))}
                      </p>
                    </div>
                    {item.available && item.product && (
                      <div className="flex items-center gap-2 shrink-0">
                        <Button
                          variant="outline"
                          size="icon-sm"
                          disabled={updating[item.id]}
                          onClick={() => updateQuantity(item.id, item.quantity - 1)}
                        >
                          <Minus className="h-3 w-3" />
                        </Button>
                        <Input
                          type="number"
                          min={1}
                          value={item.quantity}
                          onChange={(e) => {
                            const val = parseInt(e.target.value, 10);
                            if (!isNaN(val) && val >= 0) updateQuantity(item.id, val);
                          }}
                          className="w-14 text-center [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                        />
                        <Button
                          variant="outline"
                          size="icon-sm"
                          disabled={updating[item.id]}
                          onClick={() => updateQuantity(item.id, item.quantity + 1)}
                        >
                          <Plus className="h-3 w-3" />
                        </Button>
                      </div>
                    )}
                    <div className="text-right shrink-0 min-w-[80px]">
                      {item.available && item.product && (
                        <p className="font-medium tabular-nums">
                          {money(Number(item.unit_price) * item.quantity)}
                        </p>
                      )}
                      <Button
                        variant="ghost"
                        size="icon-sm"
                        className="text-destructive mt-1"
                        onClick={() => removeItem(item.id)}
                        title="Remove from cart"
                      >
                        <Trash2 className="h-3.5 w-3.5" />
                      </Button>
                    </div>
                  </CardContent>
                </Card>
              ))}
            </div>

            <div>
              <Card>
                <CardHeader>
                  <CardTitle>Order Summary</CardTitle>
                </CardHeader>
                <CardContent className="space-y-3">
                  <div className="space-y-2">
                    {appliedCoupons.length > 0 && (
                      <div className="space-y-1.5 mb-2">
                        {appliedCoupons.map((ac) => (
                          <div key={ac.coupon_id} className="flex items-center justify-between bg-muted/50 rounded-md px-2.5 py-1.5">
                            <div className="flex items-center gap-1.5 min-w-0">
                              <Ticket className="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                              <span className="text-xs font-medium truncate">{ac.code}</span>
                              {ac.type === "free_shipping" && (
                                <span className="text-[10px] text-muted-foreground uppercase tracking-wider">Free Shipping</span>
                              )}
                            </div>
                            <Button
                              variant="ghost"
                              size="icon-xs"
                              className="h-5 w-5 shrink-0 ml-1"
                              disabled={couponApplying}
                              onClick={() => removeCoupon(ac.code)}
                            >
                              <X className="h-3 w-3" />
                            </Button>
                          </div>
                        ))}
                      </div>
                    )}
                    <div className="flex gap-2">
                      <Input
                        placeholder="Coupon code"
                        value={couponCode}
                        onChange={(e) => setCouponCode(e.target.value)}
                        onKeyDown={(e) => { if (e.key === "Enter") applyCoupon(); }}
                        className="flex-1"
                      />
                      <Button variant="outline" size="sm" onClick={applyCoupon} disabled={couponApplying || !couponCode.trim()}>
                        <Ticket className="mr-1 h-3.5 w-3.5" /> Apply
                      </Button>
                    </div>
                  </div>
                  <div className="flex justify-between text-sm">
                    <span className="text-muted-foreground">Subtotal</span>
                    <span className="tabular-nums">
                      {money(cart.subtotal)}
                    </span>
                  </div>
                  {cart.discount > 0 && (
                    <div className="flex justify-between text-sm">
                      <span className="text-muted-foreground">Discount</span>
                      <span className="tabular-nums text-destructive">
                        -{money(cart.discount)}
                      </span>
                    </div>
                  )}
                  <Separator />
                  <div className="flex justify-between font-medium">
                    <span>Total</span>
                    <span className="tabular-nums">
                      {money(cart.total)}
                    </span>
                  </div>
                  {unavailableItems.length > 0 ? (
                    <div className="rounded-md border border-destructive/40 bg-destructive/5 px-3 py-2.5 mt-2">
                      <p className="text-sm text-destructive flex items-start gap-2">
                        <AlertTriangle className="h-4 w-4 shrink-0 mt-0.5" />
                        <span>
                          {unavailableItems.length === 1
                            ? "1 item is no longer available. Remove it to continue."
                            : `${unavailableItems.length} items are no longer available. Remove them to continue.`}
                        </span>
                      </p>
                      <Button
                        variant="outline"
                        size="sm"
                        className="mt-2 w-full"
                        disabled={removingUnavailable}
                        onClick={removeUnavailableItems}
                      >
                        <Trash2 className="mr-2 h-3.5 w-3.5" />
                        {removingUnavailable
                          ? "Removing…"
                          : `Remove ${unavailableItems.length} unavailable item${unavailableItems.length > 1 ? "s" : ""}`}
                      </Button>
                    </div>
                  ) : (
                    <Link href={route("store.checkout.index")}>
                      <Button className="w-full mt-2">
                        Proceed to Checkout
                        <ArrowRight className="ml-2 h-4 w-4" />
                      </Button>
                    </Link>
                  )}
                </CardContent>
              </Card>
            </div>
          </div>
        )}
      </div>
    </StoreLayout>
  );
}
