import { useState, useCallback } from "react";
import { Popover, PopoverContent, PopoverTrigger } from "@/Components/ui/popover";
import { Toggle } from "@/Components/ui/toggle";
import { Input } from "@/Components/ui/input";
import { cn } from "@/lib/utils";
import { Tooltip, TooltipContent, TooltipTrigger } from "@/Components/ui/tooltip";

export const TEXT_COLORS = [
  { label: "Default", value: "" },
  { label: "Black", value: "#000000" },
  { label: "Gray", value: "#6b7280" },
  { label: "Brown", value: "#92400e" },
  { label: "Orange", value: "#c2410c" },
  { label: "Yellow", value: "#a16207" },
  { label: "Green", value: "#15803d" },
  { label: "Blue", value: "#1d4ed8" },
  { label: "Purple", value: "#7c3aed" },
  { label: "Pink", value: "#be185d" },
  { label: "Red", value: "#dc2626" },
  { label: "Teal", value: "#0d9488" },
  { label: "Indigo", value: "#4338ca" },
  { label: "Dark Gray", value: "#374151" },
  { label: "Light Gray", value: "#9ca3af" },
];

export const HIGHLIGHT_COLORS = [
  { label: "Default", value: "" },
  { label: "Gray", value: "#e5e7eb" },
  { label: "Brown", value: "#fde68a" },
  { label: "Orange", value: "#fed7aa" },
  { label: "Yellow", value: "#fef08a" },
  { label: "Green", value: "#bbf7d0" },
  { label: "Blue", value: "#bfdbfe" },
  { label: "Purple", value: "#ddd6fe" },
  { label: "Pink", value: "#fbcfe8" },
  { label: "Red", value: "#fecaca" },
  { label: "Teal", value: "#ccfbf1" },
  { label: "Indigo", value: "#c7d2fe" },
  { label: "Rose", value: "#ffe4e6" },
  { label: "Amber", value: "#fef3c7" },
  { label: "Lime", value: "#ecfccb" },
];

interface ColorPickerProps {
  colors: { label: string; value: string }[];
  currentColor: string;
  onSelect: (_color: string) => void;
  label: string;
  icon: React.ComponentType<{ className?: string }>;
}

export function ColorPicker({
  colors,
  currentColor,
  onSelect,
  label,
  icon: Icon,
}: ColorPickerProps) {
  const [open, setOpen] = useState(false);
  const [hexInput, setHexInput] = useState("");

  const isValidHex = useCallback((hex: string): boolean => {
    return /^#[0-9A-Fa-f]{6}$/i.test(hex);
  }, []);

  const handleHexSubmit = useCallback(() => {
    if (!isValidHex(hexInput)) {
      return;
    }

    onSelect(hexInput);
    setOpen(false);
    setHexInput("");
  }, [hexInput, isValidHex, onSelect]);

  const handleHexKeyDown = useCallback(
    (e: React.KeyboardEvent) => {
      if (e.key !== "Enter") {
        return;
      }

      e.preventDefault();
      handleHexSubmit();
    },
    [handleHexSubmit],
  );

  return (
    <Tooltip>
      <TooltipTrigger
        render={
          <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger render={<Toggle size="sm" variant="outline" aria-label={label} />}>
              <div className="relative">
                <Icon className="h-4 w-4" />
                <div
                  className="absolute -bottom-0.5 left-1/2 h-1 w-2.5 -translate-x-1/2 rounded-full"
                  style={{
                    backgroundColor: currentColor || "hsl(var(--foreground))",
                  }}
                />
              </div>
            </PopoverTrigger>
            <PopoverContent align="start" className="w-auto p-2">
              <div className="mb-2 grid grid-cols-5 gap-1">
                {colors.map((color) => (
                  <button
                    key={color.value || "default"}
                    type="button"
                    title={color.label}
                    onClick={() => {
                      onSelect(color.value);
                      setOpen(false);
                    }}
                    className={cn(
                      "flex h-7 w-7 items-center justify-center rounded-sm border transition-colors hover:ring-2 hover:ring-ring",
                      currentColor === color.value && "ring-2 ring-ring",
                    )}
                  >
                    {color.value ? (
                      <div
                        className="h-5 w-5 rounded-sm"
                        style={{ backgroundColor: color.value }}
                      />
                    ) : (
                      <span className="text-xs text-muted-foreground">A</span>
                    )}
                  </button>
                ))}
              </div>
              <div className="flex gap-1 border-t pt-2">
                <Input
                  type="text"
                  placeholder="#000000"
                  value={hexInput}
                  onChange={(e) => {
                    const val = e.target.value.startsWith("#")
                      ? e.target.value
                      : `#${e.target.value}`;
                    setHexInput(val);
                  }}
                  onKeyDown={handleHexKeyDown}
                  className="h-7 flex-1 text-xs"
                  maxLength={7}
                />
                <button
                  type="button"
                  className="inline-flex h-7 items-center justify-center rounded-md border bg-transparent px-2 text-xs font-medium transition-colors hover:bg-muted disabled:pointer-events-none disabled:opacity-50"
                  disabled={!isValidHex(hexInput)}
                  onClick={handleHexSubmit}
                >
                  Set
                </button>
              </div>
            </PopoverContent>
          </Popover>
        }
      />
      <TooltipContent>{label}</TooltipContent>
    </Tooltip>
  );
}
