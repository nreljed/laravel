import * as React from "react";
import { useFormatDate } from "@/hooks/use-format-date";
import { Calendar } from "@/Components/ui/calendar";
import { Popover, PopoverContent, PopoverTrigger } from "@/Components/ui/popover";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { cn } from "@/lib/utils";
import { CalendarDays, Clock } from "lucide-react";
import { format } from "date-fns";

interface DateTimePickerProps {
  value?: string;
  onChange?: (_value: string) => void;
  disabled?: boolean;
  minDate?: Date;
  placeholder?: string;
}

const DEFAULT_MIN_DATE = new Date();

interface TimeState {
  hours: string;
  minutes: string;
}

function DateTimePicker({
  value,
  onChange,
  disabled,
  minDate = DEFAULT_MIN_DATE,
  placeholder = "Select date and time",
}: DateTimePickerProps) {
  const { timeFormat } = useFormatDate();
  const [isOpen, setIsOpen] = React.useState(false);
  const [selectedDate, setSelectedDate] = React.useState<Date | null>(null);
  const [selectedTime, setSelectedTime] = React.useState<TimeState>({ hours: "12", minutes: "00" });

  React.useEffect(() => {
    if (!value) return;
    const date = new Date(value);
    if (!Number.isNaN(date.getTime())) {
      const timer = setTimeout(() => {
        setSelectedDate(date);
        setSelectedTime({
          hours: String(date.getHours()).padStart(2, "0"),
          minutes: String(date.getMinutes()).padStart(2, "0"),
        });
      }, 0);
      return () => clearTimeout(timer);
    }
  }, [value]);

  const handleDateSelect = (date: Date | undefined) => {
    if (!date) return;

    const newDate = new Date(date);
    const hours = Number.parseInt(selectedTime.hours) || 12;
    const minutes = Number.parseInt(selectedTime.minutes) || 0;
    newDate.setHours(hours, minutes, 0, 0);

    setSelectedDate(newDate);
    onChange?.(newDate.toISOString());
  };

  const handleTimeChange = (field: "hours" | "minutes", newValue: string) => {
    const cleaned = newValue.replaceAll(/[^0-9]/g, "").slice(0, 2);
    const newTime = { ...selectedTime, [field]: cleaned || "00" };
    setSelectedTime(newTime);

    if (selectedDate) {
      const updatedDate = new Date(selectedDate);
      updatedDate.setHours(
        Number.parseInt(newTime.hours) || 0,
        Number.parseInt(newTime.minutes) || 0,
        0,
        0,
      );
      onChange?.(updatedDate.toISOString());
    }
  };

  const handleTimeBlur = (field: "hours" | "minutes") => {
    const newTime = { ...selectedTime };
    if (field === "hours") {
      const hours = Number.parseInt(newTime.hours);
      newTime.hours = Number.isNaN(hours) || hours > 23 ? "12" : String(hours).padStart(2, "0");
    } else {
      const minutes = Number.parseInt(newTime.minutes);
      newTime.minutes =
        Number.isNaN(minutes) || minutes > 59 ? "00" : String(minutes).padStart(2, "0");
    }
    setSelectedTime(newTime);

    if (selectedDate) {
      const updatedDate = new Date(selectedDate);
      updatedDate.setHours(
        Number.parseInt(newTime.hours) || 0,
        Number.parseInt(newTime.minutes) || 0,
        0,
        0,
      );
      onChange?.(updatedDate.toISOString());
    }
  };

  const formatDisplayValue = (): string => {
    if (!selectedDate) return placeholder;
    const d = selectedDate;
    const map: Record<string, string> = {
      H: String(d.getHours()).padStart(2, "0"),
      i: String(d.getMinutes()).padStart(2, "0"),
      g: String(d.getHours() % 12 || 12),
      G: String(d.getHours()),
      a: d.getHours() >= 12 ? "pm" : "am",
      A: d.getHours() >= 12 ? "PM" : "AM",
    };
    const tf = timeFormat || "H:i";
    let timeStr = "";
    for (const ch of tf) {
      timeStr += map[ch] || ch;
    }
    return format(selectedDate, "dd MMM yyyy") + " " + timeStr;
  };

  return (
    <Popover open={isOpen} onOpenChange={setIsOpen}>
      <PopoverTrigger
        render={
          <Button
            variant="outline"
            className={cn(
              "w-full justify-start text-left font-normal",
              !selectedDate && "text-muted-foreground",
            )}
            disabled={disabled}
          />
        }
      >
        <CalendarDays className="mr-2 h-4 w-4" />
        {formatDisplayValue()}
      </PopoverTrigger>
      <PopoverContent className="w-auto p-0" align="start">
        <div className="flex flex-col gap-4 p-4">
          <Calendar
            mode="single"
            selected={selectedDate ?? undefined}
            onSelect={handleDateSelect}
            disabled={(date: Date) => date < minDate}
          />
          <div className="flex items-center gap-2">
            <Clock className="h-4 w-4 text-muted-foreground" />
            <div className="flex items-center gap-1">
              <Input
                type="text"
                value={selectedTime.hours}
                onChange={(e) => handleTimeChange("hours", e.target.value)}
                onBlur={() => handleTimeBlur("hours")}
                className="w-12 text-center"
                maxLength={2}
                placeholder="HH"
                disabled={disabled}
              />
              <span className="text-muted-foreground">:</span>
              <Input
                type="text"
                value={selectedTime.minutes}
                onChange={(e) => handleTimeChange("minutes", e.target.value)}
                onBlur={() => handleTimeBlur("minutes")}
                className="w-12 text-center"
                maxLength={2}
                placeholder="MM"
                disabled={disabled}
              />
            </div>
          </div>
        </div>
      </PopoverContent>
    </Popover>
  );
}

export { DateTimePicker };
