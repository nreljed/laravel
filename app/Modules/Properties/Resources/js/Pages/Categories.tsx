import { Head, useForm, router } from "@inertiajs/react";
import { toast } from "sonner";
import { useState, useCallback } from "react";
import { Plus, Pencil, Trash2, ChevronRight } from "lucide-react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Textarea } from "@/Components/ui/textarea";
import { Switch } from "@/Components/ui/switch";
import { Label } from "@/Components/ui/label";
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
} from "@/Components/ui/card";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/Components/ui/select";
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
import { cn } from "@/lib/utils";

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

interface CategoriesPageProps {
  categories: Category[];
}

export default function Categories({ categories }: CategoriesPageProps) {
  const [editingCategory, setEditingCategory] = useState<Category | null>(null);
  const [deleteId, setDeleteId] = useState<number | null>(null);
  const [dialogOpen, setDialogOpen] = useState(false);

  const form = useForm({
    name: "",
    description: "",
    parent_id: "" as string | number,
    sort_order: 0,
    is_active: true,
  });

  const openCreate = useCallback(
    (parentId?: number) => {
      form.reset();
      form.setData("parent_id", parentId ?? "");
      setEditingCategory(null);
      setDialogOpen(true);
    },
    [],
  );

  const openEdit = useCallback((category: Category) => {
    form.setData({
      name: category.name,
      description: category.description || "",
      parent_id: category.parent_id ?? "",
      sort_order: category.sort_order,
      is_active: category.is_active,
    });
    setEditingCategory(category);
    setDialogOpen(true);
  }, []);

  const handleSubmit = useCallback(
    (e: React.FormEvent) => {
      e.preventDefault();
      const isEdit = !!editingCategory;
      const data = {
        ...form.data,
        parent_id: form.data.parent_id === "" ? null : Number(form.data.parent_id),
      };

      const options = {
        preserveScroll: true,
        onSuccess: () => {
          toast.success(isEdit ? "Category updated." : "Category created.");
          setDialogOpen(false);
          setEditingCategory(null);
        },
        onError: () => toast.error("Failed to save category."),
      };

      if (isEdit) {
        router.put(route("dashboard.properties.terms.categories.update", editingCategory!.id), data, options);
      } else {
        router.post(route("dashboard.properties.terms.categories.store"), data, options);
      }
    },
    [form, editingCategory],
  );

  const handleDelete = useCallback((id: number) => {
    router.delete(route("dashboard.properties.terms.categories.destroy", id), {
      preserveScroll: true,
      onSuccess: () => toast.success("Category deleted."),
      onError: () => toast.error("Failed to delete category."),
    });
    setDeleteId(null);
  }, []);

  const renderCategoryRow = (cat: Category, depth = 0) => (
    <div
      key={cat.id}
      className={cn(
        "flex items-center justify-between px-4 py-3",
        depth > 0 && "bg-muted/30",
      )}
    >
      <div className="flex items-center gap-2">
        {depth > 0 && (
          <div className="flex items-center">
            <div className="w-4 border-l border-b h-3 -mb-1.5 border-muted-foreground/30" />
            <ChevronRight className="h-3 w-3 text-muted-foreground/50 -ml-1" />
          </div>
        )}
        <span className="text-sm font-medium">{cat.name}</span>
        {!cat.is_active && <span className="text-xs text-muted-foreground">(Inactive)</span>}
      </div>
      <div className="flex items-center gap-2">
        {depth === 0 && (
          <Button variant="ghost" size="icon" onClick={() => openCreate(cat.id)} title="Add subcategory">
            <Plus className="h-4 w-4" />
          </Button>
        )}
        <Button variant="ghost" size="icon" onClick={() => openEdit(cat)}>
          <Pencil className="h-4 w-4" />
        </Button>
        <AlertDialog>
          <AlertDialogTrigger asChild>
            <Button variant="ghost" size="icon" onClick={() => setDeleteId(cat.id)}>
              <Trash2 className="h-4 w-4 text-destructive" />
            </Button>
          </AlertDialogTrigger>
          <AlertDialogContent>
            <AlertDialogHeader>
              <AlertDialogTitle>Delete Category</AlertDialogTitle>
              <AlertDialogDescription>
                Are you sure? This will remove "{cat.name}" and all its subcategories.
              </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
              <AlertDialogCancel>Cancel</AlertDialogCancel>
              <AlertDialogAction onClick={() => handleDelete(cat.id)}>Delete</AlertDialogAction>
            </AlertDialogFooter>
          </AlertDialogContent>
        </AlertDialog>
      </div>
    </div>
  );

  const renderCategoryTree = (cats: Category[], depth = 0) =>
    cats.map((cat) => (
      <div key={cat.id}>
        {renderCategoryRow(cat, depth)}
        {cat.children && cat.children.length > 0 && renderCategoryTree(cat.children, depth + 1)}
      </div>
    ));

  const allParentCats = categories;

  return (
    <AuthenticatedLayout header="Property Categories">
      <Head title="Property Categories" />

      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h2 className="text-lg font-semibold">Categories</h2>
            <p className="text-sm text-muted-foreground">Organize properties into categories and subcategories.</p>
          </div>
          <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
            <DialogTrigger asChild>
              <Button onClick={() => openCreate()}>
                <Plus className="mr-1 h-4 w-4" /> Add Category
              </Button>
            </DialogTrigger>
            <DialogContent>
              <form onSubmit={handleSubmit}>
                <DialogHeader>
                  <DialogTitle>{editingCategory ? "Edit Category" : "Add Category"}</DialogTitle>
                  <DialogDescription>
                    {editingCategory ? "Update category details." : "Create a new category."}
                  </DialogDescription>
                </DialogHeader>
                <div className="grid gap-4 py-4">
                  <div className="grid gap-2">
                    <Label htmlFor="cat-name">Name</Label>
                    <Input id="cat-name" value={form.data.name} onChange={(e) => form.setData("name", e.target.value)} required />
                    {form.errors.name && <p className="text-sm text-destructive">{form.errors.name}</p>}
                  </div>
                  <div className="grid gap-2">
                    <Label htmlFor="cat-desc">Description</Label>
                    <Textarea id="cat-desc" value={form.data.description} onChange={(e) => form.setData("description", e.target.value)} />
                  </div>
                  <div className="grid gap-2">
                    <Label htmlFor="cat-parent">Parent Category</Label>
                    <Select
                      value={String(form.data.parent_id)}
                      onValueChange={(v) => form.setData("parent_id", v === "" ? "" : Number(v))}
                    >
                      <SelectTrigger id="cat-parent"><SelectValue placeholder="None (top-level)" /></SelectTrigger>
                      <SelectContent>
                        <SelectItem value="">None (top-level)</SelectItem>
                        {allParentCats.filter((c) => c.id !== editingCategory?.id).map((cat) => (
                          <SelectItem key={cat.id} value={String(cat.id)}>{cat.name}</SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>
                  <div className="grid gap-2">
                    <Label htmlFor="cat-sort">Sort Order</Label>
                    <Input id="cat-sort" type="number" min="0" value={form.data.sort_order} onChange={(e) => form.setData("sort_order", parseInt(e.target.value) || 0)} />
                  </div>
                  <label className="flex items-center gap-2 cursor-pointer">
                    <Switch checked={form.data.is_active} onCheckedChange={(v) => form.setData("is_active", v)} />
                    <span className="text-sm">Active</span>
                  </label>
                </div>
                <DialogFooter>
                  <Button type="submit" disabled={form.processing}>
                    {form.processing ? "Saving..." : editingCategory ? "Update" : "Create"}
                  </Button>
                </DialogFooter>
              </form>
            </DialogContent>
          </Dialog>
        </div>

        <Card>
          {categories.length === 0 ? (
            <CardContent className="p-6 text-center text-sm text-muted-foreground">
              No categories defined yet.
            </CardContent>
          ) : (
            <div className="divide-y">{renderCategoryTree(categories)}</div>
          )}
        </Card>
      </div>
    </AuthenticatedLayout>
  );
}
