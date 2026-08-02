import * as React from "react";
import { Copy, Plus, Trash2, GripVertical, ChevronDown, ChevronRight } from "lucide-react";
import {
  DndContext,
  closestCenter,
  KeyboardSensor,
  PointerSensor,
  useSensor,
  useSensors,
  DragOverlay,
  type UniqueIdentifier,
} from "@dnd-kit/core";
import {
  arrayMove,
  SortableContext,
  sortableKeyboardCoordinates,
  useSortable,
  verticalListSortingStrategy,
} from "@dnd-kit/sortable";
import { CSS } from "@dnd-kit/utilities";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Textarea } from "@/Components/ui/textarea";
import { Label } from "@/Components/ui/label";
import { Switch } from "@/Components/ui/switch";
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from "@/Components/ui/collapsible";
import { PhoneInput } from "@/Components/shared/phone-input";
import { UrlInput } from "@/Components/shared/url-input";
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

interface ConfirmDeleteButtonProps {
  onConfirm?: () => void;
  disabled?: boolean;
  children: React.ReactNode;
}

interface RepeaterField {
  name: string;
  label: string;
  type?: string;
  placeholder?: string;
  required?: boolean;
  rows?: number;
  fullWidth?: boolean;
  presetMode?: boolean;
  presetOtherValue?: string;
  customPlaceholder?: string;
  options?: Array<{ value: string; label: string }>;
}

interface SortableRepeaterItemProps {
  id: string | number;
  index: number;
  fields: RepeaterField[];
  item: Record<string, unknown>;
  onChange: (_index: number, _item: Record<string, unknown>) => void;
  onRemove: (_index: number) => void;
  onDuplicate: (_index: number) => void;
  disabled?: boolean;
  errors?: Record<string, string>;
}

interface SortableAccordionItemProps {
  id: string | number;
  index: number;
  fields: RepeaterField[];
  item: Record<string, unknown>;
  onChange: (_index: number, _item: Record<string, unknown>) => void;
  onRemove: (_index: number) => void;
  onDuplicate: (_index: number) => void;
  disabled?: boolean;
  titleField?: string | null;
  showFieldsInTitle?: string[];
  defaultOpen?: boolean;
  isOpen?: boolean;
  onToggle?: (_open: boolean) => void;
  errors?: Record<string, string>;
}

interface DragOverlayItemProps {
  fields: RepeaterField[];
  item: Record<string, unknown>;
  titleField?: string | null;
}

export interface RepeaterProps {
  label?: string;
  description?: string;
  value?: Array<Record<string, unknown>>;
  onChange: (_value: Array<Record<string, unknown>>) => void;
  fields?: RepeaterField[];
  maxItems?: number;
  minItems?: number;
  disabled?: boolean;
  enableDragOverlay?: boolean;
  accordionMode?: boolean;
  titleField?: string | null;
  showFieldsInTitle?: string[];
  accordionDefaultOpen?: boolean;
  uiState?: Record<string, boolean>;
  onUiStateChange?: (_state: Record<string, boolean>) => void;
  errors?: Record<string, string>;
}

function ConfirmDeleteButton({ onConfirm, disabled, children }: ConfirmDeleteButtonProps) {
  const [open, setOpen] = React.useState(false);

  const handleConfirm = () => {
    if (onConfirm) {
      onConfirm();
    }
    setOpen(false);
  };

  return (
    <AlertDialog open={open} onOpenChange={setOpen}>
      <AlertDialogTrigger render={<span />}>{children}</AlertDialogTrigger>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>Delete Item</AlertDialogTitle>
          <AlertDialogDescription>
            Are you sure you want to delete this item? This action cannot be undone.
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel>Cancel</AlertDialogCancel>
          <AlertDialogAction
            onClick={handleConfirm}
            disabled={disabled}
            className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
          >
            Delete
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  );
}

