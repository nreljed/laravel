import { Head, useForm, router } from "@inertiajs/react";
import { toast } from "sonner";
import { useState } from "react";
import { Plus, Pencil, Trash2, Truck, Check, X } from "lucide-react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Textarea } from "@/Components/ui/textarea";
import { Label } from "@/Components/ui/label";
import { Badge } from "@/Components/ui/badge";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from "@/Components/ui/dialog";
import { PageHeader, PaginationLinks, TableList } from "../../components/templates";

interface ShippingMethod {
  id: number; name: string; description: string | null;
  price: number; free_above: number | null;
  estimated_days_min: number | null; estimated_days_max: number | null;
  is_active: boolean; sort_order: number;
}

interface PaginatedData {
  data: ShippingMethod[]; current_page: number; last_page: number;
  links: { url: string | null; label: string; active: boolean }[];
}

interface Props { methods: PaginatedData }

const currency = (v: number) => new Intl.NumberFormat("en-US", { style: "currency", currency: "EUR" }).format(v);

export default function ShippingMethodsIndex({ methods }: Props) {
  const [editing, setEditing] = useState<ShippingMethod | null>(null);
  const [showCreate, setShowCreate] = useState(false);
  const [showDelete, setShowDelete] = useState<ShippingMethod | null>(null);

  const form = useForm({
    name: "", description: "", price: "", free_above: "",
    estimated_days_min: "", estimated_days_max: "",
    is_active: true, sort_order: "0",
  });

  const resetForm = () => form.reset();
  const fillForm = (m: ShippingMethod) => form.setData({
    name: m.name, description: m.description || "", price: m.price.toString(),
    free_above: m.free_above?.toString() || "", estimated_days_min: m.estimated_days_min?.toString() || "",
    estimated_days_max: m.estimated_days_max?.toString() || "", is_active: m.is_active, sort_order: m.sort_order.toString(),
  });

  const handleCreate = (e: React.FormEvent) => {
    e.preventDefault();
    form.post(route("dashboard.shop.shipping-methods.store"), {
      preserveScroll: true, onSuccess: () => { setShowCreate(false); resetForm(); toast.success("Method created."); },
      onError: () => toast.error("Failed."),
    });
  };

  const handleUpdate = (e: React.FormEvent) => {
    e.preventDefault();
    form.put(route("dashboard.shop.shipping-methods.update", editing!.id), {
      preserveScroll: true, onSuccess: () => { setEditing(null); resetForm(); toast.success("Method updated."); },
      onError: () => toast.error("Failed."),
    });
  };

  const handleDelete = () => {
    router.delete(route("dashboard.shop.shipping-methods.destroy", showDelete!.id), {
      preserveScroll: true, onSuccess: () => { setShowDelete(null); toast.success("Deleted."); },
    });
  };

  const toggleActive = (m: ShippingMethod) => {
    router.post(route("dashboard.shop.shipping-methods.toggle-active", m.id), {}, {
      preserveScroll: true, onSuccess: () => toast.success(m.is_active ? "Deactivated." : "Activated."),
    });
  };

  const methodDialog = (onSubmit: (e: React.FormEvent) => void, title: string) => (
    <form onSubmit={onSubmit}>
      <DialogHeader><DialogTitle>{title}</DialogTitle><DialogDescription>{title === "Create Method" ? "Add a new shipping method." : ""}</DialogDescription></DialogHeader>
      <div className="space-y-3 py-4">
        <div className="grid gap-2"><Label>Name</Label><Input value={form.data.name} onChange={(e) => form.setData("name", e.target.value)} required /></div>
        <div className="grid gap-2"><Label>Description</Label><Textarea rows={2} value={form.data.description} onChange={(e) => form.setData("description", e.target.value)} /></div>
        <div className="grid grid-cols-2 gap-3">
          <div className="grid gap-2"><Label>Price (EUR)</Label><Input type="number" step="0.01" min="0" value={form.data.price} onChange={(e) => form.setData("price", e.target.value)} required /></div>
          <div className="grid gap-2"><Label>Free Above</Label><Input type="number" step="0.01" min="0" value={form.data.free_above} onChange={(e) => form.setData("free_above", e.target.value)} placeholder="Optional" /></div>
        </div>
        <div className="grid grid-cols-2 gap-3">
          <div className="grid gap-2"><Label>Est. Min Days</Label><Input type="number" min="1" value={form.data.estimated_days_min} onChange={(e) => form.setData("estimated_days_min", e.target.value)} /></div>
          <div className="grid gap-2"><Label>Est. Max Days</Label><Input type="number" min="1" value={form.data.estimated_days_max} onChange={(e) => form.setData("estimated_days_max", e.target.value)} /></div>
        </div>
        <div className="grid gap-2"><Label>Sort Order</Label><Input type="number" min="0" value={form.data.sort_order} onChange={(e) => form.setData("sort_order", e.target.value)} /></div>
      </div>
      <DialogFooter>
        <Button type="button" variant="outline" onClick={() => { setShowCreate(false); setEditing(null); }}>Cancel</Button>
        <Button type="submit" disabled={form.processing}>{title.startsWith("Create") ? "Create" : "Update"}</Button>
      </DialogFooter>
    </form>
  );

  return (
    <AuthenticatedLayout header="Shipping Methods">
      <Head title="Shipping Methods" />
      <div className="space-y-6">
        <PageHeader
          title="Shipping Methods"
          actions={
            <Dialog open={showCreate} onOpenChange={setShowCreate}>
              <DialogTrigger asChild><Button><Plus className="mr-1 h-4 w-4" /> Add Method</Button></DialogTrigger>
              <DialogContent className="max-w-lg">{methodDialog(handleCreate, "Create Method")}</DialogContent>
            </Dialog>
          }
        />

        <TableList
          data={methods.data}
          emptyTitle="No methods yet"
          emptyDescription="Add shipping methods to offer delivery options."
          emptyIcon={<Truck />}
          columns={[
            {
              key: "name", header: "Name",
              cell: (m) => <><p className="font-medium text-sm">{m.name}</p>{m.description && <p className="text-xs text-muted-foreground">{m.description}</p>}</>,
            },
            { key: "price", header: "Price", cell: (m) => <span className="text-xs">{currency(m.price)}</span> },
            { key: "free_above", header: "Free Above", cell: (m) => <span className="text-xs">{m.free_above ? currency(m.free_above) : "—"}</span> },
            { key: "days", header: "Est. Days", cell: (m) => <span className="text-xs">{m.estimated_days_min ? `${m.estimated_days_min}-${m.estimated_days_max} days` : "—"}</span> },
            { key: "status", header: "Status", cell: (m) => <Badge variant={m.is_active ? "default" : "secondary"} className="text-xs">{m.is_active ? "Active" : "Inactive"}</Badge> },
            {
              key: "actions", header: "", className: "w-28",
              cell: (m) => (
                <div className="flex items-center gap-1">
                  <Button variant="ghost" size="icon-sm" onClick={() => toggleActive(m)} title={m.is_active ? "Deactivate" : "Activate"}>
                    {m.is_active ? <X className="h-3.5 w-3.5" /> : <Check className="h-3.5 w-3.5 text-green-500" />}
                  </Button>
                  <Button variant="ghost" size="icon-sm" onClick={() => { setEditing(m); fillForm(m); }}><Pencil className="h-3.5 w-3.5" /></Button>
                  <Button variant="ghost" size="icon-sm" onClick={() => setShowDelete(m)}><Trash2 className="h-3.5 w-3.5 text-destructive" /></Button>
                </div>
              ),
            },
          ]}
        />

        <PaginationLinks links={methods.links} />

        <Dialog open={!!editing} onOpenChange={(o) => { if (!o) { setEditing(null); resetForm(); } }}>
          <DialogContent className="max-w-lg">{methodDialog(handleUpdate, "Edit Method")}</DialogContent>
        </Dialog>

        <Dialog open={!!showDelete} onOpenChange={(o) => { if (!o) setShowDelete(null); }}>
          <DialogContent>
            <DialogHeader><DialogTitle>Delete Method</DialogTitle><DialogDescription>Delete "{showDelete?.name}"?</DialogDescription></DialogHeader>
            <DialogFooter>
              <Button variant="outline" onClick={() => setShowDelete(null)}>Cancel</Button>
              <Button variant="destructive" onClick={handleDelete}>Delete</Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      </div>
    </AuthenticatedLayout>
  );
}
