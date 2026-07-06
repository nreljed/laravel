import { Head, useForm, router } from "@inertiajs/react";
import { toast } from "sonner";
import { useState } from "react";
import { Plus, Pencil, Trash2, FolderTree } from "lucide-react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Textarea } from "@/Components/ui/textarea";
import { Label } from "@/Components/ui/label";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/Components/ui/table";
import { Badge } from "@/Components/ui/badge";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/Components/ui/select";
import { Empty, EmptyHeader, EmptyTitle, EmptyDescription, EmptyMedia } from "@/Components/ui/empty";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/Components/ui/dialog";

interface Category {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  parent_id: number | null;
  sort_order: number;
  is_active: boolean;
  children: Category[];
}

interface Props {
  categories: Category[];
}

export default function CategoriesIndex({ categories }: Props) {
  const [editing, setEditing] = useState<Category | null>(null);
  const [showCreate, setShowCreate] = useState(false);
  const [showDelete, setShowDelete] = useState<Category | null>(null);

  const form = useForm({
    name: "",
    description: "",
    parent_id: "",
    sort_order: "0",
  });

  const handleCreate = (e: React.FormEvent) => {
    e.preventDefault();
    form.post(route("dashboard.shop.categories.store"), {
      preserveScroll: true,
      onSuccess: () => { setShowCreate(false); form.reset(); toast.success("Category created."); },
      onError: () => toast.error("Failed to create category."),
    });
  };

  const handleUpdate = (e: React.FormEvent) => {
    e.preventDefault();
    form.put(route("dashboard.shop.categories.update", editing!.id), {
      preserveScroll: true,
      onSuccess: () => { setEditing(null); form.reset(); toast.success("Category updated."); },
      onError: () => toast.error("Failed to update category."),
    });
  };

  const handleDelete = () => {
    router.delete(route("dashboard.shop.categories.destroy", showDelete!.id), {
      preserveScroll: true,
      onSuccess: () => { setShowDelete(null); toast.success("Category deleted."); },
      onError: () => toast.error("Failed to delete category."),
    });
  };

  const startEdit = (cat: Category) => {
    setEditing(cat);
    form.setData({
      name: cat.name,
      description: cat.description || "",
      parent_id: cat.parent_id?.toString() || "",
      sort_order: cat.sort_order.toString(),
    });
  };

  const rootCategories = categories.filter((c) => c.parent_id === null);

  return (
    <AuthenticatedLayout header="Categories">
      <Head title="Categories" />

      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <h2 className="text-lg font-semibold">Categories</h2>
          <Dialog open={showCreate} onOpenChange={setShowCreate}>
            <DialogTrigger asChild>
              <Button><Plus className="mr-1 h-4 w-4" /> Add Category</Button>
            </DialogTrigger>
            <DialogContent>
              <form onSubmit={handleCreate}>
                <DialogHeader>
                  <DialogTitle>Create Category</DialogTitle>
                  <DialogDescription>Add a new product category.</DialogDescription>
                </DialogHeader>
                <div className="space-y-4 py-4">
                  <div className="grid gap-2">
                    <Label htmlFor="name">Name</Label>
                    <Input id="name" value={form.data.name} onChange={(e) => form.setData("name", e.target.value)} required />
                  </div>
                  <div className="grid gap-2">
                    <Label htmlFor="description">Description</Label>
                    <Textarea id="description" rows={2} value={form.data.description} onChange={(e) => form.setData("description", e.target.value)} />
                  </div>
                  <div className="grid gap-2">
                    <Label htmlFor="parent_id">Parent Category</Label>
                    <Select value={form.data.parent_id} onValueChange={(v) => form.setData("parent_id", v)}>
                      <SelectTrigger id="parent_id">
                        <SelectValue placeholder="None (top level)" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="">None (top level)</SelectItem>
                        {rootCategories.map((cat) => (
                          <SelectItem key={cat.id} value={String(cat.id)}>{cat.name}</SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>
                </div>
                <DialogFooter>
                  <Button type="button" variant="outline" onClick={() => setShowCreate(false)}>Cancel</Button>
                  <Button type="submit" disabled={form.processing}>Create</Button>
                </DialogFooter>
              </form>
            </DialogContent>
          </Dialog>
        </div>

        {categories.length === 0 ? (
          <Empty>
            <EmptyHeader>
              <EmptyMedia variant="icon"><FolderTree /></EmptyMedia>
              <EmptyTitle>No categories yet</EmptyTitle>
              <EmptyDescription>Create categories to organize your products.</EmptyDescription>
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
                    <TableHead>Parent</TableHead>
                    <TableHead>Products</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead className="w-24"></TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {categories.map((cat) => (
                    <TableRow key={cat.id}>
                      <TableCell className="font-medium">{cat.name}</TableCell>
                      <TableCell className="text-xs text-muted-foreground">{cat.slug}</TableCell>
                      <TableCell className="text-xs text-muted-foreground">
                        {cat.parent_id ? categories.find((p) => p.id === cat.parent_id)?.name || "—" : "—"}
                      </TableCell>
                      <TableCell>
                        <Badge variant="outline" className="text-xs">{cat.children?.length || 0}</Badge>
                      </TableCell>
                      <TableCell>
                        <Badge variant={cat.is_active ? "default" : "secondary"} className="text-xs">
                          {cat.is_active ? "Active" : "Inactive"}
                        </Badge>
                      </TableCell>
                      <TableCell>
                        <div className="flex items-center gap-1">
                          <Button variant="ghost" size="icon-sm" onClick={() => startEdit(cat)}>
                            <Pencil className="h-3.5 w-3.5" />
                          </Button>
                          <Button variant="ghost" size="icon-sm" onClick={() => setShowDelete(cat)}>
                            <Trash2 className="h-3.5 w-3.5 text-destructive" />
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

        <Dialog open={!!editing} onOpenChange={(o) => { if (!o) setEditing(null); }}>
          <DialogContent>
            <form onSubmit={handleUpdate}>
              <DialogHeader>
                <DialogTitle>Edit Category</DialogTitle>
              </DialogHeader>
              <div className="space-y-4 py-4">
                <div className="grid gap-2">
                  <Label htmlFor="edit-name">Name</Label>
                  <Input id="edit-name" value={form.data.name} onChange={(e) => form.setData("name", e.target.value)} required />
                </div>
                <div className="grid gap-2">
                  <Label htmlFor="edit-description">Description</Label>
                  <Textarea id="edit-description" rows={2} value={form.data.description} onChange={(e) => form.setData("description", e.target.value)} />
                </div>
                <div className="grid gap-2">
                  <Label htmlFor="edit-parent_id">Parent Category</Label>
                  <Select value={form.data.parent_id} onValueChange={(v) => form.setData("parent_id", v)}>
                    <SelectTrigger id="edit-parent_id">
                      <SelectValue placeholder="None (top level)" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="">None (top level)</SelectItem>
                      {rootCategories.filter((c) => c.id !== editing?.id).map((cat) => (
                        <SelectItem key={cat.id} value={String(cat.id)}>{cat.name}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
              </div>
              <DialogFooter>
                <Button type="button" variant="outline" onClick={() => setEditing(null)}>Cancel</Button>
                <Button type="submit" disabled={form.processing}>Update</Button>
              </DialogFooter>
            </form>
          </DialogContent>
        </Dialog>

        <Dialog open={!!showDelete} onOpenChange={(o) => { if (!o) setShowDelete(null); }}>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>Delete Category</DialogTitle>
              <DialogDescription>Are you sure you want to delete "{showDelete?.name}"?</DialogDescription>
            </DialogHeader>
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setShowDelete(null)}>Cancel</Button>
              <Button type="button" variant="destructive" onClick={handleDelete}>Delete</Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      </div>
    </AuthenticatedLayout>
  );
}
