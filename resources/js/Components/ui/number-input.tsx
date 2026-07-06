import * as React from "react";
import { Minus, Plus } from "lucide-react";
import { cn } from "@/lib/utils";
import { Button } from "@/Components/ui/button";

interface NumberInputProps {
  className?: string;
  value?: number;
  defaultValue?: number;
  onValueChange?: (_value: number | string) => void;
  min?: number;
  max?: number;
  step?: number;
  placeholder?: string;
  disabled?: boolean;
  id?: string;
}

const NumberInput = React.forwardRef<HTMLInputElement, NumberInputProps>(
  (
    {
      className,
      value,
      defaultValue,
      onValueChange,
      min = 0,
      max = 999,
      step = 1,
      placeholder = "0",
      disabled = false,
      id,
    },
    ref,
  ) => {
    const [internalValue, setInternalValue] = React.useState<number | string>(defaultValue ?? 0);

    const currentValue = value !== undefined ? value : internalValue;

    const handleIncrement = () => {
      const newValue = Math.min(Number(currentValue) + step, max);
      setInternalValue(newValue);
      onValueChange?.(newValue);
    };

    const handleDecrement = () => {
      const newValue = Math.max(Number(currentValue) - step, min);
      setInternalValue(newValue);
      onValueChange?.(newValue);
    };

    const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
      const raw = e.target.value;
      if (raw === "") {
        setInternalValue("");
        onValueChange?.("");
        return;
      }

      const cleaned = raw.replace(/^0+(?=\d)/, "");
      const numValue = Number(cleaned);
      if (!Number.isNaN(numValue)) {
        const clampedValue = Math.min(Math.max(numValue, min), max);
        setInternalValue(clampedValue);
        onValueChange?.(clampedValue as number);
      }
    };

    return (
      <div className={cn("flex items-center gap-1", className)}>
        <input
          type="number"
          id={id}
          value={currentValue}
          onChange={handleInputChange}
          placeholder={placeholder}
          disabled={disabled}
          min={min}
          max={max}
          ref={ref}
          data-slot="input"
          className={cn(
            "h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none selection:bg-primary selection:text-primary-foreground file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground placeholder:text-muted-foreground disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm dark:bg-input/30 dark:text-foreground",
            "focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50",
            "aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40",
            "w-20 text-center [-moz-appearance:textfield] [&::-webkit-outer-spin-button]:m-0 [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:m-0 [&::-webkit-inner-spin-button]:appearance-none h-9",
            className,
          )}
        />
        <Button
          type="button"
          variant="outline"
          size="icon"
          className="h-9 w-9"
          onClick={handleDecrement}
          disabled={disabled || Number(currentValue) <= min}
          aria-label="Decrease value"
        >
          <Minus className="h-4 w-4" />
        </Button>
        <Button
          type="button"
          variant="outline"
          size="icon"
          className="h-9 w-9"
          onClick={handleIncrement}
          disabled={disabled || Number(currentValue) >= max}
          aria-label="Increase value"
        >
          <Plus className="h-4 w-4" />
        </Button>
      </div>
    );
  },
);
NumberInput.displayName = "NumberInput";

export { NumberInput };
