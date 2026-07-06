import { useState, useMemo, useCallback, useRef } from "react";
import { toast } from "sonner";
import { Plus, Trash2, Sparkles, Save, AlertTriangle, Image as ImageIcon, X, Info, Layers, Star, Check, Pencil, Percent, GripVertical } from "lucide-react";
import { DndContext, closestCenter, PointerSensor, useSensor, useSensors, type DragEndEvent } from "@dnd-kit/core";
import { arrayMove, SortableContext, verticalListSortingStrategy, useSortable } from "@dnd-kit/sortable";
import { CSS } from "@dnd-kit/utilities";
import { Button } from "@/Components/ui/button";
import { Checkbox } from "@/Components/ui/checkbox";
import { Input } from "@/Components/ui/input";
import { Textarea } from "@/Components/ui/textarea";
import { Label } from "@/Components/ui/label";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Switch } from "@/Components/ui/switch";
import { Badge } from "@/Components/ui/badge";
import { Tooltip, TooltipContent, TooltipTrigger } from "@/Components/ui/tooltip";
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from "@/Components/ui/select";
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from "@/Components/ui/table";
import {
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from "@/Components/ui/dialog";
import {
  Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle,
} from "@/Components/ui/sheet";
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
  AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from "@/Components/ui/alert-dialog";
import { DateTimePicker } from "@/Components/ui/datetime-picker";
import { Separator } from "@/Components/ui/separator";
import { GalleryUploader } from "@/Components/shared/gallery-uploader";
import type { GalleryImage } from "@/Components/shared/gallery-uploader";

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";

async function apiFetch(url: string, method: string, data?: unknown): Promise<any> {
  const options: RequestInit = {
    method,
    headers: {
      "X-Requested-With": "XMLHttpRequest",
      "X-CSRF-TOKEN": csrfToken(),
      Accept: "application/json",
      "Content-Type": "application/json",
    },
  };
  if (data !== undefined && method !== "GET") {
    options.body = JSON.stringify(data);
  }
  const res = await fetch(url, options);
  if (!res.ok) {
    const text = await res.text();
    let msg: string;
    try {
      msg = JSON.parse(text).message || `HTTP ${res.status}`;
    } catch {
      msg = `HTTP ${res.status}`;
    }
    throw new Error(msg);
  }
  return res.json();
}

interface AttributeTerm {
  id: number; name: string; slug: string; attribute_id: number;
}

interface Attribute {
  id: number; name: string; terms: AttributeTerm[];
}

interface VariationAttribute {
  attribute_id: number; attribute_term_id: number; name: string;
}

interface Variation {
  id: number; product_id: number; sku: string | null; barcode: string | null;
  price: string | null; compare_price: string | null; sale_price: string | null;
  sale_from: string | null; sale_to: string | null;
  stock_quantity: number; incoming_stock: number; weight: string | null;
  length: string | null; width: string | null; height: string | null;
  is_active: boolean; visible: boolean; image_url: string | null; images: GalleryImage[] | null; description: string | null;
  attributes: VariationAttribute[] | null; sort_order: number;
  manage_stock: string; backorders: string; stock_status: string | null;
  is_visible?: boolean; on_sale?: boolean; purchasable?: boolean;
  backorders_allowed?: boolean; backordered?: boolean;
}

interface ProductAttributeItem {
  id?: number; attribute_id: number | null; name: string;
  position: number; visible: boolean; variation: boolean;
  options: string[] | null;
  tempId?: string;
}

interface AttributeTermInfo {
  attributeId: number | null;
  termName: string;
}

interface Props {
  productId: number;
  attributes: Attribute[];
  initialVariations?: Variation[];
  productAttributes?: ProductAttributeItem[];
  isCreating?: boolean;
  attrList: ProductAttributeItem[];
  usedGlobalIds: Set<number>;
  availableGlobalAttrs: Attribute[];
  addGlobalAttribute: (id: number) => void;
  addCustomAttribute: (name: string, values: string) => void;
  removeAttribute: (index: number) => void;
  toggleVisible: (index: number) => void;
  toggleVariation: (index: number) => void;
  setAttrOptions: (index: number, value: string) => void;
  defaultAttributes: number[];
  setDefaultAttributes: (ids: number[]) => void;
  globalAttrMap: Map<number, Attribute>;
  formType: string;
  showCustomInput: boolean;
  reorderAttributes?: (reordered: ProductAttributeItem[]) => void;
  setShowCustomInput: (v: boolean) => void;
  customAttrName: string;
  setCustomAttrName: (v: string) => void;
  customAttrValues: string;
  setCustomAttrValues: (v: string) => void;
}

interface RowDirty {
  [variationId: number]: Partial<Variation>;
}

export function VariationsManager({
  productId, attributes, initialVariations, productAttributes = [], isCreating = false,
  attrList, usedGlobalIds, availableGlobalAttrs,
  addGlobalAttribute, addCustomAttribute, removeAttribute, toggleVisible, toggleVariation, setAttrOptions,
  defaultAttributes, setDefaultAttributes, globalAttrMap, formType,
  showCustomInput, setShowCustomInput, customAttrName, setCustomAttrName, customAttrValues, setCustomAttrValues,
  reorderAttributes,
}: Props) {
  const [variations, setVariations] = useState<Variation[]>(initialVariations || []);
  const [dirty, setDirty] = useState<RowDirty>({});
  const [saving, setSaving] = useState(false);
  const [drawerVariation, setDrawerVariation] = useState<Variation | null>(null);
  const [drawerForm, setDrawerForm] = useState<Partial<Variation>>({});
  const [showAdd, setShowAdd] = useState(false);
  const [addForm, setAddForm] = useState<Partial<Variation>>({ is_active: true, manage_stock: 'true', backorders: 'no', stock_quantity: 0 });
  const [generating, setGenerating] = useState(false);
  const [generateGlobalIds, setGenerateGlobalIds] = useState<number[]>([]);
  const [generateCustomIds, setGenerateCustomIds] = useState<number[]>([]);
  const [generateDialogOpen, setGenerateDialogOpen] = useState(false);
  const [dialogTermIds, setDialogTermIds] = useState<number[]>([]);
  const [dialogCustomOptions, setDialogCustomOptions] = useState<Record<number, string[]>>({});
  const [variationToDelete, setVariationToDelete] = useState<Variation | null>(null);
  const [selectedVarIds, setSelectedVarIds] = useState<Set<number>>(new Set());
  const [bulkDialog, setBulkDialog] = useState(false);
  const [bulkPriceMode, setBulkPriceMode] = useState<"set" | "increase" | "decrease" | "percent">("set");
  const [bulkPriceValue, setBulkPriceValue] = useState("");
  const [bulkSalePrice, setBulkSalePrice] = useState("");
  const [bulkSaleFrom, setBulkSaleFrom] = useState("");
  const [bulkSaleTo, setBulkSaleTo] = useState("");
  const [bulkStockMode, setBulkStockMode] = useState<"set" | "increase" | "decrease" | "percent">("set");
  const [bulkStockValue, setBulkStockValue] = useState("");
  const [bulkWeightMode, setBulkWeightMode] = useState<"set" | "increase" | "decrease" | "percent">("set");
  const [bulkWeightValue, setBulkWeightValue] = useState("");
  const [bulkLengthMode, setBulkLengthMode] = useState<"set" | "increase" | "decrease">("set");
  const [bulkLengthValue, setBulkLengthValue] = useState("");
  const [bulkWidthValue, setBulkWidthValue] = useState("");
  const [bulkHeightValue, setBulkHeightValue] = useState("");
  const [bulkManageStock, setBulkManageStock] = useState("");
  const [bulkBackorders, setBulkBackorders] = useState("");
  const [bulkStockStatus, setBulkStockStatus] = useState("");
  const [bulkLowStock, setBulkLowStock] = useState("");
  const [bulkSkuPrefix, setBulkSkuPrefix] = useState("");
  const [bulkSkuSuffix, setBulkSkuSuffix] = useState("");
  const [editingCell, setEditingCell] = useState<{ id: number; field: string } | null>(null);
  const [editValue, setEditValue] = useState("");
  const editInputRef = useRef<HTMLInputElement>(null);
  const vmSensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 8 } }));

  const getUploadCsrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";

  const handleVarUpload = useCallback(
    async (file: File, onProgress: (pct: number) => void, signal: AbortSignal, _pendingId: string) => {
      return new Promise<{ url?: string; path?: string }>((resolve, reject) => {
        const formData = new FormData();
        formData.append("file", file);
        const xhr = new XMLHttpRequest();
        xhr.upload.onprogress = (e) => {
          if (e.lengthComputable) onProgress(Math.round((e.loaded / e.total) * 100));
        };
        xhr.onload = () => {
          if (xhr.status >= 200 && xhr.status < 300) {
            try { resolve(JSON.parse(xhr.responseText)); } catch { reject(new Error("Invalid response")); }
          } else { reject(new Error("Upload failed")); }
        };
        xhr.onerror = () => reject(new Error("Network error"));
        xhr.onabort = () => reject(new Error("Upload cancelled"));
        signal.addEventListener("abort", () => { xhr.abort(); reject(new Error("Upload cancelled")); }, { once: true });
        xhr.open("POST", route("dashboard.shop.products.upload-image"));
        xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
        xhr.setRequestHeader("X-CSRF-TOKEN", getUploadCsrf());
        xhr.send(formData);
      });
    },
    [],
  );

  const handleVarDelete = useCallback(async (image: GalleryImage) => {
    const res = await fetch(route("dashboard.shop.products.delete-image"), {
      method: "DELETE",
      headers: { "Content-Type": "application/json", "X-Requested-With": "XMLHttpRequest", "X-CSRF-TOKEN": getUploadCsrf() },
      body: JSON.stringify({ path: image.path || "", url: image.url, entity_id: productId }),
    });
    if (!res.ok) throw new Error("Delete failed");
  }, [productId]);

  const handleVmAttrDragEnd = useCallback((event: DragEndEvent) => {
    const { active, over } = event;
    if (!over || active.id === over.id) return;
    const list = attrList;
    const oldIndex = list.findIndex((a) => (a.tempId || a.id) === active.id);
    const newIndex = list.findIndex((a) => (a.tempId || a.id) === over.id);
    if (oldIndex === -1 || newIndex === -1) return;
    const next = [...list];
    const [moved] = next.splice(oldIndex, 1);
    next.splice(newIndex, 0, moved);
    next.forEach((a, i) => (a.position = i));
    if (reorderAttributes) reorderAttributes(next);
  }, [attrList, reorderAttributes]);

  function SortableVariationRow({ v, children }: { v: Variation; children: React.ReactNode }) {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: v.id });
    const style = {
      transform: CSS.Transform.toString(transform),
      transition,
      opacity: isDragging ? 0.7 : 1,
    };
    return (
      <TableRow ref={setNodeRef} style={style} className={`${!v.is_active ? "opacity-55" : ""}`}>
        <TableCell className="p-1 w-8">
          <span {...attributes} {...listeners} className="cursor-grab inline-flex text-muted-foreground/50 hover:text-muted-foreground touch-pan-y">
            <GripVertical className="h-3.5 w-3.5" />
          </span>
        </TableCell>
        {children}
      </TableRow>
    );
  }

  const varSensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 8 } }));

  const handleVarDragEnd = useCallback(async (event: DragEndEvent) => {
    const { active, over } = event;
    if (!over || active.id === over.id) return;
    const oldIndex = variations.findIndex((v) => v.id === active.id);
    const newIndex = variations.findIndex((v) => v.id === over.id);
    if (oldIndex === -1 || newIndex === -1) return;
    const next = arrayMove(variations, oldIndex, newIndex);
    setVariations(next);
    try {
      await apiFetch(route("dashboard.shop.products.variations.reorder", productId), "POST", {
        variations: next.map((v, i) => ({ id: v.id, sort_order: i })),
      });
    } catch {
      setVariations(variations);
      toast.error("Failed to reorder variations.");
    }
  }, [variations, productId]);

  const startEdit = (v: Variation, field: string) => {
    const val = (v as any)[field]?.toString() || "";
    setEditingCell({ id: v.id, field });
    setEditValue(val);
    setTimeout(() => editInputRef.current?.focus(), 50);
  };

  const saveEdit = async () => {
    if (!editingCell) return;
    const { id, field } = editingCell;
    try {
      await apiFetch(route("dashboard.shop.products.variations.update", [productId, id]), "PUT", {
        [field]: editValue,
      });
      setEditingCell(null);
      fetchVariations();
    } catch {
      toast.error("Failed to save.");
      setEditingCell(null);
    }
  };

  const isEditing = (v: Variation, field: string) =>
    editingCell?.id === v.id && editingCell?.field === field;

  const customNameRef = useRef<HTMLInputElement>(null);

  const fetchVariations = useCallback(async () => {
    try {
      const data = await apiFetch(route("dashboard.shop.products.variations.index", productId), "GET");
      if (Array.isArray(data)) {
        setVariations(data);
        setDirty({});
      }
    } catch {
      toast.error("Failed to fetch variations.");
    }
  }, [productId]);

  const updateDirty = (vid: number, patch: Partial<Variation>) => {
    setDirty((prev) => ({
      ...prev,
      [vid]: { ...(prev[vid] || {}), ...patch },
    }));
  };

  const saveRow = async (v: Variation) => {
    const patch = dirty[v.id];
    if (!patch || Object.keys(patch).length === 0) return;
    try {
      await apiFetch(route("dashboard.shop.products.variations.update", [productId, v.id]), "PUT", patch);
      toast.success("Variation saved.");
      setDirty((prev) => {
        const next = { ...prev };
        delete next[v.id];
        return next;
      });
      fetchVariations();
    } catch {
      toast.error("Failed to save variation.");
    }
  };

  const toggleActive = async (v: Variation) => {
    try {
      await apiFetch(route("dashboard.shop.products.variations.update", [productId, v.id]), "PUT", {
        is_active: !v.is_active,
      });
      fetchVariations();
    } catch {
      toast.error("Failed to toggle variation.");
    }
  };

  const openDrawer = (v: Variation) => {
    setDrawerVariation(v);
    setDrawerForm({ ...v });
  };

  const closeDrawer = () => {
    setDrawerVariation(null);
    setDrawerForm({});
  };

  const saveDrawer = async () => {
    if (!drawerVariation) return;
    try {
      await apiFetch(route("dashboard.shop.products.variations.update", [productId, drawerVariation.id]), "PUT", drawerForm);
      toast.success("Variation updated.");
      closeDrawer();
      fetchVariations();
    } catch {
      toast.error("Failed to update variation.");
    }
  };

  const confirmDelete = async () => {
    if (!variationToDelete) return;
    try {
      await apiFetch(route("dashboard.shop.products.variations.destroy", [productId, variationToDelete.id]), "DELETE");
      toast.success("Variation deleted.");
      setVariationToDelete(null);
      fetchVariations();
    } catch {
      toast.error("Failed to delete variation.");
    }
  };

  const handleGenerate = async () => {
    if (generateGlobalIds.length === 0 && generateCustomIds.length === 0) { toast.error("Select at least one attribute."); return; }
    setGenerating(true);
    try {
      const result = await apiFetch(route("dashboard.shop.products.variations.generate", productId), "POST", {
        attribute_ids: generateGlobalIds,
        custom_attribute_ids: generateCustomIds,
      });
      toast.success(`Generated ${result.created || 0} variations.`);
      fetchVariations();
    } catch {
      toast.error("Failed to generate variations.");
    } finally {
      setGenerating(false);
    }
  };

  const openGenerateDialog = () => {
    const allTermIds: number[] = [];
    const allCustomOptions: Record<number, string[]> = {};
    for (const src of variationTermSources) {
      if (src.sourceType === 'global') {
        for (const term of src.terms) {
          if (term.id != null) allTermIds.push(term.id);
        }
      } else {
        allCustomOptions[src.customId!] = src.terms.map(t => t.name);
      }
    }
    setDialogTermIds(allTermIds);
    setDialogCustomOptions(allCustomOptions);
    setGenerateDialogOpen(true);
  };

  const toggleDialogTerm = (termId: number) => {
    setDialogTermIds(prev =>
      prev.includes(termId) ? prev.filter(id => id !== termId) : [...prev, termId]
    );
  };

  const toggleDialogAttribute = (src: typeof variationTermSources[0]) => {
    if (src.sourceType === 'global') {
      const allIds = src.terms.map(t => t.id!).filter(id => id != null);
      const allSelected = allIds.every(id => dialogTermIds.includes(id));
      setDialogTermIds(prev =>
        allSelected ? prev.filter(id => !allIds.includes(id)) : [...prev.filter(id => !allIds.includes(id)), ...allIds]
      );
    } else {
      const allOptions = src.terms.map(t => t.name);
      const current = dialogCustomOptions[src.customId!] || [];
      const allSelected = allOptions.every(o => current.includes(o));
      setDialogCustomOptions(prev => ({
        ...prev,
        [src.customId!]: allSelected ? [] : allOptions,
      }));
    }
  };

  // Unified variation attribute sources: global attrs with variation=true + custom attrs with variation=true
  const variationTermSources = useMemo(() => {
    const sources: { sourceType: 'global' | 'custom'; globalId?: number; customId?: number; name: string; terms: { id?: number; name: string }[] }[] = [];
    const globalVarAttrs = productAttributes.filter(a => a.attribute_id && a.variation);
    for (const pa of globalVarAttrs) {
      const attr = attributes.find(a => a.id === pa.attribute_id);
      if (attr && attr.terms.length > 0) {
        sources.push({ sourceType: 'global', globalId: attr.id, name: attr.name, terms: attr.terms.map(t => ({ id: t.id, name: t.name })) });
      }
    }
    const customVarAttrs = productAttributes.filter(a => !a.attribute_id && a.variation);
    for (const pa of customVarAttrs) {
      const options = pa.options || [];
      if (options.length > 0) {
        sources.push({ sourceType: 'custom', customId: pa.id, name: pa.name, terms: options.map(o => ({ name: o })) });
      }
    }
    return sources;
  }, [productAttributes, attributes]);

  const dialogPreviewCount = useMemo(() => {
    const groups: { terms: string[] }[] = [];
    for (const src of variationTermSources) {
      if (src.sourceType === 'global') {
        const selected = src.terms.filter(t => t.id != null && dialogTermIds.includes(t.id));
        if (selected.length > 0) groups.push({ terms: selected.map(t => t.name) });
      } else {
        const selected = dialogCustomOptions[src.customId!] || [];
        if (selected.length > 0) groups.push({ terms: selected });
      }
    }
    if (groups.length === 0) return 0;
    return groups.reduce((acc, g) => acc * g.terms.length, 1);
  }, [dialogTermIds, dialogCustomOptions, variationTermSources]);

  const handleGenerateFromDialog = async () => {
    const termIds = [...dialogTermIds];
    const customAttrIds: number[] = [];
    const customOptNames: string[] = [];
    for (const [attrId, options] of Object.entries(dialogCustomOptions)) {
      if (options.length > 0) {
        customAttrIds.push(parseInt(attrId));
        customOptNames.push(...options);
      }
    }
    if (termIds.length === 0 && customAttrIds.length === 0) {
      toast.error("Select at least one attribute term.");
      return;
    }
    setGenerating(true);
    try {
      const payload: Record<string, unknown> = {};
      if (termIds.length > 0) payload.term_ids = termIds;
      if (customAttrIds.length > 0) payload.custom_attribute_ids = customAttrIds;
      if (customOptNames.length > 0) payload.custom_option_names = customOptNames;
      const result = await apiFetch(route("dashboard.shop.products.variations.generate", productId), "POST", payload);
      toast.success(`Generated ${result.created || 0} variations.`);
      setGenerateDialogOpen(false);
      fetchVariations();
    } catch {
      toast.error("Failed to generate variations.");
    } finally {
      setGenerating(false);
    }
  };

  const handleAdd = async () => {
    if (!addForm.attributes || addForm.attributes.length === 0) { toast.error("Select at least one attribute term."); return; }
    try {
      await apiFetch(route("dashboard.shop.products.variations.store", productId), "POST", addForm);
      setShowAdd(false);
      setAddForm({ is_active: true, manage_stock: 'true', backorders: 'no', stock_quantity: 0 });
      toast.success("Variation added.");
      fetchVariations();
    } catch { toast.error("Failed to add variation."); }
  };

  const handleBulkSave = async () => {
    setSaving(true);
    try {
      const dirtyEntries = Object.entries(dirty);
      if (dirtyEntries.length === 0) { toast.success("Nothing to save."); return; }
      const payload = dirtyEntries.map(([id, patch]) => ({ id: parseInt(id), ...patch }));
      const selectedTargets = selectedVarIds.size > 0 ? Array.from(selectedVarIds) : null;
      await apiFetch(route("dashboard.shop.products.variations.bulk", productId), "POST", {
        variations: payload,
        selected_ids: selectedTargets,
        bulk_edit: true,
      });
      toast.success(`${payload.length} variation(s) saved.`);
      setDirty({});
      fetchVariations();
    } catch { toast.error("Failed to save variations."); } finally {
      setSaving(false);
    }
  };

  const selectedTargetIds = useMemo(() => {
    if (selectedVarIds.size === 0) return null;
    return Array.from(selectedVarIds);
  }, [selectedVarIds]);

  const applyBulkEdit = async () => {
    setSaving(true);
    try {
      const targets = selectedTargetIds
        ? variations.filter((v) => selectedVarIds.has(v.id))
        : variations;
      if (targets.length === 0) { toast.success("Nothing to update."); return; }
      const patches: Record<string, unknown>[] = [];
      targets.forEach((v) => {
        const patch: Record<string, unknown> = {};
        if (bulkPriceValue) {
          if (bulkPriceMode === "set") patch.price = bulkPriceValue;
          else if (bulkPriceMode === "increase") patch.price = (parseFloat(v.price || "0") + parseFloat(bulkPriceValue)).toFixed(2);
          else if (bulkPriceMode === "decrease") patch.price = Math.max(0, parseFloat(v.price || "0") - parseFloat(bulkPriceValue)).toFixed(2);
          else if (bulkPriceMode === "percent") patch.price = (parseFloat(v.price || "0") * (1 + parseFloat(bulkPriceValue) / 100)).toFixed(2);
        }
        if (bulkStockValue) {
          const sv = parseInt(bulkStockValue) || 0;
          if (bulkStockMode === "set") patch.stock_quantity = sv;
          else if (bulkStockMode === "increase") patch.stock_quantity = (v.stock_quantity || 0) + sv;
          else if (bulkStockMode === "decrease") patch.stock_quantity = Math.max(0, (v.stock_quantity || 0) - sv);
          else if (bulkStockMode === "percent") patch.stock_quantity = Math.round((v.stock_quantity || 0) * (1 + sv / 100));
        }
        if (bulkWeightValue) {
          const wv = parseFloat(bulkWeightValue);
          if (bulkWeightMode === "set") patch.weight = wv.toString();
          else if (bulkWeightMode === "increase") patch.weight = (parseFloat(v.weight || "0") + wv).toFixed(2);
          else if (bulkWeightMode === "decrease") patch.weight = Math.max(0, parseFloat(v.weight || "0") - wv).toFixed(2);
          else if (bulkWeightMode === "percent") patch.weight = (parseFloat(v.weight || "0") * (1 + wv / 100)).toFixed(2);
        }
        if (bulkLengthValue) {
          const lv = parseFloat(bulkLengthValue);
          if (bulkLengthMode === "set") patch.length = lv.toString();
          else patch.length = (parseFloat(v.length || "0") + lv).toFixed(2);
        }
        if (bulkWidthValue) {
          patch.width = (parseFloat(bulkWidthValue)).toFixed(2);
        }
        if (bulkHeightValue) {
          patch.height = (parseFloat(bulkHeightValue)).toFixed(2);
        }
        if (bulkSalePrice) patch.sale_price = bulkSalePrice;
        if (bulkSaleFrom) patch.sale_from = bulkSaleFrom;
        if (bulkSaleTo) patch.sale_to = bulkSaleTo;
        if (bulkManageStock) patch.manage_stock = bulkManageStock;
        if (bulkBackorders) patch.backorders = bulkBackorders;
        if (bulkStockStatus) patch.stock_status = bulkStockStatus;
        if (bulkLowStock) patch.low_stock_threshold = parseInt(bulkLowStock) || 0;
        if (bulkSkuPrefix || bulkSkuSuffix) patch.sku = `${bulkSkuPrefix}${v.sku || ""}${bulkSkuSuffix}`;
        if (Object.keys(patch).length > 0) {
          patches.push({ id: v.id, ...patch });
        }
      });
      if (patches.length === 0) { toast.success("Nothing to update."); return; }
      await apiFetch(route("dashboard.shop.products.variations.bulk", productId), "POST", {
        variations: patches,
        bulk_edit: true,
        selected_ids: selectedTargetIds,
      });
      toast.success(`${patches.length} variation(s) updated.`);
      setBulkDialog(false);
      setBulkPriceValue(""); setBulkPriceMode("set");
      setBulkStockValue(""); setBulkStockMode("set");
      setBulkWeightValue(""); setBulkWeightMode("set");
      setBulkLengthValue(""); setBulkLengthMode("set"); setBulkWidthValue(""); setBulkHeightValue("");
      setBulkSalePrice(""); setBulkSaleFrom(""); setBulkSaleTo("");
      setBulkManageStock(""); setBulkBackorders(""); setBulkStockStatus(""); setBulkLowStock("");
      setBulkSkuPrefix(""); setBulkSkuSuffix("");
      setSelectedVarIds(new Set());
      fetchVariations();
    } catch { toast.error("Failed to apply bulk edit."); } finally {
      setSaving(false);
    }
  };

  const activeAttrs = attributes.filter((a) => a.terms.length > 0);

  const previewVariations = useMemo(() => {
    if (!isCreating) return [] as AttributeTermInfo[][];
    const varAttrs = productAttributes.filter((a) => a.variation);
    if (varAttrs.length === 0) return [];
    const termSets = varAttrs.map((pa) => {
      if (pa.attribute_id != null) {
        const attr = attributes.find((a) => a.id === pa.attribute_id);
        return (attr?.terms || []).map((t) => ({ attributeId: pa.attribute_id!, termName: t.name }));
      }
      return (pa.options || []).map((o) => ({ attributeId: null, termName: o }));
    });
    return termSets.reduce((acc, terms) =>
      acc.flatMap((combo) => terms.map((term) => [...combo, term])), [[]] as AttributeTermInfo[][]);
  }, [isCreating, productAttributes, attributes]);

  const variationCount = isCreating ? previewVariations.length : variations.length;
  const hasUnsavedChanges = Object.keys(dirty).length > 0;

  const stockStatus = (v: Variation): { label: string; color: string } => {
    if (v.manage_stock === 'parent') return { label: "Parent", color: "bg-blue-500/10 text-blue-600" };
    if (v.manage_stock !== 'true') return { label: "In Stock", color: "bg-green-500/10 text-green-600" };
    if (v.stock_quantity > 0) return { label: "In Stock", color: "bg-green-500/10 text-green-600" };
    if (v.backorders !== 'no') return { label: "Backorder", color: "bg-amber-500/10 text-amber-600" };
    return { label: "Out of Stock", color: "bg-red-500/10 text-red-600" };
  };

  const isPriceSet = (v: Variation): boolean => {
    return v.price !== null && v.price !== undefined && v.price !== '';
  };

  const isDefault = (v: Variation): boolean => {
    if (!v.attributes || v.attributes.length === 0 || defaultAttributes.length === 0) return false;
    const vIds = v.attributes.map((a) => a.attribute_term_id).sort();
    const defIds = [...defaultAttributes].sort();
    return vIds.length === defIds.length && vIds.every((id, i) => id === defIds[i]);
  };

  const setAsDefault = (v: Variation) => {
    const ids = v.attributes?.map((a) => a.attribute_term_id) || [];
    setDefaultAttributes(ids);
  };

  const addCustomAttr = () => {
    if (!customAttrName.trim()) return;
    addCustomAttribute(customAttrName.trim(), customAttrValues);
    setShowCustomInput(false);
  };

  const formatPrice = (val: string | null | undefined): string => {
    if (!val) return "—";
    return `$${parseFloat(val).toFixed(2)}`;
  };

  function SortableAttrRow({ attr, index }: { attr: ProductAttributeItem; index: number }) {
    const isGlobal = attr.attribute_id != null;
    const sortId = attr.tempId || attr.id || index;
    const { attributes: sortAttrs, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: sortId });
    const style = {
      transform: CSS.Transform.toString(transform),
      transition,
      opacity: isDragging ? 0.5 : 1,
      position: "relative" as const,
      zIndex: isDragging ? 1 : 0,
    };
    return (
      <div ref={setNodeRef} style={style} className="flex items-center gap-2 rounded-md border bg-background px-2.5 py-1.5">
        <button type="button" className="cursor-grab active:cursor-grabbing text-muted-foreground hover:text-foreground shrink-0" {...listeners} {...sortAttrs}>
          <GripVertical className="h-3.5 w-3.5" />
        </button>
        <div className="flex-1 min-w-0 flex items-center gap-2">
          <span className="text-xs font-medium truncate">{attr.name}</span>
          {isGlobal && <span className="text-[10px] text-muted-foreground bg-muted px-1 py-0.5 rounded">Global</span>}
          {attr.variation && <span className="text-[10px] text-blue-600 bg-blue-100 dark:bg-blue-900/40 dark:text-blue-400 px-1 py-0.5 rounded">Variation</span>}
        </div>
        <div className="flex items-center gap-0.5 shrink-0">
          <Tooltip><TooltipTrigger asChild>
            <button type="button" onClick={() => toggleVisible(index)}
              className={`h-6 w-6 rounded flex items-center justify-center ${attr.visible ? "text-primary" : "text-muted-foreground"}`}>
              {attr.visible ? "👁" : "👁‍🗨"}
            </button>
          </TooltipTrigger><TooltipContent><p>{attr.visible ? "Visible" : "Hidden"}</p></TooltipContent></Tooltip>
          {formType === "variable" ? (
            <Tooltip><TooltipTrigger asChild>
              <button type="button" onClick={() => toggleVariation(index)}
                className={`h-6 w-6 rounded flex items-center justify-center ${attr.variation ? "bg-primary/10 text-primary" : "text-muted-foreground"}`}>
                <Layers className="h-3 w-3" />
              </button>
            </TooltipTrigger><TooltipContent><p>{attr.variation ? "Used for variations" : "Not used for variations"}</p></TooltipContent></Tooltip>
          ) : (
            <span className="h-6 w-6 rounded flex items-center justify-center text-muted-foreground/30 cursor-not-allowed"><Layers className="h-3 w-3" /></span>
          )}
          <button type="button" onClick={() => removeAttribute(index)}
            className="h-6 w-6 rounded flex items-center justify-center text-destructive hover:bg-destructive/10"><X className="h-3 w-3" /></button>
        </div>
      </div>
    );
  }

  return (
    <Card>
      <CardHeader>
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2">
            <CardTitle>Attributes & Variations</CardTitle>
            <Badge variant="secondary">{variationCount} {isCreating ? "expected" : "total"}</Badge>
            {isCreating && <Badge variant="outline" className="text-muted-foreground">Preview</Badge>}
            {!isCreating && hasUnsavedChanges && (
              <Badge variant="outline" className="text-amber-500 border-amber-500">
                <AlertTriangle className="h-3 w-3 mr-1" /> {Object.keys(dirty).length} unsaved
              </Badge>
            )}
          </div>
          {!isCreating && (
            <div className="flex items-center gap-2">
              {variationTermSources.length >= 1 && (
                <Button type="button" variant="outline" size="sm" onClick={openGenerateDialog} disabled={generating}>
                  <Sparkles className="mr-1 h-3 w-3" />{generating ? "Generating..." : "Generate variations"}
                </Button>
              )}
              {selectedVarIds.size > 0 && (
                <>
                  <Badge variant="secondary" className="text-xs mr-1">{selectedVarIds.size} selected</Badge>
                  <Button type="button" variant="outline" size="sm" onClick={() => {
                    if (confirm(`Delete ${selectedVarIds.size} selected variation(s)?`)) {
                      apiFetch(route("dashboard.shop.products.variations.delete-bulk", productId), "POST", {
                        selected_ids: Array.from(selectedVarIds),
                      }).then(() => {
                        toast.success(`${selectedVarIds.size} variation(s) deleted.`);
                        setSelectedVarIds(new Set());
                        fetchVariations();
                      }).catch(() => toast.error("Failed to delete variations."));
                    }
                  }}><Trash2 className="mr-1 h-3 w-3" /> Delete Selected</Button>
                </>
              )}
              <Button type="button" variant="outline" size="sm" onClick={() => setBulkDialog(true)}><Percent className="mr-1 h-3 w-3" /> Bulk Edit</Button>
              <Button type="button" variant="outline" size="sm" onClick={() => setShowAdd(true)}><Plus className="mr-1 h-3 w-3" /> Add</Button>
              {hasUnsavedChanges && (
                <Button type="button" size="sm" onClick={handleBulkSave} disabled={saving}>
                  <Save className="mr-1 h-3 w-3" />{saving ? "Saving..." : `Save All (${Object.keys(dirty).length})`}
                </Button>
              )}
            </div>
          )}
        </div>
      </CardHeader>
      <CardContent className="space-y-6">
        {/* Attribute Management */}
        <div className="rounded-lg border bg-muted/30 p-4 space-y-3">
          <div className="flex items-center gap-2">
            <h4 className="text-sm font-semibold">Product Attributes</h4>
            {availableGlobalAttrs.length === 0 ? (
              <span className="inline-flex items-center gap-1 px-3 h-7 rounded-md border border-border text-xs text-muted-foreground cursor-not-allowed bg-muted/30">
                <Plus className="h-3 w-3" />
                All added
              </span>
            ) : (
              <Select onValueChange={(v) => { const id = parseInt(v); if (id) addGlobalAttribute(id); }}>
                <SelectTrigger className="h-7 w-auto text-xs gap-1">
                  <Plus className="h-3 w-3" /><SelectValue placeholder="Add existing" />
                </SelectTrigger>
                <SelectContent>
                  {availableGlobalAttrs.map((a) => (
                    <SelectItem key={a.id} value={a.id.toString()}>{a.name}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            )}
            <Button type="button" variant="outline" size="sm" className="h-7 text-xs"
              onClick={() => { setShowCustomInput(true); setTimeout(() => customNameRef.current?.focus(), 50); }}>
              <Plus className="h-3 w-3 mr-1" /> Custom
            </Button>
          </div>

          {showCustomInput && (
            <div className="rounded-lg border bg-background p-3 space-y-2">
              <div className="grid grid-cols-2 gap-2">
                <div className="grid gap-1">
                  <Label className="text-xs">Attribute Name</Label>
                  <Input ref={customNameRef} placeholder="e.g. Size, Color" value={customAttrName}
                    onChange={(e) => setCustomAttrName(e.target.value)}
                    onKeyDown={(e) => { if (e.key === "Enter") { e.preventDefault(); addCustomAttr(); } }} />
                </div>
                <div className="grid gap-1">
                  <Label className="text-xs">Values (pipe-separated)</Label>
                  <Input placeholder="S | M | L" value={customAttrValues}
                    onChange={(e) => setCustomAttrValues(e.target.value)}
                    onKeyDown={(e) => { if (e.key === "Enter") { e.preventDefault(); addCustomAttr(); } }} />
                </div>
              </div>
              <div className="flex justify-end gap-2">
                <Button type="button" variant="ghost" size="sm" className="h-7 text-xs"
                  onClick={() => { setShowCustomInput(false); setCustomAttrName(""); setCustomAttrValues(""); }}>Cancel</Button>
                <Button type="button" size="sm" className="h-7 text-xs" onClick={addCustomAttr}>Add</Button>
              </div>
            </div>
          )}

          {attrList.length === 0 && !showCustomInput ? (
            <p className="text-xs text-muted-foreground text-center py-2">No attributes yet. Add global or custom ones above.</p>
          ) : (
            <DndContext sensors={vmSensors} collisionDetection={closestCenter} onDragEnd={handleVmAttrDragEnd}>
              <SortableContext items={attrList.map((a) => a.tempId || a.id || "")} strategy={verticalListSortingStrategy}>
                <div className="space-y-1.5">
                  {attrList.map((attr, index) => (
                    <SortableAttrRow key={attr.tempId || attr.id || index} attr={attr} index={index} />
                  ))}
                </div>
              </SortableContext>
            </DndContext>
          )}
        </div>

        {/* Variation Table */}
        {isCreating ? (
          <div className="flex items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 dark:border-blue-900 dark:bg-blue-950/30 p-3 text-sm">
            <Info className="h-4 w-4 text-blue-500 shrink-0" />
            <p className="text-blue-700 dark:text-blue-300 text-xs">Save the product to start managing variations — set prices, stock, SKUs, and images for each combination.</p>
          </div>
        ) : null}

        {isCreating && previewVariations.length === 0 ? (
          <div className="text-center py-6 space-y-2">
            <Layers className="mx-auto h-6 w-6 text-muted-foreground/40" />
            <p className="text-xs text-muted-foreground">Mark attributes as "Variation" to preview combinations.</p>
          </div>
        ) : !isCreating && variations.length === 0 ? (
          <div className="text-center py-6 space-y-3">
            <Layers className="mx-auto h-6 w-6 text-muted-foreground/40" />
            <p className="text-xs text-muted-foreground">No variations yet. Add one manually or generate from attributes.</p>
            <div className="flex items-center justify-center gap-2">
              <Button type="button" variant="outline" size="sm" onClick={() => setShowAdd(true)}><Plus className="mr-1 h-3 w-3" /> Add Manually</Button>
              {variationTermSources.length >= 1 && (
                <Button type="button" variant="outline" size="sm" onClick={() => {
                  setGenerateGlobalIds(variationTermSources.filter(s => s.sourceType === 'global').map(s => s.globalId!));
                  setGenerateCustomIds(variationTermSources.filter(s => s.sourceType === 'custom').map(s => s.customId!));
                  setTimeout(handleGenerate, 50);
                }} disabled={generating}>
                  <Sparkles className="mr-1 h-3 w-3" /> Generate All
                </Button>
              )}
            </div>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead className="w-8 p-1"></TableHead>
                  <TableHead className="w-8 p-1">
                    <Checkbox checked={!isCreating && variations.length > 0 && selectedVarIds.size === variations.length}
                      indeterminate={selectedVarIds.size > 0 && selectedVarIds.size < variations.length}
                      onCheckedChange={(val) => {
                        if (val) setSelectedVarIds(new Set(variations.map((v) => v.id)));
                        else setSelectedVarIds(new Set());
                      }} />
                  </TableHead>
                  <TableHead className="w-20">Default & ID</TableHead>
                  <TableHead className="w-14">Img</TableHead>
                  <TableHead>Attributes</TableHead>
                  <TableHead className="w-28">SKU</TableHead>
                  <TableHead className="w-28 text-right">Price</TableHead>
                  <TableHead className="w-16 text-right">Stock</TableHead>
                  <TableHead className="w-28">Status</TableHead>
                  <TableHead className="w-12">On</TableHead>
                  <TableHead className="w-12"></TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {isCreating ? (
                  previewVariations.map((combo, idx) => (
                    <TableRow key={idx} className="opacity-70">
                      <TableCell></TableCell>
                      <TableCell></TableCell>
                      <TableCell className="text-xs text-muted-foreground">—</TableCell>
                      <TableCell><ImageIcon className="h-4 w-4 text-muted-foreground/20" /></TableCell>
                      <TableCell>
                        <div className="flex gap-1 flex-nowrap overflow-hidden">
                          {combo.map((t, i) => <Badge key={i} variant="secondary" className="text-xs shrink-0">{t.termName}</Badge>)}
                        </div>
                      </TableCell>
                      <TableCell className="text-xs text-muted-foreground">—</TableCell>
                      <TableCell className="text-right text-xs text-muted-foreground">—</TableCell>
                      <TableCell className="text-right text-xs text-muted-foreground">—</TableCell>
                      <TableCell className="text-xs text-muted-foreground">—</TableCell>
                      <TableCell><Switch checked disabled className="opacity-30" /></TableCell>
                      <TableCell></TableCell>
                    </TableRow>
                  ))
                ) : (
                  <DndContext sensors={varSensors} collisionDetection={closestCenter} onDragEnd={handleVarDragEnd}>
                    <SortableContext items={variations.map((v) => v.id)} strategy={verticalListSortingStrategy}>
                      {variations.map((v) => (
                        <SortableVariationRow key={v.id} v={v}>
                          {/* Select checkbox */}
                          <TableCell className="p-1">
                            <Checkbox checked={selectedVarIds.has(v.id)}
                          onCheckedChange={(val) => {
                            setSelectedVarIds((prev) => {
                              const next = new Set(prev);
                              if (val) next.add(v.id);
                              else next.delete(v.id);
                              return next;
                            });
                          }} />
                      </TableCell>
                      {/* Default star + ID */}
                      <TableCell>
                        <div className="flex items-center gap-1.5">
                          <button type="button" onClick={() => setAsDefault(v)}
                            className={`h-5 w-5 rounded flex items-center justify-center shrink-0 transition-colors ${isDefault(v) ? "text-amber-400" : "text-muted-foreground/30 hover:text-muted-foreground/60"}`}
                            title="Set as default variation">
                            <Star className={`h-3 w-3 ${isDefault(v) ? "fill-current" : ""}`} />
                          </button>
                          <span className="text-xs font-mono text-muted-foreground whitespace-nowrap">#{v.id}</span>
                        </div>
                      </TableCell>
                      {/* Image */}
                      <TableCell>
                        <button type="button" onClick={() => openDrawer(v)} className="block">
                          {v.image_url
                            ? <img src={v.image_url} alt="" className="h-8 w-8 rounded object-cover border shrink-0" />
                            : <div className="h-8 w-8 rounded border border-dashed border-muted-foreground/30 flex items-center justify-center shrink-0"><ImageIcon className="h-4 w-4 text-muted-foreground/40" /></div>
                          }
                        </button>
                      </TableCell>
                      {/* Attributes */}
                      <TableCell>
                        <div className="flex gap-1 flex-nowrap overflow-hidden">
                          {v.attributes?.map((a, i) => <Badge key={i} variant="secondary" className="text-xs shrink-0">{a.name}</Badge>)}
                        </div>
                      </TableCell>
                      {/* SKU (inline editable) */}
                      <TableCell>
                        {isEditing(v, "sku") ? (
                          <Input ref={editInputRef} className="h-7 text-xs w-full min-w-0"
                            value={editValue} onChange={(e) => setEditValue(e.target.value)}
                            onBlur={saveEdit}
                            onKeyDown={(e) => { if (e.key === "Enter") saveEdit(); if (e.key === "Escape") setEditingCell(null); }} />
                        ) : (
                          <span className="text-xs font-mono text-muted-foreground truncate block max-w-[120px] cursor-pointer hover:text-foreground"
                            onClick={() => startEdit(v, "sku")} title="Click to edit SKU">
                            {v.sku || "—"}
                          </span>
                        )}
                      </TableCell>
                      {/* Price (inline editable) — merged with sale strikethrough */}
                      <TableCell className="text-right">
                        {isEditing(v, "price") ? (
                          <Input ref={editInputRef} className="h-7 text-xs w-full min-w-0 text-right"
                            type="number" step="0.01" min="0"
                            value={editValue} onChange={(e) => setEditValue(e.target.value)}
                            onBlur={saveEdit}
                            onKeyDown={(e) => { if (e.key === "Enter") saveEdit(); if (e.key === "Escape") setEditingCell(null); }} />
                        ) : (
                          <div className="flex items-center justify-end gap-1">
                            <span className={`text-xs font-medium cursor-pointer hover:text-foreground whitespace-nowrap ${!isPriceSet(v) ? 'text-muted-foreground/40 line-through' : ''} ${v.on_sale ? 'line-through text-muted-foreground/60' : ''}`}
                              onClick={() => startEdit(v, "price")} title="Click to edit price">
                              {formatPrice(v.price)}
                            </span>
                            {v.on_sale ? (
                              <span className="text-xs font-semibold text-green-600 dark:text-green-400 whitespace-nowrap">{formatPrice(v.sale_price)}</span>
                            ) : v.sale_price ? (
                              <span className="text-xs font-medium text-amber-600 dark:text-amber-400 whitespace-nowrap">{formatPrice(v.sale_price)}</span>
                            ) : null}
                          </div>
                        )}
                      </TableCell>
                      {/* Stock (inline editable) */}
                      <TableCell className="text-right">
                        {isEditing(v, "stock_quantity") ? (
                          <Input ref={editInputRef} className="h-7 text-xs w-full min-w-0 text-right"
                            type="number" min="0"
                            value={editValue} onChange={(e) => setEditValue(e.target.value)}
                            onBlur={saveEdit}
                            onKeyDown={(e) => { if (e.key === "Enter") saveEdit(); if (e.key === "Escape") setEditingCell(null); }} />
                        ) : (
                          <span className="text-xs tabular-nums cursor-pointer hover:text-foreground whitespace-nowrap"
                            onClick={() => v.manage_stock !== 'parent' ? startEdit(v, "stock_quantity") : undefined}
                            title={v.manage_stock === 'parent' ? "Stock managed by parent" : "Click to edit stock"}>
                            {v.manage_stock === 'parent' ? "—" : v.manage_stock ? v.stock_quantity : "∞"}
                          </span>
                        )}
                      </TableCell>
                      {/* Stock Status badge */}
                      <TableCell>
                        <div className="flex gap-1 flex-nowrap overflow-hidden">
                          {v.is_visible === false && (
                            <span className="inline-flex items-center text-[10px] font-medium px-1.5 py-0.5 rounded-full bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400 shrink-0">No Price</span>
                          )}
                          {v.on_sale && (
                            <span className="inline-flex items-center text-[10px] font-medium px-1.5 py-0.5 rounded-full bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400 shrink-0">Sale</span>
                          )}
                          {v.backordered && (
                            <span className="inline-flex items-center text-[10px] font-medium px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400 shrink-0">Backordered</span>
                          )}
                          <span className={`inline-flex items-center text-[10px] font-medium px-1.5 py-0.5 rounded-full shrink-0 ${stockStatus(v).color}`}>
                            {stockStatus(v).label}
                          </span>
                        </div>
                      </TableCell>
                      {/* Active */}
                      <TableCell>
                        <Switch checked={v.is_active} onCheckedChange={() => toggleActive(v)} />
                      </TableCell>
                      {/* Edit + Delete */}
                      <TableCell>
                        <div className="flex items-center gap-0.5">
                          <button type="button" onClick={() => openDrawer(v)}
                            className="h-6 w-6 rounded flex items-center justify-center text-muted-foreground hover:text-foreground"
                            title="Edit variation details"><Pencil className="h-3 w-3" /></button>
                          <button type="button" onClick={() => setVariationToDelete(v)}
                            className="h-6 w-6 rounded flex items-center justify-center text-destructive hover:bg-destructive/10"
                            title="Delete"><Trash2 className="h-3 w-3" /></button>
                        </div>
                      </TableCell>
                    </SortableVariationRow>
                  ))}
                    </SortableContext>
                  </DndContext>
                )}
              </TableBody>
            </Table>
          </div>
        )}

        {/* Enriched Sheet Drawer — all variation fields */}
        <Sheet open={!!drawerVariation} onOpenChange={(open) => { if (!open) closeDrawer(); }}>
          <SheetContent side="right" className="w-full sm:max-w-lg p-0 flex flex-col">
            {drawerVariation && (
              <>
                <SheetHeader className="sticky top-0 z-20 bg-background p-6 pb-4 border-b">
                  <SheetTitle>Edit Variation</SheetTitle>
                  <SheetDescription>
                    <div className="flex flex-wrap gap-1 mt-1">
                      {drawerVariation.attributes?.map((a, i) => <Badge key={i} variant="secondary" className="text-xs">{a.name}</Badge>)}
                    </div>
                    <p className="text-xs text-muted-foreground mt-1">Variation #{drawerVariation.id} · Product #{productId}</p>
                  </SheetDescription>
                </SheetHeader>

                <div className="flex-1 overflow-y-auto px-6 py-4 space-y-6">
                  {/* Basic Info: SKU + Barcode + Visible */}
                  <div>
                    <h4 className="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-3">Basic Info</h4>
                    <div className="grid grid-cols-2 gap-3">
                      <div className="grid gap-1.5">
                        <Label className="text-xs">SKU</Label>
                        <Input className="h-8 text-sm" value={drawerForm.sku || ""}
                          onChange={(e) => setDrawerForm({ ...drawerForm, sku: e.target.value })} />
                      </div>
                      <div className="grid gap-1.5">
                        <Label className="text-xs">Barcode</Label>
                        <Input className="h-8 text-sm" value={drawerForm.barcode || ""}
                          onChange={(e) => setDrawerForm({ ...drawerForm, barcode: e.target.value })} />
                      </div>
                    </div>
                    <div className="flex items-center gap-2 mt-3">
                      <Switch id="visible-toggle" checked={drawerForm.visible !== false}
                        onCheckedChange={(val) => setDrawerForm({ ...drawerForm, visible: val })} />
                      <Label htmlFor="visible-toggle" className="text-xs cursor-pointer">Visible on storefront</Label>
                    </div>
                  </div>

                  <Separator />

                  {/* Pricing */}
                  <div>
                    <h4 className="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-3">Pricing</h4>
                    <div className="grid grid-cols-2 gap-3">
                      <div className="grid gap-1.5">
                        <Label className="text-xs">Regular Price</Label>
                        <Input className="h-8 text-sm" type="number" step="0.01" min="0" value={drawerForm.price || ""}
                          onChange={(e) => setDrawerForm({ ...drawerForm, price: e.target.value })} />
                      </div>
                      <div className="grid gap-1.5">
                        <Label className="text-xs">Compare Price</Label>
                        <Input className="h-8 text-sm" type="number" step="0.01" min="0" value={drawerForm.compare_price || ""}
                          onChange={(e) => setDrawerForm({ ...drawerForm, compare_price: e.target.value })} />
                      </div>
                    </div>
                    <div className="grid grid-cols-2 gap-3 mt-3">
                      <div className="grid gap-1.5">
                        <Label className="text-xs">Sale Price</Label>
                        <Input className="h-8 text-sm" type="number" step="0.01" min="0" value={drawerForm.sale_price || ""}
                          onChange={(e) => setDrawerForm({ ...drawerForm, sale_price: e.target.value })} />
                      </div>
                      <div className="grid gap-1.5">
                        <Label className="text-xs">Weight</Label>
                        <Input className="h-8 text-sm" type="number" step="0.01" min="0" value={drawerForm.weight || ""}
                          onChange={(e) => setDrawerForm({ ...drawerForm, weight: e.target.value })} />
                      </div>
                    </div>
                    <div className="grid grid-cols-2 gap-3 mt-3">
                      <div className="grid gap-1.5">
                        <Label className="text-xs">Sale From</Label>
                        <DateTimePicker value={drawerForm.sale_from ?? undefined}
                          onChange={(date) => setDrawerForm({ ...drawerForm, sale_from: date })} placeholder="Start" />
                      </div>
                      <div className="grid gap-1.5">
                        <Label className="text-xs">Sale To</Label>
                        <DateTimePicker value={drawerForm.sale_to ?? undefined}
                          onChange={(date) => setDrawerForm({ ...drawerForm, sale_to: date })} placeholder="End" />
                      </div>
                    </div>
                  </div>

                  <Separator />

                  {/* Inventory */}
                  <div>
                    <h4 className="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-3">Inventory</h4>
                    <div className="grid grid-cols-2 gap-3 mb-3">
                      <div className="grid gap-1.5">
                        <Label className="text-xs">Manage Stock</Label>
                        <Select value={drawerForm.manage_stock === 'parent' ? 'parent' : (drawerForm.manage_stock === 'true' ? 'true' : 'false')}
                          onValueChange={(val) => setDrawerForm({ ...drawerForm, manage_stock: val === 'true' ? 'true' : val === 'parent' ? 'parent' : 'false' })}>
                          <SelectTrigger className="h-8 text-sm"><SelectValue /></SelectTrigger>
                          <SelectContent>
                            <SelectItem value="true">On</SelectItem>
                            <SelectItem value="false">Off</SelectItem>
                            <SelectItem value="parent">Parent</SelectItem>
                          </SelectContent>
                        </Select>
                      </div>
                      <div className="grid gap-1.5">
                        <Label className="text-xs">Backorders</Label>
                        <Select value={drawerForm.backorders || "no"}
                          onValueChange={(val) => setDrawerForm({ ...drawerForm, backorders: val })}>
                          <SelectTrigger className="h-8 text-sm"><SelectValue /></SelectTrigger>
                          <SelectContent>
                            <SelectItem value="no">No</SelectItem>
                            <SelectItem value="notify">Notify</SelectItem>
                            <SelectItem value="yes">Yes</SelectItem>
                          </SelectContent>
                        </Select>
                      </div>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                      <div className="grid gap-1.5">
                        <Label className="text-xs">Stock Quantity</Label>
                        <Input className="h-8 text-sm" type="number" min="0"
                          value={(drawerForm.manage_stock === 'true' || drawerForm.manage_stock === 'parent') ? (drawerForm.stock_quantity ?? 0) : ""}
                          disabled={drawerForm.manage_stock !== 'true' && drawerForm.manage_stock !== 'parent'}
                          onChange={(e) => setDrawerForm({ ...drawerForm, stock_quantity: parseInt(e.target.value) || 0 })} />
                      </div>
                      <div className="grid gap-1.5">
                        <Label className="text-xs">Incoming Stock</Label>
                        <Input className="h-8 text-sm" type="number" min="0"
                          value={drawerForm.incoming_stock ?? 0}
                          onChange={(e) => setDrawerForm({ ...drawerForm, incoming_stock: parseInt(e.target.value) || 0 })} />
                      </div>
                    </div>
                  </div>

                  <Separator />

                  {/* Dimensions */}
                  <div>
                    <h4 className="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-3">Dimensions</h4>
                    <div className="grid grid-cols-4 gap-3">
                      <div className="grid gap-1.5"><Label className="text-xs">Length</Label><Input className="h-8 text-sm" type="number" step="0.01" value={drawerForm.length || ""} onChange={(e) => setDrawerForm({ ...drawerForm, length: e.target.value })} /></div>
                      <div className="grid gap-1.5"><Label className="text-xs">Width</Label><Input className="h-8 text-sm" type="number" step="0.01" value={drawerForm.width || ""} onChange={(e) => setDrawerForm({ ...drawerForm, width: e.target.value })} /></div>
                      <div className="grid gap-1.5"><Label className="text-xs">Height</Label><Input className="h-8 text-sm" type="number" step="0.01" value={drawerForm.height || ""} onChange={(e) => setDrawerForm({ ...drawerForm, height: e.target.value })} /></div>
                      <div className="grid gap-1.5"><Label className="text-xs">Barcode</Label><Input className="h-8 text-sm" value={drawerForm.barcode || ""} onChange={(e) => setDrawerForm({ ...drawerForm, barcode: e.target.value })} /></div>
                    </div>
                  </div>

                  <Separator />

                  {/* Gallery */}
                  <div>
                    <h4 className="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-3">Images</h4>
                    <GalleryUploader label="" description="Add variation images (first is cover)"
                      value={drawerForm.images || []} onChange={(images) => {
                        const featured = images.find((img) => img.featured);
                        setDrawerForm({ ...drawerForm, images, image_url: featured?.url || images[0]?.url || null });
                      }} thumbnailUrl={drawerForm.image_url || undefined}
                      onThumbnailChange={(url) => setDrawerForm({ ...drawerForm, image_url: url })} maxImages={10}
                      onUpload={handleVarUpload} onDelete={handleVarDelete} />
                  </div>

                  <Separator />

                  {/* Description */}
                  <div>
                    <h4 className="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-3">Description</h4>
                    <Textarea className="text-sm min-h-[80px]" value={drawerForm.description || ""}
                      onChange={(e) => setDrawerForm({ ...drawerForm, description: e.target.value })} placeholder="Optional variation description" />
                  </div>
                </div>

                <SheetFooter className="sticky bottom-0 z-20 bg-background p-6 pt-4 border-t gap-2">
                  <Button variant="outline" onClick={closeDrawer}>Cancel</Button>
                  <Button onClick={saveDrawer}>Save Changes</Button>
                </SheetFooter>
              </>
            )}
          </SheetContent>
        </Sheet>

        {/* Generate Variations Dialog */}
        <Dialog open={generateDialogOpen} onOpenChange={setGenerateDialogOpen}>
          <DialogContent className="max-w-lg">
            <DialogHeader>
              <DialogTitle>Generate Variations</DialogTitle>
              <DialogDescription>
                Select the attribute terms to combine. All terms are selected by default.
              </DialogDescription>
            </DialogHeader>
            <div className="space-y-4 py-3 max-h-[60vh] overflow-y-auto">
              {variationTermSources.map((src) => {
                const isGlobal = src.sourceType === 'global';
                const termIds = isGlobal ? src.terms.map(t => t.id!).filter(id => id != null) : [];
                const optionNames = !isGlobal ? src.terms.map(t => t.name) : [];
                const currentTermIds = isGlobal ? dialogTermIds : [];
                const currentOptions = !isGlobal ? (dialogCustomOptions[src.customId!] || []) : [];
                const allSelected = isGlobal
                  ? termIds.length > 0 && termIds.every(id => currentTermIds.includes(id))
                  : optionNames.length > 0 && optionNames.every(o => currentOptions.includes(o));
                const anySelected = isGlobal
                  ? termIds.some(id => currentTermIds.includes(id))
                  : optionNames.some(o => currentOptions.includes(o));
                return (
                  <div key={isGlobal ? `g-${src.globalId}` : `c-${src.customId}`} className="rounded-lg border p-3 space-y-2">
                    <div className="flex items-center gap-2">
                      <Checkbox id={`attr-${isGlobal ? src.globalId : src.customId}`}
                        checked={allSelected}
                        onCheckedChange={() => toggleDialogAttribute(src)} />
                      <label htmlFor={`attr-${isGlobal ? src.globalId : src.customId}`}
                        className="text-sm font-medium leading-none cursor-pointer peer-disabled:cursor-not-allowed peer-disabled:opacity-70">
                        {src.name}
                      </label>
                      {!isGlobal && <span className="text-[10px] text-muted-foreground bg-muted px-1 rounded">Custom</span>}
                      <Badge variant="secondary" className="text-[10px]">{src.terms.length}</Badge>
                    </div>
                    <div className="ml-6 grid grid-cols-3 gap-1.5">
                      {src.terms.map((term) => {
                        const termSelected = isGlobal
                          ? currentTermIds.includes(term.id!)
                          : currentOptions.includes(term.name);
                        return (
                          <div key={isGlobal ? term.id : term.name} className="flex items-center gap-1.5">
                            <Checkbox id={`t-${isGlobal ? term.id : term.name}`}
                              checked={termSelected}
                              onCheckedChange={() => {
                                if (isGlobal && term.id != null) {
                                  toggleDialogTerm(term.id);
                                } else {
                                  const cid = src.customId!;
                                  setDialogCustomOptions(prev => {
                                    const current = prev[cid] || [];
                                    const next = current.includes(term.name)
                                      ? current.filter(o => o !== term.name)
                                      : [...current, term.name];
                                    return { ...prev, [cid]: next };
                                  });
                                }
                              }} />
                            <label htmlFor={`t-${isGlobal ? term.id : term.name}`}
                              className="text-xs leading-none cursor-pointer peer-disabled:cursor-not-allowed peer-disabled:opacity-70">
                              {term.name}
                            </label>
                          </div>
                        );
                      })}
                    </div>
                  </div>
                );
              })}
              {variationTermSources.length === 0 && (
                <p className="text-xs text-muted-foreground text-center py-4">No attributes marked for variations.</p>
              )}
              <div className="rounded-md bg-muted p-3 text-center">
                <p className="text-xs font-medium">
                  {dialogPreviewCount} variation(s) will be generated
                </p>
              </div>
            </div>
            <DialogFooter>
              <Button variant="outline" onClick={() => setGenerateDialogOpen(false)}>Cancel</Button>
              <Button onClick={handleGenerateFromDialog} disabled={generating || dialogPreviewCount === 0}>
                <Sparkles className="mr-1 h-3 w-3" />
                {generating ? "Generating..." : `Generate ${dialogPreviewCount} variation(s)`}
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>

        {/* Add Variation Dialog */}
        <Dialog open={showAdd} onOpenChange={setShowAdd}>
          <DialogContent className="max-w-lg">
            <DialogHeader>
              <DialogTitle>Add Variation</DialogTitle>
              <DialogDescription>Select attribute terms for this variation.</DialogDescription>
            </DialogHeader>
            <div className="space-y-4 py-2">
              <div className="space-y-2">
                <Label className="text-xs">Attributes</Label>
                {variationTermSources.length === 0 ? (
                  <p className="text-xs text-muted-foreground">No attributes marked for variations.</p>
                ) : (
                  variationTermSources.map((src) => (
                    <div key={src.sourceType === 'global' ? `g-${src.globalId}` : `c-${src.customId}`}>
                      <p className="text-xs font-medium text-muted-foreground mb-1">{src.name}</p>
                      <div className="flex flex-wrap gap-1">
                        {src.terms.map((term) => {
                          const selected = addForm.attributes?.some((a) =>
                            src.sourceType === 'global'
                              ? a.attribute_term_id === term.id
                              : a.product_attribute_id === src.customId && a.name === term.name
                          );
                          return (
                            <Badge key={term.id ?? term.name} variant={selected ? "default" : "outline"} className="cursor-pointer"
                              onClick={() => {
                                const current = addForm.attributes || [];
                                const attrEntry = src.sourceType === 'global'
                                  ? { attribute_id: src.globalId, attribute_term_id: term.id, name: term.name }
                                  : { attribute_id: null, attribute_term_id: null, product_attribute_id: src.customId, name: term.name };
                                const updated = selected
                                  ? current.filter((a) => {
                                      if (src.sourceType === 'global') return a.attribute_term_id !== term.id;
                                      return !(a.product_attribute_id === src.customId && a.name === term.name);
                                    })
                                  : [...current, attrEntry];
                                setAddForm({ ...addForm, attributes: updated });
                              }}>
                              {term.name}
                            </Badge>
                          );
                        })}
                      </div>
                    </div>
                  ))
                )}
              </div>
              <div className="grid grid-cols-2 gap-2">
                <div className="grid gap-1"><Label className="text-xs">SKU</Label><Input size={1} value={addForm.sku || ""} onChange={(e) => setAddForm({ ...addForm, sku: e.target.value })} /></div>
                <div className="grid gap-1"><Label className="text-xs">Price</Label><Input size={1} type="number" step="0.01" value={addForm.price || ""} onChange={(e) => setAddForm({ ...addForm, price: e.target.value })} /></div>
              </div>
              <div className="grid grid-cols-2 gap-2">
                <div className="grid gap-1"><Label className="text-xs">Stock</Label><Input size={1} type="number" min="0" value={addForm.stock_quantity ?? 0} onChange={(e) => setAddForm({ ...addForm, stock_quantity: parseInt(e.target.value) || 0 })} /></div>
                <div className="grid gap-1"><Label className="text-xs">Weight (kg)</Label><Input size={1} type="number" step="0.01" value={addForm.weight || ""} onChange={(e) => setAddForm({ ...addForm, weight: e.target.value })} /></div>
              </div>
            </div>
            <DialogFooter>
              <Button variant="outline" onClick={() => setShowAdd(false)}>Cancel</Button>
              <Button onClick={handleAdd}>Add Variation</Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>

        {/* Delete Confirmation */}
        <AlertDialog open={!!variationToDelete} onOpenChange={() => setVariationToDelete(null)}>
          <AlertDialogContent>
            <AlertDialogHeader>
              <AlertDialogTitle>Delete Variation?</AlertDialogTitle>
              <AlertDialogDescription>This will permanently remove this variation.</AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
              <AlertDialogCancel>Cancel</AlertDialogCancel>
              <AlertDialogAction onClick={confirmDelete} className="bg-destructive">Delete</AlertDialogAction>
            </AlertDialogFooter>
          </AlertDialogContent>
        </AlertDialog>

        {/* Bulk Edit Dialog */}
        <Dialog open={bulkDialog} onOpenChange={setBulkDialog}>
          <DialogContent className="max-w-2xl max-h-[80vh] flex flex-col p-0 gap-0 overflow-hidden">
            <DialogHeader className="sticky top-0 z-10 bg-background px-6 pt-6 pb-4 border-b shrink-0">
              <DialogTitle>Bulk Edit Variations</DialogTitle>
              <DialogDescription>
                {selectedVarIds.size > 0
                  ? `Editing ${selectedVarIds.size} selected variation(s). Leave fields empty to skip.`
                  : "No variations selected — changes will apply to all variations. Select specific variations in the table to limit scope."}
              </DialogDescription>
            </DialogHeader>
            <div className="flex-1 overflow-y-auto px-6 py-4 space-y-5">
              {/* Pricing */}
              <div className="rounded-lg border p-3 space-y-3">
                <h4 className="text-xs font-semibold uppercase tracking-wider">Pricing</h4>
                <div className="grid gap-2">
                  <Label className="text-xs">Regular Price</Label>
                  <div className="flex gap-2">
                    <Select value={bulkPriceMode} onValueChange={(v) => setBulkPriceMode(v as any)}>
                      <SelectTrigger className="h-8 w-28 text-xs"><SelectValue /></SelectTrigger>
                      <SelectContent>
                        <SelectItem value="set">Set to</SelectItem>
                        <SelectItem value="increase">Increase by</SelectItem>
                        <SelectItem value="decrease">Decrease by</SelectItem>
                        <SelectItem value="percent">Percent (±)</SelectItem>
                      </SelectContent>
                    </Select>
                    <Input type="number" step="0.01" placeholder={bulkPriceMode === "percent" ? "%" : "0.00"}
                      value={bulkPriceValue} onChange={(e) => setBulkPriceValue(e.target.value)} className="h-8" />
                  </div>
                </div>
                <div className="grid gap-1.5">
                  <Label className="text-xs">Sale Price</Label>
                  <Input type="number" step="0.01" min="0" placeholder="Leave empty to skip"
                    value={bulkSalePrice} onChange={(e) => setBulkSalePrice(e.target.value)} className="h-8" />
                </div>
                <div className="grid grid-cols-2 gap-2">
                  <div className="grid gap-1.5">
                    <Label className="text-xs">Sale From</Label>
                    <DateTimePicker value={bulkSaleFrom || undefined} onChange={(d) => setBulkSaleFrom(d || "")} placeholder="Start" />
                  </div>
                  <div className="grid gap-1.5">
                    <Label className="text-xs">Sale To</Label>
                    <DateTimePicker value={bulkSaleTo || undefined} onChange={(d) => setBulkSaleTo(d || "")} placeholder="End" />
                  </div>
                </div>
              </div>

              {/* Inventory */}
              <div className="rounded-lg border p-3 space-y-3">
                <h4 className="text-xs font-semibold uppercase tracking-wider">Inventory</h4>
                <div className="grid gap-2">
                  <Label className="text-xs">Stock Quantity</Label>
                  <div className="flex gap-2">
                    <Select value={bulkStockMode} onValueChange={(v) => setBulkStockMode(v as any)}>
                      <SelectTrigger className="h-8 w-28 text-xs"><SelectValue /></SelectTrigger>
                      <SelectContent>
                        <SelectItem value="set">Set to</SelectItem>
                        <SelectItem value="increase">Increase by</SelectItem>
                        <SelectItem value="decrease">Decrease by</SelectItem>
                        <SelectItem value="percent">Percent</SelectItem>
                      </SelectContent>
                    </Select>
                    <Input type="number" min="0" placeholder={bulkStockMode === "percent" ? "%" : "0"}
                      value={bulkStockValue} onChange={(e) => setBulkStockValue(e.target.value)} className="h-8" />
                  </div>
                </div>
                <div className="grid grid-cols-3 gap-2">
                  <div className="grid gap-1.5">
                    <Label className="text-xs">Manage Stock</Label>
                    <Select value={bulkManageStock} onValueChange={setBulkManageStock}>
                      <SelectTrigger className="h-8 text-xs"><SelectValue placeholder="Skip" /></SelectTrigger>
                      <SelectContent>
                        <SelectItem value="true">On</SelectItem>
                        <SelectItem value="false">Off</SelectItem>
                        <SelectItem value="parent">Use Parent</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                  <div className="grid gap-1.5">
                    <Label className="text-xs">Backorders</Label>
                    <Select value={bulkBackorders} onValueChange={setBulkBackorders}>
                      <SelectTrigger className="h-8 text-xs"><SelectValue placeholder="Skip" /></SelectTrigger>
                      <SelectContent>
                        <SelectItem value="no">No</SelectItem>
                        <SelectItem value="notify">Notify</SelectItem>
                        <SelectItem value="yes">Yes</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                  <div className="grid gap-1.5">
                    <Label className="text-xs">Stock Status</Label>
                    <Select value={bulkStockStatus} onValueChange={setBulkStockStatus}>
                      <SelectTrigger className="h-8 text-xs"><SelectValue placeholder="Skip" /></SelectTrigger>
                      <SelectContent>
                        <SelectItem value="instock">In Stock</SelectItem>
                        <SelectItem value="outofstock">Out of Stock</SelectItem>
                        <SelectItem value="onbackorder">On Backorder</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                </div>
                <div className="grid gap-1.5">
                  <Label className="text-xs">Low Stock Threshold</Label>
                  <Input type="number" min="0" placeholder="Leave empty to skip"
                    value={bulkLowStock} onChange={(e) => setBulkLowStock(e.target.value)} className="h-8" />
                </div>
              </div>

              {/* Shipping */}
              <div className="rounded-lg border p-3 space-y-3">
                <h4 className="text-xs font-semibold uppercase tracking-wider">Shipping</h4>
                <div className="grid gap-2">
                  <Label className="text-xs">Weight</Label>
                  <div className="flex gap-2">
                    <Select value={bulkWeightMode} onValueChange={(v) => setBulkWeightMode(v as any)}>
                      <SelectTrigger className="h-8 w-28 text-xs"><SelectValue /></SelectTrigger>
                      <SelectContent>
                        <SelectItem value="set">Set to</SelectItem>
                        <SelectItem value="increase">Increase by</SelectItem>
                        <SelectItem value="decrease">Decrease by</SelectItem>
                        <SelectItem value="percent">Percent</SelectItem>
                      </SelectContent>
                    </Select>
                    <Input type="number" step="0.01" placeholder={bulkWeightMode === "percent" ? "%" : "0.00"}
                      value={bulkWeightValue} onChange={(e) => setBulkWeightValue(e.target.value)} className="h-8" />
                  </div>
                </div>
                <div className="grid grid-cols-3 gap-2">
                  <div className="grid gap-1.5">
                    <Label className="text-xs">Length</Label>
                    <div className="flex gap-1">
                      <Select value={bulkLengthMode} onValueChange={(v) => setBulkLengthMode(v as any)} className="w-20">
                        <SelectTrigger className="h-8 text-xs w-20"><SelectValue /></SelectTrigger>
                        <SelectContent>
                          <SelectItem value="set">Set</SelectItem>
                          <SelectItem value="increase">+</SelectItem>
                          <SelectItem value="decrease">-</SelectItem>
                        </SelectContent>
                      </Select>
                      <Input type="number" step="0.01" placeholder="0"
                        value={bulkLengthValue} onChange={(e) => setBulkLengthValue(e.target.value)} className="h-8" />
                    </div>
                  </div>
                  <div className="grid gap-1.5">
                    <Label className="text-xs">Width</Label>
                    <Input type="number" step="0.01" placeholder="0"
                      value={bulkWidthValue} onChange={(e) => setBulkWidthValue(e.target.value)} className="h-8" />
                  </div>
                  <div className="grid gap-1.5">
                    <Label className="text-xs">Height</Label>
                    <Input type="number" step="0.01" placeholder="0"
                      value={bulkHeightValue} onChange={(e) => setBulkHeightValue(e.target.value)} className="h-8" />
                  </div>
                </div>
              </div>

              {/* SKU Pattern */}
              <div className="rounded-lg border p-3 space-y-3">
                <h4 className="text-xs font-semibold uppercase tracking-wider">SKU Pattern</h4>
                <p className="text-[10px] text-muted-foreground">Add a prefix and/or suffix to existing SKUs. Leave both empty to skip.</p>
                <div className="grid grid-cols-2 gap-2">
                  <div className="grid gap-1.5">
                    <Label className="text-xs">Prefix</Label>
                    <Input placeholder="e.g. WS-" value={bulkSkuPrefix} onChange={(e) => setBulkSkuPrefix(e.target.value)} className="h-8" />
                  </div>
                  <div className="grid gap-1.5">
                    <Label className="text-xs">Suffix</Label>
                    <Input placeholder="e.g. -2026" value={bulkSkuSuffix} onChange={(e) => setBulkSkuSuffix(e.target.value)} className="h-8" />
                  </div>
                </div>
              </div>
            </div>
            <DialogFooter className="sticky bottom-0 z-10 bg-background px-6 py-4 border-t shrink-0 flex items-center justify-between">
              <span className="text-xs text-muted-foreground">
                {selectedVarIds.size > 0 ? `${selectedVarIds.size} variation(s) selected` : "Applies to all variations"}
              </span>
              <div className="flex gap-2">
                <Button variant="outline" onClick={() => { setBulkDialog(false); setSelectedVarIds(new Set()); }}>Cancel</Button>
                <Button onClick={applyBulkEdit} disabled={saving}>{saving ? "Applying..." : "Apply Changes"}</Button>
              </div>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      </CardContent>
    </Card>
  );
}
