import { useState, useMemo, useEffect } from "react";
import { Check } from "lucide-react";
import { cn } from "@/lib/utils";

interface AttributeTerm {
  id: number; name: string; slug: string;
  swatch_value?: string | null;
}

interface AttributeDef {
  id: number; name: string; slug: string;
  display_type?: string;
  terms: AttributeTerm[];
}

interface VariationAttr {
  attribute_id: number; attribute_term_id: number; name: string;
}

interface Variation {
  id: number; sku: string | null; price: string | null;
  compare_price: string | null; sale_price: string | null;
  stock_quantity: number; manage_stock: string; backorders: string;
  image_url: string | null; attributes: VariationAttr[] | null;
  is_active: boolean;
}

interface Props {
  attributes: AttributeDef[];
  variations: Variation[];
  defaultVariation?: Variation | null;
  onVariationChange: (variation: Variation | null) => void;
  onImageChange?: (imageUrl: string | null) => void;
}

const COLOR_NAMES: Record<string, string> = {
  red: "#ef4444", blue: "#3b82f6", green: "#22c55e", yellow: "#eab308",
  black: "#000000", white: "#ffffff", gray: "#6b7280", grey: "#6b7280",
  orange: "#f97316", purple: "#a855f7", pink: "#ec4899", brown: "#92400e",
  navy: "#1e3a5f", teal: "#14b8a6", cyan: "#06b6d4", indigo: "#6366f1",
  lime: "#84cc16", amber: "#f59e0b", emerald: "#10b981", rose: "#f43f5e",
  violet: "#8b5cf6", fuchsia: "#d946ef", sky: "#0ea5e9", slate: "#64748b",
  neutral: "#737373", stone: "#78716c", zinc: "#71717a",
};

