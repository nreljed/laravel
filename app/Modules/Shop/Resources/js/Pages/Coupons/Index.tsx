import { Head, Link, router } from "@inertiajs/react";
import { toast } from "sonner";
import { useState } from "react";
import { Plus, Pencil, Trash2, Tag, Copy, Check, Truck, Sparkles } from "lucide-react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Button } from "@/Components/ui/button";
import { Badge } from "@/Components/ui/badge";
import {
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from "@/Components/ui/dialog";
import { PageHeader, PaginationLinks, TableList } from "../../components/templates";

interface Coupon {
  id: number; code: string; type: string; value: number;
  min_order_amount: number | null; max_discount: number | null;
  usage_limit: number | null; per_user_limit: number | null;
  used_count: number; is_active: boolean; is_automatic: boolean;
  auto_discount_label: string | null;
  starts_at: string | null; expires_at: string | null;
  usages_count: number;
  created_at: string;
}

interface PaginatedData {
  data: Coupon[];
  current_page: number; last_page: number;
  links: { url: string | null; label: string; active: boolean }[];
}

interface Props { coupons: PaginatedData }

function discountLabel(c: Coupon): string {
  if (c.type === "free_shipping") return "Free Shipping";
  if (c.type === "percentage") return `${c.value}%`;
  return new Intl.NumberFormat("en-US", { style: "currency", currency: "EUR" }).format(c.value);
}

function maxDiscountLabel(c: Coupon): string {
  if (!c.max_discount) return "";
  return ` (max ${new Intl.NumberFormat("en-US", { style: "currency", currency: "EUR" }).format(c.max_discount)})`;
}

export default function CouponsIndex({ coupons }: Props) {
  const [showDelete, setShowDelete] = useState<Coupon | null>(null);
  const [copiedId, setCopiedId] = useState<number | null>(null);

  const handleDelete = () => {
    if (!showDelete) return;
    router.delete(route("dashboard.shop.coupons.destroy", showDelete.id), {
      preserveScroll: true,
      onSuccess: () => { setShowDelete(null); toast.success("Coupon deleted."); },
      onError: () => toast.error("Failed to delete coupon."),
    });
  };

  const toggleActive = (coupon: Coupon) => {
    router.post(route("dashboard.shop.coupons.toggle-active", coupon.id), {}, {
      preserveScroll: true,
      onSuccess: () => toast.success(coupon.is_active ? "Coupon deactivated." : "Coupon activated."),
    });
  };

  const copyCode = async (code: string, id: number) => {
    try { await navigator.clipboard.writeText(code); setCopiedId(id); setTimeout(() => setCopiedId(null), 2000); }
    catch { toast.error("Failed to copy."); }
  };

  return (
    <AuthenticatedLayout header="Coupons">
      <Head title="Coupons" />

      <div className="space-y-6">
        <PageHeader
          title="Coupons"
          actions={
            <Link href={route("dashboard.shop.coupons.create")}>
              <Button><Plus className="mr-1 h-4 w-4" /> Create Coupon</Button>
            </Link>
          }
        />

        <TableList
          columns={[
            {
              key: "code",
              header: "Code",
              cell: (c: Coupon) => (
                <div className="flex items-center gap-1.5">
                  <span className="font-mono font-medium text-xs">{c.code}</span>
                  {c.is_automatic && <Sparkles className="h-3 w-3 text-amber-500" title="Auto-discount" />}
                  {c.type === "free_shipping" && <Truck className="h-3 w-3 text-muted-foreground" title="Free shipping" />}
                </div>
              ),
            },
            {
              key: "discount",
              header: "Discount",
              cell: (c: Coupon) => <span className="text-xs">{discountLabel(c)}{maxDiscountLabel(c)}</span>,
            },
            {
              key: "usage",
              header: "Usage",
              cell: (c: Coupon) => <span className="text-xs">{c.used_count}{c.usage_limit ? ` / ${c.usage_limit}` : ""}</span>,
            },
            {
              key: "valid",
              header: "Valid",
              cell: (c: Coupon) => (
                <span className="text-xs text-muted-foreground">
                  {c.expires_at ? `Until ${new Date(c.expires_at).toLocaleDateString()}` : "No expiry"}
                </span>
              ),
            },
            {
              key: "status",
              header: "Status",
              cell: (c: Coupon) => (
                <Badge variant={c.is_active ? (c.usage_limit && c.used_count >= c.usage_limit ? "secondary" : "default") : "secondary"} className="text-xs">
                  {c.is_active ? (c.usage_limit && c.used_count >= c.usage_limit ? "Exhausted" : "Active") : "Inactive"}
                </Badge>
              ),
            },
            {
              key: "actions",
              header: "",
              className: "w-24",
              cell: (c: Coupon) => (
                <div className="flex items-center gap-1">
                  <Button variant="ghost" size="icon-sm" onClick={() => copyCode(c.code, c.id)} title="Copy code">
                    {copiedId === c.id ? <Check className="h-3.5 w-3.5 text-green-500" /> : <Copy className="h-3.5 w-3.5" />}
                  </Button>
                  <Button variant="ghost" size="icon-sm" onClick={() => toggleActive(c)} title={c.is_active ? "Deactivate" : "Activate"}>
                    <Tag className={`h-3.5 w-3.5 ${c.is_active ? "text-muted-foreground" : "text-destructive"}`} />
                  </Button>
                  <Link href={route("dashboard.shop.coupons.edit", c.id)}>
                    <Button variant="ghost" size="icon-sm"><Pencil className="h-3.5 w-3.5" /></Button>
                  </Link>
                  <Button variant="ghost" size="icon-sm" onClick={() => setShowDelete(c)}>
                    <Trash2 className="h-3.5 w-3.5 text-destructive" />
                  </Button>
                </div>
              ),
            },
          ]}
          data={coupons.data}
          emptyTitle="No coupons yet"
          emptyDescription="Create discount coupons to offer promotions to your customers."
          emptyIcon={<Tag />}
        />

        <PaginationLinks links={coupons.links} />

        <Dialog open={!!showDelete} onOpenChange={(o) => { if (!o) setShowDelete(null); }}>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>Delete Coupon</DialogTitle>
              <DialogDescription>Are you sure you want to delete "{showDelete?.code}"?</DialogDescription>
            </DialogHeader>
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setShowDelete(null)}>Cancel</Button>
              <Button type="button" variant="destructive" onClick={handleDelete}>Delete</Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      </div>
    </AuthenticatedLayout>
  );
}
