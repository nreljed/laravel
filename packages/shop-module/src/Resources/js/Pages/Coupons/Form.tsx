import { Head, Link, useForm } from "@inertiajs/react";
import { toast } from "@nreljed/platform/Components/ui/toast";
import { ArrowLeft, Percent, DollarSign, Truck, Sparkles, Gift, Tag, Layers, Plus, Trash2 } from "lucide-react";
import AuthenticatedLayout from "@nreljed/platform/Layouts/AuthenticatedLayout";
import { Button } from "@nreljed/platform/Components/ui/button";
import { Input } from "@nreljed/platform/Components/ui/input";
import { Label } from "@nreljed/platform/Components/ui/label";
import { Card, CardContent, CardHeader, CardTitle } from "@nreljed/platform/Components/ui/card";
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from "@nreljed/platform/Components/ui/select";
import { Switch } from "@nreljed/platform/Components/ui/switch";
import { TermsSelector } from "@nreljed/platform/Components/shared/terms-selector";

interface Product { id: number; name: string }
interface Tier { min_qty: string; discount_percent: string }
interface CouponData {
  id?: number; code: string; type: string; value: string;
  min_order_amount: string; max_discount: string;
  usage_limit: string; per_user_limit: string;
  is_active: boolean; is_automatic: boolean; auto_discount_label: string;
  customer_group_id: string | null; exclude_sale_items: boolean;
  buy_qty: string; free_qty: string; free_product_id: string;
  tiers: Tier[];
  starts_at: string; expires_at: string;
  product_ids: number[];
  usages_count?: number; total_discount_given?: number; last_used_at?: string;
  products?: Product[];
}

interface Props {
  coupon: (CouponData & { usages_count?: number; total_discount_given?: number; last_used_at?: string }) | null;
  products: Product[];
  currency: string;
}

