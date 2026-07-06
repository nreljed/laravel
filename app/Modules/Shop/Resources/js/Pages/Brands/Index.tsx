import { useState } from "react";
import { Head, useForm, usePage, router } from "@inertiajs/react";
import { toast } from "sonner";
import { Building2, Plus, Pencil, Trash2 } from "lucide-react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Button } from "@/Components/ui/button";
import {
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from "@/Components/ui/dialog";
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription,
  AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from "@/Components/ui/alert-dialog";
import { Input } from "@/Components/ui/input";
import { Textarea } from "@/Components/ui/textarea";
import { Label } from "@/Components/ui/label";
import { Switch } from "@/Components/ui/switch";
import { Badge } from "@/Components/ui/badge";
import {
  Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator,
} from "@/Components/ui/breadcrumb";

interface BrandItem {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  image: string | null;
  sort_order: number;
  is_active: boolean;
  products_count: number;
}

interface PageProps {
  brands: BrandItem[];
}

export default function BrandsIndex() {
  const { props } = usePage<PageProps>();
  const brands = props.brands || [];

  const [createOpen, setCreateOpen] = useState(false);
  const [editOpen, setEditOpen] = useState(false);
  const [deleteOpen, setDeleteOpen] = useState(false);
  const [selectedBrand, setSelectedBrand] = useState<BrandItem | null>(null);

  const form = useForm({
    name: "",
    description: "",
    image: "",
    sort_order: "0",
    is_active: true as boolean,
  });

  const openCreate = () => {
    form.reset();
    setCreateOpen(true);
  };

  const openEdit = (brand: BrandItem) => {
    setSelectedBrand(brand);
    form.setData({
      name: brand.name,
      description: brand.description || "",
      image: brand.image || "",
      sort_order: String(brand.sort_order),
      is_active: brand.is_active,
    });
    setEditOpen(true);
  };

  const openDelete = (brand: BrandItem) => {
    setSelectedBrand(brand);
    setDeleteOpen(true);
  };

  const handleCreate = () => {
    form.post(route("dashboard.shop.brands.store"), {
      preserveScroll: true,
      onSuccess: () => {
        toast.success("Brand created.");
        setCreateOpen(false);
        form.reset();
      },
      onError: (errors) => {
        const first = Object.values(errors)[0];
        if (first) toast.error(first);
      },
    });
  };

  const handleUpdate = () => {
    if (!selectedBrand) return;
    form.put(route("dashboard.shop.brands.update", selectedBrand.id), {
      preserveScroll: true,
      onSuccess: () => {
        toast.success("Brand updated.");
        setEditOpen(false);
        setSelectedBrand(null);
      },
      onError: (errors) => {
        const first = Object.values(errors)[0];
        if (first) toast.error(first);
      },
    });
  };

  const handleDelete = () => {
    if (!selectedBrand) return;
    router.delete(route("dashboard.shop.brands.destroy", selectedBrand.id), {
      preserveScroll: true,
      onSuccess: () => {
        toast.success("Brand deleted.");
        setDeleteOpen(false);
        setSelectedBrand(null);
      },
    });
  };

  return (
    <AuthenticatedLayout header="Brands">
      <Head title="Brands" />

      <div className="container mx-auto py-6 space-y-6">
        <Breadcrumb>
          <BreadcrumbList>
            <BreadcrumbItem>
              <BreadcrumbLink href={route("dashboard")}>Dashboard</BreadcrumbLink>
            </BreadcrumbItem>
            <BreadcrumbSeparator />
            <BreadcrumbItem>
              <BreadcrumbLink href={route("dashboard.shop.dashboard")}>Shop</BreadcrumbLink>
            </BreadcrumbItem>
            <BreadcrumbSeparator />
            <BreadcrumbItem>
              <BreadcrumbPage>Brands</BreadcrumbPage>
            </BreadcrumbItem>
          </BreadcrumbList>
        </Breadcrumb>

        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold">Brands</h1>
            <p className="text-muted-foreground">Manage product brands</p>
          </div>
          <Button onClick={openCreate}>
            <Plus className="h-4 w-4 mr-2" /> Add Brand
          </Button>
        </div>

        {brands.length === 0 ? (
          <div className="flex flex-col items-center justify-center py-16 text-muted-foreground">
            <Building2 className="h-16 w-16 mb-4 opacity-20" />
            <p className="text-lg font-medium">No brands yet</p>
            <p className="text-sm">Create your first brand to organize products.</p>
            <Button variant="outline" className="mt-4" onClick={openCreate}>
              <Plus className="h-4 w-4 mr-2" /> Add Brand
            </Button>
          </div>
        ) : (
          <div className="rounded-lg border">
            <table className="w-full">
              <thead>
                <tr className="border-b bg-muted/50">
                  <th className="text-left px-4 py-3 text-sm font-medium">Name</th>
                  <th className="text-left px-4 py-3 text-sm font-medium">Slug</th>
                  <th className="text-center px-4 py-3 text-sm font-medium">Products</th>
                  <th className="text-center px-4 py-3 text-sm font-medium">Status</th>
                  <th className="text-right px-4 py-3 text-sm font-medium">Actions</th>
                </tr>
              </thead>
              <tbody>
                {brands.map((brand) => (
                  <tr key={brand.id} className="border-b last:border-0 hover:bg-muted/30">
                    <td className="px-4 py-3 text-sm font-medium">
                      <div className="flex items-center gap-3">
                        {brand.image && (
                          <img src={brand.image} alt={brand.name} className="h-8 w-8 rounded object-contain border" />
                        )}
                        <span>{brand.name}</span>
                      </div>
                    </td>
                    <td className="px-4 py-3 text-sm text-muted-foreground">{brand.slug}</td>
                    <td className="px-4 py-3 text-sm text-center">
                      <Badge variant="secondary">{brand.products_count}</Badge>
                    </td>
                    <td className="px-4 py-3 text-center">
                      <Switch
                        checked={brand.is_active}
                        onCheckedChange={() => {
                          form.put(route("dashboard.shop.brands.update", brand.id), {
                            data: { ...brand, is_active: !brand.is_active },
                            preserveScroll: true,
                            onSuccess: () => toast.success("Brand updated."),
                          });
                        }}
                      />
                    </td>
                    <td className="px-4 py-3 text-right">
                      <div className="flex items-center justify-end gap-1">
                        <Button variant="ghost" size="sm" onClick={() => openEdit(brand)}>
                          <Pencil className="h-4 w-4" />
                        </Button>
                        <Button variant="ghost" size="sm" onClick={() => openDelete(brand)}>
                          <Trash2 className="h-4 w-4 text-destructive" />
                        </Button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      <Dialog open={createOpen} onOpenChange={setCreateOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Add Brand</DialogTitle>
            <DialogDescription>Create a new product brand.</DialogDescription>
          </DialogHeader>
          <div className="space-y-4">
            <div className="grid gap-2">
              <Label htmlFor="name">Name</Label>
              <Input id="name" value={form.data.name} onChange={(e) => form.setData("name", e.target.value)} placeholder="Enter brand name" />
              {form.errors.name && <p className="text-sm text-destructive">{form.errors.name}</p>}
            </div>
            <div className="grid gap-2">
              <Label htmlFor="description">Description</Label>
              <Textarea id="description" value={form.data.description} onChange={(e) => form.setData("description", e.target.value)} placeholder="Optional description" />
            </div>
            <div className="grid gap-2">
              <Label htmlFor="image">Brand Logo URL</Label>
              <Input id="image" value={form.data.image} onChange={(e) => form.setData("image", e.target.value)} placeholder="https://example.com/logo.png" />
              {form.data.image && (
                <img src={form.data.image} alt="Preview" className="mt-1 h-12 w-12 rounded border object-contain" />
              )}
            </div>
            <div className="grid gap-2">
              <Label htmlFor="sort_order">Sort Order</Label>
              <Input id="sort_order" type="number" min="0" value={form.data.sort_order} onChange={(e) => form.setData("sort_order", e.target.value)} />
            </div>
            <div className="flex items-center justify-between">
              <Label>Active</Label>
              <Switch checked={form.data.is_active} onCheckedChange={(v) => form.setData("is_active", v)} />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setCreateOpen(false)}>Cancel</Button>
            <Button onClick={handleCreate} disabled={form.processing}>Create</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={editOpen} onOpenChange={setEditOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Edit Brand</DialogTitle>
            <DialogDescription>Update brand details.</DialogDescription>
          </DialogHeader>
          <div className="space-y-4">
            <div className="grid gap-2">
              <Label htmlFor="edit-name">Name</Label>
              <Input id="edit-name" value={form.data.name} onChange={(e) => form.setData("name", e.target.value)} />
              {form.errors.name && <p className="text-sm text-destructive">{form.errors.name}</p>}
            </div>
            <div className="grid gap-2">
              <Label htmlFor="edit-description">Description</Label>
              <Textarea id="edit-description" value={form.data.description} onChange={(e) => form.setData("description", e.target.value)} />
            </div>
            <div className="grid gap-2">
              <Label htmlFor="edit-image">Brand Logo URL</Label>
              <Input id="edit-image" value={form.data.image} onChange={(e) => form.setData("image", e.target.value)} />
              {form.data.image && (
                <img src={form.data.image} alt="Preview" className="mt-1 h-12 w-12 rounded border object-contain" />
              )}
            </div>
            <div className="grid gap-2">
              <Label htmlFor="edit-sort_order">Sort Order</Label>
              <Input id="edit-sort_order" type="number" min="0" value={form.data.sort_order} onChange={(e) => form.setData("sort_order", e.target.value)} />
            </div>
            <div className="flex items-center justify-between">
              <Label>Active</Label>
              <Switch checked={form.data.is_active} onCheckedChange={(v) => form.setData("is_active", v)} />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setEditOpen(false)}>Cancel</Button>
            <Button onClick={handleUpdate} disabled={form.processing}>Save</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <AlertDialog open={deleteOpen} onOpenChange={setDeleteOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete Brand</AlertDialogTitle>
            <AlertDialogDescription>
              Are you sure you want to delete "{selectedBrand?.name}"? This action cannot be undone.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction onClick={handleDelete} className="bg-destructive text-destructive-foreground">Delete</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </AuthenticatedLayout>
  );
}
