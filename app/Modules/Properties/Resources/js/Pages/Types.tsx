import { Head, useForm, router } from "@inertiajs/react";
import { toast } from "sonner";
import { useState, useCallback } from "react";
import { Plus, Pencil, Trash2, GripVertical } from "lucide-react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Textarea } from "@/Components/ui/textarea";
import { Switch } from "@/Components/ui/switch";
import { Label } from "@/Components/ui/label";
import {
  Card,
  CardContent,
  CardDescription,
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
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/Components/ui/dialog";

interface Type {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  sort_order: number;
  is_active: boolean;
}

interface TypesPageProps {
  types: Type[];
}

export default function Types({ types }: TypesPageProps) {
  const [editingType, setEditingType] = useState<Type | null>(null);
  const [deleteId, setDeleteId] = useState<number | null>(null);
  const [dialogOpen, setDialogOpen] = useState(false);

  const form = useForm({
    name: "",
    description: "",
    sort_order: 0,
    is_active: true,
  });

  const openCreate = useCallback(() => {
    form.reset();
    setEditingType(null);
    setDialogOpen(true);
  }, []);

  const openEdit = useCallback((type: Type) => {
    form.setData({
      name: type.name,
      description: type.description || "",
      sort_order: type.sort_order,
      is_active: type.is_active,
    });
    setEditingType(type);
    setDialogOpen(true);
  }, []);

  const handleSubmit = useCallback(
    (e: React.FormEvent) => {
      e.preventDefault();
      const isEdit = !!editingType;

      const options = {
        preserveScroll: true,
        onSuccess: () => {
          toast.success(isEdit ? "Type updated." : "Type created.");
          setDialogOpen(false);
          setEditingType(null);
        },
        onError: () => toast.error("Failed to save type."),
      };

      if (isEdit) {
        form.put(route("dashboard.properties.terms.types.update", editingType!.id), options);
      } else {
        form.post(route("dashboard.properties.terms.types.store"), options);
      }
    },
    [form, editingType],
  );

  const handleDelete = useCallback((id: number) => {
    router.delete(route("dashboard.properties.terms.types.destroy", id), {
      preserveScroll: true,
      onSuccess: () => toast.success("Type deleted."),
      onError: () => toast.error("Failed to delete type."),
    });
    setDeleteId(null);
  }, []);

  return (
    <AuthenticatedLayout header="Property Types">
      <Head title="Property Types" />

      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h2 className="text-lg font-semibold">Property Types</h2>
            <p className="text-sm text-muted-foreground">Manage property listing types (Rent, Buy, etc.)</p>
          </div>
          <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
            <DialogTrigger asChild>
              <Button onClick={openCreate}>
                <Plus className="mr-1 h-4 w-4" /> Add Type
              </Button>
            </DialogTrigger>
            <DialogContent>
              <form onSubmit={handleSubmit}>
                <DialogHeader>
                  <DialogTitle>{editingType ? "Edit Type" : "Add Type"}</DialogTitle>
                  <DialogDescription>
                    {editingType ? "Update property type details." : "Create a new property type."}
                  </DialogDescription>
                </DialogHeader>
                <div className="grid gap-4 py-4">
                  <div className="grid gap-2">
                    <Label htmlFor="name">Name</Label>
                    <Input id="name" value={form.data.name} onChange={(e) => form.setData("name", e.target.value)} required />
                    {form.errors.name && <p className="text-sm text-destructive">{form.errors.name}</p>}
                  </div>
                  <div className="grid gap-2">
                    <Label htmlFor="description">Description</Label>
                    <Textarea id="description" value={form.data.description} onChange={(e) => form.setData("description", e.target.value)} />
                  </div>
                  <div className="grid gap-2">
                    <Label htmlFor="sort_order">Sort Order</Label>
                    <Input id="sort_order" type="number" min="0" value={form.data.sort_order} onChange={(e) => form.setData("sort_order", parseInt(e.target.value) || 0)} />
                  </div>
                  <label className="flex items-center gap-2 cursor-pointer">
                    <Switch checked={form.data.is_active} onCheckedChange={(v) => form.setData("is_active", v)} />
                    <span className="text-sm">Active</span>
                  </label>
                </div>
                <DialogFooter>
                  <Button type="submit" disabled={form.processing}>
                    {form.processing ? "Saving..." : editingType ? "Update" : "Create"}
                  </Button>
                </DialogFooter>
              </form>
            </DialogContent>
          </Dialog>
        </div>

        <Card>
          <CardContent className="p-0">
            {types.length === 0 ? (
              <div className="p-6 text-center text-sm text-muted-foreground">No types defined yet.</div>
            ) : (
              <div className="divide-y">
                {types.map((type) => (
                  <div key={type.id} className="flex items-center justify-between px-4 py-3">
                    <div className="flex items-center gap-3">
                      <GripVertical className="h-4 w-4 text-muted-foreground/50" />
                      <div>
                        <span className="text-sm font-medium">{type.name}</span>
                        {type.description && (
                          <p className="text-xs text-muted-foreground">{type.description}</p>
                        )}
                      </div>
                    </div>
                    <div className="flex items-center gap-2">
                      {!type.is_active && <span className="text-xs text-muted-foreground">Inactive</span>}
                      <Button variant="ghost" size="icon" onClick={() => openEdit(type)}>
                        <Pencil className="h-4 w-4" />
                      </Button>
                      <AlertDialog>
                        <AlertDialogTrigger asChild>
                          <Button variant="ghost" size="icon" onClick={() => setDeleteId(type.id)}>
                            <Trash2 className="h-4 w-4 text-destructive" />
                          </Button>
                        </AlertDialogTrigger>
                        <AlertDialogContent>
                          <AlertDialogHeader>
                            <AlertDialogTitle>Delete Type</AlertDialogTitle>
                            <AlertDialogDescription>
                              Are you sure? This will remove the type "{type.name}".
                            </AlertDialogDescription>
                          </AlertDialogHeader>
                          <AlertDialogFooter>
                            <AlertDialogCancel>Cancel</AlertDialogCancel>
                            <AlertDialogAction onClick={() => handleDelete(type.id)}>Delete</AlertDialogAction>
                          </AlertDialogFooter>
                        </AlertDialogContent>
                      </AlertDialog>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </CardContent>
        </Card>
      </div>
    </AuthenticatedLayout>
  );
}