export default function CouponForm({ coupon, products, currency }: Props) {
  const isEdit = !!coupon?.id;

  const form = useForm({
    code: coupon?.code || "",
    type: coupon?.type || "percentage",
    value: coupon?.value?.toString() || "",
    min_order_amount: coupon?.min_order_amount?.toString() || "",
    max_discount: coupon?.max_discount?.toString() || "",
    usage_limit: coupon?.usage_limit?.toString() || "",
    per_user_limit: coupon?.per_user_limit?.toString() || "",
    is_active: coupon?.is_active ?? true,
    is_automatic: coupon?.is_automatic ?? false,
    auto_discount_label: coupon?.auto_discount_label || "",
    customer_group_id: coupon?.customer_group_id?.toString() || "",
    exclude_sale_items: coupon?.exclude_sale_items ?? false,
    buy_qty: coupon?.buy_qty?.toString() || "",
    free_qty: coupon?.free_qty?.toString() || "",
    free_product_id: coupon?.free_product_id?.toString() || "",
    tiers: coupon?.tiers?.length
      ? coupon.tiers.map((t) => ({ min_qty: String(t.min_qty), discount_percent: String(t.discount_percent) }))
      : [{ min_qty: "5", discount_percent: "10" }],
    starts_at: coupon?.starts_at ? coupon.starts_at.slice(0, 16) : "",
    expires_at: coupon?.expires_at ? coupon.expires_at.slice(0, 16) : "",
    product_ids: coupon?.products?.map((p) => p.id) || [],
  });

  const isFreeShipping = form.data.type === "free_shipping";
  const isBogof = form.data.type === "buy_x_get_y";
  const isTiered = form.data.type === "tiered";
  const isProductScoped = form.data.type === "fixed_product" || form.data.type === "percentage_product" || isTiered;
  const showValue = !isFreeShipping && !isBogof && !isTiered;

  const updateTier = (index: number, field: "min_qty" | "discount_percent", value: string) => {
    const tiers = form.data.tiers.map((t, i) => (i === index ? { ...t, [field]: value } : t));
    form.setData("tiers", tiers);
  };

  const addTier = () => {
    form.setData("tiers", [...form.data.tiers, { min_qty: "", discount_percent: "" }]);
  };

  const removeTier = (index: number) => {
    form.setData("tiers", form.data.tiers.filter((_, i) => i !== index));
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (isEdit) {
      form.put(route("dashboard.shop.coupons.update", coupon!.id), {
        preserveScroll: true,
        onSuccess: () => toast.add({ title: "Coupon updated.", type: "success" }),
        onError: () => toast.add({ title: "Failed to update coupon.", type: "error" }),
      });
    } else {
      form.post(route("dashboard.shop.coupons.store"), {
        preserveScroll: true,
        onSuccess: () => toast.add({ title: "Coupon created.", type: "success" }),
        onError: () => toast.add({ title: "Failed to create coupon.", type: "error" }),
      });
    }
  };

  return (
    <AuthenticatedLayout header={isEdit ? `Edit Coupon: ${coupon!.code}` : "Create Coupon"}>
      <Head title={isEdit ? "Edit Coupon" : "Create Coupon"} />

      <div className="space-y-6">
        <Link href={route("dashboard.shop.coupons.index")} className="inline-flex items-center text-sm text-muted-foreground hover:text-foreground">
          <ArrowLeft className="mr-1 h-4 w-4" /> Back to Coupons
        </Link>

        <form onSubmit={handleSubmit} className="space-y-6">
          <Card>
            <CardHeader>
              <CardTitle>Details</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid gap-2">
                <Label htmlFor="code">Code</Label>
                <Input id="code" value={form.data.code} onChange={(e) => form.setData("code", e.target.value.toUpperCase())} placeholder="SUMMER20" maxLength={50} required />
                {form.errors.code && <p className="text-xs text-destructive">{form.errors.code}</p>}
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div className="grid gap-2">
                  <Label htmlFor="type">Discount Type</Label>
                  <Select value={form.data.type} onValueChange={(v) => form.setData("type", v)}>
                    <SelectTrigger id="type">
                      <SelectValue placeholder="Select type" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="percentage"><Percent className="mr-2 h-4 w-4 inline" /> Percentage</SelectItem>
                      <SelectItem value="fixed"><DollarSign className="mr-2 h-4 w-4 inline" /> Fixed Amount</SelectItem>
                      <SelectItem value="free_shipping"><Truck className="mr-2 h-4 w-4 inline" /> Free Shipping</SelectItem>
                      <SelectItem value="buy_x_get_y"><Gift className="mr-2 h-4 w-4 inline" /> Buy X Get Y Free</SelectItem>
                      <SelectItem value="fixed_product"><Tag className="mr-2 h-4 w-4 inline" /> Fixed Amount on Products</SelectItem>
                      <SelectItem value="percentage_product"><Tag className="mr-2 h-4 w-4 inline" /> Percentage on Products</SelectItem>
                      <SelectItem value="tiered"><Layers className="mr-2 h-4 w-4 inline" /> Quantity Tiers</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                {showValue && (
                  <div className="grid gap-2">
                    <Label htmlFor="value">
                      Value{" "}
                      {form.data.type === "percentage"
                        ? "(%)"
                        : form.data.type === "percentage_product"
                          ? "(% per product)"
                          : form.data.type === "fixed_product"
                          ? `(${currency} per product)`
                          : `(${currency})`}
                    
                    </Label>
                    <Input id="value" type="number" step="0.01" min="0" value={form.data.value}
                      onChange={(e) => form.setData("value", e.target.value)}
                      placeholder={form.data.type === "percentage" || form.data.type === "percentage_product" ? "10" : "5.00"} required={showValue} />
                    {form.errors.value && <p className="text-xs text-destructive">{form.errors.value}</p>}
                  </div>
                )}
              </div>

              {isBogof && (
                <div className="grid gap-4">
                  <div className="grid grid-cols-2 gap-4">
                    <div className="grid gap-2">
                      <Label htmlFor="buy_qty">Buy Quantity</Label>
                      <Input id="buy_qty" type="number" min="1" value={form.data.buy_qty}
                        onChange={(e) => form.setData("buy_qty", e.target.value)} placeholder="2" />
                      {form.errors.buy_qty && <p className="text-xs text-destructive">{form.errors.buy_qty}</p>}
                    </div>
                    <div className="grid gap-2">
                      <Label htmlFor="free_qty">Free Quantity</Label>
                      <Input id="free_qty" type="number" min="1" value={form.data.free_qty}
                        onChange={(e) => form.setData("free_qty", e.target.value)} placeholder="1" />
                      {form.errors.free_qty && <p className="text-xs text-destructive">{form.errors.free_qty}</p>}
                    </div>
                  </div>
                  <div className="grid gap-2">
                    <Label htmlFor="free_product_id">Free Product (optional)</Label>
                    <Select value={form.data.free_product_id} onValueChange={(v) => form.setData("free_product_id", v)}>
                      <SelectTrigger id="free_product_id">
                        <SelectValue placeholder="Cheapest product in cart" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="">Cheapest product in cart</SelectItem>
                        {products.map((p) => (
                          <SelectItem key={p.id} value={p.id.toString()}>{p.name}</SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                    {form.errors.free_product_id && <p className="text-xs text-destructive">{form.errors.free_product_id}</p>}
                    <p className="text-xs text-muted-foreground">Leave empty to grant the cheapest eligible item. "Buy {form.data.buy_qty || 2} get {form.data.free_qty || 1} free" applies in groups.</p>
                  </div>
                </div>
              )}

              {isTiered && (
                <div className="grid gap-3">
                  <div className="flex items-center justify-between">
                    <Label>Quantity Tiers</Label>
                    <Button type="button" size="sm" variant="outline" onClick={addTier}>
                      <Plus className="mr-1 h-3.5 w-3.5" /> Add Tier
                    </Button>
                  </div>
                  <div className="space-y-2">
                    {form.data.tiers.map((tier, index) => (
                      <div key={index} className="flex items-end gap-2 rounded-md border p-3">
                        <div className="grid gap-1.5">
                          <Label htmlFor={`tier-min-${index}`}>Min Quantity</Label>
                          <Input id={`tier-min-${index}`} type="number" min="1" value={tier.min_qty}
                            onChange={(e) => updateTier(index, "min_qty", e.target.value)} placeholder="5" />
                        </div>
                        <div className="grid gap-1.5">
                          <Label htmlFor={`tier-percent-${index}`}>Discount (%)</Label>
                          <Input id={`tier-percent-${index}`} type="number" step="0.01" min="0.01" max="100"
                            value={tier.discount_percent} onChange={(e) => updateTier(index, "discount_percent", e.target.value)}
                            placeholder="10" />
                        </div>
                        <Button type="button" size="icon" variant="ghost"
                          className="mb-0.5 text-destructive" onClick={() => removeTier(index)}
                          aria-label={`Remove tier ${index + 1}`}>
                          <Trash2 className="h-4 w-4" />
                        </Button>
                      </div>
                    ))}
                  </div>
                  <p className="text-xs text-muted-foreground">
                    The highest tier whose minimum quantity is met applies its percentage to the eligible subtotal.
                    {form.data.is_automatic && " Enable Auto-discount to apply quantity breaks to every cart automatically."}
                  </p>
                </div>
              )}

              {isProductScoped && (
                <div className="grid gap-2">
                  <TermsSelector
                    label="Products"
                    description="Discount applies only to the selected products."
                    options={products.map((p) => ({ id: p.id, name: p.name }))}
                    value={form.data.product_ids}
                    onChange={(v) => form.setData("product_ids", v.map(Number))}
                    canAdd={false}
                    placeholder="Select products..."
                    error={form.errors.product_ids as string | undefined}
                  />
                  {isTiered
                    ? form.data.product_ids.length === 0 && (
                        <p className="text-xs text-muted-foreground">No products selected — all cart items are eligible for the tiers.</p>
                      )
                    : form.data.product_ids.length === 0 && (
                        <p className="text-xs text-amber-600">Select at least one product, otherwise the coupon never applies.</p>
                      )}
                </div>
              )}

              {!isFreeShipping && (
                <div className="grid gap-2">
                  <Label htmlFor="max_discount">Maximum Discount (leave empty for no limit)</Label>
                  <Input id="max_discount" type="number" step="0.01" min="0" value={form.data.max_discount}
                    onChange={(e) => form.setData("max_discount", e.target.value)} placeholder="No limit" />
                </div>
              )}

              <div className="grid gap-2">
                <Label htmlFor="min_order_amount">Minimum Order Amount</Label>
                <Input id="min_order_amount" type="number" step="0.01" min="0" value={form.data.min_order_amount}
                  onChange={(e) => form.setData("min_order_amount", e.target.value)} placeholder="No minimum" />
              </div>

              <div className="flex items-center gap-2">
                <Switch id="is_automatic" checked={form.data.is_automatic}
                  onCheckedChange={(v) => form.setData("is_automatic", v)} />
                <Label htmlFor="is_automatic" className="flex items-center gap-1">
                  <Sparkles className="h-3.5 w-3.5 text-amber-500" /> Auto-discount (applied without code)
                </Label>
              </div>

              {form.data.is_automatic && (
                <div className="grid gap-2">
                  <Label htmlFor="auto_discount_label">Display Label</Label>
                  <Input id="auto_discount_label" value={form.data.auto_discount_label}
                    onChange={(e) => form.setData("auto_discount_label", e.target.value)}
                    placeholder="e.g. Free Shipping on orders over €50" maxLength={255} />
                  <p className="text-xs text-muted-foreground">Shown in the cart to explain the applied discount.</p>
                </div>
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Restrictions</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <div className="grid gap-2">
                  <Label htmlFor="usage_limit">Global Usage Limit</Label>
                  <Input id="usage_limit" type="number" min="1" value={form.data.usage_limit}
                    onChange={(e) => form.setData("usage_limit", e.target.value)} placeholder="Unlimited" />
                </div>
                <div className="grid gap-2">
                  <Label htmlFor="per_user_limit">Per-User Limit</Label>
                  <Input id="per_user_limit" type="number" min="1" value={form.data.per_user_limit}
                    onChange={(e) => form.setData("per_user_limit", e.target.value)} placeholder="Unlimited" />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div className="grid gap-2">
                  <Label htmlFor="starts_at">Valid From</Label>
                  <Input id="starts_at" type="datetime-local" value={form.data.starts_at}
                    onChange={(e) => form.setData("starts_at", e.target.value)} />
                </div>
                <div className="grid gap-2">
                  <Label htmlFor="expires_at">Expires At</Label>
                  <Input id="expires_at" type="datetime-local" value={form.data.expires_at}
                    onChange={(e) => form.setData("expires_at", e.target.value)} />
                </div>
              </div>

              <div className="flex items-center gap-2">
                <Switch id="is_active" checked={form.data.is_active}
                  onCheckedChange={(v) => form.setData("is_active", v)} />
                <Label htmlFor="is_active">Active</Label>
              </div>
            </CardContent>
          </Card>

          {isEdit && (coupon?.usages_count ?? 0) > 0 && (
            <Card>
              <CardHeader>
                <CardTitle>Usage Stats</CardTitle>
              </CardHeader>
              <CardContent className="grid grid-cols-3 gap-4">
                <div>
                  <p className="text-sm text-muted-foreground">Times Used</p>
                  <p className="text-2xl font-bold">{coupon?.usages_count ?? 0}</p>
                </div>
                <div>
                  <p className="text-sm text-muted-foreground">Total Discount Given</p>
                  <p className="text-2xl font-bold">
                    {new Intl.NumberFormat("en-US", { style: "currency", currency }).format(coupon?.total_discount_given ?? 0)}
                  </p>
                </div>
                <div>
                  <p className="text-sm text-muted-foreground">Last Used</p>
                  <p className="text-2xl font-bold text-sm">
                    {coupon?.last_used_at ? new Date(coupon.last_used_at).toLocaleDateString() : "—"}
                  </p>
                </div>
              </CardContent>
            </Card>
          )}

          <div className="flex items-center gap-3">
            <Button type="submit" disabled={form.processing}>
              {form.processing ? "Saving..." : isEdit ? "Update Coupon" : "Create Coupon"}
            </Button>
            <Link href={route("dashboard.shop.coupons.index")}>
              <Button type="button" variant="outline">Cancel</Button>
            </Link>
          </div>
        </form>
      </div>
    </AuthenticatedLayout>
  );
}
