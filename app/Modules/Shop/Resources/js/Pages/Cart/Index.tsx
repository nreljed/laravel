import { Head, Link, router } from "@inertiajs/react";
import { Trash2, ShoppingCart, Minus, Plus, ArrowRight, Package, Ticket, X } from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Button } from "@/Components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Input } from "@/Components/ui/input";
import { Separator } from "@/Components/ui/separator";
import { Empty, EmptyHeader, EmptyTitle, EmptyDescription } from "@/Components/ui/empty";

interface CartItem {
  id: number;
  product_id: number;
  quantity: number;
  unit_price: string;
  variant_label: string | null;
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
    isInStock: boolean;
  };
}

interface CartData {
  id: number;
  items: CartItem[];
  subtotal: number;
  total: number;
  coupon_code: string | null;
  discount: number;
}

interface Props {
  cart: CartData;
}

export default function CartIndex({ cart }: Props) {
  const [updating, setUpdating] = useState<Record<number, boolean>>({});
  const [couponCode, setCouponCode] = useState(cart.coupon_code || "");
  const [couponApplying, setCouponApplying] = useState(false);

  const applyCoupon = async () => {
    if (!couponCode.trim() || couponApplying) return;
    setCouponApplying(true);
    try {
      const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
      const res = await fetch(route("dashboard.shop.cart.apply-coupon"), {
        method: "POST",
        headers: { "Content-Type": "application/json", "X-CSRF-TOKEN": token },
        body: JSON.stringify({ code: couponCode.trim() }),
      });
      if (!res.ok) {
        const err = await res.json();
        toast.error(err.coupon?.[0] || err.error || "Failed to apply coupon.");
        return;
      }
      router.reload({ only: ["cart"] });
      toast.success("Coupon applied!");
    } catch {
      toast.error("Failed to apply coupon.");
    } finally {
      setCouponApplying(false);
    }
  };

  const removeCoupon = async () => {
    setCouponApplying(true);
    try {
      const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
      await fetch(route("dashboard.shop.cart.remove-coupon"), {
        method: "POST",
        headers: { "Content-Type": "application/json", "X-CSRF-TOKEN": token },
      });
      setCouponCode("");
      router.reload({ only: ["cart"] });
      toast.success("Coupon removed.");
    } catch {
      toast.error("Failed to remove coupon.");
    } finally {
      setCouponApplying(false);
    }
  };

  const updateQuantity = async (itemId: number, newQty: number) => {
    if (newQty < 0) return;
    setUpdating((prev) => ({ ...prev, [itemId]: true }));
    try {
      await router.put(
        route("dashboard.shop.cart.update-item", itemId),
        { quantity: newQty },
        { preserveState: true, preserveScroll: true, only: ["cart"] },
      );
    } finally {
      setUpdating((prev) => ({ ...prev, [itemId]: false }));
    }
  };

  const removeItem = (itemId: number) => {
    router.delete(route("dashboard.shop.cart.remove-item", itemId), {
      preserveState: true,
      preserveScroll: true,
      only: ["cart"],
    });
  };

  return (
    <AuthenticatedLayout header="Shopping Cart">
      <Head title="Shopping Cart" />

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
                <Link href={route("dashboard.shop.products.index")}>
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
              <Card key={item.id}>
                <CardContent className="flex items-start gap-4 p-4">
                  {item.product.thumbnail_url && (
                    <img
                      src={item.product.thumbnail_url}
                      alt={item.product.name}
                      className="h-20 w-20 rounded-md object-cover shrink-0"
                    />
                  )}
                  <div className="flex-1 min-w-0">
                    <Link
                      href={route("dashboard.shop.products.edit", item.product_id)}
                      className="font-medium hover:underline truncate block"
                    >
                      {item.product.name}
                    </Link>
                    {item.variant_label && (
                      <p className="text-xs text-muted-foreground mt-0.5">{item.variant_label}</p>
                    )}
                    {item.product.sku && (
                      <p className="text-xs text-muted-foreground">SKU: {item.product.sku}</p>
                    )}
                    <p className="text-sm font-medium mt-1">
                      {new Intl.NumberFormat("en-US", { style: "currency", currency: "EUR" }).format(
                        Number(item.unit_price),
                      )}
                    </p>
                  </div>
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
                      min={0}
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
                  <div className="text-right shrink-0 min-w-[80px]">
                    <p className="font-medium tabular-nums">
                      {new Intl.NumberFormat("en-US", { style: "currency", currency: "EUR" }).format(
                        Number(item.unit_price) * item.quantity,
                      )}
                    </p>
                    <Button
                      variant="ghost"
                      size="icon-sm"
                      className="text-destructive mt-1"
                      onClick={() => removeItem(item.id)}
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
                <div className="flex gap-2">
                  <Input
                    placeholder="Coupon code"
                    value={couponCode}
                    onChange={(e) => setCouponCode(e.target.value)}
                    onKeyDown={(e) => { if (e.key === "Enter") applyCoupon(); }}
                    className="flex-1"
                  />
                  {cart.coupon_code ? (
                    <Button variant="outline" size="sm" onClick={removeCoupon} disabled={couponApplying}>
                      <X className="h-3.5 w-3.5" />
                    </Button>
                  ) : (
                    <Button variant="outline" size="sm" onClick={applyCoupon} disabled={couponApplying || !couponCode.trim()}>
                      <Ticket className="mr-1 h-3.5 w-3.5" /> Apply
                    </Button>
                  )}
                </div>
                <div className="flex justify-between text-sm">
                  <span className="text-muted-foreground">Subtotal</span>
                  <span className="tabular-nums">
                    {new Intl.NumberFormat("en-US", { style: "currency", currency: "EUR" }).format(cart.subtotal)}
                  </span>
                </div>
                {cart.discount > 0 && (
                  <div className="flex justify-between text-sm">
                    <span className="text-muted-foreground">Discount ({cart.coupon_code})</span>
                    <span className="tabular-nums text-destructive">
                      -{new Intl.NumberFormat("en-US", { style: "currency", currency: "EUR" }).format(cart.discount)}
                    </span>
                  </div>
                )}
                <Separator />
                <div className="flex justify-between font-medium">
                  <span>Total</span>
                  <span className="tabular-nums">
                    {new Intl.NumberFormat("en-US", { style: "currency", currency: "EUR" }).format(cart.total)}
                  </span>
                </div>
                <Link href={route("dashboard.shop.checkout.index")}>
                  <Button className="w-full mt-2">
                    Proceed to Checkout
                    <ArrowRight className="ml-2 h-4 w-4" />
                  </Button>
                </Link>
              </CardContent>
            </Card>
          </div>
        </div>
      )}
    </AuthenticatedLayout>
  );
}
