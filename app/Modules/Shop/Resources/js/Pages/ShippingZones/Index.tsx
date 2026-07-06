import { Head, Link, router } from "@inertiajs/react";
import { toast } from "sonner";
import { useState } from "react";
import { Plus, Pencil, Trash2, Globe } from "lucide-react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Button } from "@/Components/ui/button";
import { Badge } from "@/Components/ui/badge";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/Components/ui/dialog";
import { PageHeader, PaginationLinks, TableList } from "../../components/templates";

interface ShippingMethod { id: number; name: string; pivot?: { override_price: number | null } }
interface ShippingZone {
  id: number; name: string; countries: string[]; regions: string[] | null;
  is_active: boolean; methods: ShippingMethod[];
}

interface PaginatedData {
  data: ShippingZone[]; current_page: number; last_page: number;
  links: { url: string | null; label: string; active: boolean }[];
}
interface Props { zones: PaginatedData }

const COUNTRY_NAMES: Record<string, string> = {
  DE: "Germany", FR: "France", IT: "Italy", ES: "Spain", PT: "Portugal",
  NL: "Netherlands", BE: "Belgium", AT: "Austria", CH: "Switzerland",
  GB: "United Kingdom", IE: "Ireland", DK: "Denmark", SE: "Sweden",
  NO: "Norway", FI: "Finland", PL: "Poland", CZ: "Czech Republic",
  SK: "Slovakia", HU: "Hungary", RO: "Romania", BG: "Bulgaria",
  GR: "Greece", HR: "Croatia", SI: "Slovenia", LT: "Lithuania",
  LV: "Latvia", EE: "Estonia", LU: "Luxembourg", MT: "Malta", CY: "Cyprus",
  US: "United States", CA: "Canada", AU: "Australia", JP: "Japan",
  CN: "China", IN: "India", BR: "Brazil", MX: "Mexico",
};

export default function ShippingZonesIndex({ zones }: Props) {
  const [showDelete, setShowDelete] = useState<ShippingZone | null>(null);

  const handleDelete = () => {
    router.delete(route("dashboard.shop.shipping-zones.destroy", showDelete!.id), {
      preserveScroll: true, onSuccess: () => { setShowDelete(null); toast.success("Zone deleted."); },
    });
  };

  return (
    <AuthenticatedLayout header="Shipping Zones">
      <Head title="Shipping Zones" />
      <div className="space-y-6">
        <PageHeader
          title="Shipping Zones"
          actions={
            <Link href={route("dashboard.shop.shipping-zones.create")}>
              <Button><Plus className="mr-1 h-4 w-4" /> Create Zone</Button>
            </Link>
          }
        />

        <TableList
          data={zones.data}
          emptyTitle="No zones yet"
          emptyDescription="Create shipping zones to assign methods by country."
          emptyIcon={<Globe />}
          columns={[
            { key: "name", header: "Name", cell: (z) => <span className="font-medium text-sm">{z.name}</span> },
            {
              key: "countries", header: "Countries",
              cell: (z) => <span className="text-xs text-muted-foreground">{z.countries?.length ? z.countries.map((c) => COUNTRY_NAMES[c] || c).join(", ") : "All countries"}</span>,
            },
            {
              key: "methods", header: "Methods",
              cell: (z) => <span className="text-xs">{z.methods?.length ? z.methods.map((m) => m.name).join(", ") : "—"}</span>,
            },
            { key: "status", header: "Status", cell: (z) => <Badge variant={z.is_active ? "default" : "secondary"} className="text-xs">{z.is_active ? "Active" : "Inactive"}</Badge> },
            {
              key: "actions", header: "", className: "w-20",
              cell: (z) => (
                <div className="flex items-center gap-1">
                  <Link href={route("dashboard.shop.shipping-zones.edit", z.id)}>
                    <Button variant="ghost" size="icon-sm"><Pencil className="h-3.5 w-3.5" /></Button>
                  </Link>
                  <Button variant="ghost" size="icon-sm" onClick={() => setShowDelete(z)}><Trash2 className="h-3.5 w-3.5 text-destructive" /></Button>
                </div>
              ),
            },
          ]}
        />

        <PaginationLinks links={zones.links} />

        <Dialog open={!!showDelete} onOpenChange={(o) => { if (!o) setShowDelete(null); }}>
          <DialogContent>
            <DialogHeader><DialogTitle>Delete Zone</DialogTitle><DialogDescription>Delete "{showDelete?.name}"?</DialogDescription></DialogHeader>
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
