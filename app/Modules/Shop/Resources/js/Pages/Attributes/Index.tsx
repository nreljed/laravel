import { Head, useForm, router } from "@inertiajs/react";
import { toast } from "sonner";
import { useState, useCallback } from "react";
import { Plus, Pencil, Trash2, X, Tag, ImageIcon, Palette, Type, Square, GripVertical } from "lucide-react";
import { DndContext, closestCenter, DragEndEvent, PointerSensor, useSensor, useSensors } from "@dnd-kit/core";
import { arrayMove, SortableContext, useSortable, rectSortingStrategy, verticalListSortingStrategy } from "@dnd-kit/sortable";
import { CSS } from "@dnd-kit/utilities";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Badge } from "@/Components/ui/badge";
import { Empty, EmptyHeader, EmptyTitle, EmptyDescription, EmptyMedia } from "@/Components/ui/empty";
import {
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger,
} from "@/Components/ui/dialog";
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from "@/Components/ui/select";

interface AttributeTerm {
  id: number; attribute_id: number; name: string; slug: string;
  sort_order: number; swatch_value: string | null;
}

interface Attribute {
  id: number; name: string; slug: string; sort_order: number;
  is_active: boolean; display_type: string; terms: AttributeTerm[];
}

interface Props { attributes: Attribute[] }

const DISPLAY_TYPE_LABELS: Record<string, { label: string; icon: React.ReactNode; description: string }> = {
  text: { label: "Text", icon: <Type className="h-4 w-4" />, description: "Badge chips with text labels" },
  color: { label: "Color", icon: <Palette className="h-4 w-4" />, description: "Circular color swatches (hex values)" },
  image: { label: "Image", icon: <ImageIcon className="h-4 w-4" />, description: "Circular image swatches (photo upload)" },
  button: { label: "Button", icon: <Square className="h-4 w-4" />, description: "Rectangle buttons with text" },
};

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

function SwatchPreview({ displayType, value, name }: { displayType: string; value: string | null; name: string }) {
  if (displayType === "color" && value) {
    return <span className="inline-block h-4 w-4 rounded-full border border-border shrink-0" style={{ backgroundColor: value }} title={`${name}: ${value}`} />;
  }
  if (displayType === "image" && value) {
    return <span className="inline-block h-4 w-4 rounded-full bg-cover bg-center border border-border shrink-0" style={{ backgroundImage: `url(${value})` }} title={name} />;
  }
  return null;
}

function SwatchValueInput({ displayType, value, onChange, id }: { displayType: string; value: string; onChange: (v: string) => void; id: string }) {
  if (displayType === "color") {
    return (
      <div className="flex gap-2 items-end">
        <div className="grid gap-2 flex-1">
          <Label htmlFor={id}>Swatch Color</Label>
          <Input id={id} placeholder="#ef4444" value={value} onChange={(e) => onChange(e.target.value)} />
        </div>
        <div className="pb-1">
          <input type="color" value={value || "#000000"} onChange={(e) => onChange(e.target.value)} className="h-9 w-9 rounded cursor-pointer border border-border" />
        </div>
      </div>
    );
  }
  if (displayType === "image") {
    return (
      <div className="grid gap-2">
        <Label htmlFor={id}>Swatch Image URL</Label>
        <Input id={id} placeholder="https://example.com/swatch.png" value={value} onChange={(e) => onChange(e.target.value)} />
        {value && (
          <div className="flex items-center gap-2 text-xs text-muted-foreground mt-1">
            <span className="inline-block h-6 w-6 rounded-full bg-cover bg-center border" style={{ backgroundImage: `url(${value})` }} />
            <span>Preview</span>
          </div>
        )}
      </div>
    );
  }
  return null;
}

