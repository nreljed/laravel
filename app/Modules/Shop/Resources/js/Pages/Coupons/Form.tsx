import { Head, Link, useForm } from "@inertiajs/react";
import { toast } from "sonner";
import { ArrowLeft, Percent, DollarSign, Truck, Sparkles } from "lucide-react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from "@/Components/ui/select";
import { Switch } from "@/Components/ui/switch";

interface Product { id: number; name: string }
interface CouponData {
  id?: number; code: string; type: string; value: string;
  min_order_amount: string; max_discount: string;
  usage_limit: string; per_user_limit: string;
  is_active: boolean; is_automatic: boolean; auto_discount_label: string;
  starts_at: string; expires_at: string;
  product_ids: number[];
  usages_count?: number; total_discount_given?: number; last_used_at?: string;
  products?: Product[];
}

interface Props {
  coupon: (CouponData & { usages_count?: number; total_discount_given?: number; last_used_at?: string }) | null;
}

export default function CouponForm({ coupon }: Props) {
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
    starts_at: coupon?.starts_at ? coupon.starts_at.slice(0, 16) : "",
    expires_at: coupon?.expires_at ? coupon.expires_at.slice(0, 16) : "",
    product_ids: coupon?.products?.map((p) => p.id) || [],
  });

  const isFreeShipping = form.data.type === "free_shipping";

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (isEdit) {
      form.put(route("dashboard.shop.coupons.update", coupon!.id), {
        preserveScroll: true,
        onSuccess: () => toast.success("Coupon updated."),
        onError: () => toast.error("Failed to update coupon."),
      });
    } else {
      form.post(route("dashboard.shop.coupons.store"), {
        preserveScroll: true,
        onSuccess: () => toast.success("Coupon created."),
        onError: () => toast.error("Failed to create coupon."),
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
                    </SelectContent>
                  </Select>
                </div>
                {!isFreeShipping && (
                  <div className="grid gap-2">
                    <Label htmlFor="value">Value {form.data.type === "percentage" ? "(%)" : "(EUR)"}</Label>
                    <Input id="value" type="number" step="0.01" min="0" value={form.data.value}
                      onChange={(e) => form.setData("value", e.target.value)}
                      placeholder={form.data.type === "percentage" ? "10" : "5.00"} required={!isFreeShipping} />
                    {form.errors.value && <p className="text-xs text-destructive">{form.errors.value}</p>}
                  </div>
                )}
              </div>

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
                    {new Intl.NumberFormat("en-US", { style: "currency", currency: "EUR" }).format(coupon?.total_discount_given ?? 0)}
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