function SortableRepeaterItem({
  id,
  index,
  fields,
  item,
  onChange,
  onRemove,
  onDuplicate,
  disabled,
  errors = {},
}: SortableRepeaterItemProps) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
    id,
  });

  const style: React.CSSProperties = {
    transform: CSS.Transform.toString(transform),
    transition,
    opacity: isDragging ? 0.5 : 1,
    zIndex: isDragging ? 1000 : 1,
    position: "relative" as const,
  };

  const handleFieldChange = (fieldName: string, value: string | boolean) => {
    onChange(index, { ...item, [fieldName]: value });
  };

  return (
    <div
      ref={setNodeRef}
      style={style}
      className={`flex items-start gap-3 p-4 border rounded-lg bg-card min-w-0 ${isDragging ? "shadow-lg ring-2 ring-primary" : ""}`}
    >
      <button
        type="button"
        {...attributes}
        {...listeners}
        className="pt-2 text-muted-foreground cursor-grab hover:text-foreground active:cursor-grabbing touch-none p-1 rounded"
        title="Drag to reorder"
        aria-label="Drag to reorder"
      >
        <GripVertical className="h-5 w-5" />
      </button>

      <div className="flex-1 grid grid-cols-1 sm:grid-cols-2 gap-4">
        {fields.map((field: RepeaterField) => {
          if (field.type === "boolean") {
            return (
              <div key={field.name} className="sm:col-span-2 flex items-center gap-3">
                <Switch
                  id={`${field.name}-${index}`}
                  checked={item[field.name] === true}
                  onCheckedChange={(checked: boolean) => handleFieldChange(field.name, checked)}
                  disabled={disabled}
                />
                <Label htmlFor={`${field.name}-${index}`}>{field.label}</Label>
              </div>
            );
          }
          const fieldName = field.name;
          const indexStr = String(index);
          const matchingKey = Object.keys(errors).find((k) => {
            const parts = k.split(".");
            const lastPart = parts.at(-1);
            const hasIndex = parts.some((part, idx) => idx > 0 && part === indexStr);
            return lastPart === fieldName && hasIndex;
          });
          const error = matchingKey ? errors[matchingKey] : undefined;
          return (
            <div key={field.name} className={`space-y-2 ${field.fullWidth ? "sm:col-span-2" : ""}`}>
              <Label htmlFor={`${field.name}-${index}`}>
                {field.label}
                {field.required && <span className="text-destructive ml-1">*</span>}
              </Label>
              {field.type === "textarea" ? (
                <Textarea
                  id={`${field.name}-${index}`}
                  value={String(item[field.name] ?? "")}
                  onChange={(e) => handleFieldChange(field.name, e.target.value)}
                  placeholder={field.placeholder || ""}
                  disabled={disabled}
                  rows={field.rows || 3}
                  required={field.required}
                />
              ) : field.type === "number" ? (
                <Input
                  id={`${field.name}-${index}`}
                  type="number"
                  value={String(item[field.name] ?? "")}
                  onChange={(e) => handleFieldChange(field.name, e.target.value)}
                  placeholder={field.placeholder || ""}
                  disabled={disabled}
                  required={field.required}
                />
              ) : field.type === "phone" ? (
                <PhoneInput
                  value={String(item[field.name] ?? "")}
                  onChange={(value: string) => handleFieldChange(field.name, value || "")}
                />
              ) : field.type === "url" ? (
                <UrlInput
                  value={String(item[field.name] ?? "")}
                  onChange={(value: string) => handleFieldChange(field.name, value)}
                  placeholder={field.placeholder || "https://example.com"}
                  error={error}
                />
              ) : field.type === "date" ? (
                <Input
                  id={`${field.name}-${index}`}
                  type="date"
                  value={String(item[field.name] ?? "")}
                  onChange={(e) => handleFieldChange(field.name, e.target.value)}
                  disabled={disabled}
                  required={field.required}
                />
              ) : field.type === "select" ? (
                <div className="space-y-2">
                  <Select
                    value={String(item[field.name] ?? "")}
                    onValueChange={(value) => {
                      const updated = { ...item, [field.name]: value };
                      if (field.presetMode && value !== (field.presetOtherValue || "Other")) {
                        delete updated[field.name + "_custom"];
                      }
                      onChange(index, updated);
                    }}
                    disabled={disabled}
                    required={field.required}
                  >
                    <SelectTrigger
                      id={`${field.name}-${index}`}
                      className="w-full"
                      aria-invalid={!!error}
                    >
                      <SelectValue placeholder={field.placeholder || "Select..."} />
                    </SelectTrigger>
                    <SelectContent>
                      {field.options?.map((option: { value: string; label: string }) => (
                        <SelectItem key={option.value} value={option.value}>
                          {option.label}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  {field.presetMode && item[field.name] === (field.presetOtherValue || "Other") && (
                    <Input
                      id={`${field.name}-custom-${index}`}
                      type="text"
                      value={String(item[field.name + "_custom"] ?? "")}
                      onChange={(e) => {
                        onChange(index, { ...item, [field.name + "_custom"]: e.target.value });
                      }}
                      placeholder={field.customPlaceholder || "Enter custom platform name"}
                      disabled={disabled}
                    />
                  )}
                </div>
              ) : (
                <Input
                  id={`${field.name}-${index}`}
                  type="text"
                  value={String(item[field.name] ?? "")}
                  onChange={(e) => handleFieldChange(field.name, e.target.value)}
                  placeholder={field.placeholder || ""}
                  disabled={disabled}
                  required={field.required}
                  aria-invalid={!!error}
                />
              )}
              {error && <p className="text-sm text-destructive">{error}</p>}
            </div>
          );
        })}
      </div>

      <Button
        type="button"
        variant="ghost"
        size="icon"
        disabled={disabled}
        onClick={() => onDuplicate(index)}
        className="mt-8"
        title="Duplicate"
      >
        <Copy className="h-4 w-4" />
      </Button>

      <ConfirmDeleteButton onConfirm={() => onRemove(index)} disabled={disabled}>
        <Button
          type="button"
          variant="ghost"
          size="icon"
          disabled={disabled}
          className="text-destructive hover:text-destructive mt-8"
        >
          <Trash2 className="h-4 w-4" />
        </Button>
      </ConfirmDeleteButton>
    </div>
  );
}

function SortableAccordionItem({
  id,
  index,
  fields,
  item,
  onChange,
  onRemove,
  onDuplicate,
  disabled,
  titleField,
  showFieldsInTitle = [],
  defaultOpen = false,
  isOpen: externalIsOpen,
  onToggle,
  errors = {},
}: SortableAccordionItemProps) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
    id,
  });

  const [internalIsOpen, setInternalIsOpen] = React.useState(defaultOpen);
  const controlled = externalIsOpen !== undefined;
  const isOpen = controlled ? externalIsOpen : internalIsOpen;

  const handleOpenChange = React.useCallback(
    (open: boolean) => {
      if (onToggle) {
        onToggle(open);
      }
      if (!controlled) {
        setInternalIsOpen(open);
      }
    },
    [onToggle, controlled],
  );

  const style: React.CSSProperties = {
    transform: CSS.Transform.toString(transform),
    transition,
    opacity: isDragging ? 0.5 : 1,
    zIndex: isDragging ? 1000 : 1,
    position: "relative" as const,
  };

  const handleFieldChange = (fieldName: string, value: string | boolean) => {
    onChange(index, { ...item, [fieldName]: value });
  };

  const titleValue = titleField ? item[titleField] : undefined;
  let title = titleValue ? String(titleValue) : `Item ${index + 1}`;
  if (showFieldsInTitle.length > 0) {
    const additionalValues = showFieldsInTitle
      .map((fieldName) => {
        const value = item[fieldName];
        return value && typeof value === "string" && value.trim() ? value.trim() : null;
      })
      .filter(Boolean);
    if (additionalValues.length > 0) {
      title += ` • ${additionalValues.join(" • ")}`;
    }
  }

  return (
    <div
      ref={setNodeRef}
      style={style}
      className={`border rounded-lg bg-card min-w-0 overflow-hidden ${isDragging ? "shadow-lg ring-2 ring-primary" : ""}`}
    >
      <Collapsible open={isOpen} onOpenChange={handleOpenChange}>
        <div className="flex items-center gap-2 p-3 min-w-0 overflow-hidden">
          <button
            type="button"
            {...attributes}
            {...listeners}
            className="text-muted-foreground cursor-grab hover:text-foreground active:cursor-grabbing touch-none p-1 rounded"
            title="Drag to reorder"
            aria-label={`Drag ${title} to reorder`}
          >
            <GripVertical className="h-5 w-5" />
          </button>

          <CollapsibleTrigger
            render={
              <div className="flex items-center gap-2 px-2 flex-1 min-w-0 overflow-hidden cursor-pointer select-none" />
            }
          >
            {isOpen ? (
              <ChevronDown className="h-4 w-4 shrink-0 text-muted-foreground" />
            ) : (
              <ChevronRight className="h-4 w-4 shrink-0 text-muted-foreground" />
            )}
            <div className="min-w-0 flex-1" title={title}>
              <span className="block font-medium text-foreground text-left truncate">{title}</span>
            </div>
          </CollapsibleTrigger>

          <Button
            type="button"
            variant="ghost"
            size="icon"
            disabled={disabled}
            onClick={() => onDuplicate(index)}
            className="shrink-0"
            title="Duplicate"
          >
            <Copy className="h-4 w-4" />
          </Button>

          <ConfirmDeleteButton onConfirm={() => onRemove(index)} disabled={disabled}>
            <Button
              type="button"
              variant="ghost"
              size="icon"
              disabled={disabled}
              className="text-destructive hover:text-destructive ml-auto shrink-0"
            >
              <Trash2 className="h-4 w-4" />
            </Button>
          </ConfirmDeleteButton>
        </div>

        <CollapsibleContent>
          <div className="px-4 pb-4 pt-2 border-t">
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              {fields.map((field: RepeaterField) => {
                if (field.type === "boolean") {
                  return (
                    <div key={field.name} className="sm:col-span-2 flex items-center gap-3">
                      <Switch
                        id={`${field.name}-${index}`}
                        checked={item[field.name] === true}
                        onCheckedChange={(checked: boolean) =>
                          handleFieldChange(field.name, checked)
                        }
                        disabled={disabled}
                      />
                      <Label htmlFor={`${field.name}-${index}`}>{field.label}</Label>
                    </div>
                  );
                }
                const fieldName = field.name;
                const indexStr = String(index);
                const matchingKey = Object.keys(errors).find((k) => {
                  const parts = k.split(".");
                  const lastPart = parts.at(-1);
                  const hasIndex = parts.some((part, idx) => idx > 0 && part === indexStr);
                  return lastPart === fieldName && hasIndex;
                });
                const error = matchingKey ? errors[matchingKey] : undefined;
                return (
                  <div
                    key={field.name}
                    className={`space-y-2 ${field.fullWidth ? "sm:col-span-2" : ""}`}
                  >
                    <Label htmlFor={`${field.name}-${index}`}>
                      {field.label}
                      {field.required && <span className="text-destructive ml-1">*</span>}
                    </Label>
                    {field.type === "textarea" ? (
                      <Textarea
                        id={`${field.name}-${index}`}
                        value={String(item[field.name] ?? "")}
                        onChange={(e) => handleFieldChange(field.name, e.target.value)}
                        placeholder={field.placeholder || ""}
                        disabled={disabled}
                        rows={field.rows || 3}
                        required={field.required}
                      />
                    ) : field.type === "number" ? (
                      <Input
                        id={`${field.name}-${index}`}
                        type="number"
                        value={String(item[field.name] ?? "")}
                        onChange={(e) => handleFieldChange(field.name, e.target.value)}
                        placeholder={field.placeholder || ""}
                        disabled={disabled}
                        required={field.required}
                      />
                    ) : field.type === "phone" ? (
                      <PhoneInput
                        value={String(item[field.name] ?? "")}
                        onChange={(value: string) => handleFieldChange(field.name, value || "")}
                      />
                    ) : field.type === "url" ? (
                      <UrlInput
                        id={`${field.name}-${index}`}
                        value={String(item[field.name] ?? "")}
                        onChange={(value: string) => handleFieldChange(field.name, value)}
                        placeholder={field.placeholder || "https://example.com"}
                        disabled={disabled}
                        error={error}
                      />
                    ) : field.type === "date" ? (
                      <Input
                        id={`${field.name}-${index}`}
                        type="date"
                        value={String(item[field.name] ?? "")}
                        onChange={(e) => handleFieldChange(field.name, e.target.value)}
                        disabled={disabled}
                        required={field.required}
                      />
                    ) : field.type === "select" ? (
                      <div className="space-y-2">
                        <Select
                          value={String(item[field.name] ?? "")}
                          onValueChange={(value) => {
                            const updated = { ...item, [field.name]: value };
                            if (field.presetMode && value !== (field.presetOtherValue || "Other")) {
                              delete updated[field.name + "_custom"];
                            }
                            onChange(index, updated);
                          }}
                          disabled={disabled}
                          required={field.required}
                        >
                          <SelectTrigger
                            id={`${field.name}-${index}`}
                            className="w-full"
                            aria-invalid={!!error}
                          >
                            <SelectValue placeholder={field.placeholder || "Select..."} />
                          </SelectTrigger>
                          <SelectContent>
                            {field.options?.map((option: { value: string; label: string }) => (
                              <SelectItem key={option.value} value={option.value}>
                                {option.label}
                              </SelectItem>
                            ))}
                          </SelectContent>
                        </Select>
                        {field.presetMode &&
                          item[field.name] === (field.presetOtherValue || "Other") && (
                            <Input
                              id={`${field.name}-custom-${index}`}
                              type="text"
                              value={String(item[field.name + "_custom"] ?? "")}
                              onChange={(e) => {
                                onChange(index, {
                                  ...item,
                                  [field.name + "_custom"]: e.target.value,
                                });
                              }}
                              placeholder={field.customPlaceholder || "Enter custom platform name"}
                              disabled={disabled}
                            />
                          )}
                      </div>
                    ) : (
                      <Input
                        id={`${field.name}-${index}`}
                        type="text"
                        required={field.required}
                        value={String(item[field.name] ?? "")}
                        onChange={(e) => handleFieldChange(field.name, e.target.value)}
                        placeholder={field.placeholder || ""}
                        disabled={disabled}
                        aria-invalid={!!error}
                      />
                    )}
                    {error && <p className="text-sm text-destructive">{error}</p>}
                  </div>
                );
              })}
            </div>
          </div>
        </CollapsibleContent>
      </Collapsible>
    </div>
  );
}

