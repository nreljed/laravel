import { Head, useForm, router } from "@inertiajs/react";
import { toast } from "sonner";
import { useState, useCallback } from "react";
import { Plus, Pencil, Trash2 } from "lucide-react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
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

const FEATURE_GROUPS = [
  "Interior", "Exterior", "Utilities", "Security", "Other",
];

interface Feature {
  id: number;
  name: string;
  slug: string;
  icon: string | null;
  group: string | null;
  sort_order: number;
  is_active: boolean;
}

interface FeaturesPageProps {
  features: Feature[];
}

export default function Features({ features }: FeaturesPageProps) {
  const [editingFeature, setEditingFeature] = useState<Feature | null>(null);
  const [deleteId, setDeleteId] = useState<number | null>(null);
  const [dialogOpen, setDialogOpen] = useState(false);

  const form = useForm({
    name: "",
    icon: "",
    group: "Interior",
    sort_order: 0,
    is_active: true,
  });

  const openCreate = useCallback(() => {
    form.reset();
    form.setData("group", "Interior");
    setEditingFeature(null);
    setDialogOpen(true);
  }, []);

  const openEdit = useCallback((feature: Feature) => {
    form.setData({
      name: feature.name,
      icon: feature.icon || "",
      group: feature.group || "Other",
      sort_order: feature.sort_order,
      is_active: feature.is_active,
    });
    setEditingFeature(feature);
    setDialogOpen(true);
  }, []);

  const handleSubmit = useCallback(
    (e: React.FormEvent) => {
      e.preventDefault();
      const isEdit = !!editingFeature;

      const options = {
        preserveScroll: true,
        onSuccess: () => {
          toast.success(isEdit ? "Feature updated." : "Feature created.");
          setDialogOpen(false);
          setEditingFeature(null);
        },
        onError: () => toast.error("Failed to save feature."),
      };

      if (isEdit) {
        form.put(route("dashboard.properties.terms.features.update", editingFeature!.id), options);
      } else {
        form.post(route("dashboard.properties.terms.features.store"), options);
      }
    },
    [form, editingFeature],
  );

  const handleDelete = useCallback((id: number) => {
    router.delete(route("dashboard.properties.terms.features.destroy", id), {
      preserveScroll: true,
      onSuccess: () => toast.success("Feature deleted."),
      onError: () => toast.error("Failed to delete feature."),
    });
    setDeleteId(null);
  }, []);

  const groupedFeatures = FEATURE_GROUPS.map((group) => ({
    group,
    features: features.filter((f) => (f.group || "Other") === group),
  })).filter((g) => g.features.length > 0);

  return (
    <AuthenticatedLayout header="Property Features">
      <Head title="Property Features" />

      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h2 className="text-lg font-semibold">Features / Amenities</h2>
            <p className="text-sm text-muted-foreground">Manage property features and amenities.</p>
          </div>
          <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
            <DialogTrigger asChild>
              <Button onClick={openCreate}>
                <Plus className="mr-1 h-4 w-4" /> Add Feature
              </Button>
            </DialogTrigger>
            <DialogContent>
              <form onSubmit={handleSubmit}>
                <DialogHeader>
                  <DialogTitle>{editingFeature ? "Edit Feature" : "Add Feature"}</DialogTitle>
                  <DialogDescription>
                    {editingFeature ? "Update feature details." : "Create a new feature or amenity."}
                  </DialogDescription>
                </DialogHeader>
                <div className="grid gap-4 py-4">
                  <div className="grid gap-2">
                    <Label htmlFor="feat-name">Name</Label>
                    <Input id="feat-name" value={form.data.name} onChange={(e) => form.setData("name", e.target.value)} required />
                    {form.errors.name && <p className="text-sm text-destructive">{form.errors.name}</p>}
                  </div>
                  <div className="grid gap-2">
                    <Label htmlFor="feat-icon">Icon</Label>
                    <Input id="feat-icon" value={form.data.icon} onChange={(e) => form.setData("icon", e.target.value)} placeholder="e.g. Wifi, Pool, Snowflake" />
                    <p className="text-xs text-muted-foreground">Lucide icon name (optional)</p>
                  </div>
                  <div className="grid gap-2">
                    <Label htmlFor="feat-group">Group</Label>
                    <Select value={form.data.group} onValueChange={(v) => form.setData("group", v)}>
                      <SelectTrigger id="feat-group"><SelectValue /></SelectTrigger>
                      <SelectContent>
                        <SelectItem value="Interior">Interior</SelectItem>
                        <SelectItem value="Exterior">Exterior</SelectItem>
                        <SelectItem value="Utilities">Utilities</SelectItem>
                        <SelectItem value="Security">Security</SelectItem>
                        <SelectItem value="Other">Other</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                  <div className="grid gap-2">
                    <Label htmlFor="feat-sort">Sort Order</Label>
                    <Input id="feat-sort" type="number" min="0" value={form.data.sort_order} onChange={(e) => form.setData("sort_order", parseInt(e.target.value) || 0)} />
                  </div>
                  <label className="flex items-center gap-2 cursor-pointer">
                    <Switch checked={form.data.is_active} onCheckedChange={(v) => form.setData("is_active", v)} />
                    <span className="text-sm">Active</span>
                  </label>
                </div>
                <DialogFooter>
                  <Button type="submit" disabled={form.processing}>
                    {form.processing ? "Saving..." : editingFeature ? "Update" : "Create"}
                  </Button>
                </DialogFooter>
              </form>
            </DialogContent>
          </Dialog>
        </div>

        {groupedFeatures.length === 0 ? (
          <Card>
            <CardContent className="p-6 text-center text-sm text-muted-foreground">
              No features defined yet.
            </CardContent>
          </Card>
        ) : (
          <div className="grid gap-4 sm:grid-cols-2">
            {groupedFeatures.map(({ group, features: groupFeats }) => (
              <Card key={group}>
                <CardHeader className="pb-2">
                  <CardTitle className="text-sm font-medium">{group}</CardTitle>
                </CardHeader>
                <CardContent className="p-0">
                  <div className="divide-y">
                    {groupFeats.map((feature) => (
                      <div key={feature.id} className="flex items-center justify-between px-4 py-2.5">
                        <div className="flex items-center gap-2">
                          <span className="text-sm">{feature.name}</span>
                          {feature.icon && (
                            <span className="text-[10px] text-muted-foreground">({feature.icon})</span>
                          )}
                          {!feature.is_active && (
                            <span className="text-xs text-muted-foreground">Inactive</span>
                          )}
                        </div>
                        <div className="flex items-center gap-1">
                          <Button variant="ghost" size="icon" onClick={() => openEdit(feature)}>
                            <Pencil className="h-3.5 w-3.5" />
                          </Button>
                          <AlertDialog>
                            <AlertDialogTrigger asChild>
                              <Button variant="ghost" size="icon" onClick={() => setDeleteId(feature.id)}>
                                <Trash2 className="h-3.5 w-3.5 text-destructive" />
                              </Button>
                            </AlertDialogTrigger>
                            <AlertDialogContent>
                              <AlertDialogHeader>
                                <AlertDialogTitle>Delete Feature</AlertDialogTitle>
                                <AlertDialogDescription>
                                  Are you sure? This will remove "{feature.name}".
                                </AlertDialogDescription>
                              </AlertDialogHeader>
                              <AlertDialogFooter>
                                <AlertDialogCancel>Cancel</AlertDialogCancel>
                                <AlertDialogAction onClick={() => handleDelete(feature.id)}>Delete</AlertDialogAction>
                              </AlertDialogFooter>
                            </AlertDialogContent>
                          </AlertDialog>
                        </div>
                      </div>
                    ))}
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
