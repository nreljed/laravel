import { Head, useForm, router } from "@inertiajs/react";
import { toast } from "sonner";
import { useState } from "react";
import { Plus, Pencil, Trash2, Coins, Check, X } from "lucide-react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import { Card, CardContent } from "@/Components/ui/card";
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from "@/Components/ui/table";
import { Badge } from "@/Components/ui/badge";
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from "@/Components/ui/select";
import {
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger,
} from "@/Components/ui/dialog";
import {
  Pagination, PaginationContent, PaginationItem, PaginationLink, PaginationNext, PaginationPrevious,
} from "@/Components/ui/pagination";
import { Empty, EmptyHeader, EmptyTitle, EmptyDescription, EmptyMedia } from "@/Components/ui/empty";
import { Switch } from "@/Components/ui/switch";

interface Currency {
  id: number; code: string; name: string; symbol: string;
  exchange_rate: number; decimal_places: number;
  is_default: boolean; is_active: boolean;
}
interface PaginatedData {
  data: Currency[];
  current_page: number; last_page: number;
  links: { url: string | null; label: string; active: boolean }[];
}
interface Props { currencies: PaginatedData }

export default function CurrenciesIndex({ currencies }: Props) {
  const [editing, setEditing] = useState<Currency | null>(null);
  const [showCreate, setShowCreate] = useState(false);
  const [showDelete, setShowDelete] = useState<Currency | null>(null);

  const form = useForm({
    code: "", name: "", symbol: "", exchange_rate: "1.0",
    decimal_places: "2", is_default: false, is_active: true,
  });

  const resetForm = () => form.reset();
  const fillForm = (c: Currency) => form.setData({
    code: c.code, name: c.name, symbol: c.symbol,
    exchange_rate: c.exchange_rate.toString(),
    decimal_places: c.decimal_places.toString(),
    is_default: c.is_default, is_active: c.is_active,
  });

  const handleCreate = (e: React.FormEvent) => {
    e.preventDefault();
    form.post(route("dashboard.shop.currencies.store"), {
      preserveScroll: true, onSuccess: () => { setShowCreate(false); resetForm(); toast.success("Currency created."); },
      onError: () => toast.error("Failed."),
    });
  };

  const handleUpdate = (e: React.FormEvent) => {
    e.preventDefault();
    form.put(route("dashboard.shop.currencies.update", editing!.id), {
      preserveScroll: true, onSuccess: () => { setEditing(null); resetForm(); toast.success("Currency updated."); },
      onError: () => toast.error("Failed."),
    });
  };

  const handleDelete = () => {
    router.delete(route("dashboard.shop.currencies.destroy", showDelete!.id), {
      preserveScroll: true, onSuccess: () => { setShowDelete(null); toast.success("Deleted."); },
    });
  };

  const toggleActive = (c: Currency) => {
    router.post(route("dashboard.shop.currencies.toggle-active", c.id), {}, {
      preserveScroll: true, onSuccess: () => toast.success(c.is_active ? "Deactivated." : "Activated."),
    });
  };

  const dialogForm = (onSubmit: (e: React.FormEvent) => void) => (
    <div className="space-y-3 py-4">
      <div className="grid grid-cols-3 gap-3">
        <div className="grid gap-2"><Label>Code</Label><Input value={form.data.code} onChange={(e) => form.setData("code", e.target.value.toUpperCase())} required placeholder="USD" maxLength={3} /></div>
        <div className="grid gap-2 col-span-2"><Label>Name</Label><Input value={form.data.name} onChange={(e) => form.setData("name", e.target.value)} required placeholder="US Dollar" /></div>
      </div>
      <div className="grid grid-cols-2 gap-3">
        <div className="grid gap-2"><Label>Symbol</Label><Input value={form.data.symbol} onChange={(e) => form.setData("symbol", e.target.value)} required placeholder="$" /></div>
        <div className="grid gap-2"><Label>Decimal Places</Label>
          <Select value={form.data.decimal_places} onValueChange={(v) => form.setData("decimal_places", v)}>
            <SelectTrigger><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem value="0">0 (JPY, KRW)</SelectItem>
              <SelectItem value="2">2 (USD, EUR, GBP)</SelectItem>
              <SelectItem value="3">3 (TND, KWD)</SelectItem>
              <SelectItem value="4">4 (extra precision)</SelectItem>
            </SelectContent>
          </Select>
        </div>
      </div>
      <div className="grid gap-2"><Label>Exchange Rate (to default currency)</Label>
        <Input type="number" step="0.000001" min="0" value={form.data.exchange_rate} onChange={(e) => form.setData("exchange_rate", e.target.value)} required placeholder="1.0" />
      </div>
      <div className="flex items-center gap-4 pt-2">
        <div className="flex items-center gap-2">
          <Switch checked={form.data.is_default} onCheckedChange={(v) => form.setData("is_default", v)} id="is_default" />
          <Label htmlFor="is_default">Default</Label>
        </div>
        <div className="flex items-center gap-2">
          <Switch checked={form.data.is_active} onCheckedChange={(v) => form.setData("is_active", v)} id="is_active" />
          <Label htmlFor="is_active">Active</Label>
        </div>
      </div>
    </div>
  );

  return (
    <AuthenticatedLayout header="Currencies">
      <Head title="Currencies" />
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <h2 className="text-lg font-semibold">Currencies</h2>
          <Dialog open={showCreate} onOpenChange={setShowCreate}>
            <DialogTrigger asChild><Button><Plus className="mr-1 h-4 w-4" /> Add Currency</Button></DialogTrigger>
            <DialogContent className="max-w-lg">
              <form onSubmit={handleCreate}>
                <DialogHeader><DialogTitle>Create Currency</DialogTitle><DialogDescription>Add a new currency to your store.</DialogDescription></DialogHeader>
                {dialogForm(handleCreate)}
                <DialogFooter>
                  <Button variant="outline" onClick={() => setShowCreate(false)}>Cancel</Button>
                  <Button type="submit" disabled={form.processing}>Create</Button>
                </DialogFooter>
              </form>
            </DialogContent>
          </Dialog>
        </div>

        {currencies.data.length === 0 ? (
          <Empty><EmptyHeader><EmptyMedia variant="icon"><Coins /></EmptyMedia><EmptyTitle>No currencies yet</EmptyTitle><EmptyDescription>Add currencies for your store.</EmptyDescription></EmptyHeader></Empty>
        ) : (
          <Card><CardContent className="p-0">
            <Table>
              <TableHeader><TableRow>
                <TableHead>Code</TableHead><TableHead>Name</TableHead><TableHead>Symbol</TableHead><TableHead>Rate</TableHead><TableHead>Default</TableHead><TableHead>Status</TableHead><TableHead className="w-28"></TableHead>
              </TableRow></TableHeader>
              <TableBody>
                {currencies.data.map((c) => (
                  <TableRow key={c.id}>
                    <TableCell className="font-mono font-medium text-sm">{c.code}</TableCell>
                    <TableCell className="text-sm">{c.name}</TableCell>
                    <TableCell className="text-lg">{c.symbol}</TableCell>
                    <TableCell className="text-xs font-mono">{c.exchange_rate.toFixed(6)}</TableCell>
                    <TableCell>{c.is_default ? <Badge variant="outline" className="text-xs">Default</Badge> : "—"}</TableCell>
                    <TableCell><Badge variant={c.is_active ? "default" : "secondary"} className="text-xs">{c.is_active ? "Active" : "Inactive"}</Badge></TableCell>
                    <TableCell>
                      <div className="flex items-center gap-1">
                        <Button variant="ghost" size="icon-sm" onClick={() => toggleActive(c)}>{c.is_active ? <X className="h-3.5 w-3.5" /> : <Check className="h-3.5 w-3.5 text-green-500" />}</Button>
                        <Button variant="ghost" size="icon-sm" onClick={() => { setEditing(c); fillForm(c); }}><Pencil className="h-3.5 w-3.5" /></Button>
                        <Button variant="ghost" size="icon-sm" onClick={() => setShowDelete(c)} disabled={c.is_default}><Trash2 className="h-3.5 w-3.5 text-destructive" /></Button>
                      </div>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </CardContent></Card>
        )}

        {currencies.last_page > 1 && (
          <Pagination><PaginationContent>{currencies.links.map((l, i) => (
            <PaginationItem key={i}>
              {l.url ? (l.label.includes("Previous") ? <PaginationPrevious href={l.url} /> : l.label.includes("Next") ? <PaginationNext href={l.url} /> : <PaginationLink href={l.url} isActive={l.active}>{l.label}</PaginationLink>)
              : <span className="px-3 py-1 text-muted-foreground">{l.label}</span>}
            </PaginationItem>
          ))}</PaginationContent></Pagination>
        )}

        <Dialog open={!!editing} onOpenChange={(o) => { if (!o) setEditing(null); }}>
          <DialogContent className="max-w-lg">
            <form onSubmit={handleUpdate}>
              <DialogHeader><DialogTitle>Edit Currency</DialogTitle></DialogHeader>
              {dialogForm(handleUpdate)}
              <DialogFooter>
                <Button variant="outline" onClick={() => setEditing(null)}>Cancel</Button>
                <Button type="submit" disabled={form.processing}>Update</Button>
              </DialogFooter>
            </form>
          </DialogContent>
        </Dialog>

        <Dialog open={!!showDelete} onOpenChange={(o) => { if (!o) setShowDelete(null); }}>
          <DialogContent>
            <DialogHeader><DialogTitle>Delete Currency</DialogTitle><DialogDescription>Delete "{showDelete?.code} - {showDelete?.name}"?</DialogDescription></DialogHeader>
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
