import * as React from "react";
import {
  Plus,
  Trash2,
  X,
  ChevronDown,
  ChevronRight,
  GripVertical,
  Save,
  ChevronUp,
} from "lucide-react";
import { Collapsible, CollapsibleTrigger } from "@/Components/ui/collapsible";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import { Checkbox } from "@/Components/ui/checkbox";
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
  Combobox,
  ComboboxInput,
  ComboboxContent,
  ComboboxList,
  ComboboxItem,
  ComboboxEmpty,
  ComboboxSeparator,
} from "@/Components/ui/combobox";
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
import { toast } from "sonner";

interface AttributeTerm {
  id: number | string | null | undefined;
  name: string;
  slug?: string;
}

interface ProductAttribute {
  id: number | string | null;
  name: string;
  label?: string;
  options: string[];
  visible: boolean;
  variation: boolean;
  isGlobal: boolean;
  useExisting: boolean;
  existingTerms?: AttributeTerm[];
  _customName?: string;
  _isNew?: boolean;
}

interface GlobalAttribute {
  id: number | string;
  name: string;
  label?: string;
}

interface AttributeComboboxProps {
  existingTerms?: AttributeTerm[];
  selectedOptions?: string[];
  onChange?: (_options: string[]) => void;
  disabled?: boolean;
}

function AttributeCombobox({
  existingTerms = [],
  selectedOptions = [],
  onChange,
  disabled = false,
}: AttributeComboboxProps) {
  const [open, setOpen] = React.useState(false);
  const [search, setSearch] = React.useState("");

  const availableTerms = React.useMemo(() => {
    if (!search) return existingTerms.filter((term) => !selectedOptions.includes(term.name));
    const searchLower = search.toLowerCase();
    return existingTerms.filter(
      (term) =>
        !selectedOptions.includes(term.name) && term.name.toLowerCase().includes(searchLower),
    );
  }, [existingTerms, selectedOptions, search]);

  const handleSelect = React.useCallback(
    (value: unknown) => {
      const termName = value as string;
      if (termName && !selectedOptions.includes(termName)) {
        onChange?.([...selectedOptions, termName]);
      }
      setSearch("");
      setOpen(false);
    },
    [selectedOptions, onChange],
  );

  const handleRemove = React.useCallback(
    (termName: string) => {
      onChange?.(selectedOptions.filter((opt) => opt !== termName));
    },
    [selectedOptions, onChange],
  );

  const handleKeyDown = React.useCallback(
    (e: React.KeyboardEvent<HTMLInputElement>) => {
      if (e.key === "Enter" && search.trim()) {
        e.preventDefault();
        handleSelect(search.trim());
      } else if (e.key === "Backspace" && !search && selectedOptions.length > 0) {
        handleRemove(selectedOptions.at(-1) as string);
      }
    },
    [search, selectedOptions, handleSelect, handleRemove],
  );

  return (
    <div className="space-y-2">
      {selectedOptions.length > 0 && (
        <div className="flex flex-wrap gap-1.5">
          {selectedOptions.map((opt, idx) => (
            <span
              key={idx}
              className="inline-flex items-center gap-1 px-2 py-1 rounded-md text-xs font-medium bg-primary text-primary-foreground"
            >
              {opt}
              <button
                type="button"
                onClick={() => handleRemove(opt)}
                disabled={disabled}
                className="hover:bg-primary/80 rounded p-0.5"
              >
                <X className="h-3 w-3" />
              </button>
            </span>
          ))}
        </div>
      )}

      <Combobox open={open} onOpenChange={setOpen} onValueChange={handleSelect} value="">
        <ComboboxInput
          showTrigger={true}
          showClear={search.length > 0}
          placeholder={selectedOptions.length === 0 ? "Select or type values..." : ""}
          onKeyDown={handleKeyDown}
          onChange={(e: React.ChangeEvent<HTMLInputElement>) => setSearch(e.target.value)}
          value={search}
        />
        <ComboboxContent>
          <ComboboxList>
            <ComboboxEmpty>No matching values</ComboboxEmpty>
            {availableTerms.map((term) => (
              <ComboboxItem key={term.id} value={term.name}>
                {term.name}
              </ComboboxItem>
            ))}
            {search &&
              !existingTerms.some((t) => t.name.toLowerCase() === search.toLowerCase()) && (
                <>
                  <ComboboxSeparator />
                  <ComboboxItem value={search}>
                    <Plus className="h-4 w-4 mr-2" />
                    Add "{search}" as new value
                  </ComboboxItem>
                </>
              )}
          </ComboboxList>
        </ComboboxContent>
      </Combobox>

      <p className="text-xs text-muted-foreground">
        Select existing values or type to add custom ones. Press Enter to add custom values.
      </p>
    </div>
  );
}

