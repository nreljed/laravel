import { useState, useEffect, useMemo, useCallback, useRef, lazy, Suspense } from "react";
import { Head, Link, useForm, usePage, router } from "@inertiajs/react";
import { toast } from "sonner";
import { ArrowLeft, Calendar, HelpCircle, Layers, Archive, Star, AlertTriangle, Plus, X, Eye, EyeOff, GripVertical, Info, Tag, Tags, Images, FolderTree, ListTree, Package, Truck, Globe, ExternalLink, Download, Hash, ShoppingBag, Eye as VisibilityIcon, Copyright, Monitor, Wrench, Search } from "lucide-react";
import { DndContext, closestCenter, PointerSensor, useSensor, useSensors, type DragEndEvent } from "@dnd-kit/core";
import { SortableContext, verticalListSortingStrategy, useSortable } from "@dnd-kit/sortable";
import { CSS } from "@dnd-kit/utilities";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";

import { Button } from "@/Components/ui/button";
import { Section, SectionField } from "@/Components/section";
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription,
  AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from "@/Components/ui/alert-dialog";
import { Input } from "@/Components/ui/input";
import { Textarea } from "@/Components/ui/textarea";
import { Label } from "@/Components/ui/label";
import { Switch } from "@/Components/ui/switch";
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from "@/Components/ui/select";
import {
  Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator,
} from "@/Components/ui/breadcrumb";
import { Tooltip, TooltipContent, TooltipTrigger } from "@/Components/ui/tooltip";
import { PublishSection } from "@/Components/ui/publish-section";
import { GalleryUploader, type GalleryImage } from "@/Components/shared/gallery-uploader";
import { TermsSelector } from "@/Components/shared/terms-selector";
import { DateTimePicker } from "@/Components/ui/datetime-picker";
const VariationsManager = lazy(() => import("../../components/VariationsManager").then((m) => ({ default: m.VariationsManager })));

interface Category { id: number; name: string }
interface Tag { id: number; name: string }
interface Brand { id: number; name: string }
interface AttributeTerm { id: number; name: string; slug: string; attribute_id: number }
interface Attribute { id: number; name: string; terms: AttributeTerm[] }
interface ProductAttributeItem {
  id?: number; attribute_id: number | null; name: string;
  position: number; visible: boolean; variation: boolean;
  options: string[] | null;
  tempId?: string; // frontend-only tracking
}
interface Variation { id: number; product_id: number; sku: string | null; price: string | null; stock_quantity: number; weight: string | null; length: string | null; width: string | null; height: string | null; is_active: boolean; image_url: string | null; attributes: { attribute_id: number; attribute_term_id: number; name: string }[] | null; sort_order: number }

interface GroupedItem {
  product_id: number; quantity: number; sort_order?: number;
  name?: string; sku?: string; price?: string;
}

interface DownloadItem {
  id?: number; name: string; file: string;
  download_limit: number; download_expiry: number; sort_order: number;
}

interface SimpleProduct {
  id: number; name: string; sku: string | null; type: string; price: string | null;
}

interface Product {
  id: number; name: string; slug: string; description: string | null; short_description: string | null;
  price: number; compare_price: number | null; cost_price: number | null;
  sale_price: number | null; sale_from: string | null; sale_to: string | null;
  sku: string | null; barcode: string | null; global_unique_id: string | null;
  stock_quantity: number; low_stock_threshold: number; incoming_stock: number | null;
  hold_stock_minutes: number | null; stock_status: string | null;
  track_stock: boolean; backorders: string; status: string; type: string;
  catalog_visibility: string; virtual: boolean; downloadable: boolean;
  external_url: string | null; button_text: string | null;
  is_featured: boolean; sold_individually: boolean; min_quantity: number | null;
  max_quantity: number | null; menu_order: number | null;
  is_taxable: boolean; tax_class: string | null; weight: number | null; length: number | null;
  width: number | null; height: number | null; thumbnail_url: string | null;
  images: { url: string; path: string }[] | null; categories: Category[]; tags: Tag[]; brands: Brand[];
  publish_date: string | null; groupedItems?: GroupedItem[]; downloads?: DownloadItem[];
}

interface PageProps {
  product: Product | null; categories: Category[]; tags: Tag[]; brands: Brand[];
  attributes: Attribute[]; variations: Variation[];
  productAttributes?: ProductAttributeItem[]; flash?: { status?: string; error?: string };
  allProducts?: SimpleProduct[];
}

interface FormData {
  name: string; description: string; short_description: string;
  price: string; compare_price: string; cost_price: string;
  sale_price: string; sale_from: string | null; sale_to: string | null;
  sku: string; barcode: string; global_unique_id: string; vendor: string;
  stock_quantity: string; low_stock_threshold: string; incoming_stock: string; hold_stock_minutes: string;
  stock_status: string; track_stock: boolean; backorders: string; status: string; publish_date: string | null;
  type: string; catalog_visibility: string; virtual: boolean; downloadable: boolean;
  external_url: string; button_text: string;
  is_featured: boolean; sold_individually: boolean; min_quantity: string; max_quantity: string; menu_order: string;
  is_taxable: boolean; tax_class: string;
  weight: string; length: string; width: string; height: string;
  categories: number[]; tags: number[]; brands: number[]; thumbnail_url: string; images: GalleryImage[];
  default_attributes: number[];
  product_attributes: ProductAttributeItem[];
  grouped_items: { product_id: number; quantity: number; sort_order?: number; name?: string; sku?: string; price?: string | number }[];
  downloads: { id?: number; name: string; file: string; download_limit: number; download_expiry: number }[];
}

const minDate = new Date(Date.now() - 60000);

