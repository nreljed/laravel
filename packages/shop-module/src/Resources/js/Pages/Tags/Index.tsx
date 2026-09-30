import { useState } from "react";
import { Head, useForm, usePage, router } from "@inertiajs/react";
import type { PageProps } from "@inertiajs/core";
import { toast } from "@nreljed/platform/Components/ui/toast";
import { Tag, Plus, Pencil, Trash2 } from "lucide-react";
import AuthenticatedLayout from "@nreljed/platform/Layouts/AuthenticatedLayout";
import { Button } from "@nreljed/platform/Components/ui/button";
import {
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from "@nreljed/platform/Components/ui/dialog";
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription,
  AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from "@nreljed/platform/Components/ui/alert-dialog";
import { Input } from "@nreljed/platform/Components/ui/input";
import { Textarea } from "@nreljed/platform/Components/ui/textarea";
import { Label } from "@nreljed/platform/Components/ui/label";
import { Switch } from "@nreljed/platform/Components/ui/switch";
import { Badge } from "@nreljed/platform/Components/ui/badge";
import {
  Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator,
} from "@nreljed/platform/Components/ui/breadcrumb";

interface TagItem {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  sort_order: number;
  is_active: boolean;
  products_count: number;
}

interface TagsPageProps {
  tags: TagItem[];
}

export default function TagsIndex() {
  const { props } = usePage<PageProps & TagsPageProps>();
  const tags = props.tags || [];

  const [createOpen, setCreateOpen] = useState(false);
  const [editOpen, setEditOpen] = useState(false);
  const [deleteOpen, setDeleteOpen] = useState(false);
  const [selectedTag, setSelectedTag] = useState<TagItem | null>(null);

  const form = useForm({
    name: "",
    description: "",
    sort_order: "0",
    is_active: true as boolean,
  });

  const openCreate = () => {
    form.reset();
    setCreateOpen(true);
  };

  const openEdit = (tag: TagItem) => {
    setSelectedTag(tag);
    form.setData({
      name: tag.name,
      description: tag.description || "",
      sort_order: String(tag.sort_order),
      is_active: tag.is_active,
    });
    setEditOpen(true);
  };

  const openDelete = (tag: TagItem) => {
    setSelectedTag(tag);
    setDeleteOpen(true);
  };

  const handleCreate = () => {
    form.post(route("dashboard.shop.tags.store"), {
      preserveScroll: true,
      onSuccess: () => {
        toast.add({ title: "Tag created.", type: "success" });
        setCreateOpen(false);
        form.reset();
      },
      onError: (errors) => {
        const first = Object.values(errors)[0];
        if (first) toast.add({ title: first, type: "error" });
      },
    });
  };

  const handleUpdate = () => {
    if (!selectedTag) return;
    form.put(route("dashboard.shop.tags.update", selectedTag.id), {
      preserveScroll: true,
      onSuccess: () => {
        toast.add({ title: "Tag updated.", type: "success" });
        setEditOpen(false);
        setSelectedTag(null);
      },
      onError: (errors) => {
        const first = Object.values(errors)[0];
        if (first) toast.add({ title: first, type: "error" });
      },
    });
  };

  const handleDelete = () => {
    if (!selectedTag) return;
    router.delete(route("dashboard.shop.tags.destroy", selectedTag.id), {
      preserveScroll: true,
      onSuccess: () => {
        toast.add({ title: "Tag deleted.", type: "success" });
        setDeleteOpen(false);
        setSelectedTag(null);
      },
    });
  };

  return (
    <AuthenticatedLayout header="Tags">
      <Head title="Tags" />

      <div className="container mx-auto py-6 space-y-6">
        <Breadcrumb>
          <BreadcrumbList>
            <BreadcrumbItem>
              <BreadcrumbLink href={route("dashboard.index")}>Dashboard</BreadcrumbLink>
            </BreadcrumbItem>
            <BreadcrumbSeparator />
            <BreadcrumbItem>
              <BreadcrumbLink href={route("dashboard.shop.dashboard")}>Shop</BreadcrumbLink>
            </BreadcrumbItem>
            <BreadcrumbSeparator />
            <BreadcrumbItem>
              <BreadcrumbPage>Tags</BreadcrumbPage>
            </BreadcrumbItem>
          </BreadcrumbList>
        </Breadcrumb>

        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold">Tags</h1>
            <p className="text-muted-foreground">Manage product tags</p>
          </div>
          <Button onClick={openCreate}>
            <Plus className="h-4 w-4 mr-2" /> Add Tag
          </Button>
        </div>

        {tags.length === 0 ? (
          <div className="flex flex-col items-center justify-center py-16 text-muted-foreground">
            <Tag className="h-16 w-16 mb-4 opacity-20" />
            <p className="text-lg font-medium">No tags yet</p>
            <p className="text-sm">Create your first tag to organize products.</p>
            <Button variant="outline" className="mt-4" onClick={openCreate}>
              <Plus className="h-4 w-4 mr-2" /> Add Tag
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
                {tags.map((tag) => (
                  <tr key={tag.id} className="border-b last:border-0 hover:bg-muted/30">
                    <td className="px-4 py-3 text-sm font-medium">{tag.name}</td>
                    <td className="px-4 py-3 text-sm text-muted-foreground">{tag.slug}</td>
                    <td className="px-4 py-3 text-sm text-center">
                      <Badge variant="secondary">{tag.products_count}</Badge>
                    </td>
                    <td className="px-4 py-3 text-center">
                      <Switch
                        checked={tag.is_active}
                        onCheckedChange={() => {
                          /* `data` is not a useForm submit option - it was being
                           * sent as a literal request key, so this toggle posted
                           * the shared create/edit form's data instead of the tag
                           * row. The endpoint requires name/is_active, so send
                           * them explicitly. */
                          router.put(route("dashboard.shop.tags.update", tag.id), {
                            name: tag.name,
                            is_active: !tag.is_active,
                          }, {
                            preserveScroll: true,
                            onSuccess: () => toast.add({ title: "Tag updated.", type: "success" }),
                          });
                        }}
                      />
                    </td>
                    <td className="px-4 py-3 text-right">
                      <div className="flex items-center justify-end gap-1">
                        <Button variant="ghost" size="sm" onClick={() => openEdit(tag)}>
                          <Pencil className="h-4 w-4" />
                        </Button>
                        <Button variant="ghost" size="sm" onClick={() => openDelete(tag)}>
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
            <DialogTitle>Add Tag</DialogTitle>
            <DialogDescription>Create a new product tag.</DialogDescription>
          </DialogHeader>
          <div className="space-y-4">
            <div className="grid gap-2">
              <Label htmlFor="name">Name</Label>
              <Input id="name" value={form.data.name} onChange={(e) => form.setData("name", e.target.value)} placeholder="Enter tag name" />
              {form.errors.name && <p className="text-sm text-destructive">{form.errors.name}</p>}
            </div>
            <div className="grid gap-2">
              <Label htmlFor="description">Description</Label>
              <Textarea id="description" value={form.data.description} onChange={(e) => form.setData("description", e.target.value)} placeholder="Optional description" />
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
            <DialogTitle>Edit Tag</DialogTitle>
            <DialogDescription>Update tag details.</DialogDescription>
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
            <AlertDialogTitle>Delete Tag</AlertDialogTitle>
            <AlertDialogDescription>
              Are you sure you want to delete "{selectedTag?.name}"? This action cannot be undone.
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
