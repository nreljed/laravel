import * as React from "react";
import { router } from "@inertiajs/react";
import { Section, SectionField } from "@/Components/shared/section";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/Components/ui/select";
import { Input } from "@/Components/ui/input";
import { Button } from "@/Components/ui/button";
import {
  Combobox,
  ComboboxContent,
  ComboboxEmpty,
  ComboboxInput,
  ComboboxItem,
  ComboboxList,
  ComboboxTrigger,
  ComboboxValue,
} from "@/Components/ui/combobox";
import { CalendarDays } from "lucide-react";
import { toast } from "@/Components/ui/toast";

interface FormatOption {
  value: string;
  label: string;
  example?: string;
}

const DATE_FORMAT_OPTIONS: FormatOption[] = [
  { value: "d/m/Y", label: "d/m/Y", example: "01/06/2026" },
  { value: "m/d/Y", label: "m/d/Y", example: "06/01/2026" },
  { value: "Y-m-d", label: "Y-m-d", example: "2026-06-01" },
  { value: "F j, Y", label: "F j, Y", example: "June 1, 2026" },
  { value: "j F Y", label: "j F Y", example: "1 June 2026" },
  { value: "l, F j, Y", label: "l, F j, Y", example: "Monday, June 1, 2026" },
  { value: "D, M j", label: "D, M j", example: "Mon, Jun 1" },
  { value: "d.m.Y", label: "d.m.Y", example: "01.06.2026" },
];

const TIME_FORMAT_OPTIONS: FormatOption[] = [
  { value: "H:i", label: "H:i", example: "14:30" },
  { value: "g:i A", label: "g:i A", example: "2:30 PM" },
  { value: "H:i:s", label: "H:i:s", example: "14:30:00" },
  { value: "g:i a", label: "g:i a", example: "2:30 pm" },
  { value: "G:i", label: "G:i", example: "14:30" },
];

const DAYS_OF_WEEK: FormatOption[] = [
  { value: "0", label: "Sunday" },
  { value: "1", label: "Monday" },
  { value: "2", label: "Tuesday" },
  { value: "3", label: "Wednesday" },
  { value: "4", label: "Thursday" },
  { value: "5", label: "Friday" },
  { value: "6", label: "Saturday" },
];

const TIMEZONES: string[] = Intl.supportedValuesOf?.("timeZone") ?? [];

function formatWithOptions(date: Date, format: string): string {
  const map: Record<string, string> = {
    d: String(date.getDate()).padStart(2, "0"),
    m: String(date.getMonth() + 1).padStart(2, "0"),
    Y: String(date.getFullYear()),
    j: String(date.getDate()),
    F:
      [
        "January",
        "February",
        "March",
        "April",
        "May",
        "June",
        "July",
        "August",
        "September",
        "October",
        "November",
        "December",
      ][date.getMonth()] ?? "",
    l:
      ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"][
        date.getDay()
      ] ?? "",
    D: ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"][date.getDay()] ?? "",
    M:
      ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"][
        date.getMonth()
      ] ?? "",
    H: String(date.getHours()).padStart(2, "0"),
    i: String(date.getMinutes()).padStart(2, "0"),
    s: String(date.getSeconds()).padStart(2, "0"),
    A: date.getHours() >= 12 ? "PM" : "AM",
    a: date.getHours() >= 12 ? "pm" : "am",
    G: String(date.getHours()),
    g: String(date.getHours() % 12 || 12),
  };
  let result = format;
  for (const [key, val] of Object.entries(map)) {
    result = result.split(key).join(val);
  }
  return result;
}

function getExampleDate(): Date {
  const d = new Date();
  d.setHours(14, 30, 0, 0);
  d.setDate(1);
  d.setMonth(5);
  d.setFullYear(2026);
  return d;
}

const exampleDate = getExampleDate();

const DATE_FORMATS_WITH_EXAMPLES = DATE_FORMAT_OPTIONS.map((opt) => ({
  ...opt,
  exampleDisplay: formatWithOptions(exampleDate, opt.value),
}));

const TIME_FORMATS_WITH_EXAMPLES = TIME_FORMAT_OPTIONS.map((opt) => ({
  ...opt,
  exampleDisplay: formatWithOptions(exampleDate, opt.value),
}));

interface DateFormatSectionProps {
  settings: Record<string, string>;
  asAccordion?: boolean;
}