function SortableTerm({
  term, displayType, onEdit, onDelete,
}: {
  term: AttributeTerm; displayType: string;
  onEdit: () => void; onDelete: () => void;
}) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: term.id });

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
    zIndex: isDragging ? 10 : "auto",
    opacity: isDragging ? 0.7 : 1,
  };

  return (
    <div
      ref={setNodeRef}
      style={style}
      className="group flex items-center gap-1 bg-muted px-2 py-1 rounded-full text-sm select-none touch-none"
    >
      <span {...attributes} {...listeners} className="cursor-grab text-muted-foreground/50 hover:text-muted-foreground touch-pan-y">
        <GripVertical className="h-3.5 w-3.5" />
      </span>
      <SwatchPreview displayType={displayType} value={term.swatch_value} name={term.name} />
      <span>{term.name}</span>
      <button onClick={onEdit} className="text-muted-foreground hover:text-foreground ml-0.5">
        <Pencil className="h-3 w-3" />
      </button>
      <button onClick={onDelete} className="text-muted-foreground hover:text-destructive">
        <X className="h-3 w-3" />
      </button>
    </div>
  );
}

export default function AttributesIndex({ attributes: initialAttributes }: Props) {
  const [localData, setLocalData] = useState<Attribute[]>(() => structuredClone(initialAttributes));
  const cardSensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 8 } }));
  const [showCreate, setShowCreate] = useState(false);
  const [editingAttr, setEditingAttr] = useState<Attribute | null>(null);
  const [showCreateTermAttr, setShowCreateTermAttr] = useState<Attribute | null>(null);
  const [editingTerm, setEditingTerm] = useState<{ attr: Attribute; term: AttributeTerm } | null>(null);
  const [showDeleteAttr, setShowDeleteAttr] = useState<Attribute | null>(null);
  const [showDeleteTerm, setShowDeleteTerm] = useState<{ attr: Attribute; term: AttributeTerm } | null>(null);

  const form = useForm({ name: "", sort_order: "0", display_type: "text" });
  const termForm = useForm({ name: "", swatch_value: "" });

  const handleCreate = (e: React.FormEvent) => {
    e.preventDefault();
    form.post(route("dashboard.shop.attributes.store"), {
      preserveScroll: true,
      onSuccess: () => { setShowCreate(false); form.reset(); toast.success("Attribute created."); },
      onError: () => toast.error("Failed to create attribute."),
    });
  };

  const handleUpdate = (e: React.FormEvent) => {
    e.preventDefault();
    form.put(route("dashboard.shop.attributes.update", editingAttr!.id), {
      preserveScroll: true,
      onSuccess: () => { setEditingAttr(null); form.reset(); toast.success("Attribute updated."); },
      onError: () => toast.error("Failed to update attribute."),
    });
  };

  const handleDelete = () => {
    router.delete(route("dashboard.shop.attributes.destroy", showDeleteAttr!.id), {
      preserveScroll: true,
      onSuccess: () => { setShowDeleteAttr(null); toast.success("Attribute deleted."); },
      onError: () => toast.error("Failed to delete attribute."),
    });
  };

  const handleCreateTerm = (e: React.FormEvent) => {
    e.preventDefault();
    termForm.post(route("dashboard.shop.attributes.terms.store", showCreateTermAttr!.id), {
      preserveScroll: true,
      onSuccess: () => { setShowCreateTermAttr(null); termForm.reset(); toast.success("Term created."); },
      onError: () => toast.error("Failed to create term."),
    });
  };

  const handleUpdateTerm = (e: React.FormEvent) => {
    e.preventDefault();
    termForm.put(route("dashboard.shop.attributes.terms.update", [editingTerm!.attr.id, editingTerm!.term.id]), {
      preserveScroll: true,
      onSuccess: () => { setEditingTerm(null); termForm.reset(); toast.success("Term updated."); },
      onError: () => toast.error("Failed to update term."),
    });
  };

  const handleDeleteTerm = () => {
    router.delete(route("dashboard.shop.attributes.terms.destroy", [showDeleteTerm!.attr.id, showDeleteTerm!.term.id]), {
      preserveScroll: true,
      onSuccess: () => { setShowDeleteTerm(null); toast.success("Term deleted."); },
      onError: () => toast.error("Failed to delete term."),
    });
  };

  const startEdit = (attr: Attribute) => {
    setEditingAttr(attr);
    form.setData({ name: attr.name, sort_order: String(attr.sort_order), display_type: attr.display_type });
  };

  const startEditTerm = (attr: Attribute, term: AttributeTerm) => {
    setEditingTerm({ attr, term });
    termForm.setData({ name: term.name, swatch_value: term.swatch_value || "" });
  };

  const getLocalTerms = (attrId: number) => {
    return localData.find((a) => a.id === attrId)?.terms || [];
  };

  const updateLocalTerms = (attrId: number, terms: AttributeTerm[]) => {
    setLocalData((prev) => prev.map((a) => (a.id === attrId ? { ...a, terms } : a)));
  };

  const handleDragEnd = useCallback(async (attr: Attribute, event: DragEndEvent) => {
    const { active, over } = event;
    if (!over || active.id === over.id) return;
    const terms = getLocalTerms(attr.id);
    const oldIndex = terms.findIndex((t) => t.id === active.id);
    const newIndex = terms.findIndex((t) => t.id === over.id);
    if (oldIndex === -1 || newIndex === -1) return;
    const reordered = arrayMove(terms, oldIndex, newIndex);
    updateLocalTerms(attr.id, reordered);
    try {
      await apiPost(route("dashboard.shop.attributes.terms.reorder", attr.id), {
        terms: reordered.map((t, i) => ({ id: t.id, sort_order: i })),
      });
    } catch {
      updateLocalTerms(attr.id, terms);
      toast.error("Failed to reorder terms.");
    }
  }, [localData]);

  const handleCardDragEnd = useCallback(async (event: DragEndEvent) => {
    const { active, over } = event;
    if (!over || active.id === over.id) return;
    const oldIndex = localData.findIndex((a) => a.id === active.id);
    const newIndex = localData.findIndex((a) => a.id === over.id);
    if (oldIndex === -1 || newIndex === -1) return;
    const reordered = arrayMove(localData, oldIndex, newIndex);
    setLocalData(reordered);
    try {
      await apiPost(route("dashboard.shop.attributes.reorder"), {
        attributes: reordered.map((a, i) => ({ id: a.id, sort_order: i })),
      });
    } catch {
      setLocalData(localData);
      toast.error("Failed to reorder attributes.");
    }
  }, [localData]);

  const displayTypeBadge = (dt: string) => {
    const info = DISPLAY_TYPE_LABELS[dt];
    if (!info) return null;
    return (
      <Badge variant="outline" className="text-xs gap-1 font-normal">
        {info.icon}{info.label}
      </Badge>
    );
  };

  function SortableAttributeCard({ attr, terms }: { attr: Attribute; terms: AttributeTerm[] }) {
    const { attributes: sAttrs, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: attr.id });
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
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2">
                <button type="button" className="cursor-grab active:cursor-grabbing text-muted-foreground hover:text-foreground -ml-1" {...sAttrs} {...listeners}>
                  <GripVertical className="h-4 w-4" />
                </button>
                <CardTitle className="text-base">{attr.name}</CardTitle>
                {displayTypeBadge(attr.display_type)}
                <Badge variant="outline" className="text-xs">{terms.length} terms</Badge>
              </div>
              <div className="flex items-center gap-1">
                <Button variant="ghost" size="icon-sm" onClick={() => startEdit(attr)}>
                  <Pencil className="h-3.5 w-3.5" />
                </Button>
                <Button variant="ghost" size="icon-sm" onClick={() => setShowDeleteAttr(attr)}>
                  <Trash2 className="h-3.5 w-3.5 text-destructive" />
                </Button>
                <Dialog open={showCreateTermAttr === attr} onOpenChange={(o) => setShowCreateTermAttr(o ? attr : null)}>
                  <DialogTrigger asChild>
                    <Button variant="outline" size="sm"><Plus className="mr-1 h-3 w-3" /> Add Term</Button>
                  </DialogTrigger>
                  <DialogContent>
                    <form onSubmit={handleCreateTerm}>
                      <DialogHeader>
                        <DialogTitle>Add Term to {attr.name}</DialogTitle>
                        <DialogDescription>Add a value like "Red" or "Small".</DialogDescription>
                      </DialogHeader>
                      <div className="space-y-4 py-4">
                        <div className="grid gap-2">
                          <Label htmlFor="term-name">Name</Label>
                          <Input id="term-name" value={termForm.data.name} onChange={(e) => termForm.setData("name", e.target.value)} required />
                          {termForm.errors.name && <p className="text-sm text-destructive">{termForm.errors.name}</p>}
                        </div>
                        <SwatchValueInput displayType={attr.display_type} value={termForm.data.swatch_value} onChange={(v) => termForm.setData("swatch_value", v)} id="term-swatch" />
                        {termForm.errors.swatch_value && <p className="text-sm text-destructive">{termForm.errors.swatch_value}</p>}
                      </div>
                      <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setShowCreateTermAttr(null)}>Cancel</Button>
                        <Button type="submit" disabled={termForm.processing}>Create</Button>
                      </DialogFooter>
                    </form>
                  </DialogContent>
                </Dialog>
              </div>
            </div>
          </CardHeader>
          {terms.length > 0 && (
            <CardContent className="pb-3">
              <DndContext id={`sort-${attr.id}`} collisionDetection={closestCenter} onDragEnd={(e) => handleDragEnd(attr, e)}>
                <SortableContext items={terms.map((t) => t.id)} strategy={rectSortingStrategy}>
                  <div className="flex flex-wrap gap-2">
                    {terms.map((term) => (
                      <SortableTerm
                        key={term.id}
                        term={term}
                        displayType={attr.display_type}
                        onEdit={() => startEditTerm(attr, term)}
                        onDelete={() => setShowDeleteTerm({ attr, term })}
                      />
                    ))}
                  </div>
                </SortableContext>
              </DndContext>
            </CardContent>
          )}
        </Card>
      </div>
    );
  }

  return (
    <AuthenticatedLayout header="Product Attributes">
      <Head title="Product Attributes" />
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <h2 className="text-lg font-semibold">Attributes</h2>
          <div className="flex items-center gap-2">
          <Dialog open={showCreate} onOpenChange={setShowCreate}>
            <DialogTrigger asChild>
              <Button><Plus className="mr-1 h-4 w-4" /> Add Attribute</Button>
            </DialogTrigger>
            <DialogContent>
              <form onSubmit={handleCreate}>
                <DialogHeader>
                  <DialogTitle>Create Attribute</DialogTitle>
                  <DialogDescription>Add a new product attribute like Color, Size, or Material.</DialogDescription>
                </DialogHeader>
                <div className="space-y-4 py-4">
                  <div className="grid gap-2">
                    <Label htmlFor="attr-name">Name</Label>
                    <Input id="attr-name" value={form.data.name} onChange={(e) => form.setData("name", e.target.value)} required />
                    {form.errors.name && <p className="text-sm text-destructive">{form.errors.name}</p>}
                  </div>
                  <div className="grid gap-2">
                    <Label htmlFor="attr-display-type">Display Type</Label>
                    <Select value={form.data.display_type} onValueChange={(v) => form.setData("display_type", v)}>
                      <SelectTrigger id="attr-display-type"><SelectValue /></SelectTrigger>
                      <SelectContent>
                        {Object.entries(DISPLAY_TYPE_LABELS).map(([key, info]) => (
                          <SelectItem key={key} value={key}>
                            <span className="flex items-center gap-2">{info.icon}<span>{info.label}</span><span className="text-xs text-muted-foreground">— {info.description}</span></span>
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                    {form.errors.display_type && <p className="text-sm text-destructive">{form.errors.display_type}</p>}
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
        </div>

        {localData.length === 0 ? (
          <Empty>
            <EmptyHeader>
              <EmptyMedia variant="icon"><Tag /></EmptyMedia>
              <EmptyTitle>No attributes yet</EmptyTitle>
              <EmptyDescription>Create attributes like Color, Size, or Material to organize your products.</EmptyDescription>
            </EmptyHeader>
          </Empty>
        ) : (
          <DndContext sensors={cardSensors} collisionDetection={closestCenter} onDragEnd={handleCardDragEnd}>
            <SortableContext items={localData.map((a) => a.id)} strategy={verticalListSortingStrategy}>
              <div className="space-y-4">
                {localData.map((attr) => (
                  <SortableAttributeCard
                    key={attr.id}
                    attr={attr}
                    terms={getLocalTerms(attr.id)}
                  />
                ))}
              </div>
            </SortableContext>
          </DndContext>
        )}

        <Dialog open={!!editingAttr} onOpenChange={(o) => { if (!o) setEditingAttr(null); }}>
          <DialogContent>
            <form onSubmit={handleUpdate}>
              <DialogHeader><DialogTitle>Edit Attribute</DialogTitle></DialogHeader>
              <div className="space-y-4 py-4">
                <div className="grid gap-2">
                  <Label htmlFor="edit-attr-name">Name</Label>
                  <Input id="edit-attr-name" value={form.data.name} onChange={(e) => form.setData("name", e.target.value)} required />
                </div>
                <div className="grid gap-2">
                  <Label htmlFor="edit-attr-dt">Display Type</Label>
                  <Select value={form.data.display_type} onValueChange={(v) => form.setData("display_type", v)}>
                    <SelectTrigger id="edit-attr-dt"><SelectValue /></SelectTrigger>
                    <SelectContent>
                      {Object.entries(DISPLAY_TYPE_LABELS).map(([key, info]) => (
                        <SelectItem key={key} value={key}>
                          <span className="flex items-center gap-2">{info.icon}<span>{info.label}</span><span className="text-xs text-muted-foreground">— {info.description}</span></span>
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
              </div>
              <DialogFooter>
                <Button type="button" variant="outline" onClick={() => setEditingAttr(null)}>Cancel</Button>
                <Button type="submit" disabled={form.processing}>Update</Button>
              </DialogFooter>
            </form>
          </DialogContent>
        </Dialog>

        <Dialog open={!!editingTerm} onOpenChange={(o) => { if (!o) setEditingTerm(null); }}>
          <DialogContent>
            <form onSubmit={handleUpdateTerm}>
              <DialogHeader><DialogTitle>Edit Term</DialogTitle></DialogHeader>
              <div className="space-y-4 py-4">
                <div className="grid gap-2">
                  <Label htmlFor="edit-term-name">Name</Label>
                  <Input id="edit-term-name" value={termForm.data.name} onChange={(e) => termForm.setData("name", e.target.value)} required />
                </div>
                {editingTerm && (
                  <SwatchValueInput displayType={editingTerm.attr.display_type} value={termForm.data.swatch_value} onChange={(v) => termForm.setData("swatch_value", v)} id="edit-term-swatch" />
                )}
              </div>
              <DialogFooter>
                <Button type="button" variant="outline" onClick={() => setEditingTerm(null)}>Cancel</Button>
                <Button type="submit" disabled={termForm.processing}>Update</Button>
              </DialogFooter>
            </form>
          </DialogContent>
        </Dialog>

        <Dialog open={!!showDeleteAttr} onOpenChange={(o) => { if (!o) setShowDeleteAttr(null); }}>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>Delete Attribute</DialogTitle>
              <DialogDescription>Are you sure? This will remove "{showDeleteAttr?.name}" and all its terms.</DialogDescription>
            </DialogHeader>
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setShowDeleteAttr(null)}>Cancel</Button>
              <Button type="button" variant="destructive" onClick={handleDelete}>Delete</Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>

        <Dialog open={!!showDeleteTerm} onOpenChange={(o) => { if (!o) setShowDeleteTerm(null); }}>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>Delete Term</DialogTitle>
              <DialogDescription>Are you sure you want to delete "{showDeleteTerm?.term.name}"?</DialogDescription>
            </DialogHeader>
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setShowDeleteTerm(null)}>Cancel</Button>
              <Button type="button" variant="destructive" onClick={handleDeleteTerm}>Delete</Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      </div>
    </AuthenticatedLayout>
  );
}