function DragOverlayItem({ fields, item, titleField }: DragOverlayItemProps) {
  const rawTitle = titleField ? item[titleField] : undefined;
  const title = rawTitle ? String(rawTitle) : "(empty)";

  return (
    <div className="flex items-start gap-3 p-4 border rounded-lg bg-card shadow-lg ring-2 ring-primary opacity-90">
      <div className="pt-2 text-muted-foreground cursor-grabbing">
        <GripVertical className="h-5 w-5" />
      </div>
      <div className="flex-1">
        <p className="font-medium text-foreground mb-2">{title}</p>
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          {fields.map((field: RepeaterField) => (
            <div key={field.name} className="space-y-2">
              <Label>{field.label}</Label>
              <div className="p-2 bg-muted rounded text-muted-foreground text-sm">
                {String(item[field.name] ?? field.placeholder ?? "(empty)")}
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}

export function Repeater({
  label,
  description,
  value = [],
  onChange,
  fields = [],
  maxItems = 20,
  minItems = 0,
  disabled = false,
  enableDragOverlay = false,
  accordionMode = true,
  titleField = null,
  showFieldsInTitle = [],
  accordionDefaultOpen = false,
  uiState,
  onUiStateChange,
  errors = {},
}: RepeaterProps) {
  const [localValue, setLocalValue] = React.useState(value);
  const [activeId, setActiveId] = React.useState<string | null>(null);

  const [localOpenItems, setLocalOpenItems] = React.useState<Record<string, boolean>>({});
  const isDraggingRef = React.useRef<boolean>(false);

  const onUiStateChangeRef = React.useRef<((_state: Record<string, boolean>) => void) | undefined>(
    onUiStateChange,
  );
  React.useEffect(() => {
    onUiStateChangeRef.current = onUiStateChange;
  });

  const controlled = onUiStateChange !== undefined;
  const openItems = React.useMemo(
    () => (controlled ? uiState || {} : localOpenItems),
    [controlled, uiState, localOpenItems],
  );

  const handleToggleAccordionItem = React.useCallback(
    (index: number, forceOpen?: boolean) => {
      if (controlled) {
        const current = openItems[index];
        const nextOpen = forceOpen !== undefined ? forceOpen : !current;
        onUiStateChangeRef.current?.({
          ...openItems,
          [index]: nextOpen,
        });
      } else {
        setLocalOpenItems((prev) => {
          const prevIsOpen = prev[index];
          const nextOpen =
            forceOpen !== undefined
              ? forceOpen
              : !(prevIsOpen && Object.hasOwn(prev, String(index)));
          return { ...prev, [index]: nextOpen };
        });
      }
    },
    [controlled, openItems],
  );

  const sensors = useSensors(
    useSensor(PointerSensor, {
      activationConstraint: { distance: 8 },
    }),
    useSensor(KeyboardSensor, {
      coordinateGetter: sortableKeyboardCoordinates,
    }),
  );

  React.useEffect(() => {
    if (isDraggingRef.current) return;
    const timer = setTimeout(() => {
      setLocalValue(value);
    }, 0);
    return () => clearTimeout(timer);
  }, [value]);

  React.useEffect(() => {
    if (isDraggingRef.current) return;
    if (accordionMode && !controlled) {
      const timer = setTimeout(() => {
        setLocalOpenItems((prev) => {
          const newState: Record<string, boolean> = {};
          for (const [index] of localValue.entries()) {
            newState[index] = prev[index] !== undefined ? prev[index] : accordionDefaultOpen;
          }
          return newState;
        });
      }, 0);
      return () => clearTimeout(timer);
    }
  }, [localValue, accordionMode, accordionDefaultOpen, controlled]);

  const handleAdd = React.useCallback(() => {
    if (localValue.length >= maxItems) return;

    const itemToAdd: Record<string, unknown> = {};
    for (const field of fields) {
      itemToAdd[field.name] = "";
    }

    const newIndex = localValue.length;
    const newValue = [...localValue, itemToAdd];
    setLocalValue(newValue);
    onChange(newValue);

    if (accordionMode) {
      const newState: Record<string, boolean> = {};
      for (const index of newValue.keys()) {
        newState[index] = index === newIndex;
      }

      if (controlled) {
        onUiStateChangeRef.current?.(newState);
      } else {
        setLocalOpenItems(newState);
      }
    }
  }, [localValue, fields, maxItems, onChange, accordionMode, controlled]);

  const handleRemove = React.useCallback(
    (index: number) => {
      if (localValue.length <= minItems) return;

      const newValue = localValue.filter((_, i) => i !== index);
      setLocalValue(newValue);
      onChange(newValue);

      if (accordionMode) {
        if (controlled) {
          const newState: Record<string, boolean> = {};
          for (const [key, value] of Object.entries(openItems)) {
            const keyIndex = Number.parseInt(key);
            if (keyIndex < index) {
              newState[keyIndex] = value ?? false;
            } else if (keyIndex > index) {
              newState[keyIndex - 1] = value ?? false;
            }
          }
          onUiStateChangeRef.current?.(newState);
        } else {
          setLocalOpenItems((prev) => {
            const newState: Record<string, boolean> = {};
            for (const [key, value] of Object.entries(prev)) {
              const keyIndex = Number.parseInt(key);
              if (keyIndex < index) {
                newState[keyIndex] = value ?? false;
              } else if (keyIndex > index) {
                newState[keyIndex - 1] = value ?? false;
              }
            }
            return newState;
          });
        }
      }
    },
    [localValue, minItems, onChange, accordionMode, controlled, openItems],
  );

  const handleChange = React.useCallback(
    (index: number, updatedItem: Record<string, unknown>) => {
      const newValue = localValue.map((item, i) => (i === index ? updatedItem : item));
      setLocalValue(newValue);
      onChange(newValue);
    },
    [localValue, onChange],
  );

  const handleDuplicate = React.useCallback(
    (index: number) => {
      if (localValue.length >= maxItems) return;

      const itemToDuplicate = localValue[index];
      const clonedItem = structuredClone(itemToDuplicate!);

      const newValue = [...localValue];
      newValue.splice(index + 1, 0, clonedItem);
      setLocalValue(newValue);
      onChange(newValue);

      if (accordionMode) {
        const newState: Record<string, boolean> = {};
        for (const i of newValue.keys()) {
          if (i === index + 1) {
            newState[i] = true;
          } else if (i < index + 1) {
            newState[i] = controlled
              ? (openItems[i] ?? accordionDefaultOpen)
              : (localOpenItems[i] ?? accordionDefaultOpen);
          } else {
            const originalIndex = i - 1;
            newState[i] = controlled
              ? (openItems[originalIndex] ?? accordionDefaultOpen)
              : (localOpenItems[originalIndex] ?? accordionDefaultOpen);
          }
        }
        if (controlled) {
          onUiStateChangeRef.current?.(newState);
        } else {
          setLocalOpenItems(newState);
        }
      }
    },
    [
      localValue,
      maxItems,
      onChange,
      accordionMode,
      controlled,
      openItems,
      localOpenItems,
      accordionDefaultOpen,
    ],
  );

  const handleDragStart = React.useCallback((event: { active: { id: UniqueIdentifier } }) => {
    isDraggingRef.current = true;
    setActiveId(String(event.active.id));
  }, []);

  const handleDragEnd = React.useCallback(
    (event: { active: { id: UniqueIdentifier }; over: { id: UniqueIdentifier } | null }) => {
      const { active, over } = event;

      isDraggingRef.current = false;
      setActiveId(null);

      if (!over) return;

      const activeIndex = localValue.findIndex((_, i) => String(i) === String(active.id));
      const overIndex = localValue.findIndex((_, i) => String(i) === String(over.id));

      if (activeIndex !== overIndex) {
        const newValue = arrayMove(localValue, activeIndex, overIndex);
        setLocalValue(newValue);
        onChange(newValue);

        if (accordionMode) {
          const sourceStates = controlled ? openItems : localOpenItems;
          const openStatesArray = localValue.map((_, i) => sourceStates[i] ?? accordionDefaultOpen);
          const movedStates = arrayMove(openStatesArray, activeIndex, overIndex);
          const newOpenItems = Object.fromEntries(
            movedStates.map((state, i) => [i, state]),
          ) as Record<string, boolean>;

          if (controlled) {
            onUiStateChangeRef.current?.(newOpenItems);
          } else {
            setLocalOpenItems(newOpenItems);
          }
        }
      }
    },
    [
      localValue,
      onChange,
      accordionMode,
      controlled,
      openItems,
      localOpenItems,
      accordionDefaultOpen,
    ],
  );

  const handleDragCancel = React.useCallback(() => {
    isDraggingRef.current = false;
    setActiveId(null);
  }, []);

  const items = localValue.map((_, index) => String(index));

  const activeItem = activeId
    ? localValue[localValue.findIndex((_, i) => String(i) === String(activeId))] || {}
    : null;

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div>
          {label && <Label className="text-base font-medium">{label}</Label>}
          {description && <p className="text-sm text-muted-foreground">{description}</p>}
        </div>
        <Button
          type="button"
          variant="outline"
          size="sm"
          onClick={handleAdd}
          disabled={disabled || localValue.length >= maxItems}
        >
          <Plus className="h-4 w-4 mr-1" />
          Add
        </Button>
      </div>

      {localValue.length > 0 && (
        <DndContext
          sensors={sensors}
          collisionDetection={closestCenter}
          onDragStart={handleDragStart}
          onDragEnd={handleDragEnd}
          onDragCancel={handleDragCancel}
        >
          <SortableContext items={items} strategy={verticalListSortingStrategy}>
            <div className="space-y-3 touch-none min-w-0">
              {localValue.map((item, index) =>
                accordionMode ? (
                  <SortableAccordionItem
                    key={String(index)}
                    id={String(index)}
                    index={index}
                    fields={fields}
                    item={item}
                    onChange={handleChange}
                    onRemove={handleRemove}
                    onDuplicate={handleDuplicate}
                    disabled={disabled}
                    titleField={titleField}
                    showFieldsInTitle={showFieldsInTitle}
                    defaultOpen={accordionDefaultOpen}
                    isOpen={openItems[index]}
                    onToggle={(open) => handleToggleAccordionItem(index, open)}
                    errors={errors}
                  />
                ) : (
                  <SortableRepeaterItem
                    key={String(index)}
                    id={String(index)}
                    index={index}
                    fields={fields}
                    item={item}
                    onChange={handleChange}
                    onRemove={handleRemove}
                    onDuplicate={handleDuplicate}
                    disabled={disabled}
                    errors={errors}
                  />
                ),
              )}
            </div>
          </SortableContext>

          <DragOverlay>
            {enableDragOverlay && activeItem !== null ? (
              <DragOverlayItem fields={fields} item={activeItem} titleField={titleField} />
            ) : null}
          </DragOverlay>
        </DndContext>
      )}

      {localValue.length === 0 && (
        <div className="text-center py-8 border-2 border-dashed rounded-lg">
          <p className="text-muted-foreground">No items yet. Click "Add" to create one.</p>
        </div>
      )}

      <p className="text-sm text-muted-foreground text-right">
        {localValue.length} / {maxItems} items
        {localValue.length > 1 &&
          (accordionMode ? " • Drag to reorder, click to expand/collapse" : " • Drag to reorder")}
      </p>
    </div>
  );
}

export default Repeater;
