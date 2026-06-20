import * as React from "react";
import {
  Plus,
  Trash2,
  RefreshCw,
  Sparkles,
  GripVertical,
  ChevronDown,
  ChevronRight,
  Image,
  Edit,
  Layers,
  Calendar,
} from "lucide-react";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import { Switch } from "@/Components/ui/switch";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/Components/ui/select";
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from "@/Components/ui/collapsible";
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
} from "@/Components/ui/dialog";
import { Checkbox } from "@/Components/ui/checkbox";
import { toast } from "sonner";
import { cn } from "@/lib/utils";
import {
  DndContext,
  closestCenter,
  KeyboardSensor,
  PointerSensor,
  useSensor,
  useSensors,
  type UniqueIdentifier,
} from "@dnd-kit/core";
import {
  arrayMove,
  SortableContext,
  sortableKeyboardCoordinates,
  verticalListSortingStrategy,
  useSortable,
} from "@dnd-kit/sortable";
import { CSS } from "@dnd-kit/utilities";

interface ProductAttribute {
  name: string;
  variation: boolean;
  isGlobal: boolean;
  options?: string[];
}

interface ProductVariation {
  id?: number | string;
  regular_price?: string;
  sale_price?: string;
  sku?: string;
  stock_quantity?: string;
  stock_status?: string;
  manage_stock?: boolean;
  backorders?: string;
  weight?: string;
  length?: string;
  width?: string;
  height?: string;
  virtual?: boolean;
  downloadable?: boolean;
  attributes?: Record<string, string>;
  image_id?: number;
  image?: { id?: number; src?: string };
  image_url?: string;
  enabled?: boolean;
  low_stock_amount?: number;
  status?: string;
  description?: string;
  [key: string]: unknown;
}

function getAttributeKey(name: string, isGlobal = false): string {
  const normalizedName = name.toLowerCase();
  if (isGlobal) return `attribute_pa_${normalizedName}`;
  return `attribute_${normalizedName}`;
}

function getAttributeKeyDirect(name: string, isGlobal = false): string {
  if (isGlobal) return `attribute_pa_${name}`;
  return `attribute_${name}`;
}

function getWooCommerceAttributeKey(name: string, isGlobal: boolean): string {
  if (isGlobal) return `pa_${name}`;
  return name;
}

function parseAttributeKey(key: string): { prefix: string; name: string; isGlobal: boolean } {
  if (key.startsWith("attribute_pa_"))
    return { prefix: "attribute_pa_", name: key.replace("attribute_pa_", ""), isGlobal: true };
  if (key.startsWith("attribute_"))
    return { prefix: "attribute_", name: key.replace("attribute_", ""), isGlobal: false };
  if (key.startsWith("pa_")) return { prefix: "", name: key.replace("pa_", ""), isGlobal: true };
  return { prefix: "", name: key, isGlobal: false };
}

function generateVariationCombinations(attributes: ProductAttribute[]): Record<string, string>[] {
  if (!attributes || attributes.length === 0) return [];
  const variationAttrs = attributes.filter((attr) => attr.variation);
  if (variationAttrs.length === 0) return [];
  const combinations: Record<string, string>[] = [];
  function buildCombinations(index: number, current: Record<string, string>): void {
    if (index === variationAttrs.length) {
      combinations.push({ ...current });
      return;
    }
    const attr = variationAttrs[index];
    const options = attr?.options || [];
    if (options.length === 0) {
      buildCombinations(index + 1, current);
      return;
    }
    for (const option of options) {
      const key = getAttributeKey(attr?.name ?? "", attr?.isGlobal ?? false);
      buildCombinations(index + 1, { ...current, [key]: option });
    }
  }
  buildCombinations(0, {});
  return combinations;
}

interface BulkEditFormData {
  regular_price: string;
  sale_price: string;
  sku: string;
  stock_quantity: string;
  stock_status: string;
  manage_stock: boolean;
  enable_regular_price: boolean;
  enable_sale_price: boolean;
  enable_sku: boolean;
  enable_stock: boolean;
}

interface BulkEditDialogProps {
  isOpen: boolean;
  onClose: () => void;
  variations: ProductVariation[];
  onSave: (_updates: Record<string, unknown>) => void;
  disabled?: boolean;
}

