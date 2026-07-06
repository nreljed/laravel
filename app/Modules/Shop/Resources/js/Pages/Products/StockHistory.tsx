import { Head, Link, router } from "@inertiajs/react";
import { ArrowLeft, Package, Plus, Minus } from "lucide-react";
import { useState } from "react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Button } from "@/Components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Badge } from "@/Components/ui/badge";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import { Textarea } from "@/Components/ui/textarea";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/Components/ui/dialog";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/Components/ui/table";

interface Adjustment {
  id: number;
  type: string;
  quantity: number;
  stock_before: number;
  stock_after: number;
  reason: string | null;
  reference_type: string | null;
  reference_id: number | null;
  user: { id: number; name: string } | null;
  created_at: string;
}

interface StockHistoryProps {
  product: { id: number; name: string; sku: string | null; stock_quantity: number };
  adjustments: { data: Adjustment[] };
}

function typeBadge(type: string) {
  switch (type) {
    case "sale": return "destructive" as const;
    case "refund": return "default" as const;
    case "manual": return "secondary" as const;
    case "correction": return "outline" as const;
    default: return "secondary" as const;
  }
}

export default function StockHistory({ product, adjustments }: StockHistoryProps) {
  const [quantity, setQuantity] = useState(0);
  const [reason, setReason] = useState("");
  const [open, setOpen] = useState(false);

  const handleAdjust = (e: React.FormEvent) => {
    e.preventDefault();
    router.post(route("dashboard.shop.products.adjust-stock", product.id), {
      quantity,
      reason,
    }, {
      preserveScroll: true,
      onSuccess: () => {
        setQuantity(0);
        setReason("");
        setOpen(false);
        router.reload({ only: ["adjustments"] });
      },
    });
  };

  return (
    <AuthenticatedLayout
      header={
        <div className="flex items-center gap-3">
          <Link href={route("dashboard.shop.products.index")}>
            <Button variant="ghost" size="icon">
              <ArrowLeft className="h-4 w-4" />
            </Button>
          </Link>
          <div>
            <h2 className="text-lg font-semibold">{product.name}</h2>
            <p className="text-sm text-muted-foreground">SKU: {product.sku ?? "N/A"} &middot; Current stock: {product.stock_quantity}</p>
          </div>
        </div>
      }
    >
      <Head title={`Stock History - ${product.name}`} />

      <div className="space-y-6">
        <div className="flex items-center justify-end">
          <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger>
              <Button>
                <Plus className="mr-1 h-4 w-4" /> Adjust Stock
              </Button>
            </DialogTrigger>
            <DialogContent>
              <DialogHeader>
                <DialogTitle>Adjust Stock</DialogTitle>
              </DialogHeader>
              <form onSubmit={handleAdjust} className="space-y-4">
                <div>
                  <Label htmlFor="quantity">Quantity Change</Label>
                  <div className="flex items-center gap-2 mt-1">
                    <Button type="button" variant="outline" size="icon" onClick={() => setQuantity((q) => q - 1)}>
                      <Minus className="h-3 w-3" />
                    </Button>
                    <Input id="quantity" type="number" value={quantity} onChange={(e) => setQuantity(Number(e.target.value))} className="text-center w-24" />
                    <Button type="button" variant="outline" size="icon" onClick={() => setQuantity((q) => q + 1)}>
                      <Plus className="h-3 w-3" />
                    </Button>
                  </div>
                  <p className="text-xs text-muted-foreground mt-1">Use positive to add stock, negative to remove</p>
                </div>
                <div>
                  <Label htmlFor="reason">Reason</Label>
                  <Textarea id="reason" value={reason} onChange={(e) => setReason(e.target.value)} placeholder="e.g. Supplier restock, damaged item" required />
                </div>
                <Button type="submit" className="w-full">Save Adjustment</Button>
              </form>
            </DialogContent>
          </Dialog>
        </div>

        <Card>
          <CardContent className="p-0">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Date</TableHead>
                  <TableHead>Type</TableHead>
                  <TableHead>Qty</TableHead>
                  <TableHead>Before</TableHead>
                  <TableHead>After</TableHead>
                  <TableHead>Reason</TableHead>
                  <TableHead>By</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {adjustments.data.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={7} className="text-center text-muted-foreground py-8">No stock adjustments recorded yet.</TableCell>
                  </TableRow>
                ) : (
                  adjustments.data.map((adj) => (
                    <TableRow key={adj.id}>
                      <TableCell className="text-xs whitespace-nowrap">{new Date(adj.created_at).toLocaleString()}</TableCell>
                      <TableCell>
                        <Badge variant={typeBadge(adj.type)} className="text-xs">{adj.type}</Badge>
                      </TableCell>
                      <TableCell className={adj.quantity < 0 ? "text-destructive" : "text-green-600"}>
                        {adj.quantity > 0 ? `+${adj.quantity}` : adj.quantity}
                      </TableCell>
                      <TableCell>{adj.stock_before}</TableCell>
                      <TableCell>{adj.stock_after}</TableCell>
                      <TableCell className="text-xs max-w-48 truncate">{adj.reason ?? "-"}</TableCell>
                      <TableCell className="text-xs">{adj.user?.name ?? "System"}</TableCell>
                    </TableRow>
                  ))
                )}
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      </div>
    </AuthenticatedLayout>
  );
}
