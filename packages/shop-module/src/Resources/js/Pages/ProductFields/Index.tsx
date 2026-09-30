import { Head, useForm, router } from "@inertiajs/react";
import type { FormDataKeys, FormDataValues } from "@inertiajs/core";
import { toast } from "@nreljed/platform/Components/ui/toast";
import { useState, useCallback, useEffect } from "react";
import { Plus, Pencil, Trash2, X, SlidersHorizontal, GripVertical, Star } from "lucide-react";
import { DndContext, closestCenter, DragEndEvent, PointerSensor, useSensor, useSensors } from "@dnd-kit/core";
import { arrayMove, SortableContext, useSortable, verticalListSortingStrategy } from "@dnd-kit/sortable";
import { CSS } from "@dnd-kit/utilities";
import AuthenticatedLayout from "@nreljed/platform/Layouts/AuthenticatedLayout";
import { Button } from "@nreljed/platform/Components/ui/button";
import { Input } from "@nreljed/platform/Components/ui/input";
import { Textarea } from "@nreljed/platform/Components/ui/textarea";
import { Label } from "@nreljed/platform/Components/ui/label";
import { Card, CardContent, CardHeader, CardTitle } from "@nreljed/platform/Components/ui/card";
import { Badge } from "@nreljed/platform/Components/ui/badge";
import { Switch } from "@nreljed/platform/Components/ui/switch";
import { Empty, EmptyHeader, EmptyTitle, EmptyDescription, EmptyMedia } from "@nreljed/platform/Components/ui/empty";
import {
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger,
} from "@nreljed/platform/Components/ui/dialog";
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from "@nreljed/platform/Components/ui/select";

interface FieldOption { value: string; label: string | null }

interface ProductField {
  id: number; name: string; slug: string; type: string; description: string | null;
  options: FieldOption[] | null; default_value: string | boolean | number | null;
  is_required: boolean; is_active: boolean; show_on_storefront: boolean; sort_order: number;
  values_count?: number;
}

interface Props { productFields: ProductField[] }

interface OptionRow { value: string; label: string }

interface FieldFormData {
  name: string; slug: string; type: string; description: string;
  options: OptionRow[]; default_value: string;
  is_required: boolean; is_active: boolean; show_on_storefront: boolean;
}

const TYPE_LABELS: Record<string, string> = {
  text: "Text",
  textarea: "Textarea",
  number: "Number",
  boolean: "Yes / No",
  date: "Date",
  select: "Select",
};

const emptyField = (): FieldFormData => ({
  name: "", slug: "", type: "text", description: "", options: [], default_value: "",
  is_required: false, is_active: true, show_on_storefront: false,
});

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";

async function apiPost(url: string, data: unknown) {
  const res = await fetch(url, {
    method: "POST",
    headers: {
      "X-Requested-With": "XMLHttpRequest",
      "X-CSRF-TOKEN": csrfToken(),
      Accept: "application/json",
      "Content-Type": "application/json",
    },
    body: JSON.stringify(data),
  });
  if (!res.ok) throw new Error((await res.json().catch(() => ({}))).message || `HTTP ${res.status}`);
  return res.json();
}

function fieldOptions(field: ProductField): string[] {
  return (field.options || []).map((option) => option.value).filter(Boolean);
}

function defaultValueString(field: ProductField): string {
  const value = field.default_value;
  if (value === null || value === undefined) return "";
  if (typeof value === "boolean") return value ? "1" : "0";
  return String(value);
}