function BulkEditDialog({ isOpen, onClose, variations, onSave, disabled }: BulkEditDialogProps) {
  const [formData, setFormData] = React.useState<BulkEditFormData>({
    regular_price: "",
    sale_price: "",
    sku: "",
    stock_quantity: "",
    stock_status: "instock",
    manage_stock: false,
    enable_regular_price: false,
    enable_sale_price: false,
    enable_sku: false,
    enable_stock: false,
  });

  const handleFieldChange = (field: string, value: string | boolean): void => {
    setFormData((prev) => ({ ...prev, [field]: value }) as BulkEditFormData);
  };

  const handleToggle = (field: string): void => {
    setFormData(
      (prev) => ({ ...prev, [field]: !prev[field as keyof BulkEditFormData] }) as BulkEditFormData,
    );
  };

  const handleSubmit = (): void => {
    const updates: Record<string, unknown> = {};
    if (formData.enable_regular_price && formData.regular_price) {
      updates.regular_price = formData.regular_price;
    }
    if (formData.enable_sale_price && formData.sale_price) {
      updates.sale_price = formData.sale_price;
    }
    if (formData.enable_sku && formData.sku) {
      updates.sku = formData.sku;
    }
    if (formData.enable_stock) {
      updates.manage_stock = formData.manage_stock;
      if (formData.manage_stock && formData.stock_quantity) {
        updates.stock_quantity = formData.stock_quantity;
      }
      updates.stock_status = formData.stock_status;
    }

    onSave(updates);
    setFormData({
      regular_price: "",
      sale_price: "",
      sku: "",
      stock_quantity: "",
      stock_status: "instock",
      manage_stock: false,
      enable_regular_price: false,
      enable_sale_price: false,
      enable_sku: false,
      enable_stock: false,
    } as BulkEditFormData);
    onClose();
  };

  return (
    <Dialog open={isOpen} onOpenChange={onClose}>
      <DialogContent className="max-w-lg">
        <DialogHeader>
          <DialogTitle>Bulk Edit Variations</DialogTitle>
          <DialogDescription>
            Apply changes to all {variations.length} variations at once. Use toggles to enable each
            field.
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4 py-4">
          <div className="space-y-3">
            <div className="flex items-center gap-2">
              <Checkbox
                id="enable_regular_price"
                checked={formData.enable_regular_price}
                onCheckedChange={() => handleToggle("enable_regular_price")}
                disabled={disabled}
              />
              <Label htmlFor="enable_regular_price" className="font-normal">
                Set Regular Price
              </Label>
            </div>
            {formData.enable_regular_price && (
              <div className="pl-6">
                <Input
                  type="number"
                  step="0.01"
                  min="0"
                  placeholder="0.00"
                  value={formData.regular_price}
                  onChange={(e) => handleFieldChange("regular_price", e.target.value)}
                  disabled={disabled}
                />
              </div>
            )}

            <div className="flex items-center gap-2">
              <Checkbox
                id="enable_sale_price"
                checked={formData.enable_sale_price}
                onCheckedChange={() => handleToggle("enable_sale_price")}
                disabled={disabled}
              />
              <Label htmlFor="enable_sale_price" className="font-normal">
                Set Sale Price
              </Label>
            </div>
            {formData.enable_sale_price && (
              <div className="pl-6">
                <Input
                  type="number"
                  step="0.01"
                  min="0"
                  placeholder="0.00"
                  value={formData.sale_price}
                  onChange={(e) => handleFieldChange("sale_price", e.target.value)}
                  disabled={disabled}
                />
              </div>
            )}

            <div className="flex items-center gap-2">
              <Checkbox
                id="enable_sku"
                checked={formData.enable_sku}
                onCheckedChange={() => handleToggle("enable_sku")}
                disabled={disabled}
              />
              <Label htmlFor="enable_sku" className="font-normal">
                Set SKU
              </Label>
            </div>
            {formData.enable_sku && (
              <div className="pl-6">
                <Input
                  placeholder="SKU"
                  value={formData.sku}
                  onChange={(e) => handleFieldChange("sku", e.target.value)}
                  disabled={disabled}
                />
              </div>
            )}

            <div className="flex items-center gap-2">
              <Checkbox
                id="enable_stock"
                checked={formData.enable_stock}
                onCheckedChange={() => handleToggle("enable_stock")}
                disabled={disabled}
              />
              <Label htmlFor="enable_stock" className="font-normal">
                Set Stock Options
              </Label>
            </div>
            {formData.enable_stock && (
              <div className="pl-6 space-y-2">
                <div className="flex items-center gap-2">
                  <Checkbox
                    id="manage_stock_bulk"
                    checked={formData.manage_stock}
                    onCheckedChange={(checked) => handleFieldChange("manage_stock", checked)}
                    disabled={disabled}
                  />
                  <Label htmlFor="manage_stock_bulk" className="text-sm font-normal">
                    Manage stock
                  </Label>
                </div>
                {formData.manage_stock && (
                  <div className="flex items-center gap-2">
                    <Input
                      type="number"
                      min="0"
                      placeholder="Qty"
                      value={formData.stock_quantity}
                      onChange={(e) => handleFieldChange("stock_quantity", e.target.value)}
                      disabled={disabled}
                      className="w-20"
                    />
                    <Select
                      value={formData.stock_status}
                      onValueChange={(v) => handleFieldChange("stock_status", v)}
                      disabled={disabled}
                    >
                      <SelectTrigger className="w-32">
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
              </div>
            )}
          </div>
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={onClose} disabled={disabled}>
            Cancel
          </Button>
          <Button onClick={handleSubmit} disabled={disabled}>
            Apply to All
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

interface AddVariationFormData {
  regular_price: string;
  sale_price: string;
  sku: string;
  stock_quantity: string;
  stock_status: string;
  manage_stock: boolean;
}

interface AddVariationDialogProps {
  isOpen: boolean;
  onClose: () => void;
  attributes: ProductAttribute[];
  onAdd: (_variation: Record<string, unknown>) => void;
  disabled?: boolean;
}

function AddVariationDialog({
  isOpen,
  onClose,
  attributes,
  onAdd,
  disabled,
}: AddVariationDialogProps) {
  const [selectedAttributes, setSelectedAttributes] = React.useState<Record<string, string[]>>({});
  const [formData, setFormData] = React.useState<AddVariationFormData>({
    regular_price: "",
    sale_price: "",
    sku: "",
    stock_quantity: "",
    stock_status: "instock",
    manage_stock: false,
  });
  const tempIdRef = React.useRef(0);

  const variationAttrs = attributes.filter((attr) => attr.variation);

  const handleToggleAttribute = (attrName: string, option: string): void => {
    setSelectedAttributes((prev) => {
      const current = prev[attrName] || [];
      return current.includes(option)
        ? { ...prev, [attrName]: current.filter((o) => o !== option) }
        : { ...prev, [attrName]: [...current, option] };
    });
  };

  const handleSetAny = (attrName: string): void => {
    setSelectedAttributes((prev) => {
      if (prev[attrName]?.includes("any")) {
        return { ...prev, [attrName]: prev[attrName].filter((o) => o !== "any") };
      }
      return { ...prev, [attrName]: [...(prev[attrName] || []), "any"] };
    });
  };

  const handleSubmit = (): void => {
    const attributesObj: Record<string, string> = {};
    Object.entries(selectedAttributes).forEach(([key, options]) => {
      if (options.length > 0) {
        const attr = variationAttrs.find((a) => getAttributeKey(a.name, a.isGlobal) === key);
        if (attr) {
          attributesObj[key] = options[0] ?? "";
        }
      }
    });

    tempIdRef.current += 1;
    const newVariation: Record<string, unknown> = {
      id: `new-${tempIdRef.current}`,
      ...formData,
      attributes: attributesObj,
    };
    onAdd(newVariation);
    setSelectedAttributes({});
    setFormData({
      regular_price: "",
      sale_price: "",
      sku: "",
      stock_quantity: "",
      stock_status: "instock",
      manage_stock: false,
    } as AddVariationFormData);
  };

  const hasSelection = Object.values(selectedAttributes).some((arr) => arr.length > 0);

  return (
    <Dialog open={isOpen} onOpenChange={onClose}>
      <DialogContent className="max-w-lg max-h-[80vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>Add Variation</DialogTitle>
          <DialogDescription>
            Select one value for each attribute to create a single variation.
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4 py-4">
          {variationAttrs.map((attr) => {
            const key = getAttributeKey(attr?.name ?? "", attr?.isGlobal ?? false);
            const options = attr.options || [];
            const selected = selectedAttributes[key] || [];

            return (
              <div key={key} className="space-y-2">
                <Label>{attr.name}</Label>
                <div className="flex flex-wrap gap-2">
                  <Button
                    type="button"
                    variant={selected.includes("any") ? "default" : "outline"}
                    size="sm"
                    onClick={() => handleSetAny(key)}
                    disabled={disabled}
                  >
                    Any
                  </Button>
                  {options.length === 0 ? (
                    <span className="text-sm text-muted-foreground">No values available</span>
                  ) : (
                    options.map((option) => (
                      <Button
                        key={option}
                        type="button"
                        variant={selected.includes(option) ? "default" : "outline"}
                        size="sm"
                        onClick={() => handleToggleAttribute(key, option)}
                        disabled={disabled}
                      >
                        {option}
                      </Button>
                    ))
                  )}
                </div>
              </div>
            );
          })}

          <div className="border-t pt-4 space-y-4">
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label htmlFor="add-regular_price">Regular Price</Label>
                <Input
                  id="add-regular_price"
                  type="number"
                  step="0.01"
                  min="0"
                  value={formData.regular_price}
                  onChange={(e) =>
                    setFormData(
                      (prev) =>
                        ({ ...prev, regular_price: e.target.value }) as AddVariationFormData,
                    )
                  }
                  disabled={disabled}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="add-sale_price">Sale Price</Label>
                <Input
                  id="add-sale_price"
                  type="number"
                  step="0.01"
                  min="0"
                  value={formData.sale_price}
                  onChange={(e) =>
                    setFormData(
                      (prev) => ({ ...prev, sale_price: e.target.value }) as AddVariationFormData,
                    )
                  }
                  disabled={disabled}
                />
              </div>
            </div>

            <div className="space-y-2">
              <Label htmlFor="add-sku">SKU (optional)</Label>
              <Input
                id="add-sku"
                value={formData.sku}
                onChange={(e) =>
                  setFormData((prev) => ({ ...prev, sku: e.target.value }) as AddVariationFormData)
                }
                disabled={disabled}
                placeholder="SKU"
              />
            </div>

            <div className="flex items-center gap-4">
              <div className="flex items-center gap-2">
                <Checkbox
                  id="add-manage_stock"
                  checked={formData.manage_stock}
                  onCheckedChange={(checked) =>
                    setFormData(
                      (prev) =>
                        ({ ...prev, manage_stock: Boolean(checked) }) as AddVariationFormData,
                    )
                  }
                  disabled={disabled}
                />
                <Label htmlFor="add-manage_stock" className="text-sm font-normal">
                  Manage stock
                </Label>
              </div>
              {formData.manage_stock && (
                <div className="flex items-center gap-2">
                  <Input
                    type="number"
                    min="0"
                    placeholder="Qty"
                    value={formData.stock_quantity}
                    onChange={(e) =>
                      setFormData(
                        (prev) =>
                          ({ ...prev, stock_quantity: e.target.value }) as AddVariationFormData,
                      )
                    }
                    disabled={disabled}
                    className="w-20"
                  />
                  <Select
                    value={formData.stock_status}
                    onValueChange={(v) =>
                      setFormData((prev) => ({ ...prev, stock_status: v }) as AddVariationFormData)
                    }
                    disabled={disabled}
                  >
                    <SelectTrigger className="w-32">
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
            </div>
          </div>
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={onClose} disabled={disabled}>
            Cancel
          </Button>
          <Button onClick={handleSubmit} disabled={disabled || !hasSelection}>
            Add variation
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

interface VariationFormData {
  regular_price: string;
  sale_price: string;
  sku: string;
  stock_quantity: string;
  stock_status: string;
  manage_stock: boolean;
  backorders: string;
  weight: string;
  length: string;
  width: string;
  height: string;
  virtual: boolean;
  downloadable: boolean;
}

interface SortableVariationRowProps {
  variation: ProductVariation;
  index: number;
  onUpdate: (_index: number, _updated: ProductVariation) => void;
  onDelete: (_index: number) => void;
  disabled?: boolean;
  openByDefault?: number | null;
  allExpanded: boolean;
  attributes: ProductAttribute[];
  productId?: number;
  onImageClick?: (_variation: ProductVariation, _index: number) => void;
}

function SortableVariationRow({
  variation,
  index,
  onUpdate,
  onDelete,
  disabled,
  openByDefault,
  allExpanded,
  attributes: rowAttributes,
  productId,
  onImageClick,
}: SortableVariationRowProps) {
  const [localExpanded, setLocalExpanded] = React.useState<boolean>(
    openByDefault === index || allExpanded,
  );
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
    id: `variation-${index}`,
  });

  React.useEffect(() => {
    const timer = setTimeout(() => {
      setLocalExpanded(allExpanded);
    }, 0);
    return () => clearTimeout(timer);
  }, [allExpanded]);

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
    opacity: isDragging ? 0.5 : 1,
    zIndex: isDragging ? 10 : 1,
  };

  const [formData, setFormData] = React.useState<VariationFormData>({
    regular_price: String(variation?.regular_price || ""),
    sale_price: String(variation?.sale_price || ""),
    sku: String(variation?.sku || ""),
    stock_quantity: String(variation?.stock_quantity || ""),
    stock_status: String(variation?.stock_status || "instock"),
    manage_stock: Boolean(variation?.manage_stock || false),
    backorders: String(variation?.backorders || "no"),
    weight: String(variation?.weight || ""),
    length: String(variation?.length || ""),
    width: String(variation?.width || ""),
    height: String(variation?.height || ""),
    virtual: Boolean(variation?.virtual || false),
    downloadable: Boolean(variation?.downloadable || false),
  });

  const [localAttributes, setLocalAttributes] = React.useState<Record<string, string>>(
    (variation.attributes as Record<string, string>) || {},
  );
  const [saving, setSaving] = React.useState(false);

  React.useEffect(() => {
    const timer = setTimeout(() => {
      setLocalAttributes((variation.attributes as Record<string, string>) || {});
    }, 0);
    return () => clearTimeout(timer);
  }, [variation.attributes]);

  const variationAttrs = (rowAttributes || []).filter((attr) => attr.variation);

  const handleAttributeChange = async (attrKey: string, newValue: string): Promise<void> => {
    const updated = { ...localAttributes };
    if (newValue === "any") {
      delete updated[attrKey];
    } else {
      updated[attrKey] = newValue;
    }
    setLocalAttributes(updated);

    onUpdate(index, { ...variation, attributes: updated });
  };

  const attributeSummary = React.useMemo(() => {
    if (!variation.attributes || Object.keys(variation.attributes).length === 0) return "Default";
    return (
      Object.entries(variation.attributes)
        .map(([key, value]) => {
          const { name } = parseAttributeKey(key);
          return `${name}: ${value}`;
        })
        .join(", ") || "Default"
    );
  }, [variation.attributes]);

  const handleChange = (field: string, value: string | boolean): void => {
    setFormData((prev) => ({ ...prev, [field]: value }) as VariationFormData);
  };

  const handleSave = (): void => {
    onUpdate(index, { ...variation, ...formData });
    toast.success("Variation updated");
  };

  return (
    <div
      ref={setNodeRef}
      style={style}
      className={cn("border rounded-lg bg-card", isDragging && "shadow-lg")}
    >
      <Collapsible open={localExpanded} onOpenChange={setLocalExpanded}>
        <div className="flex items-center gap-2 p-3">
          <div
            {...attributes}
            {...listeners}
            className="cursor-grab active:cursor-grabbing touch-none p-1 shrink-0"
          >
            <GripVertical className="h-4 w-4 text-muted-foreground" />
          </div>

          <CollapsibleTrigger asChild>
            <div className="cursor-pointer p-1 shrink-0">
              {localExpanded ? (
                <ChevronDown className="h-4 w-4" />
              ) : (
                <ChevronRight className="h-4 w-4" />
              )}
            </div>
          </CollapsibleTrigger>

          <div className="flex-1 min-w-0">
            {variationAttrs.length > 0 ? (
              <div className="flex items-center gap-1 flex-wrap">
                <span className="text-xs text-muted-foreground font-medium shrink-0">
                  #{variation.id || index + 1}
                </span>
                {variationAttrs.map((attr) => {
                  const attrKey = getAttributeKeyDirect(attr.name, attr.isGlobal);
                  const attrKeyLower = getAttributeKey(attr.name, attr.isGlobal);
                  const wooCommerceKey = getWooCommerceAttributeKey(attr.name, attr.isGlobal);

                  const rawValue =
                    localAttributes[attrKey] ||
                    localAttributes[attrKeyLower] ||
                    localAttributes[wooCommerceKey] ||
                    localAttributes[attr.name.toLowerCase()] ||
                    "";

                  const options = attr.options || [];
                  const optionsLower = options.map((opt) => opt.toLowerCase());

                  let currentValue = "any";
                  if (rawValue) {
                    const idx = optionsLower.indexOf(rawValue.toLowerCase());
                    if (idx !== -1) {
                      currentValue = options[idx] ?? "any";
                    }
                  }

                  return (
                    <Select
                      key={attrKey}
                      value={currentValue}
                      onValueChange={(val) => {
                        const saveValue = val === "any" ? "any" : val.toLowerCase();
                        handleAttributeChange(attrKey, saveValue);
                      }}
                      disabled={disabled || saving}
                    >
                      <SelectTrigger className="h-6 text-xs py-0 px-2 w-auto min-w-[80px]">
                        <SelectValue placeholder={`Any ${attr.name}...`} />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="any">Any {attr.name}...</SelectItem>
                        {options.map((opt) => (
                          <SelectItem key={opt} value={opt}>
                            {opt}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  );
                })}
              </div>
            ) : (
              <div className="flex items-center gap-2 flex-wrap">
                <span className="text-xs text-muted-foreground shrink-0">
                  #{variation.id || index + 1}
                </span>
                <span className="font-medium truncate">{attributeSummary}</span>
              </div>
            )}
          </div>

          {onImageClick && (
            <Button
              type="button"
              variant="ghost"
              size="icon"
              className="h-8 w-8 shrink-0"
              onClick={() => onImageClick(variation, index)}
              disabled={disabled}
            >
              {variation.image_id || variation.image?.id ? (
                <img
                  src={variation.image?.src || variation.image_url}
                  alt="Variation"
                  className="h-6 w-6 object-cover rounded"
                />
              ) : (
                <Image className="h-4 w-4" />
              )}
            </Button>
          )}

          <AlertDialog>
            <AlertDialogTrigger asChild>
              <Button
                type="button"
                variant="ghost"
                size="icon"
                disabled={disabled}
                className="h-8 w-8 text-destructive hover:text-destructive shrink-0"
              >
                <Trash2 className="h-4 w-4" />
              </Button>
            </AlertDialogTrigger>
            <AlertDialogContent>
              <AlertDialogHeader>
                <AlertDialogTitle>Delete Variation</AlertDialogTitle>
                <AlertDialogDescription>
                  Are you sure you want to delete this variation?
                </AlertDialogDescription>
              </AlertDialogHeader>
              <AlertDialogFooter>
                <AlertDialogCancel>Cancel</AlertDialogCancel>
                <AlertDialogAction onClick={() => onDelete(index)} className="bg-destructive">
                  Delete
                </AlertDialogAction>
              </AlertDialogFooter>
            </AlertDialogContent>
          </AlertDialog>
        </div>

        <CollapsibleContent>
          <div className="px-4 pb-4 pt-2 border-t space-y-4">
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label htmlFor={`regular_price-${index}`}>Regular Price</Label>
                <Input
                  id={`regular_price-${index}`}
                  type="number"
                  step="0.01"
                  min="0"
                  value={formData.regular_price}
                  onChange={(e) => handleChange("regular_price", e.target.value)}
                  disabled={disabled}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor={`sale_price-${index}`}>Sale Price</Label>
                <Input
                  id={`sale_price-${index}`}
                  type="number"
                  step="0.01"
                  min="0"
                  value={formData.sale_price}
                  onChange={(e) => handleChange("sale_price", e.target.value)}
                  disabled={disabled}
                />
              </div>
            </div>
            <div className="space-y-2">
              <Label htmlFor={`sku-${index}`}>SKU</Label>
              <Input
                id={`sku-${index}`}
                type="text"
                value={formData.sku}
                onChange={(e) => handleChange("sku", e.target.value)}
                disabled={disabled}
                placeholder="SKU (e.g. TSHIRT-BLK-LG)"
              />
            </div>

            <div className="space-y-4">
              <div className="flex items-center justify-between">
                <div>
                  <Label>Manage Stock</Label>
                  <p className="text-xs text-muted-foreground">Enable stock management</p>
                </div>
                <Switch
                  checked={formData.manage_stock}
                  onCheckedChange={(checked) => handleChange("manage_stock", checked)}
                  disabled={disabled}
                />
              </div>
              {formData.manage_stock && (
                <div className="grid grid-cols-2 gap-4 pl-6 border-l-2 border-border">
                  <div className="space-y-2">
                    <Label htmlFor={`stock_quantity-${index}`}>Stock Qty</Label>
                    <Input
                      id={`stock_quantity-${index}`}
                      type="number"
                      min="0"
                      value={formData.stock_quantity}
                      onChange={(e) => handleChange("stock_quantity", e.target.value)}
                      disabled={disabled}
                    />
                  </div>
                  <div className="space-y-2">
                    <Label>Status</Label>
                    <Select
                      value={formData.stock_status}
                      onValueChange={(v) => handleChange("stock_status", v)}
                      disabled={disabled}
                    >
                      <SelectTrigger />
                      <SelectContent>
                        <SelectItem value="instock">In Stock</SelectItem>
                        <SelectItem value="outofstock">Out of Stock</SelectItem>
                        <SelectItem value="onbackorder">Backorder</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                </div>
              )}
            </div>

            <div className="grid grid-cols-3 gap-4">
              <div className="space-y-2">
                <Label htmlFor={`weight-${index}`}>Weight</Label>
                <Input
                  id={`weight-${index}`}
                  type="number"
                  step="0.01"
                  min="0"
                  value={formData.weight}
                  onChange={(e) => handleChange("weight", e.target.value)}
                  disabled={disabled}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor={`length-${index}`}>Length</Label>
                <Input
                  id={`length-${index}`}
                  type="number"
                  step="0.01"
                  min="0"
                  value={formData.length}
                  onChange={(e) => handleChange("length", e.target.value)}
                  disabled={disabled}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor={`width-${index}`}>Width</Label>
                <Input
                  id={`width-${index}`}
                  type="number"
                  step="0.01"
                  min="0"
                  value={formData.width}
                  onChange={(e) => handleChange("width", e.target.value)}
                  disabled={disabled}
                />
              </div>
            </div>

            <div className="flex items-center gap-6">
              <div className="flex items-center gap-2">
                <Switch
                  checked={formData.virtual}
                  onCheckedChange={(v) => handleChange("virtual", v)}
                  disabled={disabled}
                />
                <Label>Virtual</Label>
              </div>
              <div className="flex items-center gap-2">
                <Switch
                  checked={formData.downloadable}
                  onCheckedChange={(v) => handleChange("downloadable", v)}
                  disabled={disabled}
                />
                <Label>Downloadable</Label>
              </div>
            </div>

            <div className="flex justify-end gap-2 pt-2">
              <Button onClick={handleSave} disabled={disabled}>
                Save changes
              </Button>
            </div>
          </div>
        </CollapsibleContent>
      </Collapsible>
    </div>
  );
}

interface VariationsManagerProps {
  productId?: number;
  attributes?: ProductAttribute[];
  variations?: ProductVariation[];
  onChange?: (_variations: ProductVariation[]) => void;
  disabled?: boolean;
  onAddVariation?: (_variation: Record<string, unknown>) => Promise<{ variation_id?: number } | void>;
  onGenerateVariations?: () => Promise<{ variations?: Record<string, unknown>[] } | void>;
  defaultAttributes?: Record<string, string>;
  onDefaultChange?: (_value: string) => void;
  onVariationImageClick?: (_variation: ProductVariation, _index: number) => void;
  itemsPerPage?: number;
}

export function VariationsManager({
  productId,
  attributes = [],
  variations = [],
  onChange,
  disabled,
  onAddVariation,
  onGenerateVariations,
  defaultAttributes = {},
  onDefaultChange,
  onVariationImageClick,
  itemsPerPage = 20,
}: VariationsManagerProps) {
  const [showAddDialog, setShowAddDialog] = React.useState(false);
  const [showBulkEditDialog, setShowBulkEditDialog] = React.useState(false);
  const [showBulkPriceDialog, setShowBulkPriceDialog] = React.useState<string | null>(null);
  const [showBulkStockDialog, setShowBulkStockDialog] = React.useState(false);
  const [showBulkLowStockDialog, setShowBulkLowStockDialog] = React.useState(false);
  const [showBulkDimensionDialog, setShowBulkDimensionDialog] = React.useState<string | null>(null);
  const [showBulkDownloadDialog, setShowBulkDownloadDialog] = React.useState<string | null>(null);
  const [showSaleScheduleDialog, setShowSaleScheduleDialog] = React.useState(false);
  const [generating, setGenerating] = React.useState(false);
  const [currentPage, setCurrentPage] = React.useState(1);
  const [allExpanded, setAllExpanded] = React.useState(true);
  const [editingIndex, _setEditingIndex] = React.useState<number | null>(null);

  const sensors = useSensors(
    useSensor(PointerSensor, {
      activationConstraint: {
        distance: 8,
      },
    }),
    useSensor(KeyboardSensor, {
      coordinateGetter: sortableKeyboardCoordinates,
    }),
  );

  const totalPages = Math.ceil(variations.length / itemsPerPage);
  const paginatedVariations = React.useMemo((): ProductVariation[] => {
    const start = (currentPage - 1) * itemsPerPage;
    return variations.slice(start, start + itemsPerPage);
  }, [variations, currentPage, itemsPerPage]);

  React.useEffect(() => {
    if (currentPage > totalPages && totalPages > 0) {
      const timer = setTimeout(() => {
        setCurrentPage(totalPages);
      }, 0);
      return () => clearTimeout(timer);
    }
  }, [totalPages, currentPage]);

  const handleDragEnd = React.useCallback(
    (event: { active: { id: UniqueIdentifier }; over: { id: UniqueIdentifier } | null }): void => {
      const { active, over } = event;
      if (over && active.id !== over.id) {
        const oldIndex = variations.findIndex((_, i) => `variation-${i}` === active.id);
        const newIndex = variations.findIndex((_, i) => `variation-${i}` === over.id);
        if (oldIndex !== -1 && newIndex !== -1) {
          const newVariations = arrayMove(variations, oldIndex, newIndex);
          if (onChange) onChange(newVariations);
        }
      }
    },
    [variations, onChange],
  );

  const handleAdd = async (variation: Record<string, unknown>): Promise<void> => {
    if (onAddVariation) {
      try {
        const result = await onAddVariation(variation);
        if (result?.variation_id) {
          const newVars = [...variations, { ...variation, id: result.variation_id } as ProductVariation];
          if (onChange) onChange(newVars);
        }
      } catch (error: unknown) {
        toast.error(error instanceof Error ? error.message : "Failed to create variation");
      }
    } else {
      if (onChange) onChange([...variations, variation as ProductVariation]);
      toast.success("Added (local)");
    }
    setShowAddDialog(false);
  };

  const handleUpdate = (index: number, updated: ProductVariation): void => {
    const actualIndex = (currentPage - 1) * itemsPerPage + index;
    const newV = [...variations];
    newV[actualIndex] = updated;
    if (onChange) onChange(newV);
  };

  const handleDelete = (index: number): void => {
    const actualIndex = (currentPage - 1) * itemsPerPage + index;
    const newV = variations.filter((_, i) => i !== actualIndex);
    if (onChange) onChange(newV);
    toast.success("Deleted");
  };

  const handleGenerateAll = async (): Promise<void> => {
    if (generating) return;

    if (onGenerateVariations) {
      setGenerating(true);
      try {
        const result = await onGenerateVariations();
        if (result?.variations && result.variations.length > 0) {
          const newV: ProductVariation[] = result.variations.map((v: Record<string, unknown>) => ({
            id: v.id as number,
            regular_price: String(v.regular_price ?? ""),
            sale_price: String(v.sale_price ?? ""),
            sku: String(v.sku ?? ""),
            stock_quantity: String(v.stock_quantity ?? ""),
            stock_status: String(v.stock_status ?? "instock"),
            manage_stock: Boolean(v.manage_stock ?? false),
            attributes:
              typeof v.attributes === "object" ? (v.attributes as Record<string, string>) : {},
          }));
          if (onChange) onChange(newV);
        }
      } catch (error: unknown) {
        toast.error(error instanceof Error ? error.message : "Failed to generate variations");
      } finally {
        setGenerating(false);
      }
      return;
    }

    setGenerating(true);
    try {
      const combos = generateVariationCombinations(attributes);
      if (combos.length === 0) {
        toast.error("No attributes");
        return;
      }
      const newV: ProductVariation[] = combos.map((combo, idx) => ({
        id: idx + 1,
        regular_price: "",
        sale_price: "",
        sku: "",
        stock_quantity: null as unknown as string,
        stock_status: "instock",
        manage_stock: false,
        backorders: "no",
        weight: "",
        length: "",
        width: "",
        height: "",
        virtual: false,
        downloadable: false,
        status: "publish",
        description: "",
        attributes: combo,
      }));
      if (onChange) onChange(newV);
      toast.success(`Generated ${newV.length}`);
    } catch (error: unknown) {
      toast.error("Failed");
    } finally {
      setGenerating(false);
    }
  };

  const handleBulkSave = (updates: Record<string, unknown>): void => {
    const newV = variations.map((v) => ({ ...v, ...updates }));
    if (onChange) onChange(newV);
    toast.success(`Updated ${variations.length} variations`);
  };

  const handleBulkDelete = (): void => {
    if (confirm(`Are you sure you want to delete all ${variations.length} variations?`)) {
      if (onChange) onChange([]);
      toast.success("All variations deleted");
    }
  };

  const handleToggleAllEnabled = (): void => {
    const allEnabled = variations.every((v) => v.enabled !== false);
    const newV = variations.map((v) => ({ ...v, enabled: !allEnabled }));
    if (onChange) onChange(newV);
    toast.success(allEnabled ? "Disabled all variations" : "Enabled all variations");
  };

  const handleToggleAllVirtual = (): void => {
    const allVirtual = variations.every((v) => v.virtual);
    const newV = variations.map((v) => ({ ...v, virtual: !allVirtual }));
    if (onChange) onChange(newV);
    toast.success(allVirtual ? "Removed virtual from all" : "Set all as virtual");
  };

  const handleToggleAllDownloadable = (): void => {
    const allDownloadable = variations.every((v) => v.downloadable);
    const newV = variations.map((v) => ({ ...v, downloadable: !allDownloadable }));
    if (onChange) onChange(newV);
    toast.success(allDownloadable ? "Removed downloadable from all" : "Set all as downloadable");
  };

  const handleToggleManageStock = (): void => {
    const allManageStock = variations.every((v) => v.manage_stock);
    const newV = variations.map((v) => ({
      ...v,
      manage_stock: !allManageStock,
      stock_status: !allManageStock ? "instock" : v.stock_status,
    }));
    if (onChange) onChange(newV);
    toast.success(
      allManageStock ? "Disabled manage stock for all" : "Enabled manage stock for all",
    );
  };

  const handleSetStockStatus = (status: string): void => {
    const newV = variations.map((v) => ({ ...v, stock_status: status }));
    if (onChange) onChange(newV);
    toast.success(`Set stock status to ${status} for all variations`);
  };

  const handleBulkPrice = (type: string, value: string): void => {
    const newV = variations.map((v) => {
      const newVariation = { ...v };
      const currentRegular = Number.parseFloat(v.regular_price as string) || 0;
      const currentSale = Number.parseFloat(v.sale_price as string) || 0;

      switch (type) {
        case "regular": {
          newVariation.regular_price = value;
          break;
        }
        case "regular_increase": {
          const isPercent = value.endsWith("%");
          const numValue = Number.parseFloat(value);
          newVariation.regular_price = isPercent
            ? String((currentRegular * (1 + numValue / 100)).toFixed(2))
            : String((currentRegular + numValue).toFixed(2));
          break;
        }
        case "regular_decrease": {
          const isPercent = value.endsWith("%");
          const numValue = Number.parseFloat(value);
          newVariation.regular_price = isPercent
            ? String((currentRegular * (1 - numValue / 100)).toFixed(2))
            : String(Math.max(0, currentRegular - numValue).toFixed(2));
          break;
        }
        case "sale": {
          newVariation.sale_price = value;
          break;
        }
        case "sale_increase": {
          const isPercent = value.endsWith("%");
          const numValue = Number.parseFloat(value);
          newVariation.sale_price = isPercent
            ? String((currentSale * (1 + numValue / 100)).toFixed(2))
            : String((currentSale + numValue).toFixed(2));
          break;
        }
        case "sale_decrease": {
          const isPercent = value.endsWith("%");
          const numValue = Number.parseFloat(value);
          newVariation.sale_price = isPercent
            ? String((currentSale * (1 - numValue / 100)).toFixed(2))
            : String(Math.max(0, currentSale - numValue).toFixed(2));
          break;
        }
      }
      return newVariation;
    });
    if (onChange) onChange(newV);
    toast.success(`Updated prices for ${variations.length} variations`);
    setShowBulkPriceDialog(null);
  };

  const handleBulkStock = (stockQty: string, manageStock: boolean, stockStatus: string): void => {
    const newV = variations.map((v) => ({
      ...v,
      stock_quantity: stockQty,
      manage_stock: manageStock,
      stock_status: stockStatus || v.stock_status,
    }));
    if (onChange) onChange(newV);
    toast.success(`Updated stock for ${variations.length} variations`);
    setShowBulkStockDialog(false);
  };

  const handleBulkLowStock = (threshold: string): void => {
    const newV = variations.map((v) => ({ ...v, low_stock_amount: Number(threshold) }));
    if (onChange) onChange(newV);
    toast.success(`Set low stock threshold for ${variations.length} variations`);
    setShowBulkLowStockDialog(false);
  };

  const handleBulkDimension = (type: string, value: string): void => {
    const newV = variations.map((v) => ({ ...v, [type]: value }));
    if (onChange) onChange(newV);
    toast.success(`Updated ${type} for ${variations.length} variations`);
    setShowBulkDimensionDialog(null);
  };

  const handleBulkDownload = (type: string, value: string): void => {
    const newV = variations.map((v) => ({ ...v, [type]: value }));
    if (onChange) onChange(newV);
    toast.success(`Updated ${type} for ${variations.length} variations`);
    setShowBulkDownloadDialog(null);
  };

  const handleBulkSaleSchedule = (fromDate: string | null, toDate: string | null): void => {
    const newV = variations.map((v) => ({
      ...v,
      sale_price_dates_from: fromDate || null,
      sale_price_dates_to: toDate || null,
    }));
    if (onChange) onChange(newV);
    toast.success(`Set sale schedule for ${variations.length} variations`);
    setShowSaleScheduleDialog(false);
  };

  const variationAttrs = attributes.filter((attr) => attr.variation);
  const canGenerate = variationAttrs.some((a) => (a.options || []).length > 0);
  const totalCombos = React.useMemo(
    () => generateVariationCombinations(attributes).length,
    [attributes],
  );

  const variationIds = React.useMemo(
    () => paginatedVariations.map((_, i) => `variation-${i}`),
    [paginatedVariations],
  );

  const [defaultVariation, setDefaultVariation] = React.useState(() => {
    if (!defaultAttributes || Object.keys(defaultAttributes).length === 0) return "none";
    const entries = Object.entries(defaultAttributes);
    if (entries.length === 0) return "none";
    const attrs = entries
      .map(([key, val]) => {
        if (!val) return null;
        const { name } = parseAttributeKey(key);
        return `${name}: ${val}`;
      })
      .filter(Boolean);
    return attrs.length > 0 ? attrs.join(", ") : "none";
  });

  return (
    <div className="space-y-4">
      <AddVariationDialog
        isOpen={showAddDialog}
        onClose={() => setShowAddDialog(false)}
        attributes={attributes}
        onAdd={handleAdd}
        disabled={disabled}
      />

      <BulkEditDialog
        isOpen={showBulkEditDialog}
        onClose={() => setShowBulkEditDialog(false)}
        variations={variations}
        onSave={handleBulkSave}
        disabled={disabled}
      />

      {showBulkPriceDialog && (
        <Dialog open={true} onOpenChange={() => setShowBulkPriceDialog(null)}>
          <DialogContent className="max-w-md">
            <DialogHeader>
              <DialogTitle>
                {showBulkPriceDialog === "regular" && "Set Regular Prices"}
                {showBulkPriceDialog === "regular_increase" && "Increase Regular Prices"}
                {showBulkPriceDialog === "regular_decrease" && "Decrease Regular Prices"}
                {showBulkPriceDialog === "sale" && "Set Sale Prices"}
                {showBulkPriceDialog === "sale_increase" && "Increase Sale Prices"}
                {showBulkPriceDialog === "sale_decrease" && "Decrease Sale Prices"}
              </DialogTitle>
              <DialogDescription>
                Enter a value for all {variations.length} variations. Use percentage (e.g., 10% or
                -10%) for increase/decrease.
              </DialogDescription>
            </DialogHeader>
            <div className="space-y-4 py-4">
              <Input
                type="text"
                placeholder={
                  showBulkPriceDialog.includes("increase") ||
                  showBulkPriceDialog.includes("decrease")
                    ? "e.g. 10 or 10%"
                    : "e.g. 100"
                }
                id="bulk_price_value"
                onKeyDown={(e) => {
                  if (e.key === "Enter") {
                    const value = (document.querySelector("#bulk_price_value") as HTMLInputElement).value;
                    handleBulkPrice(showBulkPriceDialog, value);
                  }
                }}
              />
              <div className="flex justify-end gap-2">
                <Button variant="outline" onClick={() => setShowBulkPriceDialog(null)}>
                  Cancel
                </Button>
                <Button
                  onClick={() => {
                    const value = (document.querySelector("#bulk_price_value") as HTMLInputElement).value;
                    handleBulkPrice(showBulkPriceDialog, value);
                  }}
                >
                  Apply
                </Button>
              </div>
            </div>
          </DialogContent>
        </Dialog>
      )}

      {showBulkStockDialog && (
        <Dialog open={true} onOpenChange={() => setShowBulkStockDialog(false)}>
          <DialogContent className="max-w-md">
            <DialogHeader>
              <DialogTitle>Set Stock</DialogTitle>
              <DialogDescription>
                Set stock quantity for all {variations.length} variations.
              </DialogDescription>
            </DialogHeader>
            <div className="space-y-4 py-4">
              <div className="flex items-center gap-2">
                <Checkbox id="bulk_manage_stock" checked={true} onCheckedChange={() => {}} />
                <Label htmlFor="bulk_manage_stock" className="font-normal">
                  Manage stock
                </Label>
              </div>
              <Input type="number" placeholder="Stock quantity" id="bulk_stock_value" />
              <div className="space-y-2">
                <Label>Stock Status</Label>
                <Select defaultValue="instock">
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="instock">In stock</SelectItem>
                    <SelectItem value="outofstock">Out of stock</SelectItem>
                    <SelectItem value="onbackorder">On backorder</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div className="flex justify-end gap-2">
                <Button variant="outline" onClick={() => setShowBulkStockDialog(false)}>
                  Cancel
                </Button>
                <Button
                  onClick={() => {
                    const qty = (document.querySelector("#bulk_stock_value") as HTMLInputElement).value;
                    const statusEl = document.querySelector("#bulk_stock_status") as HTMLSelectElement;
                    const status = statusEl?.value || "instock";
                    handleBulkStock(qty, true, status);
                  }}
                >
                  Apply
                </Button>
              </div>
            </div>
          </DialogContent>
        </Dialog>
      )}

      {showBulkLowStockDialog && (
        <Dialog open={true} onOpenChange={() => setShowBulkLowStockDialog(false)}>
          <DialogContent className="max-w-md">
            <DialogHeader>
              <DialogTitle>Set Low Stock Threshold</DialogTitle>
              <DialogDescription>
                Set low stock threshold for all {variations.length} variations.
              </DialogDescription>
            </DialogHeader>
            <div className="space-y-4 py-4">
              <Input type="number" placeholder="Threshold value" id="bulk_low_stock_value" />
              <div className="flex justify-end gap-2">
                <Button variant="outline" onClick={() => setShowBulkLowStockDialog(false)}>
                  Cancel
                </Button>
                <Button
                  onClick={() => {
                    const value = (document.querySelector("#bulk_low_stock_value") as HTMLInputElement).value;
                    handleBulkLowStock(value);
                  }}
                >
                  Apply
                </Button>
              </div>
            </div>
          </DialogContent>
        </Dialog>
      )}

      {showBulkDimensionDialog && (
        <Dialog open={true} onOpenChange={() => setShowBulkDimensionDialog(null)}>
          <DialogContent className="max-w-md">
            <DialogHeader>
              <DialogTitle>
                Set {showBulkDimensionDialog.charAt(0).toUpperCase() + showBulkDimensionDialog.slice(1)}
              </DialogTitle>
              <DialogDescription>
                Set {showBulkDimensionDialog} for all {variations.length} variations.
              </DialogDescription>
            </DialogHeader>
            <div className="space-y-4 py-4">
              <Input
                type="number"
                step="0.01"
                placeholder={`Enter ${showBulkDimensionDialog}`}
                id="bulk_dimension_value"
              />
              <div className="flex justify-end gap-2">
                <Button variant="outline" onClick={() => setShowBulkDimensionDialog(null)}>
                  Cancel
                </Button>
                <Button
                  onClick={() => {
                    const value = (document.querySelector("#bulk_dimension_value") as HTMLInputElement).value;
                    handleBulkDimension(showBulkDimensionDialog, value);
                  }}
                >
                  Apply
                </Button>
              </div>
            </div>
          </DialogContent>
        </Dialog>
      )}

      {showBulkDownloadDialog && (
        <Dialog open={true} onOpenChange={() => setShowBulkDownloadDialog(null)}>
          <DialogContent className="max-w-md">
            <DialogHeader>
              <DialogTitle>
                Set {showBulkDownloadDialog === "limit" ? "Download Limit" : "Download Expiry"}
              </DialogTitle>
              <DialogDescription>
                Set {showBulkDownloadDialog === "limit" ? "download limit" : "download expiry"} for
                all {variations.length} variations.
              </DialogDescription>
            </DialogHeader>
            <div className="space-y-4 py-4">
              <Input
                type="number"
                placeholder={showBulkDownloadDialog === "limit" ? "Number of downloads" : "Days until expiry"}
                id="bulk_download_value"
              />
              <div className="flex justify-end gap-2">
                <Button variant="outline" onClick={() => setShowBulkDownloadDialog(null)}>
                  Cancel
                </Button>
                <Button
                  onClick={() => {
                    const value = (document.querySelector("#bulk_download_value") as HTMLInputElement).value;
                    handleBulkDownload(showBulkDownloadDialog, value);
                  }}
                >
                  Apply
                </Button>
              </div>
            </div>
          </DialogContent>
        </Dialog>
      )}

      {showSaleScheduleDialog && (
        <Dialog open={true} onOpenChange={() => setShowSaleScheduleDialog(false)}>
          <DialogContent className="max-w-md">
            <DialogHeader>
              <DialogTitle>Set Scheduled Sale Dates</DialogTitle>
              <DialogDescription>
                Set sale price dates for all {variations.length} variations.
              </DialogDescription>
            </DialogHeader>
            <div className="space-y-4 py-4">
              <div className="space-y-2">
                <Label>From Date</Label>
                <Input type="date" id="sale_date_from" />
              </div>
              <div className="space-y-2">
                <Label>To Date</Label>
                <Input type="date" id="sale_date_to" />
              </div>
              <div className="flex justify-end gap-2">
                <Button variant="outline" onClick={() => setShowSaleScheduleDialog(false)}>
                  Cancel
                </Button>
                <Button
                  onClick={() => {
                    const fromDate = (document.querySelector("#sale_date_from") as HTMLInputElement).value || null;
                    const toDate = (document.querySelector("#sale_date_to") as HTMLInputElement).value || null;
                    handleBulkSaleSchedule(fromDate, toDate);
                  }}
                >
                  Apply
                </Button>
              </div>
            </div>
          </DialogContent>
        </Dialog>
      )}

      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2">
          <Button
            variant="outline"
            size="sm"
            onClick={() => setShowAddDialog(true)}
            disabled={disabled || variationAttrs.length === 0}
          >
            <Plus className="h-4 w-4 mr-1" />
            Add variation
          </Button>
          {canGenerate && (
            <Button
              variant="outline"
              size="sm"
              onClick={handleGenerateAll}
              disabled={disabled || generating}
            >
              {generating ? (
                <RefreshCw className="h-4 w-4 mr-1 animate-spin" />
              ) : (
                <Sparkles className="h-4 w-4 mr-1" />
              )}
              Generate all ({totalCombos})
            </Button>
          )}
        </div>
        {variations.length > 0 && (
          <div className="text-sm text-muted-foreground flex items-center gap-2">
            <span>
              {variations.length} variation{variations.length !== 1 ? "s" : ""}
            </span>
            <Button
              variant="ghost"
              size="sm"
              onClick={() => setAllExpanded(true)}
              className="text-xs h-6 px-2"
            >
              Expand
            </Button>
            <Button
              variant="ghost"
              size="sm"
              onClick={() => setAllExpanded(false)}
              className="text-xs h-6 px-2"
            >
              Close
            </Button>
          </div>
        )}
      </div>

      {variations.length > 0 && (
        <div className="flex items-center justify-between bg-muted/30 rounded-lg p-2">
          <div className="flex items-center gap-2">
            <Select
              onValueChange={(value) => {
                switch (value) {
                  case "bulk_edit": setShowBulkEditDialog(true); break;
                  case "bulk_delete": handleBulkDelete(); break;
                  case "toggle_enabled": handleToggleAllEnabled(); break;
                  case "toggle_virtual": handleToggleAllVirtual(); break;
                  case "toggle_downloadable": handleToggleAllDownloadable(); break;
                  case "toggle_manage_stock": handleToggleManageStock(); break;
                  case "variable_regular_price": setShowBulkPriceDialog("regular"); break;
                  case "variable_regular_price_increase": setShowBulkPriceDialog("regular_increase"); break;
                  case "variable_regular_price_decrease": setShowBulkPriceDialog("regular_decrease"); break;
                  case "variable_sale_price": setShowBulkPriceDialog("sale"); break;
                  case "variable_sale_price_increase": setShowBulkPriceDialog("sale_increase"); break;
                  case "variable_sale_price_decrease": setShowBulkPriceDialog("sale_decrease"); break;
                  case "variable_stock": setShowBulkStockDialog(true); break;
                  case "variable_stock_status_instock": handleSetStockStatus("instock"); break;
                  case "variable_stock_status_outofstock": handleSetStockStatus("outofstock"); break;
                  case "variable_stock_status_onbackorder": handleSetStockStatus("onbackorder"); break;
                  case "variable_low_stock_amount": setShowBulkLowStockDialog(true); break;
                  case "variable_weight": setShowBulkDimensionDialog("weight"); break;
                  case "variable_length": setShowBulkDimensionDialog("length"); break;
                  case "variable_width": setShowBulkDimensionDialog("width"); break;
                  case "variable_height": setShowBulkDimensionDialog("height"); break;
                  case "variable_download_limit": setShowBulkDownloadDialog("limit"); break;
                  case "variable_download_expiry": setShowBulkDownloadDialog("expiry"); break;
                  case "variable_sale_schedule": setShowSaleScheduleDialog(true); break;
                }
              }}
            >
              <SelectTrigger className="w-[180px]">
                <SelectValue placeholder="Bulk actions" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="bulk_edit">
                  <Edit className="h-4 w-4 mr-2 inline" />
                  Edit all
                </SelectItem>
                <SelectItem value="bulk_delete" className="text-destructive">
                  <Trash2 className="h-4 w-4 mr-2 inline" />
                  Delete all
                </SelectItem>
                <SelectItem value="toggle_enabled">
                  <Layers className="h-4 w-4 mr-2 inline" />
                  Toggle "Enabled"
                </SelectItem>
                <SelectItem value="toggle_downloadable">
                  <Layers className="h-4 w-4 mr-2 inline" />
                  Toggle "Downloadable"
                </SelectItem>
                <SelectItem value="toggle_virtual">
                  <Layers className="h-4 w-4 mr-2 inline" />
                  Toggle "Virtual"
                </SelectItem>
                <SelectItem value="variable_regular_price">
                  <span className="h-4 w-4 mr-2 inline">$</span>
                  Set regular prices
                </SelectItem>
                <SelectItem value="variable_regular_price_increase">
                  <span className="h-4 w-4 mr-2 inline">+</span>
                  Increase regular prices
                </SelectItem>
                <SelectItem value="variable_regular_price_decrease">
                  <span className="h-4 w-4 mr-2 inline">-</span>
                  Decrease regular prices
                </SelectItem>
                <SelectItem value="variable_sale_price">
                  <span className="h-4 w-4 mr-2 inline">%</span>
                  Set sale prices
                </SelectItem>
                <SelectItem value="variable_sale_price_increase">
                  <span className="h-4 w-4 mr-2 inline">+</span>
                  Increase sale prices
                </SelectItem>
                <SelectItem value="variable_sale_price_decrease">
                  <span className="h-4 w-4 mr-2 inline">-</span>
                  Decrease sale prices
                </SelectItem>
                <SelectItem value="toggle_manage_stock">
                  <Layers className="h-4 w-4 mr-2 inline" />
                  Toggle "Manage stock"
                </SelectItem>
                <SelectItem value="variable_stock">
                  <span className="h-4 w-4 mr-2 inline">#</span>
                  Stock
                </SelectItem>
                <SelectItem value="variable_stock_status_instock">
                  <span className="h-4 w-4 mr-2 inline">✓</span>
                  Set Status - In stock
                </SelectItem>
                <SelectItem value="variable_stock_status_outofstock">
                  <span className="h-4 w-4 mr-2 inline">✗</span>
                  Set Status - Out of stock
                </SelectItem>
                <SelectItem value="variable_stock_status_onbackorder">
                  <span className="h-4 w-4 mr-2 inline">↺</span>
                  Set Status - On backorder
                </SelectItem>
                <SelectItem value="variable_low_stock_amount">
                  <span className="h-4 w-4 mr-2 inline">!</span>
                  Low stock threshold
                </SelectItem>
                <SelectItem value="variable_weight">
                  <span className="h-4 w-4 mr-2 inline">⚖</span>
                  Weight
                </SelectItem>
                <SelectItem value="variable_length">
                  <span className="h-4 w-4 mr-2 inline">↔</span>
                  Length
                </SelectItem>
                <SelectItem value="variable_width">
                  <span className="h-4 w-4 mr-2 inline">↕</span>
                  Width
                </SelectItem>
                <SelectItem value="variable_height">
                  <span className="h-4 w-4 mr-2 inline">↕</span>
                  Height
                </SelectItem>
                <SelectItem value="variable_download_limit">
                  <span className="h-4 w-4 mr-2 inline">⬇</span>
                  Download limit
                </SelectItem>
                <SelectItem value="variable_download_expiry">
                  <span className="h-4 w-4 mr-2 inline">⏱</span>
                  Download expiry
                </SelectItem>
                <SelectItem value="variable_sale_schedule">
                  <Calendar className="h-4 w-4 mr-2 inline" />
                  Set scheduled sale dates
                </SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div className="flex items-center gap-2">
            <Label className="text-xs text-muted-foreground">Default:</Label>
            <Select
              value={defaultVariation}
              onValueChange={(value) => {
                setDefaultVariation(value);
                if (onDefaultChange) onDefaultChange(value);
              }}
              disabled={disabled}
            >
              <SelectTrigger className="w-[180px]">
                <SelectValue placeholder="No default" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="any">Any</SelectItem>
                <SelectItem value="none">No default</SelectItem>
                {variations.map((v, i) => {
                  const attrSummary =
                    Object.entries(v.attributes || {})
                      .map(([key, val]) => {
                        const { name } = parseAttributeKey(key);
                        return `${name}: ${val}`;
                      })
                      .join(", ") || "Default";
                  return (
                    <SelectItem key={i} value={attrSummary}>
                      {attrSummary}
                    </SelectItem>
                  );
                })}
              </SelectContent>
            </Select>
          </div>
        </div>
      )}

      {totalPages > 1 && (
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2">
            <Button
              variant="outline"
              size="sm"
              onClick={() => setCurrentPage((p) => Math.max(1, p - 1))}
              disabled={disabled || currentPage === 1}
            >
              Previous
            </Button>
            <span className="text-sm text-muted-foreground">
              Page {currentPage} of {totalPages}
            </span>
            <Button
              variant="outline"
              size="sm"
              onClick={() => setCurrentPage((p) => Math.min(totalPages, p + 1))}
              disabled={disabled || currentPage === totalPages}
            >
              Next
            </Button>
          </div>
        </div>
      )}

      {variations.length === 0 ? (
        <div className="text-center py-8 text-muted-foreground">
          {variationAttrs.length === 0 ? (
            <p>Add variation attributes first.</p>
          ) : (
            <p>No variations yet. Click "Add variation" or "Generate all".</p>
          )}
        </div>
      ) : (
        <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
          <SortableContext items={variationIds} strategy={verticalListSortingStrategy}>
            <div className="space-y-2">
              {paginatedVariations.map((v, i) => (
                <SortableVariationRow
                  key={`variation-${i}`}
                  variation={v}
                  index={i}
                  onUpdate={handleUpdate}
                  onDelete={handleDelete}
                  disabled={disabled}
                  openByDefault={editingIndex}
                  allExpanded={allExpanded}
                  attributes={attributes}
                  productId={productId}
                  onImageClick={onVariationImageClick}
                />
              ))}
            </div>
          </SortableContext>
        </DndContext>
      )}
    </div>
  );
}

export default VariationsManager;