function getSwatchColor(name: string, swatchValue?: string | null): string | null {
  if (swatchValue) return swatchValue;
  const lower = name.toLowerCase().trim();
  if (lower.startsWith("#") && /^#[0-9a-f]{3,8}$/i.test(lower)) return lower;
  if (COLOR_NAMES[lower]) return COLOR_NAMES[lower];
  return null;
}

export function AttributeSelector({ attributes, variations, defaultVariation, onVariationChange, onImageChange }: Props) {
  const [selected, setSelected] = useState<Map<number, number>>(new Map());

  useEffect(() => {
    if (defaultVariation?.attributes && attributes.length > 0) {
      const map = new Map<number, number>();
      defaultVariation.attributes.forEach((a) => {
        map.set(a.attribute_id, a.attribute_term_id);
      });
      setSelected(map);
    }
  }, [defaultVariation, attributes]);

  // For each attribute, which terms are available given OTHER selected attributes.
  // This lets users freely switch one attribute (e.g., color) while keeping the other (e.g., size).
  const availableByAttribute = useMemo(() => {
    const result = new Map<number, Set<number>>();
    attributes.forEach((attr) => {
      const available = new Set<number>();
      variations.forEach((v) => {
        if (!v.attributes || !v.is_active) return;
        const matches = v.attributes.every((a) => {
          if (a.attribute_id === attr.id) return true;
          const sel = selected.get(a.attribute_id);
          return sel === undefined || sel === a.attribute_term_id;
        });
        if (matches) {
          v.attributes.forEach((a) => {
            if (a.attribute_id === attr.id) available.add(a.attribute_term_id);
          });
        }
      });
      result.set(attr.id, available);
    });
    return result;
  }, [selected, variations, attributes]);

  // Auto-complete: user selections + single-option defaults, computed synchronously
  const [completedSelections, autoCompleted] = useMemo(() => {
    let changed = false;
    const next = new Map(selected);
    attributes.forEach((attr) => {
      if (next.has(attr.id)) return;
      const compatibleTerms = attr.terms.filter((t) => availableByAttribute.get(attr.id)?.has(t.id) ?? false);
      if (compatibleTerms.length === 1) {
        next.set(attr.id, compatibleTerms[0].id);
        changed = true;
      }
    });
    return [next, changed];
  }, [selected, attributes, availableByAttribute]);

  // Sync auto-completions back to selected state
  useEffect(() => {
    if (autoCompleted) setSelected(completedSelections);
  }, [autoCompleted, completedSelections, setSelected]);

  const matchingVariation = useMemo(() => {
    if (completedSelections.size === 0) return null;
    return variations.find((v) => {
      if (!v.attributes) return false;
      return v.attributes.every((a) => completedSelections.get(a.attribute_id) === a.attribute_term_id);
    }) || null;
  }, [completedSelections, variations]);

  const allSelected = useMemo(() => {
    return attributes.every((attr) => completedSelections.has(attr.id));
  }, [attributes, completedSelections]);

  useEffect(() => {
    onVariationChange(allSelected ? matchingVariation : null);
  }, [matchingVariation, allSelected, onVariationChange]);

  useEffect(() => {
    if (!onImageChange) return;
    const target = allSelected ? matchingVariation : null;
    onImageChange(target?.image_url ?? null);
  }, [matchingVariation, allSelected, onImageChange]);

  const handleSelect = (attributeId: number, termId: number) => {
    setSelected((prev) => {
      if (prev.get(attributeId) === termId) return prev;
      const next = new Map(prev);
      next.set(attributeId, termId);
      return next;
    });
  };

  if (attributes.length === 0) return null;

  return (
    <div className="space-y-4">
      {attributes.map((attr) => {
        const selectedTermId = selected.get(attr.id);
        const displayType = attr.display_type || "text";

        return (
          <div key={attr.id}>
            <p className="text-sm font-medium mb-2">{attr.name}</p>
            <div className="flex flex-wrap gap-2">
              {attr.terms.map((term) => {
                const isSelected = selectedTermId === term.id;
                const isAvailable = availableByAttribute.get(attr.id)?.has(term.id) ?? false;

                if (displayType === "color") {
                  const color = getSwatchColor(term.name, term.swatch_value);
                  return (
                    <button
                      key={term.id}
                      type="button"
                      onClick={() => handleSelect(attr.id, term.id)}
                      disabled={!isAvailable}
                      className={cn(
                        "relative h-10 w-10 rounded-full border-2 transition-all",
                        isSelected ? "border-foreground ring-2 ring-foreground/20 scale-110" : "border-border hover:border-muted-foreground/50",
                        !isAvailable && "opacity-30 cursor-not-allowed",
                        isAvailable && "cursor-pointer",
                      )}
                      style={{ backgroundColor: color || "#e5e7eb" }}
                      title={term.name}
                    >
                      {isSelected && (
                        <span className="absolute inset-0 flex items-center justify-center">
                          <Check className={cn("h-4 w-4", color === "#ffffff" || color === "#fff" ? "text-black" : "text-white")} />
                        </span>
                      )}
                    </button>
                  );
                }

                if (displayType === "image") {
                  return (
                    <button
                      key={term.id}
                      type="button"
                      onClick={() => handleSelect(attr.id, term.id)}
                      disabled={!isAvailable}
                      className={cn(
                        "relative h-10 w-10 rounded-full border-2 bg-cover bg-center transition-all",
                        isSelected ? "border-foreground ring-2 ring-foreground/20 scale-110" : "border-border hover:border-muted-foreground/50",
                        !isAvailable && "opacity-30 cursor-not-allowed",
                        isAvailable && "cursor-pointer",
                      )}
                      style={term.swatch_value ? { backgroundImage: `url(${term.swatch_value})` } : undefined}
                      title={term.name}
                    >
                      {!term.swatch_value && (
                        <span className="absolute inset-0 flex items-center justify-center text-[8px] text-muted-foreground">img</span>
                      )}
                      {isSelected && (
                        <span className="absolute inset-0 flex items-center justify-center">
                          <Check className="h-4 w-4 text-white drop-shadow-[0_1px_1px_rgba(0,0,0,0.5)]" />
                        </span>
                      )}
                    </button>
                  );
                }

                if (displayType === "button") {
                  return (
                    <button
                      key={term.id}
                      type="button"
                      onClick={() => isAvailable && handleSelect(attr.id, term.id)}
                      disabled={!isAvailable}
                      className={cn(
                        "px-3 py-1.5 text-sm font-medium rounded-md border transition-all",
                        isSelected
                          ? "bg-primary text-primary-foreground border-primary"
                          : "bg-background text-foreground border-border hover:border-foreground/50",
                        !isAvailable && "opacity-30 cursor-not-allowed",
                        isAvailable && "cursor-pointer",
                      )}
                    >
                      {term.name}
                    </button>
                  );
                }

                // text (default)
                return (
                  <button
                    key={term.id}
                    type="button"
                    onClick={() => handleSelect(attr.id, term.id)}
                    disabled={!isAvailable}
                    className={cn(
                      "inline-flex items-center rounded-md px-3 py-1.5 text-sm font-medium transition-all border",
                      isSelected
                        ? "bg-primary text-primary-foreground border-primary ring-2 ring-foreground/20"
                        : "bg-background text-foreground border-border hover:border-foreground/50",
                      !isAvailable && "opacity-30 cursor-not-allowed",
                      isAvailable && "cursor-pointer",
                    )}
                  >
                    {isSelected && <Check className="h-3 w-3 mr-1" />}
                    {term.name}
                  </button>
                );
              })}
            </div>
          </div>
        );
      })}
    </div>
  );
}
