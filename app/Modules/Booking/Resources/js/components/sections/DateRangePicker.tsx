import React from "react";
import { format } from "date-fns";
import { Calendar as CalendarIcon } from "lucide-react";
import { Calendar } from "@/Components/ui/calendar";
import { Popover, PopoverContent, PopoverTrigger } from "@/Components/ui/popover";
import { Button } from "@/Components/ui/button";
import { cn } from "@/lib/utils";
import type { DateRange } from "react-day-picker";

interface DateRangePickerProps {
  value?: DateRange;
  onChange?: (_range?: DateRange) => void;
  disabled?: boolean;
}

function DateRangePicker({ value, onChange, disabled }: DateRangePickerProps) {
  const displayText = value?.from
    ? value.to
      ? `${format(value.from, "MMM d")} — ${format(value.to, "MMM d, yyyy")}`
      : format(value.from, "MMM d, yyyy")
    : "Pick check-in → check-out";

  const today = React.useMemo(() => {
    const d = new Date();
    d.setHours(0, 0, 0, 0);
    return d;
  }, []);

  return (
    <Popover>
      <PopoverTrigger asChild>
        <Button
          variant="outline"
          disabled={disabled}
          className={cn(
            "w-full justify-start text-left font-normal h-10",
            !value?.from && "text-muted-foreground",
          )}
        >
          <CalendarIcon className="mr-2 h-4 w-4" />
          {displayText}
        </Button>
      </PopoverTrigger>
      <PopoverContent className="w-auto p-0" align="start">
        <Calendar
          mode="range"
          selected={value}
          onSelect={(range: DateRange | undefined) => onChange?.(range)}
          numberOfMonths={2}
          disabled={{ before: today } as any}
        />
        {value?.from && (
          <div className="flex items-center justify-center border-t p-2">
            <Button variant="ghost" size="sm" type="button" onClick={() => onChange?.()}>
              Clear dates
            </Button>
          </div>
        )}
      </PopoverContent>
    </Popover>
  );
}

export default DateRangePicker;
