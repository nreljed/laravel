import { Head, Link, useForm } from "@inertiajs/react";
import { toast } from "sonner";
import { ArrowLeft, X } from "lucide-react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Switch } from "@/Components/ui/switch";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/Components/ui/select";

interface ShippingMethod {
  id: number; name: string; price: number;
}
interface ZoneData {
  id?: number; name: string; countries: string[]; regions: string[] | null;
  is_active: boolean; methods?: ShippingMethod[];
}
interface Props {
  zone: (ZoneData & { methods?: ShippingMethod[] }) | null;
  methods: ShippingMethod[];
}

const COUNTRY_OPTIONS = [
  { code: "DE", name: "Germany" }, { code: "FR", name: "France" }, { code: "IT", name: "Italy" },
  { code: "ES", name: "Spain" }, { code: "PT", name: "Portugal" }, { code: "NL", name: "Netherlands" },
  { code: "BE", name: "Belgium" }, { code: "AT", name: "Austria" }, { code: "CH", name: "Switzerland" },
  { code: "GB", name: "United Kingdom" }, { code: "IE", name: "Ireland" }, { code: "DK", name: "Denmark" },
  { code: "SE", name: "Sweden" }, { code: "NO", name: "Norway" }, { code: "FI", name: "Finland" },
  { code: "PL", name: "Poland" }, { code: "CZ", name: "Czech Republic" }, { code: "SK", name: "Slovakia" },
  { code: "HU", name: "Hungary" }, { code: "RO", name: "Romania" }, { code: "BG", name: "Bulgaria" },
  { code: "GR", name: "Greece" }, { code: "HR", name: "Croatia" }, { code: "US", name: "United States" },
  { code: "CA", name: "Canada" }, { code: "AU", name: "Australia" }, { code: "JP", name: "Japan" },
];

export default function ShippingZoneForm({ zone, methods }: Props) {
  const isEdit = !!zone?.id;

  const zoneMethods = zone?.methods?.map((m) => ({
    id: m.id,
    override_price: m.pivot?.override_price?.toString() || "",
  })) || [];

  const form = useForm({
    name: zone?.name || "",
    countries: zone?.countries || [] as string[],
    is_active: zone?.is_active ?? true,
    method_ids: zoneMethods,
  });

  const addCountry = (code: string) => {
    if (!form.data.countries.includes(code)) {
      form.setData("countries", [...form.data.countries, code]);
    }
  };

  const removeCountry = (code: string) => {
    form.setData("countries", form.data.countries.filter((c) => c !== code));
  };

  const toggleMethod = (methodId: number) => {
    const existing = form.data.method_ids.find((m) => m.id === methodId);
    if (existing) {
      form.setData("method_ids", form.data.method_ids.filter((m) => m.id !== methodId));
    } else {
      form.setData("method_ids", [...form.data.method_ids, { id: methodId, override_price: "" }]);
    }
  };

  const setOverridePrice = (methodId: number, price: string) => {
    form.setData("method_ids", form.data.method_ids.map((m) =>
      m.id === methodId ? { ...m, override_price: price } : m,
    ));
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (isEdit) {
      form.put(route("dashboard.shop.shipping-zones.update", zone!.id), {
        preserveScroll: true, onSuccess: () => toast.success("Zone updated."),
        onError: () => toast.error("Failed."),
      });
    } else {
      form.post(route("dashboard.shop.shipping-zones.store"), {
        preserveScroll: true, onSuccess: () => toast.success("Zone created."),
        onError: () => toast.error("Failed."),
      });
    }
  };

  return (
    <AuthenticatedLayout header={isEdit ? `Edit Zone: ${zone!.name}` : "Create Shipping Zone"}>
      <Head title={isEdit ? "Edit Zone" : "Create Zone"} />
      <div className="space-y-6">
        <Link href={route("dashboard.shop.shipping-zones.index")} className="inline-flex items-center text-sm text-muted-foreground hover:text-foreground">
          <ArrowLeft className="mr-1 h-4 w-4" /> Back to Zones
        </Link>

        <form onSubmit={handleSubmit} className="space-y-6">
          <Card>
            <CardHeader><CardTitle>Details</CardTitle></CardHeader>
            <CardContent className="space-y-4">
              <div className="grid gap-2">
                <Label htmlFor="name">Zone Name</Label>
                <Input id="name" value={form.data.name} onChange={(e) => form.setData("name", e.target.value)} required placeholder="EU Zone" />
              </div>

              <div className="grid gap-2">
                <Label>Countries</Label>
                <Select onValueChange={addCountry}>
                  <SelectTrigger><SelectValue placeholder="Add a country..." /></SelectTrigger>
                  <SelectContent>
                    {COUNTRY_OPTIONS.filter((c) => !form.data.countries.includes(c.code)).map((c) => (
                      <SelectItem key={c.code} value={c.code}>{c.name} ({c.code})</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                {form.data.countries.length > 0 && (
                  <div className="flex flex-wrap gap-1.5 mt-1">
                    {form.data.countries.map((code) => (
                      <span key={code} className="inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-xs">
                        {COUNTRY_OPTIONS.find((c) => c.code === code)?.name || code}
                        <button type="button" onClick={() => removeCountry(code)}><X className="h-3 w-3" /></button>
                      </span>
                    ))}
                  </div>
                )}
                {form.data.countries.length === 0 && <p className="text-xs text-muted-foreground">No country selected = applies to all</p>}
              </div>

              <div className="flex items-center gap-2">
                <Switch id="is_active" checked={form.data.is_active} onCheckedChange={(v) => form.setData("is_active", v)} />
                <Label htmlFor="is_active">Active</Label>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader><CardTitle>Shipping Methods</CardTitle></CardHeader>
            <CardContent className="space-y-3">
              {methods.map((method) => {
                const selected = form.data.method_ids.find((m) => m.id === method.id);
                return (
                  <div key={method.id} className="flex items-center gap-3 rounded-md border p-3">
                    <input type="checkbox" checked={!!selected} onChange={() => toggleMethod(method.id)} className="h-4 w-4" />
                    <div className="flex-1">
                      <p className="text-sm font-medium">{method.name}</p>
                      <p className="text-xs text-muted-foreground">Default: {new Intl.NumberFormat("en-US", { style: "currency", currency: "EUR" }).format(method.price)}</p>
                    </div>
                    {selected && (
                      <div className="w-32">
                        <Input
                          type="number"
                          step="0.01"
                          min="0"
                          placeholder="Override price"
                          value={selected.override_price}
                          onChange={(e) => setOverridePrice(method.id, e.target.value)}
                          className="h-8 text-xs"
                        />
                      </div>
                    )}
                  </div>
                );
              })}
            </CardContent>
          </Card>

          <div className="flex items-center gap-3">
            <Button type="submit" disabled={form.processing}>{form.processing ? "Saving..." : isEdit ? "Update Zone" : "Create Zone"}</Button>
            <Link href={route("dashboard.shop.shipping-zones.index")}><Button variant="outline">Cancel</Button></Link>
          </div>
        </form>
      </div>
    </AuthenticatedLayout>
  );
}
