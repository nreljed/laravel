import { Head, useForm, router } from "@inertiajs/react";
import { toast } from "sonner";
import { useState, useCallback, useMemo } from "react";
import { Plus, Pencil, Trash2, ChevronDown } from "lucide-react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Button } from "@/Components/ui/button";
import { Empty, EmptyHeader, EmptyTitle, EmptyDescription } from "@/Components/ui/empty";
import { Input } from "@/Components/ui/input";
import { Switch } from "@/Components/ui/switch";
import { Label } from "@/Components/ui/label";
import { Badge } from "@/Components/ui/badge";
import {
  Accordion,
  AccordionItem,
  AccordionTrigger,
  AccordionContent,
} from "@/Components/ui/accordion";
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
} from "@/Components/ui/card";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from "@/Components/ui/alert-dialog";
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogTrigger,
} from "@/Components/ui/dialog";

interface City {
  id: number;
  name: string;
  slug: string;
  image: string | null;
  governorate: string;
  governorate_slug: string;
  sort_order: number;
  is_active: boolean;
}

interface CitiesPageProps {
  cities: City[];
}

export default function Cities({ cities }: CitiesPageProps) {
  const [editingCity, setEditingCity] = useState<City | null>(null);
  const [deleteId, setDeleteId] = useState<number | null>(null);
  const [dialogOpen, setDialogOpen] = useState(false);

  const form = useForm({
    name: "",
    image: "",
    governorate: "",
    sort_order: 0,
    is_active: true,
  });

  const openCreate = useCallback(() => {
    form.reset();
    setEditingCity(null);
    setDialogOpen(true);
  }, []);

  const openEdit = useCallback((city: City) => {
    form.setData({
      name: city.name,
      image: city.image || "",
      governorate: city.governorate,
      sort_order: city.sort_order,
      is_active: city.is_active,
    });
    setEditingCity(city);
    setDialogOpen(true);
  }, []);

  const handleSubmit = useCallback(
    (e: React.FormEvent) => {
      e.preventDefault();
      const isEdit = !!editingCity;

      const options = {
        preserveScroll: true,
        onSuccess: () => {
          toast.success(isEdit ? "City updated." : "City created.");
          setDialogOpen(false);
          setEditingCity(null);
        },
        onError: () => toast.error("Failed to save city."),
      };

      if (isEdit) {
        router.put(route("dashboard.properties.terms.cities.update", editingCity!.id), form.data, options);
      } else {
        router.post(route("dashboard.properties.terms.cities.store"), form.data, options);
      }
    },
    [form, editingCity],
  );

  const handleDelete = useCallback((id: number) => {
    router.delete(route("dashboard.properties.terms.cities.destroy", id), {
      preserveScroll: true,
      onSuccess: () => toast.success("City deleted."),
      onError: () => toast.error("Failed to delete city."),
    });
    setDeleteId(null);
  }, []);

  const groupedCities = useMemo(() => {
    const groups = new Map<string, City[]>();
    for (const city of cities) {
      const gov = city.governorate;
      if (!groups.has(gov)) groups.set(gov, []);
      groups.get(gov)!.push(city);
    }
    return Array.from(groups.entries()).sort(([a], [b]) => a.localeCompare(b));
  }, [cities]);

  const allGovernorates = useMemo(() => {
    return Array.from(new Set(cities.map((c) => c.governorate))).sort();
  }, [cities]);

  return (
    <AuthenticatedLayout header="Property Cities">
      <Head title="Property Cities" />

      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h2 className="text-lg font-semibold">Cities</h2>
            <p className="text-sm text-muted-foreground">Manage cities grouped by governorate.</p>
          </div>
          <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
            <DialogTrigger asChild>
              <Button onClick={openCreate}>
                <Plus className="mr-1 h-4 w-4" /> Add City
              </Button>
            </DialogTrigger>
            <DialogContent>
              <form onSubmit={handleSubmit}>
                <DialogHeader>
                  <DialogTitle>{editingCity ? "Edit City" : "Add City"}</DialogTitle>
                  <DialogDescription>
                    {editingCity ? "Update city details." : "Create a new city."}
                  </DialogDescription>
                </DialogHeader>
                <div className="grid gap-4 py-4">
                  <div className="grid gap-2">
                    <Label htmlFor="city-name">Name</Label>
                    <Input id="city-name" value={form.data.name} onChange={(e) => form.setData("name", e.target.value)} required />
                    {form.errors.name && <p className="text-sm text-destructive">{form.errors.name}</p>}
                  </div>
                  <div className="grid gap-2">
                    <Label htmlFor="city-image">Image URL</Label>
                    <Input id="city-image" value={form.data.image} onChange={(e) => form.setData("image", e.target.value)} placeholder="https://example.com/image.jpg" />
                    {form.data.image && <img src={form.data.image} alt="" className="h-12 w-12 rounded object-cover border" />}
                  </div>
                  <div className="grid gap-2">
                    <Label htmlFor="city-governorate">Governorate</Label>
                    <Input id="city-governorate" value={form.data.governorate} onChange={(e) => form.setData("governorate", e.target.value)} list="governorates-list" required />
                    <datalist id="governorates-list">
                      {allGovernorates.map((g) => (
                        <option key={g} value={g} />
                      ))}
                    </datalist>
                    {form.errors.governorate && <p className="text-sm text-destructive">{form.errors.governorate}</p>}
                  </div>
                  <div className="grid gap-2">
                    <Label htmlFor="city-sort">Sort Order</Label>
                    <Input id="city-sort" type="number" min="0" value={form.data.sort_order} onChange={(e) => form.setData("sort_order", parseInt(e.target.value) || 0)} />
                  </div>
                  <label className="flex items-center gap-2 cursor-pointer">
                    <Switch checked={form.data.is_active} onCheckedChange={(v) => form.setData("is_active", v)} />
                    <span className="text-sm">Active</span>
                  </label>
                </div>
                <DialogFooter>
                  <Button type="submit" disabled={form.processing}>
                    {form.processing ? "Saving..." : editingCity ? "Update" : "Create"}
                  </Button>
                </DialogFooter>
              </form>
            </DialogContent>
          </Dialog>
        </div>

        {cities.length === 0 ? (
          <Empty>
            <EmptyHeader>
              <EmptyTitle>No cities yet</EmptyTitle>
              <EmptyDescription>No cities have been defined yet. Create your first city to get started.</EmptyDescription>
            </EmptyHeader>
          </Empty>
        ) : (
          <Accordion type="multiple" defaultValue={groupedCities.map(([g]) => g)} className="space-y-3">
            {groupedCities.map(([governorate, govCities]) => (
              <AccordionItem key={governorate} value={governorate} className="border rounded-lg bg-card">
                <AccordionTrigger className="px-4 py-3 hover:no-underline hover:bg-muted/50 rounded-t-lg [&[data-state=closed]]:rounded-lg">
                  <div className="flex items-center gap-3">
                    <span className="font-medium">{governorate}</span>
                    <Badge variant="secondary" className="text-xs font-normal">{govCities.length}</Badge>
                  </div>
                </AccordionTrigger>
                <AccordionContent>
                  <div className="divide-y border-t px-4">
                    {govCities.map((city) => (
                      <div key={city.id} className="flex items-center justify-between py-2.5">
                        <div className="flex items-center gap-2">
                          {city.image && <img src={city.image} alt="" className="h-5 w-5 rounded object-cover border shrink-0" />}
                          <span className="text-sm">{city.name}</span>
                          {!city.is_active && <Badge variant="outline" className="text-xs">Inactive</Badge>}
                        </div>
                        <div className="flex items-center gap-1">
                          <Button variant="ghost" size="icon" onClick={() => openEdit(city)}>
                            <Pencil className="h-4 w-4" />
                          </Button>
                          <AlertDialog>
                            <AlertDialogTrigger asChild>
                              <Button variant="ghost" size="icon" onClick={() => setDeleteId(city.id)}>
                                <Trash2 className="h-4 w-4 text-destructive" />
                              </Button>
                            </AlertDialogTrigger>
                            <AlertDialogContent>
                              <AlertDialogHeader>
                                <AlertDialogTitle>Delete City</AlertDialogTitle>
                                <AlertDialogDescription>
                                  Are you sure? This will remove "{city.name}".
                                </AlertDialogDescription>
                              </AlertDialogHeader>
                              <AlertDialogFooter>
                                <AlertDialogCancel>Cancel</AlertDialogCancel>
                                <AlertDialogAction onClick={() => handleDelete(city.id)}>Delete</AlertDialogAction>
                              </AlertDialogFooter>
                            </AlertDialogContent>
                          </AlertDialog>
                        </div>
                      </div>
                    ))}
                  </div>
                </AccordionContent>
              </AccordionItem>
            ))}
          </Accordion>
        )}
      </div>
    </AuthenticatedLayout>
  );
}
