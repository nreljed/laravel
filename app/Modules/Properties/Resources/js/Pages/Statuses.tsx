import { Head, useForm, router } from "@inertiajs/react";
import { toast } from "sonner";
import { useState, useCallback } from "react";
import { Plus, Pencil, Trash2 } from "lucide-react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Button } from "@/Components/ui/button";
import { Empty, EmptyHeader, EmptyTitle, EmptyDescription } from "@/Components/ui/empty";
import { Input } from "@/Components/ui/input";
import { Textarea } from "@/Components/ui/textarea";
import { Switch } from "@/Components/ui/switch";
import { Label } from "@/Components/ui/label";
import { Badge } from "@/Components/ui/badge";
import {
  Card,
  CardContent,
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

interface Status {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  sort_order: number;
  is_active: boolean;
}

interface StatusesPageProps {
  statuses: Status[];
}

export default function Statuses({ statuses }: StatusesPageProps) {
  const [editingStatus, setEditingStatus] = useState<Status | null>(null);
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
    setEditingStatus(null);
    setDialogOpen(true);
  }, []);

  const openEdit = useCallback((status: Status) => {
    form.setData({
      name: status.name,
      description: status.description || "",
      sort_order: status.sort_order,
      is_active: status.is_active,
    });
    setEditingStatus(status);
    setDialogOpen(true);
  }, []);

  const handleSubmit = useCallback(
    (e: React.FormEvent) => {
      e.preventDefault();
      const isEdit = !!editingStatus;

      const options = {
        preserveScroll: true,
        onSuccess: () => {
          toast.success(isEdit ? "Status updated." : "Status created.");
          setDialogOpen(false);
          setEditingStatus(null);
        },
        onError: () => toast.error("Failed to save status."),
      };

      if (isEdit) {
        form.put(route("dashboard.properties.terms.statuses.update", editingStatus!.id), options);
      } else {
        form.post(route("dashboard.properties.terms.statuses.store"), options);
      }
    },
    [form, editingStatus],
  );

  const handleDelete = useCallback((id: number) => {
    router.delete(route("dashboard.properties.terms.statuses.destroy", id), {
      preserveScroll: true,
      onSuccess: () => toast.success("Status deleted."),
      onError: () => toast.error("Failed to delete status."),
    });
    setDeleteId(null);
  }, []);

  return (
    <AuthenticatedLayout header="Property Statuses">
      <Head title="Property Statuses" />

      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h2 className="text-lg font-semibold">Property Statuses</h2>
            <p className="text-sm text-muted-foreground">Manage property listing statuses (For Sale, For Rent, etc.)</p>
          </div>
          <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
            <DialogTrigger asChild>
              <Button onClick={openCreate}>
                <Plus className="mr-1 h-4 w-4" /> Add Status
              </Button>
            </DialogTrigger>
            <DialogContent>
              <form onSubmit={handleSubmit}>
                <DialogHeader>
                  <DialogTitle>{editingStatus ? "Edit Status" : "Add Status"}</DialogTitle>
                  <DialogDescription>
                    {editingStatus ? "Update property status details." : "Create a new property status."}
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
                    {form.processing ? "Saving..." : editingStatus ? "Update" : "Create"}
                  </Button>
                </DialogFooter>
              </form>
            </DialogContent>
          </Dialog>
        </div>

        {statuses.length === 0 ? (
          <Empty>
            <EmptyHeader>
              <EmptyTitle>No statuses yet</EmptyTitle>
              <EmptyDescription>No property statuses have been defined yet. Create your first status to get started.</EmptyDescription>
            </EmptyHeader>
          </Empty>
        ) : (
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {statuses.map((status) => (
              <Card key={status.id} className="relative">
                <CardContent className="p-4">
                  <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0 space-y-1">
                      <div className="flex items-center gap-2">
                        <span className="font-medium">{status.name}</span>
                        {!status.is_active && <Badge variant="outline" className="text-xs shrink-0">Inactive</Badge>}
                      </div>
                      {status.description && (
                        <p className="text-xs text-muted-foreground line-clamp-2">{status.description}</p>
                      )}
                      <p className="text-[10px] text-muted-foreground/60">Order: {status.sort_order}</p>
                    </div>
                    <div className="flex items-center gap-1 shrink-0">
                      <Button variant="ghost" size="icon" className="size-8" onClick={() => openEdit(status)}>
                        <Pencil className="h-3.5 w-3.5" />
                      </Button>
                      <AlertDialog>
                        <AlertDialogTrigger asChild>
                          <Button variant="ghost" size="icon" className="size-8" onClick={() => setDeleteId(status.id)}>
                            <Trash2 className="h-3.5 w-3.5 text-destructive" />
                          </Button>
                        </AlertDialogTrigger>
                        <AlertDialogContent>
                          <AlertDialogHeader>
                            <AlertDialogTitle>Delete Status</AlertDialogTitle>
                            <AlertDialogDescription>
                              Are you sure? This will remove the status "{status.name}".
                            </AlertDialogDescription>
                          </AlertDialogHeader>
                          <AlertDialogFooter>
                            <AlertDialogCancel>Cancel</AlertDialogCancel>
                            <AlertDialogAction onClick={() => handleDelete(status.id)}>Delete</AlertDialogAction>
                          </AlertDialogFooter>
                        </AlertDialogContent>
                      </AlertDialog>
                    </div>
                  </div>
                </CardContent>
              </Card>
            ))}
          </div>
        )}
      </div>
    </AuthenticatedLayout>
  );
}