function DateFormatSection({ settings, asAccordion }: DateFormatSectionProps) {
  const [dateFormat, setDateFormat] = React.useState<string>(settings.date_format || "Y-m-d");
  const [timeFormat, setTimeFormat] = React.useState<string>(settings.time_format || "H:i");
  const [startOfWeek, setStartOfWeek] = React.useState<string>(settings.start_of_week || "0");
  const [timezone, setTimezone] = React.useState<string>(settings.timezone || "UTC");

  const [loading, setLoading] = React.useState(false);
  const [errors, setErrors] = React.useState<Record<string, string>>({});

  const [showCustomDate, setShowCustomDate] = React.useState(() =>
    DATE_FORMAT_OPTIONS.every((o) => o.value !== (settings.date_format || "Y-m-d")),
  );
  const [showCustomTime, setShowCustomTime] = React.useState(() =>
    TIME_FORMAT_OPTIONS.every((o) => o.value !== (settings.time_format || "H:i")),
  );

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (loading) return;

    setLoading(true);
    setErrors({});

    router.put(
      route("dashboard.admin.settings.update"),
      {
        date_format: dateFormat,
        time_format: timeFormat,
        start_of_week: startOfWeek,
        timezone: timezone,
      },
      {
        preserveScroll: true,
        preserveState: true,
        replace: true,
        onSuccess: () => {
          toast.add({ title: "Date & time settings saved!", type: "success" });
        },
        onError: (errs) => {
          setErrors(errs as Record<string, string>);
          toast.add({
            title: Object.values(errs as Record<string, string>)[0] || "Failed to save",
            type: "error",
          });
        },
        onFinish: () => setLoading(false),
      },
    );
  };

  return (
    <Section
      title="Date & Time Format"
      description="Global date, time, and timezone settings — these apply to every feature, email, and calendar across the entire dashboard."
      icon={CalendarDays}
      onSubmit={handleSubmit}
      loading={loading}
      error={errors?.general || errors?.message}
      fieldErrors={errors as unknown as Record<string, string[]>}
      submitLabel="Save Date & Time Settings"
      asAccordion={asAccordion}
    >
      <SectionField
        label="Date Format"
        htmlFor="date_format"
        error={errors?.date_format}
        description={`Format: ${dateFormat} — Example: ${formatWithOptions(exampleDate, dateFormat)}`}
      >
        <div className="space-y-2">
          <Select
            value={showCustomDate ? "__custom__" : dateFormat}
            onValueChange={(val) => {
              if (val === "__custom__") {
                setShowCustomDate(true);
              } else {
                setShowCustomDate(false);
                setDateFormat(val ?? "");
              }
            }}
          >
            <SelectTrigger className="w-full">
              <SelectValue placeholder="Select date format" />
            </SelectTrigger>
            <SelectContent>
              {DATE_FORMATS_WITH_EXAMPLES.map((opt) => (
                <SelectItem key={opt.value} value={opt.value}>
                  <span className="font-mono text-xs">{opt.label}</span>
                  <span className="ml-2 text-muted-foreground">— {opt.exampleDisplay}</span>
                </SelectItem>
              ))}
              <SelectItem value="__custom__">
                <span className="italic text-muted-foreground">Custom...</span>
              </SelectItem>
            </SelectContent>
          </Select>
          {showCustomDate && (
            <Input
              id="date_format"
              value={dateFormat}
              onChange={(e) => setDateFormat(e.target.value)}
              placeholder="d/m/Y"
              className="font-mono text-sm"
            />
          )}
        </div>
      </SectionField>

      <SectionField
        label="Time Format"
        htmlFor="time_format"
        error={errors?.time_format}
        description={`Format: ${timeFormat} — Example: ${formatWithOptions(exampleDate, timeFormat)}`}
      >
        <div className="space-y-2">
          <Select
            value={showCustomTime ? "__custom__" : timeFormat}
            onValueChange={(val) => {
              if (val === "__custom__") {
                setShowCustomTime(true);
              } else {
                setShowCustomTime(false);
                setTimeFormat(val ?? "");
              }
            }}
          >
            <SelectTrigger className="w-full">
              <SelectValue placeholder="Select time format" />
            </SelectTrigger>
            <SelectContent>
              {TIME_FORMATS_WITH_EXAMPLES.map((opt) => (
                <SelectItem key={opt.value} value={opt.value}>
                  <span className="font-mono text-xs">{opt.label}</span>
                  <span className="ml-2 text-muted-foreground">— {opt.exampleDisplay}</span>
                </SelectItem>
              ))}
              <SelectItem value="__custom__">
                <span className="italic text-muted-foreground">Custom...</span>
              </SelectItem>
            </SelectContent>
          </Select>
          {showCustomTime && (
            <Input
              id="time_format"
              value={timeFormat}
              onChange={(e) => setTimeFormat(e.target.value)}
              placeholder="H:i"
              className="font-mono text-sm"
            />
          )}
        </div>
      </SectionField>

      <SectionField
        label="Start of Week"
        htmlFor="start_of_week"
        error={errors?.start_of_week}
        description="Sets which day the calendar week starts on"
      >
        <Select value={String(startOfWeek)} onValueChange={(val) => setStartOfWeek(val ?? "")}>
          <SelectTrigger className="w-full">
            <SelectValue placeholder="Select start of week" />
          </SelectTrigger>
          <SelectContent>
            {DAYS_OF_WEEK.map((day) => (
              <SelectItem key={day.value} value={day.value}>
                {day.label}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </SectionField>

      <div className="md:col-span-2">
        <SectionField
          label="Timezone"
          htmlFor="timezone"
          error={errors?.timezone}
          description="Sets the timezone used for date and time display"
        >
          <Combobox
            value={timezone}
            onValueChange={(val) => setTimezone(typeof val === "string" ? val : "UTC")}
            items={TIMEZONES}
            itemToStringValue={(tz) => tz}
          >
            <ComboboxTrigger
              render={<Button variant="outline" className="w-full justify-between font-normal" />}
            >
              <ComboboxValue placeholder="Select timezone" />
            </ComboboxTrigger>
            <ComboboxContent>
              <ComboboxInput showTrigger={false} placeholder="Search timezone..." />
              <ComboboxEmpty>No timezone found.</ComboboxEmpty>
              <ComboboxList>
                {(tz: string) => (
                  <ComboboxItem key={tz} value={tz}>
                    {tz}
                  </ComboboxItem>
                )}
              </ComboboxList>
            </ComboboxContent>
          </Combobox>
        </SectionField>
      </div>
    </Section>
  );
}

export { DateFormatSection };
