import * as React from "react";
import { format } from "date-fns";
import { Calendar } from "@/Components/ui/calendar";
import { Popover, PopoverContent, PopoverTrigger } from "@/Components/ui/popover";
import { Button } from "@/Components/ui/button";
import { cn } from "@/lib/utils";
import { CalendarDays } from "lucide-react";

interface DateRange {
  from: string;
  to: string;
}

interface DateRangePickerProps {
  value?: DateRange;
  onChange?: (_value: DateRange) => void;
}

function DateRangePicker({ value, onChange }: DateRangePickerProps) {
  const [isOpen, setIsOpen] = React.useState(false);

  const fromDate = React.useMemo(
    () => (value?.from ? new Date(value.from + "T00:00:00") : undefined),
    [value],
  );
  const toDate = React.useMemo(
    () => (value?.to ? new Date(value.to + "T00:00:00") : undefined),
    [value],
  );

  const [pendingFrom, setPendingFrom] = React.useState(fromDate);
  const [pendingTo, setPendingTo] = React.useState(toDate);

  const handleSelect = (range: { from: Date | undefined; to?: Date | undefined } | undefined) => {
    setPendingFrom(range?.from);
    setPendingTo(range?.to);
  };

  const handleApply = () => {
    const newFrom = pendingFrom ? format(pendingFrom, "yyyy-MM-dd") : "";
    const newTo = pendingTo ? format(pendingTo, "yyyy-MM-dd") : "";
    onChange?.({ from: newFrom, to: newTo });
    setIsOpen(false);
  };

  const handleClear = () => {
    setPendingFrom(undefined);
    setPendingTo(undefined);
    onChange?.({ from: "", to: "" });
  };

  const displayText = (): string => {
    if (!fromDate) return "Pick a date range";
    if (!toDate) return `${format(fromDate, "MMM d, yyyy")} - ...`;
    return `${format(fromDate, "MMM d, yyyy")} - ${format(toDate, "MMM d, yyyy")}`;
  };

  const hasSelection = !!(fromDate || toDate);
  const hasPendingSelection = !!(pendingFrom || pendingTo);

  return (
    <Popover
      open={isOpen}
      onOpenChange={(open) => {
        setIsOpen(open);
        if (open) {
          setPendingFrom(fromDate);
          setPendingTo(toDate);
        }
      }}
    >
      <PopoverTrigger
        render={
          <Button
            variant="outline"
            className={cn(
              "justify-start text-left font-normal",
              !hasSelection && "text-muted-foreground",
            )}
          />
        }
      >
        <CalendarDays className="mr-2 h-4 w-4 shrink-0" />
        <span className="truncate">{displayText()}</span>
        {isOpen && <span className="sr-only">Close calendar</span>}
      </PopoverTrigger>
      <PopoverContent className="w-auto p-0" align="start">
        <Calendar
          mode="range"
          selected={pendingFrom || pendingTo ? { from: pendingFrom, to: pendingTo } : undefined}
          onSelect={handleSelect}
          numberOfMonths={2}
        />
        <div className="flex items-center justify-between gap-2 border-t p-3">
          <Button variant="ghost" size="sm" onClick={handleClear}>
            Clear
          </Button>
          <Button variant="default" size="sm" onClick={handleApply} disabled={!hasPendingSelection}>
            Apply
          </Button>
        </div>
      </PopoverContent>
    </Popover>
  );
}

export { DateRangePicker };
export type { DateRange };
