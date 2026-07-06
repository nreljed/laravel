import { Head, useForm, router } from "@inertiajs/react";
import { toast } from "sonner";
import { useState } from "react";
import { Plus, Pencil, Trash2, Percent, Check, X } from "lucide-react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import { Badge } from "@/Components/ui/badge";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/Components/ui/select";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from "@/Components/ui/dialog";
import { PageHeader, PaginationLinks, TableList } from "../../components/templates";

interface TaxRate {
  id: number; name: string; rate: number; type: string;
  country: string | null; state: string | null;
  is_active: boolean; is_default: boolean;
}

interface PaginatedData {
  data: TaxRate[]; current_page: number; last_page: number;
  links: { url: string | null; label: string; active: boolean }[];
}

interface Props { rates: PaginatedData }

const CTRY: Record<string, string> = {
  DE: "Germany", FR: "France", IT: "Italy", ES: "Spain", GB: "United Kingdom",
  US: "United States", CA: "Canada", AU: "Australia", AT: "Austria",
  NL: "Netherlands", BE: "Belgium", CH: "Switzerland",
};

const currency = (v: number) => new Intl.NumberFormat("en-US", { style: "currency", currency: "EUR" }).format(v);

export default function TaxRatesIndex({ rates }: Props) {
  const [editing, setEditing] = useState<TaxRate | null>(null);
  const [showCreate, setShowCreate] = useState(false);
  const [showDelete, setShowDelete] = useState<TaxRate | null>(null);

  const form = useForm({
    name: "", rate: "", type: "percentage", country: "DE", state: "",
    is_active: true, is_default: false,
  });

  const resetForm = () => form.reset();
  const fillForm = (r: TaxRate) => form.setData({
    name: r.name, rate: r.rate.toString(), type: r.type, country: r.country || "",
    state: r.state || "", is_active: r.is_active, is_default: r.is_default,
  });

  const handleCreate = (e: React.FormEvent) => {
    e.preventDefault();
    form.post(route("dashboard.shop.tax-rates.store"), {
      preserveScroll: true, onSuccess: () => { setShowCreate(false); resetForm(); toast.success("Rate created."); },
      onError: () => toast.error("Failed."),
    });
  };

  const handleUpdate = (e: React.FormEvent) => {
    e.preventDefault();
    form.put(route("dashboard.shop.tax-rates.update", editing!.id), {
      preserveScroll: true, onSuccess: () => { setEditing(null); resetForm(); toast.success("Rate updated."); },
      onError: () => toast.error("Failed."),
    });
  };

  const handleDelete = () => {
    router.delete(route("dashboard.shop.tax-rates.destroy", showDelete!.id), {
      preserveScroll: true, onSuccess: () => { setShowDelete(null); toast.success("Deleted."); },
    });
  };

  const toggleActive = (r: TaxRate) => {
    router.post(route("dashboard.shop.tax-rates.toggle-active", r.id), {}, {
      preserveScroll: true, onSuccess: () => toast.success(r.is_active ? "Deactivated." : "Activated."),
    });
  };

  const dialogForm = () => (
    <div className="space-y-3 py-4">
      <div className="grid gap-2"><Label>Name</Label><Input value={form.data.name} onChange={(e) => form.setData("name", e.target.value)} required placeholder="VAT 20%" /></div>
      <div className="grid grid-cols-2 gap-3">
        <div className="grid gap-2"><Label>Type</Label>
          <Select value={form.data.type} onValueChange={(v) => form.setData("type", v)}>
            <SelectTrigger><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem value="percentage">Percentage</SelectItem>
              <SelectItem value="fixed">Fixed Amount</SelectItem>
            </SelectContent>
          </Select>
        </div>
        <div className="grid gap-2"><Label>Rate</Label>
          <Input type="number" step="0.01" min="0" max="100" value={form.data.rate} onChange={(e) => form.setData("rate", e.target.value)} required placeholder="20" />
        </div>
      </div>
      <div className="grid grid-cols-2 gap-3">
        <div className="grid gap-2"><Label>Country (ISO2)</Label>
          <Input value={form.data.country} onChange={(e) => form.setData("country", e.target.value.toUpperCase())} placeholder="DE" maxLength={2} />
        </div>
        <div className="grid gap-2"><Label>State/Region</Label>
          <Input value={form.data.state} onChange={(e) => form.setData("state", e.target.value)} placeholder="Optional" />
        </div>
      </div>
    </div>
  );

  return (
    <AuthenticatedLayout header="Tax Rates">
      <Head title="Tax Rates" />
      <div className="space-y-6">
        <PageHeader
          title="Tax Rates"
          actions={
            <Dialog open={showCreate} onOpenChange={setShowCreate}>
              <DialogTrigger asChild><Button><Plus className="mr-1 h-4 w-4" /> Add Rate</Button></DialogTrigger>
              <DialogContent>
                <form onSubmit={handleCreate}>
                  <DialogHeader><DialogTitle>Create Tax Rate</DialogTitle><DialogDescription>Add a new tax rate.</DialogDescription></DialogHeader>
                  {dialogForm()}
                  <DialogFooter>
                    <Button variant="outline" onClick={() => setShowCreate(false)}>Cancel</Button>
                    <Button type="submit" disabled={form.processing}>Create</Button>
                  </DialogFooter>
                </form>
              </DialogContent>
            </Dialog>
          }
        />

        <TableList
          data={rates.data}
          emptyTitle="No tax rates yet"
          emptyDescription="Add tax rates for your store."
          emptyIcon={<Percent />}
          columns={[
            { key: "name", header: "Name", cell: (r) => <span className="font-medium text-sm">{r.name}</span> },
            {
              key: "rate", header: "Rate",
              cell: (r) => <span className="text-xs">{r.type === "percentage" ? `${r.rate}%` : currency(r.rate)}</span>,
            },
            { key: "country", header: "Country", cell: (r) => <span className="text-xs">{r.country ? `${CTRY[r.country] || r.country}` : "—"}</span> },
            { key: "default", header: "Default", cell: (r) => r.is_default ? <Badge variant="outline" className="text-xs">Default</Badge> : <span className="text-xs">—</span> },
            { key: "status", header: "Status", cell: (r) => <Badge variant={r.is_active ? "default" : "secondary"} className="text-xs">{r.is_active ? "Active" : "Inactive"}</Badge> },
            {
              key: "actions", header: "", className: "w-28",
              cell: (r) => (
                <div className="flex items-center gap-1">
                  <Button variant="ghost" size="icon-sm" onClick={() => toggleActive(r)}>{r.is_active ? <X className="h-3.5 w-3.5" /> : <Check className="h-3.5 w-3.5 text-green-500" />}</Button>
                  <Button variant="ghost" size="icon-sm" onClick={() => { setEditing(r); fillForm(r); }}><Pencil className="h-3.5 w-3.5" /></Button>
                  <Button variant="ghost" size="icon-sm" onClick={() => setShowDelete(r)}><Trash2 className="h-3.5 w-3.5 text-destructive" /></Button>
                </div>
              ),
            },
          ]}
        />

        <PaginationLinks links={rates.links} />

        <Dialog open={!!editing} onOpenChange={(o) => { if (!o) { setEditing(null); resetForm(); } }}>
          <DialogContent>
            <form onSubmit={handleUpdate}>
              <DialogHeader><DialogTitle>Edit Rate</DialogTitle></DialogHeader>
              {dialogForm()}
              <DialogFooter>
                <Button variant="outline" onClick={() => setEditing(null)}>Cancel</Button>
                <Button type="submit" disabled={form.processing}>Update</Button>
              </DialogFooter>
            </form>
          </DialogContent>
        </Dialog>

        <Dialog open={!!showDelete} onOpenChange={(o) => { if (!o) setShowDelete(null); }}>
          <DialogContent>
            <DialogHeader><DialogTitle>Delete Rate</DialogTitle><DialogDescription>Delete "{showDelete?.name}"?</DialogDescription></DialogHeader>
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