interface SortableAttributeRowProps {
  attribute: ProductAttribute;
  index: number;
  onChange: (_index: number, _updated: ProductAttribute) => void;
  onRemove: (_index: number) => void;
  globalAttributes: GlobalAttribute[];
  disabled?: boolean;
  isNew?: boolean;
  isOpen?: boolean;
  onFetchTerms?: (_globalAttrId: string | number) => Promise<AttributeTerm[]>;
  onCreateValue?: (_attr: ProductAttribute) => void;
}

function SortableAttributeRow({
  attribute,
  index,
  onChange,
  onRemove,
  globalAttributes,
  disabled,
  isNew,
  isOpen = true,
  onFetchTerms,
  onCreateValue,
}: SortableAttributeRowProps) {
  const [localOpen, setLocalOpen] = React.useState(isOpen);
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
    id: `attribute-${index}`,
    disabled: isNew,
  });

  React.useEffect(() => {
    const timer = setTimeout(() => {
      setLocalOpen(isOpen);
    }, 0);
    return () => clearTimeout(timer);
  }, [isOpen]);

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
    opacity: isDragging ? 0.5 : 1,
    zIndex: isDragging ? 10 : 1,
  };

  const [attributeType, setAttributeType] = React.useState(() => {
    if (attribute.isGlobal || attribute.useExisting) return "existing";
    return "custom";
  });

  const handleFieldChange = (field: string, value: unknown) => {
    const updated = { ...attribute, [field]: value };
    onChange(index, updated);
  };

  const handleTypeChange = (value: string) => {
    setAttributeType(value);
    if (value === "existing") {
      const updated: ProductAttribute = {
        ...attribute,
        useExisting: true,
        isGlobal: true,
        id: null,
        name: "",
        options: [],
        existingTerms: [],
        _customName: attribute.name || "",
      };
      onChange(index, updated);
    } else {
      const updated: ProductAttribute = {
        ...attribute,
        useExisting: false,
        isGlobal: false,
        id: null,
        name: attribute._customName || "",
        options: attribute.options || [],
        existingTerms: [],
        _customName: "",
      };
      onChange(index, updated);
    }
  };

  const handleGlobalSelect = async (attrId: string) => {
    const selected = globalAttributes.find((a) => a.id?.toString() === attrId?.toString());
    if (!selected) return;

    let existingTerms: AttributeTerm[] = [];

    if (onFetchTerms) {
      try {
        existingTerms = await onFetchTerms(selected.id);
      } catch {
        console.error("Failed to fetch attribute terms");
      }
    }

    const updated: ProductAttribute = {
      ...attribute,
      id: selected.id,
      name: selected.name || selected.label || "",
      label: selected.label || selected.name,
      isGlobal: true,
      useExisting: true,
      options: [],
      existingTerms,
    };
    onChange(index, updated);
  };

  const isGlobal = attributeType === "existing";
  const displayName = isGlobal
    ? attribute.name || attribute.label || "Select attribute"
    : attribute.name || "Attribute name";
  const optionsText = (attribute.options || []).join(", ");
  const existingTerms = attribute.existingTerms || [];

  return (
    <div
      ref={setNodeRef}
      style={style}
      className={cn(
        "border rounded-lg bg-card",
        isDragging && "shadow-lg",
        isNew && "border-primary/50",
      )}
    >
      <div className="flex items-center gap-2 p-3">
        {!isNew && (
          <div
            {...attributes}
            {...listeners}
            className="cursor-grab active:cursor-grabbing touch-none"
          >
            <GripVertical className="h-4 w-4 text-muted-foreground" />
          </div>
        )}

        <Collapsible open={localOpen} onOpenChange={setLocalOpen}>
          <CollapsibleTrigger asChild>
            <div className="cursor-pointer">
              {localOpen ? (
                <ChevronDown className="h-4 w-4" />
              ) : (
                <ChevronRight className="h-4 w-4" />
              )}
            </div>
          </CollapsibleTrigger>
        </Collapsible>

        <div className="flex-1 min-w-0">
          <span className="font-medium truncate">
            {displayName}
            {isGlobal && <span className="ml-2 text-xs text-muted-foreground">(Global)</span>}
          </span>
          {optionsText && (
            <span className="ml-2 text-xs text-muted-foreground truncate">: {optionsText}</span>
          )}
        </div>

        <AlertDialog>
          <AlertDialogTrigger asChild>
            <Button
              type="button"
              variant="ghost"
              size="icon"
              disabled={disabled}
              className="text-destructive hover:text-destructive"
            >
              <Trash2 className="h-4 w-4" />
            </Button>
          </AlertDialogTrigger>
          <AlertDialogContent>
            <AlertDialogHeader>
              <AlertDialogTitle>Delete Attribute</AlertDialogTitle>
              <AlertDialogDescription>
                Are you sure you want to remove this attribute? This cannot be undone.
              </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
              <AlertDialogCancel>Cancel</AlertDialogCancel>
              <AlertDialogAction
                onClick={() => onRemove(index)}
                className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
              >
                Delete
              </AlertDialogAction>
            </AlertDialogFooter>
          </AlertDialogContent>
        </AlertDialog>
      </div>

      {localOpen && (
        <div className="px-4 pb-4 pt-2 border-t space-y-4">
          <div className="space-y-2">
            <Label>Attribute Type</Label>
            <Select value={attributeType} onValueChange={handleTypeChange} disabled={disabled}>
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="existing">Global Attribute</SelectItem>
                <SelectItem value="custom">Custom Attribute</SelectItem>
              </SelectContent>
            </Select>
          </div>

          {isGlobal ? (
            !attribute.id ? (
              <div className="space-y-2">
                <Label>Select Global Attribute</Label>
                <Select value="" onValueChange={handleGlobalSelect} disabled={disabled}>
                  <SelectTrigger>
                    <SelectValue placeholder="Choose an attribute..." />
                  </SelectTrigger>
                  <SelectContent>
                    {globalAttributes.map((attr) => (
                      <SelectItem key={attr.id} value={String(attr.id)}>
                        {attr.label || attr.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            ) : (
              <div className="space-y-2">
                <Label>Value(s)</Label>

                {existingTerms.length > 0 && (
                  <AttributeCombobox
                    existingTerms={existingTerms}
                    selectedOptions={attribute.options || []}
                    onChange={(newOptions: string[]) => handleFieldChange("options", newOptions)}
                    disabled={disabled}
                  />
                )}

                {existingTerms.length === 0 && (
                  <>
                    <Input
                      value={optionsText}
                      onChange={(e: React.ChangeEvent<HTMLInputElement>) => {
                        const values = e.target.value
                          .split(",")
                          .map((v) => v.trim())
                          .filter(Boolean);
                        handleFieldChange("options", values);
                      }}
                      placeholder="Enter values separated by commas (e.g., Red, Blue, Green)"
                      disabled={disabled}
                      className="flex-1"
                    />
                    <p className="text-xs text-muted-foreground">
                      Enter values separated by commas. These will be available for variations.
                    </p>
                  </>
                )}

                {isGlobal && attribute.id && onCreateValue && (
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => onCreateValue(attribute)}
                    disabled={disabled}
                    className="mt-2"
                    title="Create new value"
                  >
                    <Plus className="h-4 w-4 mr-1" />
                    Create new value
                  </Button>
                )}
              </div>
            )
          ) : (
            <div className="space-y-2">
              <Label>Name</Label>
              <Input
                value={attribute.name || ""}
                onChange={(e: React.ChangeEvent<HTMLInputElement>) =>
                  handleFieldChange("name", e.target.value)
                }
                placeholder="e.g., Color, Size, Material"
                disabled={disabled}
              />
            </div>
          )}

          {!isGlobal && (
            <div className="space-y-2">
              <Label>Value(s)</Label>
              <Input
                value={optionsText}
                onChange={(e: React.ChangeEvent<HTMLInputElement>) => {
                  const values = e.target.value
                    .split(",")
                    .map((v) => v.trim())
                    .filter(Boolean);
                  handleFieldChange("options", values);
                }}
                placeholder="Enter values separated by commas (e.g., Small, Medium, Large)"
                disabled={disabled}
              />
              <p className="text-xs text-muted-foreground">
                Enter values separated by commas. These will be available for variations.
              </p>
            </div>
          )}

          <div className="flex flex-col gap-3">
            <div className="flex items-center gap-2">
              <Checkbox
                id={`visible-${index}`}
                checked={attribute.visible !== false}
                onCheckedChange={(checked) => handleFieldChange("visible", checked)}
                disabled={disabled}
              />
              <Label htmlFor={`visible-${index}`} className="text-sm font-normal">
                Visible on the product page
              </Label>
            </div>

            <div className="flex items-center gap-2">
              <Checkbox
                id={`variation-${index}`}
                checked={attribute.variation === true}
                onCheckedChange={(checked) => handleFieldChange("variation", checked)}
                disabled={disabled}
              />
              <Label htmlFor={`variation-${index}`} className="text-sm font-normal">
                Used for variations
              </Label>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

interface AttributeSelectorProps {
  label?: string;
  description?: string;
  value?: ProductAttribute[];
  onChange?: (_attributes: ProductAttribute[]) => void;
  onSave?: (_attributes: ProductAttribute[]) => Promise<void>;
  globalAttributes?: GlobalAttribute[];
  onFetchTerms?: (_globalAttrId: string | number) => Promise<AttributeTerm[]>;
  onAddValue?: (_attrId: string | number, _value: string, _attrName?: string) => Promise<{ success: boolean; error?: string }>;
  canCreate?: boolean;
  maxItems?: number;
  disabled?: boolean;
}

export function AttributeSelector({
  label,
  description,
  value = [],
  onChange,
  onSave,
  globalAttributes = [],
  onFetchTerms,
  onAddValue,
  canCreate = true,
  maxItems = 10,
  disabled = false,
}: AttributeSelectorProps) {
  const [localValue, setLocalValue] = React.useState<ProductAttribute[]>(value);
  const [isSaving, setIsSaving] = React.useState(false);
  const [allOpen, setAllOpen] = React.useState(true);
  const [showCreateValueDialog, setShowCreateValueDialog] = React.useState(false);
  const [createValueForAttr, setCreateValueForAttr] = React.useState<ProductAttribute | null>(null);
  const [, setShowAddNew] = React.useState(false);

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

  React.useEffect(() => {
    const timer = setTimeout(() => {
      setLocalValue(value);
    }, 0);
    return () => clearTimeout(timer);
  }, [value]);

  const handleDragEnd = React.useCallback(
    (event: { active: { id: UniqueIdentifier }; over: { id: UniqueIdentifier } | null }) => {
      const { active, over } = event;
      if (over && active.id !== over.id) {
        const oldIndex = localValue.findIndex((_, i) => `attribute-${i}` === active.id);
        const newIndex = localValue.findIndex((_, i) => `attribute-${i}` === over.id);
        if (oldIndex !== -1 && newIndex !== -1) {
          const newValue = arrayMove(localValue, oldIndex, newIndex);
          setLocalValue(newValue);
          onChange?.(newValue);
        }
      }
    },
    [localValue, onChange],
  );

  const handleAddNew = React.useCallback(() => {
    if (localValue.length >= maxItems) return;

    const newAttribute: ProductAttribute = {
      id: null,
      name: "",
      label: "",
      options: [],
      visible: true,
      variation: false,
      isGlobal: false,
      useExisting: false,
      _isNew: true,
    };

    const newValue = [...localValue, newAttribute];
    setLocalValue(newValue);
    onChange?.(newValue);
    setShowAddNew(true);
  }, [localValue, maxItems, onChange]);

  const handleAddExisting = React.useCallback(() => {
    if (localValue.length >= maxItems) return;

    const newAttribute: ProductAttribute = {
      id: null,
      name: "",
      label: "",
      options: [],
      visible: true,
      variation: false,
      isGlobal: true,
      useExisting: true,
      _isNew: true,
    };

    const newValue = [...localValue, newAttribute];
    setLocalValue(newValue);
    onChange?.(newValue);
    setShowAddNew(true);
  }, [localValue, maxItems, onChange]);

  const handleChange = React.useCallback(
    (index: number, updated: ProductAttribute) => {
      setLocalValue((prev) => {
        const currentItem = prev[index];
        if (JSON.stringify(currentItem) === JSON.stringify(updated)) {
          return prev;
        }
        updated._isNew = false;
        const newValue = prev.map((item, i) => (i === index ? updated : item));
        onChange?.(newValue);
        return newValue;
      });
    },
    [onChange],
  );

  const handleRemove = React.useCallback(
    (index: number) => {
      const newValue = localValue.filter((_, i) => i !== index);
      setLocalValue(newValue);
      onChange?.(newValue);
    },
    [localValue, onChange],
  );

  const handleSave = React.useCallback(async () => {
    setIsSaving(true);
    try {
      if (onSave) {
        await onSave(localValue);
      } else {
        onChange?.(localValue);
      }
      toast.success("Attributes saved successfully");
    } catch (error: unknown) {
      console.error("Failed to save attributes:", error);
      toast.error("Failed to save attributes");
    } finally {
      setIsSaving(false);
    }
  }, [localValue, onChange, onSave]);

  const itemIds = React.useMemo(() => localValue.map((_, i) => `attribute-${i}`), [localValue]);

  const hasChanges = JSON.stringify(localValue) !== JSON.stringify(value);

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div>
          {label && <Label className="text-base font-medium">{label}</Label>}
          {description && <p className="text-sm text-muted-foreground">{description}</p>}
        </div>
        {localValue.length > 0 && (
          <div className="flex items-center gap-1">
            <Button
              type="button"
              variant="ghost"
              size="sm"
              onClick={() => setAllOpen(true)}
              disabled={disabled}
            >
              <ChevronDown className="h-4 w-4 mr-1" />
              Expand
            </Button>
            <Button
              type="button"
              variant="ghost"
              size="sm"
              onClick={() => setAllOpen(false)}
              disabled={disabled}
            >
              <ChevronUp className="h-4 w-4 mr-1" />
              Close
            </Button>
          </div>
        )}
      </div>

      {showCreateValueDialog && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div className="bg-background border rounded-lg p-6 w-full max-w-md shadow-lg">
            <h3 className="text-lg font-semibold mb-4">Create New Value</h3>
            <p className="text-sm text-muted-foreground mb-4">
              Add a new value for: <strong>{createValueForAttr?.name}</strong>
            </p>
            <Input
              id="new_term_value"
              placeholder="Enter value name (e.g., XL, Red, Cotton)"
              className="mb-4"
              autoFocus
            />
            <div className="flex justify-end gap-2">
              <Button
                variant="outline"
                onClick={() => {
                  setShowCreateValueDialog(false);
                  setCreateValueForAttr(null);
                }}
              >
                Cancel
              </Button>
              <Button
                onClick={async () => {
                  const input = document.querySelector<HTMLInputElement>("#new_term_value");
                  const newValue = input?.value.trim();
                  if (!newValue) {
                    toast.error("Please enter a value name");
                    return;
                  }

                  if (!createValueForAttr?.id) {
                    toast.error("Invalid attribute for term creation");
                    return;
                  }

                  if (onAddValue) {
                    try {
                      const result = await onAddValue(createValueForAttr.id, newValue, createValueForAttr.name);
                      if (result.success) {
                        const currentOptions = createValueForAttr?.options || [];
                        const updatedOptions = [...currentOptions, newValue];
                        const attrIndex = localValue.findIndex(
                          (a) => a.name === createValueForAttr?.name,
                        );
                        if (attrIndex !== -1 && createValueForAttr) {
                          const newLocal = [...localValue];
                          const existing = newLocal[attrIndex];
                          newLocal[attrIndex] = {
                            ...existing,
                            options: updatedOptions,
                            existingTerms: [
                              ...(existing.existingTerms || []),
                              { id: Date.now(), name: newValue },
                            ],
                          };
                          setLocalValue(newLocal);
                          onChange?.(newLocal);
                        }
                        toast.success(`Created value "${newValue}"`);
                        setShowCreateValueDialog(false);
                        setCreateValueForAttr(null);
                      } else {
                        toast.error(result.error || "Failed to create value");
                      }
                    } catch {
                      toast.error("Failed to create value");
                    }
                  }
                }}
              >
                Create
              </Button>
            </div>
          </div>
        </div>
      )}

      {localValue.length === 0 ? (
        <div className="text-center py-8 border-2 border-dashed rounded-lg">
          <p className="text-muted-foreground mb-4">
            No attributes yet. Add attributes to create variations for your product.
          </p>
          <div className="flex justify-center gap-2">
            {globalAttributes.length > 0 && (
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={handleAddExisting}
                disabled={disabled}
              >
                <Plus className="h-4 w-4 mr-1" />
                Add existing
              </Button>
            )}
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={handleAddNew}
              disabled={disabled}
            >
              <Plus className="h-4 w-4 mr-1" />
              Add new
            </Button>
          </div>
        </div>
      ) : (
        <>
          <DndContext
            sensors={sensors}
            collisionDetection={closestCenter}
            onDragEnd={handleDragEnd}
          >
            <SortableContext items={itemIds} strategy={verticalListSortingStrategy}>
              <div className="space-y-3">
                {localValue.map((attribute, index) => (
                  <SortableAttributeRow
                    key={`attribute-${index}`}
                    attribute={attribute}
                    index={index}
                    onChange={handleChange}
                    onRemove={handleRemove}
                    globalAttributes={globalAttributes}
                    disabled={disabled}
                    isNew={attribute._isNew}
                    isOpen={allOpen}
                    onFetchTerms={onFetchTerms}
                    onCreateValue={(attr: ProductAttribute) => {
                      setCreateValueForAttr(attr);
                      setShowCreateValueDialog(true);
                    }}
                  />
                ))}
              </div>
            </SortableContext>
          </DndContext>

          {canCreate && localValue.length > 0 && localValue.length < maxItems && (
            <div className="flex items-center gap-2">
              {globalAttributes.length > 0 && (
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  onClick={handleAddExisting}
                  disabled={disabled}
                >
                  <Plus className="h-4 w-4 mr-1" />
                  Add existing
                </Button>
              )}
              <Button
                type="button"
                variant="ghost"
                size="sm"
                onClick={handleAddNew}
                disabled={disabled}
              >
                <Plus className="h-4 w-4 mr-1" />
                Add new
              </Button>
            </div>
          )}

          <div className="flex items-center justify-between pt-2 border-t">
            <p className="text-sm text-muted-foreground">
              {localValue.length} / {maxItems} attributes
              {hasChanges && <span className="ml-2 text-primary">(unsaved changes)</span>}
            </p>
            <Button
              type="button"
              onClick={handleSave}
              disabled={disabled || isSaving || !hasChanges}
            >
              <Save className="h-4 w-4 mr-1" />
              {isSaving ? "Saving..." : "Save attributes"}
            </Button>
          </div>
        </>
      )}
    </div>
  );
}

export default AttributeSelector;