function FieldEditor({ data, errors, onChange }: {
  data: FieldFormData;
  errors: Record<string, string | undefined>;
  onChange: <K extends FormDataKeys<FieldFormData>>(key: K, value: FormDataValues<FieldFormData, K>) => void;
}) {
  const inputId = (key: string) => `field-${key}`;

  function setOption(index: number, key: keyof OptionRow, value: string) {
    onChange("options", data.options.map((option, i) => (i === index ? { ...option, [key]: value } : option)));
  }

  return (
    <div className="space-y-4 py-4">
      <div className="grid gap-2">
        <Label htmlFor={inputId("name")}>Name</Label>
        <Input
          id={inputId("name")}
          placeholder="e.g. Warranty"
          value={data.name}
          onChange={(e) => onChange("name", e.target.value)}
          required
        />
        {errors.name && <p className="text-sm text-destructive">{errors.name}</p>}
      </div>

      <div className="grid gap-2">
        <Label htmlFor={inputId("type")}>Type</Label>
        <Select value={data.type} onValueChange={(v) => onChange("type", v)}>
          <SelectTrigger id={inputId("type")}><SelectValue /></SelectTrigger>
          <SelectContent>
            {Object.entries(TYPE_LABELS).map(([key, label]) => (
              <SelectItem key={key} value={key}>{label}</SelectItem>
            ))}
          </SelectContent>
        </Select>
        {errors.type && <p className="text-sm text-destructive">{errors.type}</p>}
      </div>

      <div className="grid gap-2">
        <Label htmlFor={inputId("description")}>Description</Label>
        <Textarea
          id={inputId("description")}
          placeholder="Help text shown under the field in the product form"
          value={data.description}
          onChange={(e) => onChange("description", e.target.value)}
        />
        {errors.description && <p className="text-sm text-destructive">{errors.description}</p>}
      </div>

      {data.type === "select" && (
        <div className="grid gap-2">
          <Label>Options</Label>
          <div className="space-y-2">
            {data.options.map((option, index) => (
              <div key={index} className="flex items-center gap-2">
                <Input
                  placeholder="value"
                  value={option.value}
                  onChange={(e) => setOption(index, "value", e.target.value)}
                  className="flex-1"
                />
                <Input
                  placeholder="label"
                  value={option.label}
                  onChange={(e) => setOption(index, "label", e.target.value)}
                  className="flex-1"
                />
                <Button
                  type="button"
                  variant="ghost"
                  size="icon-sm"
                  onClick={() => onChange("options", data.options.filter((_, i) => i !== index))}
                >
                  <X className="h-3.5 w-3.5 text-destructive" />
                </Button>
              </div>
            ))}
          </div>
          <Button
            type="button"
            variant="outline"
            size="sm"
            className="w-fit"
            onClick={() => onChange("options", [...data.options, { value: "", label: "" }])}
          >
            <Plus className="mr-1 h-3 w-3" /> Add option
          </Button>
          {errors.options && <p className="text-sm text-destructive">{errors.options}</p>}
        </div>
      )}

      <div className="grid gap-2">
        <Label htmlFor={inputId("default")}>Default value</Label>
        {data.type === "boolean" ? (
          <Select
            value={data.default_value === "" ? "none" : data.default_value}
            onValueChange={(v) => onChange("default_value", v === "none" ? "" : v)}
          >
            <SelectTrigger id={inputId("default")}><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem value="none">No default</SelectItem>
              <SelectItem value="1">Yes</SelectItem>
              <SelectItem value="0">No</SelectItem>
            </SelectContent>
          </Select>
        ) : data.type === "select" ? (
          <Select
            value={data.default_value || "none"}
            onValueChange={(v) => onChange("default_value", v === "none" ? "" : v)}
          >
            <SelectTrigger id={inputId("default")}><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem value="none">No default</SelectItem>
              {data.options.filter((option) => option.value.trim() !== "").map((option) => (
                <SelectItem key={option.value} value={option.value}>
                  {option.label || option.value}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        ) : data.type === "textarea" ? (
          <Textarea
            id={inputId("default")}
            value={data.default_value}
            onChange={(e) => onChange("default_value", e.target.value)}
          />
        ) : (
          <Input
            id={inputId("default")}
            type={data.type === "number" ? "number" : data.type === "date" ? "date" : "text"}
            value={data.default_value}
            onChange={(e) => onChange("default_value", e.target.value)}
          />
        )}
        {errors.default_value && <p className="text-sm text-destructive">{errors.default_value}</p>}
      </div>

      <div className="space-y-3 rounded-lg border p-3">
        <div className="flex items-center justify-between gap-4">
          <div className="grid gap-0.5">
            <Label htmlFor={inputId("required")}>Required</Label>
            <p className="text-xs text-muted-foreground">Products must fill this field before saving.</p>
          </div>
          <Switch
            id={inputId("required")}
            checked={data.is_required}
            onCheckedChange={(checked) => onChange("is_required", checked)}
          />
        </div>
        <div className="flex items-center justify-between gap-4">
          <div className="grid gap-0.5">
            <Label htmlFor={inputId("active")}>Active</Label>
            <p className="text-xs text-muted-foreground">Inactive fields are hidden from the product form.</p>
          </div>
          <Switch
            id={inputId("active")}
            checked={data.is_active}
            onCheckedChange={(checked) => onChange("is_active", checked)}
          />
        </div>
        <div className="flex items-center justify-between gap-4">
          <div className="grid gap-0.5">
            <Label htmlFor={inputId("storefront")}>Show on storefront</Label>
            <p className="text-xs text-muted-foreground">Display filled values in the product specifications table.</p>
          </div>
          <Switch
            id={inputId("storefront")}
            checked={data.show_on_storefront}
            onCheckedChange={(checked) => onChange("show_on_storefront", checked)}
          />
        </div>
      </div>
    </div>
  );
}

export default function ProductFieldsIndex({ productFields: initialFields }: Props) {
  const [localData, setLocalData] = useState<ProductField[]>(() => structuredClone(initialFields));
  const [showCreate, setShowCreate] = useState(false);
  const [editingField, setEditingField] = useState<ProductField | null>(null);
  const [showDeleteField, setShowDeleteField] = useState<ProductField | null>(null);
  const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 8 } }));

  const form = useForm<FieldFormData>(emptyField());

  useEffect(() => {
    setLocalData(structuredClone(initialFields));
  }, [initialFields]);

  /* Inertia types setData against the form-data shape of the field, not the raw
   * field type - `FormDataValues` is what it actually accepts, so the helper has
   * to mirror that signature or nested keys (options, arrays) fail to typecheck. */
  function setFieldData<K extends FormDataKeys<FieldFormData>>(key: K, value: FormDataValues<FieldFormData, K>) {
    form.clearErrors(key);
    form.setData(key, value);
  }

  const handleCreate = (e: React.FormEvent) => {
    e.preventDefault();
    form.post(route("dashboard.shop.product-fields.store"), {
      preserveScroll: true,
      onSuccess: () => { setShowCreate(false); form.reset(); toast.add({ title: "Product field created.", type: "success" }); },
      onError: () => toast.add({ title: "Failed to create product field.", type: "error" }),
    });
  };

  const handleUpdate = (e: React.FormEvent) => {
    e.preventDefault();
    form.put(route("dashboard.shop.product-fields.update", editingField!.id), {
      preserveScroll: true,
      onSuccess: () => { setEditingField(null); form.reset(); toast.add({ title: "Product field updated.", type: "success" }); },
      onError: () => toast.add({ title: "Failed to update product field.", type: "error" }),
    });
  };

  const handleDelete = () => {
    router.delete(route("dashboard.shop.product-fields.destroy", showDeleteField!.id), {
      preserveScroll: true,
      onSuccess: () => { setShowDeleteField(null); toast.add({ title: "Product field deleted.", type: "success" }); },
      onError: () => toast.add({ title: "Failed to delete product field.", type: "error" }),
    });
  };

  const startEdit = (field: ProductField) => {
    setEditingField(field);
    form.setData({
      name: field.name,
      slug: field.slug,
      type: field.type,
      description: field.description || "",
      options: (field.options || []).map((option) => ({ value: option.value, label: option.label || "" })),
      default_value: defaultValueString(field),
      is_required: field.is_required,
      is_active: field.is_active,
      show_on_storefront: field.show_on_storefront,
    });
  };

  const handleDragEnd = useCallback(async (event: DragEndEvent) => {
    const { active, over } = event;
    if (!over || active.id === over.id) return;
    const oldIndex = localData.findIndex((f) => f.id === active.id);
    const newIndex = localData.findIndex((f) => f.id === over.id);
    if (oldIndex === -1 || newIndex === -1) return;
    const reordered = arrayMove(localData, oldIndex, newIndex);
    setLocalData(reordered);
    try {
      await apiPost(route("dashboard.shop.product-fields.reorder"), {
        product_fields: reordered.map((f, i) => ({ id: f.id, sort_order: i })),
      });
    } catch {
      setLocalData(localData);
      toast.add({ title: "Failed to reorder product fields.", type: "error" });
    }
  }, [localData]);

  function SortableFieldCard({ field }: { field: ProductField }) {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: field.id });
    const style = {
      transform: CSS.Transform.toString(transform),
      transition,
      zIndex: isDragging ? 10 : 'auto' as const,
      opacity: isDragging ? 0.9 : 1,
    };

    return (
      <div ref={setNodeRef} style={style}>
        <Card>
          <CardHeader className="pb-3">
            <div className="flex items-center justify-between gap-2">
              <div className="flex items-center gap-2 flex-wrap">
                <button type="button" className="cursor-grab active:cursor-grabbing text-muted-foreground hover:text-foreground -ml-1" {...attributes} {...listeners}>
                  <GripVertical className="h-4 w-4" />
                </button>
                <CardTitle className="text-base">{field.name}</CardTitle>
                <Badge variant="outline" className="text-xs font-normal">
                  {TYPE_LABELS[field.type] || field.type}
                </Badge>
                {field.is_required && <Badge variant="outline" className="text-xs gap-1 font-normal"><Star className="h-3 w-3" /> Required</Badge>}
                {!field.is_active && <Badge variant="outline" className="text-xs font-normal text-muted-foreground">Inactive</Badge>}
                {field.show_on_storefront && <Badge variant="outline" className="text-xs font-normal text-muted-foreground">Storefront</Badge>}
                <Badge variant="outline" className="text-xs text-muted-foreground">{field.values_count ?? 0} products</Badge>
              </div>
              <div className="flex items-center gap-1 shrink-0">
                <Button variant="ghost" size="icon-sm" onClick={() => startEdit(field)}>
                  <Pencil className="h-3.5 w-3.5" />
                </Button>
                <Button variant="ghost" size="icon-sm" onClick={() => setShowDeleteField(field)}>
                  <Trash2 className="h-3.5 w-3.5 text-destructive" />
                </Button>
              </div>
            </div>
          </CardHeader>
          {(field.description || fieldOptions(field).length > 0) && (
            <CardContent className="pb-3 space-y-1">
              {field.description && <p className="text-sm text-muted-foreground">{field.description}</p>}
              {fieldOptions(field).length > 0 && (
                <div className="flex flex-wrap gap-1.5 pt-1">
                  {fieldOptions(field).map((option) => (
                    <span key={option} className="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground">{option}</span>
                  ))}
                </div>
              )}
            </CardContent>
          )}
        </Card>
      </div>
    );
  }

  return (
    <AuthenticatedLayout header="Product Fields">
      <Head title="Product Fields" />
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div className="grid gap-0.5">
            <h2 className="text-lg font-semibold">Product Fields</h2>
            <p className="text-sm text-muted-foreground">
              Extra spec fields like warranty, weight capacity, or materials — filled per product and shown on the storefront.
            </p>
          </div>
          <Dialog open={showCreate} onOpenChange={setShowCreate}>
            <DialogTrigger render={<Button />}><Plus className="mr-1 h-4 w-4" /> Add Field</DialogTrigger>
            <DialogContent>
              <form onSubmit={handleCreate}>
                <DialogHeader>
                  <DialogTitle>Create Product Field</DialogTitle>
                  <DialogDescription>Add a custom field that shop managers can fill in for each product.</DialogDescription>
                </DialogHeader>
                <FieldEditor data={form.data} errors={form.errors} onChange={setFieldData} />
                <DialogFooter>
                  <Button type="button" variant="outline" onClick={() => setShowCreate(false)}>Cancel</Button>
                  <Button type="submit" disabled={form.processing}>Create</Button>
                </DialogFooter>
              </form>
            </DialogContent>
          </Dialog>
        </div>

        {localData.length === 0 ? (
          <Empty>
            <EmptyHeader>
              <EmptyMedia variant="icon"><SlidersHorizontal /></EmptyMedia>
              <EmptyTitle>No product fields yet</EmptyTitle>
              <EmptyDescription>Create fields like Warranty, Battery life, or Dimensions to capture product specifications.</EmptyDescription>
            </EmptyHeader>
          </Empty>
        ) : (
          <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
            <SortableContext items={localData.map((f) => f.id)} strategy={verticalListSortingStrategy}>
              <div className="space-y-4">
                {localData.map((field) => (
                  <SortableFieldCard key={field.id} field={field} />
                ))}
              </div>
            </SortableContext>
          </DndContext>
        )}

        <Dialog open={!!editingField} onOpenChange={(o) => { if (!o) setEditingField(null); }}>
          <DialogContent>
            <form onSubmit={handleUpdate}>
              <DialogHeader>
                <DialogTitle>Edit Product Field</DialogTitle>
                <DialogDescription>Update how this field behaves across the product form and storefront.</DialogDescription>
              </DialogHeader>
              <FieldEditor data={form.data} errors={form.errors} onChange={setFieldData} />
              <DialogFooter>
                <Button type="button" variant="outline" onClick={() => setEditingField(null)}>Cancel</Button>
                <Button type="submit" disabled={form.processing}>Update</Button>
              </DialogFooter>
            </form>
          </DialogContent>
        </Dialog>

        <Dialog open={!!showDeleteField} onOpenChange={(o) => { if (!o) setShowDeleteField(null); }}>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>Delete Product Field</DialogTitle>
              <DialogDescription>
                Are you sure? This removes "{showDeleteField?.name}" and every value saved for it.
              </DialogDescription>
            </DialogHeader>
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setShowDeleteField(null)}>Cancel</Button>
              <Button type="button" variant="destructive" onClick={handleDelete}>Delete</Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      </div>
    </AuthenticatedLayout>
  );
}
