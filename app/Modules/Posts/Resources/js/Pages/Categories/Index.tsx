import { useState } from "react";
import { Head, router } from "@inertiajs/react";
import { Plus, Pencil, Trash2, FolderTree } from "lucide-react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Textarea } from "@/Components/ui/textarea";
import { Label } from "@/Components/ui/label";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger,
} from "@/Components/ui/dialog";
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from "@/Components/ui/table";
import { Badge } from "@/Components/ui/badge";
import { Empty, EmptyHeader, EmptyTitle, EmptyDescription, EmptyMedia } from "@/Components/ui/empty";

interface Category {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  parent_id: number | null;
  order: number;
  posts_count: number;
}

interface Props {
  categories: Category[];
}

export default function PostCategoriesIndex({ categories }: Props) {
  const [editing, setEditing] = useState<Category | null>(null);
  const [open, setOpen] = useState(false);
  const [form, setForm] = useState({ name: "", description: "", parent_id: "", order: "0" });

  const resetForm = () => {
    setForm({ name: "", description: "", parent_id: "", order: "0" });
    setEditing(null);
  };

  const openEdit = (cat: Category) => {
    setEditing(cat);
    setForm({
      name: cat.name,
      description: cat.description || "",
      parent_id: cat.parent_id ? String(cat.parent_id) : "",
      order: String(cat.order),
    });
    setOpen(true);
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (editing) {
      router.put(route("dashboard.posts.categories.update", editing.id), form, {
        preserveScroll: true,
        onSuccess: () => { setOpen(false); resetForm(); },
      });
    } else {
      router.post(route("dashboard.posts.categories.store"), form, {
        preserveScroll: true,
        onSuccess: () => { setOpen(false); resetForm(); },
      });
    }
  };

  const handleDelete = (cat: Category) => {
    if (!confirm(`Delete "${cat.name}"?`)) return;
    router.delete(route("dashboard.posts.categories.destroy", cat.id), { preserveScroll: true });
  };

  return (
    <AuthenticatedLayout header="Post Categories">
      <Head title="Post Categories" />

      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <h2 className="text-lg font-semibold">Categories</h2>
          <Dialog open={open} onOpenChange={(v) => { setOpen(v); if (!v) resetForm(); }}>
            <DialogTrigger>
              <Button onClick={() => resetForm()}>
                <Plus className="mr-1 h-4 w-4" /> Add Category
              </Button>
            </DialogTrigger>
            <DialogContent>
              <DialogHeader>
                <DialogTitle>{editing ? "Edit Category" : "Add Category"}</DialogTitle>
              </DialogHeader>
              <form onSubmit={handleSubmit} className="space-y-4">
                <div className="grid gap-2">
                  <Label htmlFor="cat-name">Name</Label>
                  <Input id="cat-name" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required />
                </div>
                <div className="grid gap-2">
                  <Label htmlFor="cat-desc">Description</Label>
                  <Textarea id="cat-desc" value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} />
                </div>
                <div className="grid gap-2">
                  <Label htmlFor="cat-parent">Parent Category</Label>
                  <select
                    id="cat-parent"
                    value={form.parent_id}
                    onChange={(e) => setForm({ ...form, parent_id: e.target.value })}
                    className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm"
                  >
                    <option value="">None (top level)</option>
                    {categories.filter((c) => c.id !== editing?.id).map((cat) => (
                      <option key={cat.id} value={String(cat.id)}>{cat.name}</option>
                    ))}
                  </select>
                </div>
                <div className="grid gap-2">
                  <Label htmlFor="cat-order">Order</Label>
                  <Input id="cat-order" type="number" min="0" value={form.order} onChange={(e) => setForm({ ...form, order: e.target.value })} />
                </div>
                <Button type="submit" className="w-full">{editing ? "Update" : "Create"}</Button>
              </form>
            </DialogContent>
          </Dialog>
        </div>

        {categories.length === 0 ? (
          <Empty>
            <EmptyHeader>
              <EmptyMedia variant="icon"><FolderTree /></EmptyMedia>
              <EmptyTitle>No categories yet</EmptyTitle>
              <EmptyDescription>Create categories to organize your posts.</EmptyDescription>
            </EmptyHeader>
          </Empty>
        ) : (
          <Card>
            <CardContent className="p-0">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Name</TableHead>
                    <TableHead>Slug</TableHead>
                    <TableHead>Posts</TableHead>
                    <TableHead className="w-24">Actions</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {categories.map((cat) => (
                    <TableRow key={cat.id}>
                      <TableCell>
                        <span className="font-medium">{cat.name}</span>
                        {cat.parent_id && <Badge variant="outline" className="ml-2 text-xs">Sub</Badge>}
                        {cat.description && <p className="text-xs text-muted-foreground">{cat.description}</p>}
                      </TableCell>
                      <TableCell className="text-xs text-muted-foreground">{cat.slug}</TableCell>
                      <TableCell>{cat.posts_count}</TableCell>
                      <TableCell>
                        <div className="flex gap-1">
                          <Button variant="ghost" size="icon-sm" onClick={() => openEdit(cat)}>
                            <Pencil className="h-3.5 w-3.5" />
                          </Button>
                          <Button variant="ghost" size="icon-sm" onClick={() => handleDelete(cat)}>
                            <Trash2 className="h-3.5 w-3.5" />
                          </Button>
                        </div>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        )}
      </div>
    </AuthenticatedLayout>
  );
}