export default function ProductsForm() {
  const { props } = usePage<PageProps>();
  const product = props.product || null;
  const categories = props.categories || [];
  const tags = props.tags || [];
  const brands = props.brands || [];
  const attributes = props.attributes || [];
  const variations = props.variations || [];
  const initialProductAttrs = (props.productAttributes || []) as ProductAttributeItem[];
  const allProducts = props.allProducts || [];
  const isEditing = !!product;
  const flash = useMemo(() => props.flash || {} as { status?: string; error?: string }, [props.flash]);

  const [saleScheduled, setSaleScheduled] = useState(!!product?.sale_from || !!product?.sale_to);
  const [showTypeWarning, setShowTypeWarning] = useState(false);
  const [pendingType, setPendingType] = useState<string | null>(null);
  const [groupedItems, setGroupedItems] = useState<GroupedItem[]>(
    (product?.groupedItems || []).map((item, i) => ({
      ...item,
      sort_order: item.sort_order ?? i,
      name: item.name || "",
      sku: item.sku || "",
      price: item.price || "",
    }))
  );
  const [downloads, setDownloads] = useState<DownloadItem[]>(
    (product?.downloads || []).map((d, i) => ({ ...d, sort_order: d.sort_order ?? i }))
  );
  const [searchProductTerm, setSearchProductTerm] = useState("");
  const [showProductPicker, setShowProductPicker] = useState(false);
  const [attrList, setAttrList] = useState<ProductAttributeItem[]>(
    initialProductAttrs.length > 0
      ? initialProductAttrs
      : []
  );
  const [customAttrName, setCustomAttrName] = useState("");
  const [customAttrValues, setCustomAttrValues] = useState("");
  const [showCustomInput, setShowCustomInput] = useState(false);
  const customNameRef = useRef<HTMLInputElement>(null);
  const nextTempId = useRef(1);

  const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 8 } }));

  const handleAttrDragEnd = useCallback((event: DragEndEvent) => {
    const { active, over } = event;
    if (!over || active.id === over.id) return;
    setAttrList((prev) => {
      const oldIndex = prev.findIndex((a) => (a.tempId || a.id) === active.id);
      const newIndex = prev.findIndex((a) => (a.tempId || a.id) === over.id);
      if (oldIndex === -1 || newIndex === -1) return prev;
      const next = [...prev];
      const [moved] = next.splice(oldIndex, 1);
      next.splice(newIndex, 0, moved);
      return next.map((a, i) => ({ ...a, position: i }));
    });
  }, []);

  const globalAttrMap = useMemo(() => {
    const map = new Map<number, Attribute>();
    attributes.forEach((a) => map.set(a.id, a));
    return map;
  }, [attributes]);

  const usedGlobalIds = useMemo(() => {
    return new Set(
      attrList.filter((a) => a.attribute_id).map((a) => a.attribute_id!)
    );
  }, [attrList]);

  useEffect(() => {
    if (flash.status) toast.success(flash.status);
    if (flash.error) toast.error(flash.error);
  }, [flash.status, flash.error]);

  const form = useForm<FormData>({
    name: product?.name || "",
    description: product?.description || "",
    short_description: product?.short_description || "",
    price: product?.price?.toString() || "",
    compare_price: product?.compare_price?.toString() || "",
    cost_price: product?.cost_price?.toString() || "",
    sale_price: product?.sale_price?.toString() || "",
    sale_from: product?.sale_from || null,
    sale_to: product?.sale_to || null,
    sku: product?.sku || "",
    barcode: product?.barcode || "",
    global_unique_id: product?.global_unique_id || "",
    vendor: product?.vendor || "",
    stock_quantity: product?.stock_quantity?.toString() || "0",
    low_stock_threshold: product?.low_stock_threshold?.toString() || "5",
    incoming_stock: product?.incoming_stock?.toString() || "0",
    hold_stock_minutes: product?.hold_stock_minutes?.toString() || "",
    stock_status: product?.stock_status || "instock",
    track_stock: product?.track_stock ?? true,
    backorders: product?.backorders || "no",
    status: product?.status || "draft",
    publish_date: product?.publish_date || null,
    type: product?.type || "simple",
    catalog_visibility: product?.catalog_visibility || "visible",
    virtual: product?.virtual ?? false,
    downloadable: product?.downloadable ?? false,
    external_url: product?.external_url || "",
    button_text: product?.button_text || "",
    is_featured: product?.is_featured ?? false,
    sold_individually: product?.sold_individually ?? false,
    min_quantity: product?.min_quantity?.toString() || "",
    max_quantity: product?.max_quantity?.toString() || "",
    menu_order: product?.menu_order?.toString() || "",
    is_taxable: product?.is_taxable ?? true,
    tax_class: product?.tax_class || "",
    weight: product?.weight?.toString() || "",
    length: product?.length?.toString() || "",
    width: product?.width?.toString() || "",
    height: product?.height?.toString() || "",
    categories: product?.categories?.map((c) => c.id) || [],
    tags: product?.tags?.map((t) => t.id) || [],
    brands: product?.brands?.map((b) => b.id) || [],
    thumbnail_url: product?.thumbnail_url || "",
    images: product?.images || [],
    default_attributes: product?.default_attributes || [],
    product_attributes: initialProductAttrs,
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!form.data.name.trim()) { toast.error("Product name is required"); return; }

    form.setData("product_attributes", attrList);
    form.setData("grouped_items", groupedItems);
    form.setData("downloads", downloads);

    const options = {
      preserveScroll: true,
      onError: (errs: Record<string, string>) => {
        const first = Object.values(errs)[0];
        if (first) toast.error(first);
      },
    };

    if (isEditing) {
      form.put(route("dashboard.shop.products.update", product!.id), options);
    } else {
      form.post(route("dashboard.shop.products.store"), options);
    }
  };

  const handleCreateCategory = useCallback(async (name: string) => {
    try {
      const res = await fetch(route("dashboard.shop.categories.store").toString(), {
        method: "POST",
        headers: { "Content-Type": "application/json", "Accept": "application/json", "X-CSRF-TOKEN": (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || "" },
        body: JSON.stringify({ name }),
      });
      const data = await res.json();
      if (res.ok && data.id) return { id: data.id, name };
      return null;
    } catch {
      toast.error("Failed to create category");
      return null;
    }
  }, []);

  const handleCreateBrand = useCallback(async (name: string) => {
    try {
      const res = await fetch(route("dashboard.shop.brands.store").toString(), {
        method: "POST",
        headers: { "Content-Type": "application/json", "Accept": "application/json", "X-CSRF-TOKEN": (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || "" },
        body: JSON.stringify({ name }),
      });
      const data = await res.json();
      if (res.ok && data.id) return { id: data.id, name };
      return null;
    } catch {
      toast.error("Failed to create brand");
      return null;
    }
  }, []);

  const handleCreateTag = useCallback(async (name: string) => {
    try {
      const res = await fetch(route("dashboard.shop.tags.store").toString(), {
        method: "POST",
        headers: { "Content-Type": "application/json", "Accept": "application/json", "X-CSRF-TOKEN": (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || "" },
        body: JSON.stringify({ name }),
      });
      const data = await res.json();
      if (res.ok && data.id) return { id: data.id, name };
      return null;
    } catch {
      toast.error("Failed to create tag");
      return null;
    }
  }, []);

  const getCsrfToken = () =>
    (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || "";

  const handleUpload = useCallback(
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
            try {
              resolve(JSON.parse(xhr.responseText));
            } catch {
              reject(new Error("Invalid response"));
            }
          } else {
            reject(new Error("Upload failed"));
          }
        };
        xhr.onerror = () => reject(new Error("Network error"));
        xhr.onabort = () => reject(new Error("Upload cancelled"));
        signal.addEventListener("abort", () => { xhr.abort(); reject(new Error("Upload cancelled")); }, { once: true });
        xhr.open("POST", route("dashboard.shop.products.upload-image"));
        xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
        xhr.setRequestHeader("X-CSRF-TOKEN", getCsrfToken());
        xhr.send(formData);
      });
    },
    [],
  );

  const handleDelete = useCallback(async (image: GalleryImage) => {
    await fetch(route("dashboard.shop.products.delete-image"), {
      method: "DELETE",
      headers: { "Content-Type": "application/json", "X-Requested-With": "XMLHttpRequest", "X-CSRF-TOKEN": getCsrfToken() },
      body: JSON.stringify({ path: image.path || "", url: image.url, entity_id: product?.id }),
    });
  }, [product?.id]);

  const addGlobalAttribute = useCallback((attributeId: number) => {
    const attr = globalAttrMap.get(attributeId);
    if (!attr) return;
    setAttrList((prev) => [
      ...prev,
      {
        attribute_id: attributeId,
        name: attr.name,
        options: null,
        position: prev.length,
        visible: true,
        variation: form.data.type === "variable",
        tempId: `new_${nextTempId.current++}`,
      },
    ]);
  }, [globalAttrMap, form.data.type]);

  const addCustomAttribute = useCallback(() => {
    if (!customAttrName.trim()) return;
    const values = customAttrValues.split("|").map((s) => s.trim()).filter(Boolean);
    if (values.length === 0) return;
    setAttrList((prev) => [
      ...prev,
      {
        attribute_id: null,
        name: customAttrName.trim(),
        options: values,
        position: prev.length,
        visible: true,
        variation: form.data.type === "variable",
        tempId: `custom_${nextTempId.current++}`,
      },
    ]);
    setCustomAttrName("");
    setCustomAttrValues("");
    setShowCustomInput(false);
  }, [customAttrName, customAttrValues, form.data.type]);

  const removeAttribute = useCallback((index: number) => {
    setAttrList((prev) => prev.filter((_, i) => i !== index));
  }, []);

  const toggleVisible = useCallback((index: number) => {
    setAttrList((prev) => prev.map((a, i) => (i === index ? { ...a, visible: !a.visible } : a)));
  }, []);

  const toggleVariation = useCallback((index: number) => {
    setAttrList((prev) => prev.map((a, i) => (i === index ? { ...a, variation: !a.variation } : a)));
  }, []);

  const setAttrOptions = useCallback((index: number, value: string) => {
    const values = value.split("|").map((s) => s.trim()).filter(Boolean);
    setAttrList((prev) => prev.map((a, i) => (i === index ? { ...a, options: values } : a)));
  }, []);

  const maybeChangeType = useCallback((newType: string) => {
    if (form.data.type === "variable" && newType !== "variable" && variations.length > 0) {
      setPendingType(newType);
      setShowTypeWarning(true);
    } else if (form.data.type === "grouped" && newType !== "grouped" && groupedItems.length > 0) {
      setPendingType(newType);
      setShowTypeWarning(true);
    } else {
      form.setData("type", newType);
      if (newType !== "variable") {
        setAttrList((prev) => prev.map((a) => ({ ...a, variation: false })));
      } else {
        setAttrList((prev) => prev.map((a) => ({ ...a, variation: true })));
      }
      if (newType === "external") {
        form.setData("track_stock", false);
        form.setData("virtual", true);
      }
      if (newType === "grouped") {
        form.setData("track_stock", false);
      }
      if (newType === "digital") {
        form.setData("virtual", true);
        form.setData("downloadable", true);
      }
      if (newType === "service") {
        form.setData("virtual", true);
        form.setData("downloadable", false);
      }
    }
  }, [form.data.type, variations.length, groupedItems.length]);

  const availableGlobalAttrs = useMemo(
    () => attributes.filter((a) => !usedGlobalIds.has(a.id)),
    [attributes, usedGlobalIds]
  );

  function SortableAttrItem({ attr, index }: { attr: ProductAttributeItem; index: number }) {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
      id: attr.tempId || attr.id || index,
    });
    const style = {
      transform: CSS.Transform.toString(transform),
      transition,
      opacity: isDragging ? 0.5 : undefined,
    };
    const isGlobal = attr.attribute_id != null;
    const termDisplay = isGlobal
      ? globalAttrMap.get(attr.attribute_id!)?.terms.map((t) => t.name).join(", ") || ""
      : attr.options?.join(", ") || "";
    return (
      <div ref={setNodeRef} style={style} className="flex items-start gap-2 rounded-lg border p-2.5">
        <button type="button" {...attributes} {...listeners} className="h-7 w-7 rounded flex items-center justify-center text-muted-foreground hover:text-foreground cursor-grab active:cursor-grabbing shrink-0 mt-0.5">
          <GripVertical className="h-3.5 w-3.5" />
        </button>
        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-2">
            <span className="text-sm font-medium truncate">{attr.name}</span>
            {isGlobal && <span className="text-[10px] text-muted-foreground bg-muted px-1.5 py-0.5 rounded">Global</span>}
          </div>
          {termDisplay ? (
            <div className="flex flex-wrap gap-1 mt-1">
              {(isGlobal
                ? globalAttrMap.get(attr.attribute_id!)?.terms || []
                : (attr.options || []).map((o) => ({ id: o, name: o }))
              ).map((term) => (
                <span key={term.id} className="inline-flex items-center text-xs bg-secondary text-secondary-foreground px-1.5 py-0.5 rounded">{term.name}</span>
              ))}
            </div>
          ) : (
            <div className="mt-1">
              <Input
                placeholder="Enter pipe-separated values: S | M | L"
                defaultValue={attr.options?.join(" | ") || ""}
                onChange={(e) => setAttrOptions(index, e.target.value)}
                className="h-7 text-xs"
              />
            </div>
          )}
          {isGlobal && (
            <div className="mt-1">
              <Input
                placeholder="Override values (pipe-separated)"
                defaultValue={attr.options?.join(" | ") || ""}
                onChange={(e) => setAttrOptions(index, e.target.value)}
                className="h-7 text-xs"
              />
            </div>
          )}
          {attr.variation && (
            <div className="mt-2 flex items-center gap-2">
              <span className="text-[10px] text-muted-foreground font-medium">Default:</span>
              {isGlobal ? (
                <select
                  className="h-6 text-[11px] rounded border border-border bg-background px-1.5 max-w-[160px]"
                  value={(() => {
                    const defs = form.data.default_attributes || [];
                    const attrTerms = globalAttrMap.get(attr.attribute_id!)?.terms || [];
                    const matched = defs.find((d) => attrTerms.some((t) => t.id === d));
                    return matched ?? "";
                  })()}
                  onChange={(e) => {
                    const val = e.target.value;
                    const defs = [...(form.data.default_attributes || [])];
                    const attrTerms = globalAttrMap.get(attr.attribute_id!)?.terms || [];
                    const existingDefaultIndex = defs.findIndex((d) => attrTerms.some((t) => t.id === d));
                    if (existingDefaultIndex >= 0) defs.splice(existingDefaultIndex, 1);
                    if (val) defs.push(parseInt(val));
                    form.setData("default_attributes", defs);
                  }}
                >
                  <option value="">No default</option>
                  {(globalAttrMap.get(attr.attribute_id!)?.terms || []).map((t) => (
                    <option key={t.id} value={t.id}>{t.name}</option>
                  ))}
                </select>
              ) : (
                <span className="text-[10px] text-muted-foreground italic">Not available for custom attributes</span>
              )}
            </div>
          )}
        </div>
        <div className="flex items-center gap-1 shrink-0 pt-0.5">
          <button type="button" onClick={() => toggleVisible(index)} className={`h-7 w-7 rounded flex items-center justify-center transition-colors ${attr.visible ? "text-primary hover:text-primary/80" : "text-muted-foreground hover:text-foreground"}`} title={attr.visible ? "Visible on product page" : "Hidden on product page"}>
            {attr.visible ? <Eye className="h-3.5 w-3.5" /> : <EyeOff className="h-3.5 w-3.5" />}
          </button>
          {form.data.type === "variable" ? (
            <button type="button" onClick={() => toggleVariation(index)} className={`h-7 w-7 rounded flex items-center justify-center transition-colors ${attr.variation ? "bg-primary/10 text-primary" : "text-muted-foreground hover:text-foreground"}`} title={attr.variation ? "Used for variations" : "Not used for variations"}>
              <Layers className="h-3.5 w-3.5" />
            </button>
          ) : (
            <Tooltip>
              <TooltipTrigger asChild>
                <span className="h-7 w-7 rounded flex items-center justify-center text-muted-foreground/30 cursor-not-allowed" title="Switch to Variable type to use variations">
                  <Layers className="h-3.5 w-3.5" />
                </span>
              </TooltipTrigger>
              <TooltipContent>
                <p>Switch to Variable product type to use variations.</p>
              </TooltipContent>
            </Tooltip>
          )}
          <button type="button" onClick={() => removeAttribute(index)} className="h-7 w-7 rounded flex items-center justify-center text-destructive hover:bg-destructive/10 transition-colors" title="Remove attribute">
            <X className="h-3.5 w-3.5" />
          </button>
        </div>
      </div>
    );
  }

  return (
    <AuthenticatedLayout header={isEditing ? "Edit Product" : "Add Product"}>
      <Head title={isEditing ? "Edit Product" : "Add Product"} />
      <div className="container mx-auto py-6 space-y-6">
        <Breadcrumb>
          <BreadcrumbList>
            <BreadcrumbItem>
              <BreadcrumbLink href={route("dashboard")}>Dashboard</BreadcrumbLink>
            </BreadcrumbItem>
            <BreadcrumbSeparator />
            <BreadcrumbItem>
              <BreadcrumbLink href={route("dashboard.shop.products.index")}>Products</BreadcrumbLink>
            </BreadcrumbItem>
            <BreadcrumbSeparator />
            <BreadcrumbItem>
              <BreadcrumbPage>{isEditing ? "Edit" : "Add New"}</BreadcrumbPage>
            </BreadcrumbItem>
          </BreadcrumbList>
        </Breadcrumb>

        <div className="flex items-center justify-between">
          <div className="flex items-center gap-4">
            <Button variant="ghost" size="icon" onClick={() => router.visit(route("dashboard.shop.products.index"))}>
              <ArrowLeft className="h-5 w-5" />
            </Button>
            <div>
              <h1 className="text-3xl font-bold">{isEditing ? "Edit Product" : "Add New Product"}</h1>
              <p className="text-muted-foreground">{isEditing ? "Update product details" : "Create a new product"}</p>
            </div>
          </div>
        </div>

        <form onSubmit={handleSubmit} className="space-y-6">
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div className="lg:col-span-2 space-y-6">
              <Section title="General" description="Product name and description" icon={Info} columns={2}>
                <SectionField label="Product Name" htmlFor="name" error={form.errors.name} required className="md:col-span-2">
                  <Input placeholder="Enter product name" value={form.data.name} onChange={(e) => form.setData("name", e.target.value)} />
                </SectionField>
                <SectionField label="Description" htmlFor="description" className="md:col-span-2">
                  <Textarea placeholder="Enter product description" value={form.data.description} onChange={(e) => form.setData("description", e.target.value)} rows={6} />
                </SectionField>
                <SectionField label="Short Description" htmlFor="short_description" className="md:col-span-2">
                  <Textarea placeholder="Enter a brief summary" value={form.data.short_description} onChange={(e) => form.setData("short_description", e.target.value)} rows={3} />
                </SectionField>
              </Section>

              <Section title="Pricing" description="Set product pricing and sale scheduling" icon={Tag}>
                {form.data.type === "variable" ? (
                  <div className="rounded-lg border border-dashed border-muted-foreground/30 p-4 text-center text-sm text-muted-foreground">
                    <Layers className="mx-auto h-6 w-6 mb-2 opacity-50" />
                    Pricing is managed per variation below. Set individual prices for each variation combination.
                  </div>
                ) : form.data.type === "external" ? (
                  <div className="rounded-lg border border-dashed border-muted-foreground/30 p-4 text-center text-sm text-muted-foreground">
                    <ExternalLink className="mx-auto h-6 w-6 mb-2 opacity-50" />
                    Pricing is displayed for reference. Customers are redirected to the external URL to purchase.
                  </div>
                ) : form.data.type === "grouped" ? (
                  <div className="rounded-lg border border-dashed border-muted-foreground/30 p-4 text-center text-sm text-muted-foreground">
                    <ShoppingBag className="mx-auto h-6 w-6 mb-2 opacity-50" />
                    Pricing is determined by the individual products in this group.
                  </div>
                ) : (
                <div className="space-y-4">
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <SectionField label="Regular Price" htmlFor="price" error={form.errors.price}>
                      <Input id="price" type="number" step="0.01" min="0" placeholder="0.00" value={form.data.price} onChange={(e) => form.setData("price", e.target.value)} />
                    </SectionField>
                    <SectionField label="Sale Price" htmlFor="sale_price">
                      <Input id="sale_price" type="number" step="0.01" min="0" placeholder="0.00" value={form.data.sale_price} onChange={(e) => form.setData("sale_price", e.target.value)} />
                    </SectionField>
                    <SectionField label="Cost Price" htmlFor="cost_price" description="Purchase cost for margin calculation (not shown to customers)">
                      <Input id="cost_price" type="number" step="0.01" min="0" placeholder="0.00" value={form.data.cost_price} onChange={(e) => form.setData("cost_price", e.target.value)} />
                    </SectionField>
                  </div>

                  <div className="space-y-3">
                    <button type="button" onClick={() => setSaleScheduled(!saleScheduled)} className="flex items-center gap-2 text-left hover:text-primary transition-colors">
                      <Calendar className={`h-4 w-4 transition-transform ${saleScheduled ? "rotate-90" : ""}`} />
                      <span className="font-medium">Schedule Sale</span>
                      <Tooltip>
                        <TooltipTrigger asChild>
                          <HelpCircle className="h-4 w-4 text-muted-foreground ml-1 cursor-help" />
                        </TooltipTrigger>
                        <TooltipContent>
                          <p>The sale will start at 00:00:00 of 'From' date and end at 23:59:59 of 'To' date.</p>
                        </TooltipContent>
                      </Tooltip>
                      <span className="text-muted-foreground text-sm ml-auto">{saleScheduled ? "Click to hide" : "Click to show"}</span>
                    </button>
                    {saleScheduled && (
                      <div className="grid grid-cols-1 md:grid-cols-2 gap-4 pl-6 border-l-2 border-border">
                        <SectionField label="Sale start date" htmlFor="sale_from">
                          <DateTimePicker value={form.data.sale_from ?? undefined} onChange={(date: string) => form.setData("sale_from", date)} minDate={minDate} placeholder="Select start date" />
                        </SectionField>
                        <SectionField label="Sale end date" htmlFor="sale_to">
                          <DateTimePicker value={form.data.sale_to ?? undefined} onChange={(date: string) => form.setData("sale_to", date)} minDate={form.data.sale_from ? new Date(form.data.sale_from) : minDate} placeholder="Select end date" />
                        </SectionField>
                      </div>
                    )}
                  </div>

                  <div className="flex items-center gap-6 pt-2">
                    <label className="flex items-center gap-2">
                      <Switch checked={form.data.is_taxable} onCheckedChange={(v) => form.setData("is_taxable", v)} />
                      <span className="text-sm">Taxable</span>
                    </label>
                    {form.data.is_taxable && (
                      <div className="grid gap-2">
                        <Label htmlFor="tax_class" className="text-xs">Tax Class</Label>
                        <Input id="tax_class" value={form.data.tax_class} onChange={(e) => form.setData("tax_class", e.target.value)} className="w-40" />
                      </div>
                    )}
                  </div>
                </div>
                )}
              </Section>

              {form.data.type === "external" && (
                <Section title="External Link" description="Link to external/affiliate product" icon={Globe}>
                  <SectionField label="Product URL" htmlFor="external_url" error={form.errors.external_url} required className="md:col-span-2">
                    <Input id="external_url" placeholder="https://example.com/product" value={form.data.external_url} onChange={(e) => form.setData("external_url", e.target.value)} />
                  </SectionField>
                  <SectionField label="Button Text" htmlFor="button_text" description='Default: "Buy product"'>
                    <Input id="button_text" placeholder="Buy now" value={form.data.button_text} onChange={(e) => form.setData("button_text", e.target.value)} />
                  </SectionField>
                </Section>
              )}

              <Section title="Product Gallery" description="Add product images" icon={Images}>
                <GalleryUploader
                  label=""
                  description="Add product images"
                  value={form.data.images}
                  onChange={(images) => form.setData("images", images)}
                  thumbnailUrl={form.data.thumbnail_url}
                  onThumbnailChange={(url) => form.setData("thumbnail_url", url)}
                  maxImages={10}
                  onUpload={handleUpload}
                  onDelete={handleDelete}
                />
              </Section>

              <Section title="Categories" description="Organize products by category" icon={FolderTree}>
                <TermsSelector
                  label=""
                  options={categories}
                  value={form.data.categories}
                  onChange={(values) => form.setData("categories", values as number[])}
                  canAdd={true}
                  onCreateTerm={handleCreateCategory}
                  placeholder="Select categories..."
                  taxonomyName="Category"
                />
              </Section>

              <Section title="Brands" description="Add product brands" icon={Copyright}>
                <TermsSelector
                  label=""
                  options={brands}
                  value={form.data.brands}
                  onChange={(values) => form.setData("brands", values as number[])}
                  canAdd={true}
                  onCreateTerm={handleCreateBrand}
                  placeholder="Select brands..."
                  taxonomyName="Brand"
                />
              </Section>

              <Section title="Tags" description="Add product tags" icon={Tags}>
                <TermsSelector
                  label=""
                  options={tags}
                  value={form.data.tags}
                  onChange={(values) => form.setData("tags", values as number[])}
                  canAdd={true}
                  onCreateTerm={handleCreateTag}
                  placeholder="Select tags..."
                  taxonomyName="Tag"
                />
              </Section>

              <Section title="Attributes" description={form.data.type === "variable" ? 'Mark attributes as "Variation" to generate product variations with different prices, stock, and SKUs.' : "Attributes help describe your product. Switch to Variable type to create variations."} icon={ListTree}>
                <div className="flex items-center gap-2 pb-2">
                  {attributes.length === 0 ? (
                    <div className="flex items-center gap-2">
                      <span className="inline-flex items-center gap-1 px-3 h-8 rounded-md border border-border text-xs text-muted-foreground cursor-not-allowed bg-muted/30">
                        <Plus className="h-3 w-3" />
                        Add existing
                      </span>
                      <p className="text-xs text-muted-foreground">
                        No attributes yet.{" "}
                        <Link href={route("dashboard.shop.attributes.index")} className="underline underline-offset-2 hover:text-foreground">
                          Create one
                        </Link>
                      </p>
                    </div>
                  ) : availableGlobalAttrs.length === 0 ? (
                    <div className="flex items-center gap-2">
                      <span className="inline-flex items-center gap-1 px-3 h-8 rounded-md border border-border text-xs text-muted-foreground cursor-not-allowed bg-muted/30">
                        <Plus className="h-3 w-3" />
                        All added
                      </span>
                      <p className="text-xs text-muted-foreground">All global attributes are assigned to this product.</p>
                    </div>
                  ) : (
                    <Select onValueChange={(v) => { const id = parseInt(v); if (id) addGlobalAttribute(id); }}>
                      <SelectTrigger className="h-8 w-auto text-xs gap-1">
                        <Plus className="h-3 w-3" />
                        <SelectValue placeholder="Add existing" />
                      </SelectTrigger>
                      <SelectContent>
                        {availableGlobalAttrs.map((attr) => (
                          <SelectItem key={attr.id} value={attr.id.toString()}>{attr.name}</SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  )}
                  <Button type="button" variant="outline" size="sm" className="h-8 text-xs" onClick={() => { setShowCustomInput(true); setTimeout(() => customNameRef.current?.focus(), 50); }}>
                    <Plus className="h-3 w-3 mr-1" /> Custom
                  </Button>
                </div>

                {showCustomInput && (
                  <div className="rounded-lg border bg-muted/30 p-3 space-y-2">
                    <div className="grid grid-cols-2 gap-2">
                      <div className="grid gap-1">
                        <Label className="text-xs">Attribute Name</Label>
                        <Input ref={customNameRef} placeholder="e.g. Size, Color" value={customAttrName} onChange={(e) => setCustomAttrName(e.target.value)} onKeyDown={(e) => { if (e.key === "Enter") { e.preventDefault(); addCustomAttribute(); } }} />
                      </div>
                      <div className="grid gap-1">
                        <Label className="text-xs">Values (pipe-separated)</Label>
                        <Input placeholder="S | M | L" value={customAttrValues} onChange={(e) => setCustomAttrValues(e.target.value)} onKeyDown={(e) => { if (e.key === "Enter") { e.preventDefault(); addCustomAttribute(); } }} />
                      </div>
                    </div>
                    <div className="flex justify-end gap-2">
                      <Button type="button" variant="ghost" size="sm" className="h-7 text-xs" onClick={() => { setShowCustomInput(false); setCustomAttrName(""); setCustomAttrValues(""); }}>Cancel</Button>
                      <Button type="button" size="sm" className="h-7 text-xs" onClick={addCustomAttribute}>Add Attribute</Button>
                    </div>
                  </div>
                )}

                {attrList.length === 0 && !showCustomInput && (
                  <p className="text-sm text-muted-foreground text-center py-4">No attributes yet. Add an existing global attribute or create a custom one.</p>
                )}

                <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleAttrDragEnd}>
                  <SortableContext items={attrList.map((a, i) => a.tempId || a.id || i)} strategy={verticalListSortingStrategy}>
                    <div className="space-y-2">
                      {attrList.map((attr, index) => (
                        <SortableAttrItem key={attr.tempId || attr.id || index} attr={attr} index={index} />
                      ))}
                    </div>
                  </SortableContext>
                </DndContext>
              </Section>

              <Section title="Inventory" description="Manage stock and identifiers" icon={Package}>
                {form.data.type === "variable" || form.data.type === "grouped" || form.data.type === "external" ? (
                  <div className="rounded-lg border border-dashed border-muted-foreground/30 p-4 text-center text-sm text-muted-foreground">
                    <Layers className="mx-auto h-6 w-6 mb-2 opacity-50" />
                    {form.data.type === "variable" ? "SKU, stock, and inventory are managed per variation below. Each variation can have its own SKU and stock tracking." :
                     form.data.type === "grouped" ? "Stock and pricing are managed by the individual products in this group." :
                     "External/affiliate products are managed by the external provider."}
                  </div>
                ) : (
                <div className="space-y-4">
                  <SectionField label="SKU" htmlFor="sku_inv" description="Stock-Keeping Unit, a unique identifier for each distinct product">
                    <Input id="sku_inv" placeholder="Enter SKU" value={form.data.sku} onChange={(e) => form.setData("sku", e.target.value)} />
                  </SectionField>
                  <SectionField label="Barcode" htmlFor="barcode" description="GTIN, UPC, EAN, or ISBN">
                    <Input id="barcode" placeholder="Enter barcode" value={form.data.barcode} onChange={(e) => form.setData("barcode", e.target.value)} />
                  </SectionField>
                  <SectionField label="Global ID" htmlFor="global_unique_id" description="Unique global identifier (GTIN/UPC/EAN/ISBN)">
                    <Input id="global_unique_id" placeholder="e.g. 9780262510875" value={form.data.global_unique_id} onChange={(e) => form.setData("global_unique_id", e.target.value)} />
                  </SectionField>
                  <SectionField label="Vendor" htmlFor="vendor" description="Brand/Supplier name (free text)">
                    <Input id="vendor" placeholder="e.g. Acme Corp" value={form.data.vendor} onChange={(e) => form.setData("vendor", e.target.value)} />
                  </SectionField>
                  <div className="flex items-center justify-between pt-2">
                    <Label>Manage Stock</Label>
                    <Switch checked={form.data.track_stock} onCheckedChange={(v) => form.setData("track_stock", v)} />
                  </div>
                  {form.data.track_stock ? (
                    <div className="space-y-4 pl-6 border-l-2 border-border">
                      <div className="grid grid-cols-2 gap-4">
                        <div className="grid gap-2">
                          <Label className="text-muted-foreground text-xs">Quantity</Label>
                          <Input type="number" min="0" placeholder="0" value={form.data.stock_quantity} onChange={(e) => form.setData("stock_quantity", e.target.value)} />
                        </div>
                        <div className="grid gap-2">
                          <Label className="text-muted-foreground text-xs">Low stock threshold</Label>
                          <div className="flex items-center gap-2">
                            <Input type="number" min="0" placeholder="5" value={form.data.low_stock_threshold} onChange={(e) => form.setData("low_stock_threshold", e.target.value)} className="flex-1" />
                            <Tooltip>
                              <TooltipTrigger asChild>
                                <HelpCircle className="h-4 w-4 text-muted-foreground cursor-help shrink-0" />
                              </TooltipTrigger>
                              <TooltipContent>
                                <p>Notify when stock reaches this threshold</p>
                              </TooltipContent>
                            </Tooltip>
                          </div>
                        </div>
                      </div>
                      <div className="grid gap-2">
                        <Label className="text-sm">Backorders</Label>
                        <Select value={form.data.backorders} onValueChange={(v) => form.setData("backorders", v)}>
                          <SelectTrigger>
                            <SelectValue />
                          </SelectTrigger>
                          <SelectContent>
                            <SelectItem value="no">No backorders</SelectItem>
                            <SelectItem value="notify">Allow, but notify customer</SelectItem>
                            <SelectItem value="yes">Allow</SelectItem>
                          </SelectContent>
                        </Select>
                      </div>

                      <div className="grid gap-2">
                        <Label className="text-sm">Incoming stock</Label>
                        <Input type="number" min="0" placeholder="0" value={form.data.incoming_stock} onChange={(e) => form.setData("incoming_stock", e.target.value)} />
                        {parseInt(form.data.incoming_stock || '0') > 0 && (
                          <p className="text-xs text-amber-600 dark:text-amber-400">{form.data.incoming_stock} units expected</p>
                        )}
                      </div>

                      <div className="grid gap-2">
                        <Label className="text-sm text-muted-foreground">Hold stock (minutes)</Label>
                        <Input type="number" min="0" placeholder="Use default" value={form.data.hold_stock_minutes} onChange={(e) => form.setData("hold_stock_minutes", e.target.value)} />
                      </div>
                    </div>
                  ) : (
                    <div className="grid gap-2">
                      <Label htmlFor="stock_status">Stock Status</Label>
                      <Select
                        value={form.data.stock_status || (form.data.stock_quantity && Number(form.data.stock_quantity) > 0 ? "instock" : "outofstock")}
                        onValueChange={(v) => {
                          form.setData("stock_status", v);
                          if (v === "instock") form.setData("stock_quantity", "10");
                          else form.setData("stock_quantity", "0");
                        }}
                      >
                        <SelectTrigger id="stock_status">
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="instock">In stock</SelectItem>
                          <SelectItem value="outofstock">Out of stock</SelectItem>
                          <SelectItem value="onbackorder">On backorder</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>
                  )}

                  <div className="border-t pt-3 mt-3">
                    <p className="text-sm font-medium mb-2">Quantity Rules</p>
                    <div className="grid grid-cols-2 gap-4">
                      <label className="flex items-center gap-2">
                        <Switch checked={form.data.sold_individually} onCheckedChange={(v) => form.setData("sold_individually", v)} />
                        <span className="text-sm">Sold individually</span>
                      </label>
                    </div>
                    {!form.data.sold_individually && (
                      <div className="grid grid-cols-2 gap-4 mt-2">
                        <div className="grid gap-1">
                          <Label className="text-xs text-muted-foreground">Min quantity</Label>
                          <Input type="number" min="0" placeholder="0" value={form.data.min_quantity} onChange={(e) => form.setData("min_quantity", e.target.value)} />
                        </div>
                        <div className="grid gap-1">
                          <Label className="text-xs text-muted-foreground">Max quantity</Label>
                          <Input type="number" min="0" placeholder="0 (unlimited)" value={form.data.max_quantity} onChange={(e) => form.setData("max_quantity", e.target.value)} />
                        </div>
                      </div>
                    )}
                  </div>

                  <div className="border-t pt-3">
                    <SectionField label="Menu Order" htmlFor="menu_order" description="Custom sort order (lower first)">
                      <Input id="menu_order" type="number" min="0" placeholder="0" value={form.data.menu_order} onChange={(e) => form.setData("menu_order", e.target.value)} />
                    </SectionField>
                  </div>
                </div>
                )}
              </Section>

              {!form.data.virtual && (
              <Section title="Shipping" description="Physical dimensions for shipping calculations" icon={Truck}>
                <div className="grid grid-cols-4 gap-4">
                  <SectionField label="Weight" htmlFor="weight">
                    <Input id="weight" type="number" step="0.01" min="0" value={form.data.weight} onChange={(e) => form.setData("weight", e.target.value)} />
                  </SectionField>
                  <SectionField label="Length" htmlFor="length">
                    <Input id="length" type="number" step="0.01" min="0" value={form.data.length} onChange={(e) => form.setData("length", e.target.value)} />
                  </SectionField>
                  <SectionField label="Width" htmlFor="width">
                    <Input id="width" type="number" step="0.01" min="0" value={form.data.width} onChange={(e) => form.setData("width", e.target.value)} />
                  </SectionField>
                  <SectionField label="Height" htmlFor="height">
                    <Input id="height" type="number" step="0.01" min="0" value={form.data.height} onChange={(e) => form.setData("height", e.target.value)} />
                  </SectionField>
                </div>
              </Section>
              )}

              <Section title="Product Type" description="Choose how this product is sold" icon={Archive}>
                <div className="grid grid-cols-2 gap-2">
                  <Button type="button" variant={form.data.type === "simple" ? "default" : "outline"} className="flex items-center gap-2" onClick={() => maybeChangeType("simple")}>
                    <Archive className="h-4 w-4" /> Simple
                  </Button>
                  <Button type="button" variant={form.data.type === "variable" ? "default" : "outline"} className="flex items-center gap-2" onClick={() => maybeChangeType("variable")}>
                    <Layers className="h-4 w-4" /> Variable
                  </Button>
                  <Button type="button" variant={form.data.type === "grouped" ? "default" : "outline"} className="flex items-center gap-2" onClick={() => maybeChangeType("grouped")}>
                    <ShoppingBag className="h-4 w-4" /> Grouped
                  </Button>
                  <Button type="button" variant={form.data.type === "external" ? "default" : "outline"} className="flex items-center gap-2" onClick={() => maybeChangeType("external")}>
                    <ExternalLink className="h-4 w-4" /> External
                  </Button>
                  <Button type="button" variant={form.data.type === "digital" ? "default" : "outline"} className="flex items-center gap-2" onClick={() => maybeChangeType("digital")}>
                    <Monitor className="h-4 w-4" /> Digital
                  </Button>
                  <Button type="button" variant={form.data.type === "service" ? "default" : "outline"} className="flex items-center gap-2" onClick={() => maybeChangeType("service")}>
                    <Wrench className="h-4 w-4" /> Service
                  </Button>
                </div>
                <div className="flex items-center gap-4 pt-3">
                  <label className="flex items-center gap-2">
                    <Switch checked={form.data.virtual} onCheckedChange={(v) => form.setData("virtual", v)} disabled={form.data.type === "variable" || form.data.type === "external"} />
                    <span className="text-sm">Virtual</span>
                  </label>
                  <label className="flex items-center gap-2">
                    <Switch checked={form.data.downloadable} onCheckedChange={(v) => { form.setData("downloadable", v); if (v) form.setData("virtual", true); }} disabled={form.data.type === "variable" || form.data.type === "external" || form.data.type === "grouped"} />
                    <span className="text-sm">Downloadable</span>
                  </label>
                </div>
              </Section>

              {form.data.type === "grouped" && (
                <Section title="Grouped Products" description="Select products to include in this group" icon={ShoppingBag}>
                  <div className="space-y-3">
                    <div className="flex gap-2">
                      <div className="relative flex-1">
                        <Input
                          placeholder="Search products..."
                          value={searchProductTerm}
                          onChange={(e) => setSearchProductTerm(e.target.value)}
                          onFocus={() => setShowProductPicker(true)}
                        />
                        {showProductPicker && (
                          <div className="absolute z-50 mt-1 w-full rounded-md border bg-popover shadow-md max-h-48 overflow-y-auto">
                            {allProducts
                              .filter((p) => p.id !== product?.id && (form.data.type !== "grouped" || !groupedItems.some((gi) => gi.product_id === p.id)))
                              .filter((p) => !searchProductTerm || p.name.toLowerCase().includes(searchProductTerm.toLowerCase()) || (p.sku && p.sku.toLowerCase().includes(searchProductTerm.toLowerCase())))
                              .slice(0, 10)
                              .map((p) => (
                                <button
                                  key={p.id}
                                  type="button"
                                  className="w-full px-3 py-2 text-left text-sm hover:bg-accent transition-colors flex items-center justify-between"
                                  onClick={() => {
                                    if (!groupedItems.some((gi) => gi.product_id === p.id)) {
                                      setGroupedItems((prev) => [...prev, { product_id: p.id, quantity: 1, sort_order: prev.length, name: p.name, sku: p.sku || "", price: p.price || "" }]);
                                    }
                                    setSearchProductTerm("");
                                    setShowProductPicker(false);
                                  }}
                                >
                                  <span className="font-medium">{p.name}</span>
                                  <span className="text-xs text-muted-foreground">{p.sku || p.price ? `${p.sku || ""}${p.sku && p.price ? " - " : ""}${p.price ? `$${p.price}` : ""}` : ""}</span>
                                </button>
                              ))}
                            {allProducts.filter((p) => !searchProductTerm || p.name.toLowerCase().includes(searchProductTerm.toLowerCase())).length === 0 && (
                              <div className="px-3 py-2 text-sm text-muted-foreground">No products found</div>
                            )}
                          </div>
                        )}
                      </div>
                    </div>
                    {groupedItems.length === 0 ? (
                      <p className="text-sm text-muted-foreground text-center py-4">No products in this group yet. Search and add products above.</p>
                    ) : (
                      <div className="space-y-2">
                        {groupedItems.map((item, idx) => (
                          <div key={item.product_id} className="flex items-center gap-2 rounded-lg border p-2.5">
                            <GripVertical className="h-4 w-4 text-muted-foreground shrink-0" />
                            <div className="flex-1 min-w-0">
                              <p className="text-sm font-medium truncate">{item.name || `Product #${item.product_id}`}</p>
                              {item.sku && <p className="text-xs text-muted-foreground">{item.sku}</p>}
                            </div>
                            <div className="flex items-center gap-2">
                              <Label className="text-xs">Qty</Label>
                              <Input
                                type="number"
                                min="1"
                                className="h-7 w-16"
                                value={item.quantity}
                                onChange={(e) => {
                                  const qty = parseInt(e.target.value) || 1;
                                  setGroupedItems((prev) => prev.map((gi, i) => (i === idx ? { ...gi, quantity: qty } : gi)));
                                }}
                              />
                            </div>
                            <button type="button" onClick={() => setGroupedItems((prev) => prev.filter((_, i) => i !== idx))} className="h-7 w-7 rounded flex items-center justify-center text-destructive hover:bg-destructive/10 transition-colors">
                              <X className="h-3.5 w-3.5" />
                            </button>
                          </div>
                        ))}
                      </div>
                    )}
                  </div>
                </Section>
              )}

              {form.data.downloadable && (
                <Section title="Downloadable Files" description="Manage downloadable files for this product" icon={Download}>
                  <div className="space-y-3">
                    {downloads.map((d, idx) => (
                      <div key={d.id || idx} className="rounded-lg border p-3 space-y-2">
                        <div className="flex items-center gap-2">
                          <div className="flex-1 grid gap-1">
                            <Label className="text-xs">File Name</Label>
                            <Input
                              value={d.name}
                              onChange={(e) => setDownloads((prev) => prev.map((di, i) => (i === idx ? { ...di, name: e.target.value } : di)))}
                              placeholder="e.g. Product Manual PDF"
                            />
                          </div>
                          <div className="flex-1 grid gap-1">
                            <Label className="text-xs">File Path / URL</Label>
                            <Input
                              value={d.file}
                              onChange={(e) => setDownloads((prev) => prev.map((di, i) => (i === idx ? { ...di, file: e.target.value } : di)))}
                              placeholder="/storage/products/manual.pdf"
                            />
                          </div>
                        </div>
                        <div className="flex items-center gap-4">
                          <div className="grid gap-1">
                            <Label className="text-xs">Download Limit</Label>
                            <Input
                              type="number"
                              min="-1"
                              className="h-7 w-24"
                              value={d.download_limit}
                              onChange={(e) => setDownloads((prev) => prev.map((di, i) => (i === idx ? { ...di, download_limit: parseInt(e.target.value) || -1 } : di)))}
                            />
                          </div>
                          <div className="grid gap-1">
                            <Label className="text-xs">Expiry (days)</Label>
                            <Input
                              type="number"
                              min="0"
                              className="h-7 w-24"
                              value={d.download_expiry}
                              onChange={(e) => setDownloads((prev) => prev.map((di, i) => (i === idx ? { ...di, download_expiry: parseInt(e.target.value) || 0 } : di)))}
                            />
                          </div>
                          <button type="button" onClick={() => setDownloads((prev) => prev.filter((_, i) => i !== idx))} className="h-7 w-7 rounded flex items-center justify-center text-destructive hover:bg-destructive/10 transition-colors mt-5">
                            <X className="h-3.5 w-3.5" />
                          </button>
                        </div>
                      </div>
                    ))}
                    <Button type="button" variant="outline" size="sm" onClick={() => setDownloads((prev) => [...prev, { name: "", file: "", download_limit: -1, download_expiry: 0, sort_order: prev.length }])}>
                      <Plus className="h-3.5 w-3.5 mr-1" /> Add File
                    </Button>
                  </div>
                </Section>
              )}

              {form.data.type === "variable" && (
                <Suspense fallback={<div className="py-8 text-center text-muted-foreground text-sm">Loading variations...</div>}>
                  <VariationsManager
                  productId={product?.id ?? 0}
                  attributes={attributes}
                  initialVariations={variations}
                  productAttributes={attrList}
                  isCreating={!product}
                  attrList={attrList}
                  usedGlobalIds={usedGlobalIds}
                  availableGlobalAttrs={availableGlobalAttrs}
                  addGlobalAttribute={addGlobalAttribute}
                  addCustomAttribute={addCustomAttribute}
                  removeAttribute={removeAttribute}
                  toggleVisible={toggleVisible}
                  toggleVariation={toggleVariation}
                  setAttrOptions={setAttrOptions}
                  defaultAttributes={form.data.default_attributes}
                  setDefaultAttributes={(ids: number[]) => form.setData("default_attributes", ids)}
                  globalAttrMap={globalAttrMap}
                  formType={form.data.type}
                  showCustomInput={showCustomInput}
                  setShowCustomInput={setShowCustomInput}
                  customAttrName={customAttrName}
                  setCustomAttrName={setCustomAttrName}
                  customAttrValues={customAttrValues}
                  setCustomAttrValues={setCustomAttrValues}
                  reorderAttributes={(reordered: ProductAttributeItem[]) => setAttrList(reordered)}
                />
                </Suspense>
              )}
            </div>

            <div className="space-y-6">
              <PublishSection
                status={form.data.status}
                publishDate={form.data.publish_date}
                onStatusChange={(s) => form.setData("status", s)}
                onDateChange={(d) => form.setData("publish_date", d)}
                disabled={form.processing}
                onSubmit={() => handleSubmit({ preventDefault: () => {} } as React.FormEvent)}
                loading={form.processing}
                cancelUrl={route("dashboard.shop.products.index")}
              />

              <Section title="Catalog Visibility" description="Control where this product appears" icon={VisibilityIcon}>
                <Select value={form.data.catalog_visibility} onValueChange={(v) => form.setData("catalog_visibility", v)}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="visible">Visible everywhere</SelectItem>
                    <SelectItem value="catalog">Catalog only</SelectItem>
                    <SelectItem value="search">Search only</SelectItem>
                    <SelectItem value="hidden">Hidden</SelectItem>
                  </SelectContent>
                </Select>
              </Section>

              <Section title="Featured Product" description="Highlight this product across the store" icon={Star}>
                <div className="flex items-center justify-between">
                  <Label>Show as featured</Label>
                  <Switch checked={form.data.is_featured} onCheckedChange={(v) => form.setData("is_featured", v)} />
                </div>
              </Section>

              <Section title="SEO" description="Search engine optimization" icon={Search}>
                <div className="space-y-4">
                  <SectionField label="Meta Title" htmlFor="meta_title" description={`${(form.data.meta_title || '').length}/70 characters`}>
                    <Input id="meta_title" maxLength={70} placeholder="SEO title (leave blank to use product name)" value={form.data.meta_title} onChange={(e) => form.setData("meta_title", e.target.value)} />
                  </SectionField>
                  <SectionField label="Meta Description" htmlFor="meta_description" description={`${(form.data.meta_description || '').length}/160 characters`}>
                    <textarea id="meta_description" maxLength={160} rows={3} placeholder="Brief description for search results" value={form.data.meta_description} onChange={(e) => form.setData("meta_description", e.target.value)} className="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm" />
                  </SectionField>
                </div>
              </Section>
            </div>
          </div>
        </form>

        <AlertDialog open={showTypeWarning} onOpenChange={setShowTypeWarning}>
          <AlertDialogContent>
            <AlertDialogHeader>
              <AlertDialogTitle className="flex items-center gap-2">
                <AlertTriangle className="h-5 w-5 text-destructive" />
                Switch Product Type?
              </AlertDialogTitle>
              <AlertDialogDescription>
                {form.data.type === "variable" ? (
                  `Switching to ${pendingType || "a different"} product type will remove all current variations (${variations.length} total). This action cannot be undone.`
                ) : form.data.type === "grouped" ? (
                  `Switching to ${pendingType || "a different"} product type will remove all current grouped items (${groupedItems.length} total). This action cannot be undone.`
                ) : (
                  "Changing the product type may affect how this product is displayed and sold."
                )}
              </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
              <AlertDialogCancel>Cancel</AlertDialogCancel>
              <AlertDialogAction onClick={() => {
                if (pendingType) {
                  form.setData("type", pendingType);
                  if (pendingType !== "variable") {
                    setAttrList((prev) => prev.map((a) => ({ ...a, variation: false })));
                  } else {
                    setAttrList((prev) => prev.map((a) => ({ ...a, variation: true })));
                  }
                  if (pendingType !== "grouped") setGroupedItems([]);
                  if (pendingType === "external") {
                    form.setData("track_stock", false);
                    form.setData("virtual", true);
                  }
                  if (pendingType === "grouped") form.setData("track_stock", false);
                }
                setShowTypeWarning(false);
                setPendingType(null);
              }}>
                Yes, switch to {pendingType || "new type"}
              </AlertDialogAction>
            </AlertDialogFooter>
          </AlertDialogContent>
        </AlertDialog>
      </div>
    </AuthenticatedLayout>
  );
}
