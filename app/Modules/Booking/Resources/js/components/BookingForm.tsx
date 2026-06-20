import { useState, useCallback, useEffect, useMemo, useRef } from "react";
import { format, parseISO } from "date-fns";
import { useForm, useWatch } from "react-hook-form";
import { z } from "zod";
import { zodResolver } from "@hookform/resolvers/zod";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Textarea } from "@/Components/ui/textarea";
import { Switch } from "@/Components/ui/switch";
import { Label } from "@/Components/ui/label";
import { RadioGroup, RadioGroupItem } from "@/Components/ui/radio-group";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/Components/ui/select";
import { Popover, PopoverContent, PopoverTrigger } from "@/Components/ui/popover";
import { Calendar } from "@/Components/ui/calendar";
import { CalendarIcon, Loader2 } from "lucide-react";
import { toast } from "sonner";
import {
  Form,
  FormField,
  FormItem,
  FormLabel,
  FormControl,
  FormMessage,
} from "@/Components/ui/form";
import type { DateRange } from "react-day-picker";

interface FormattingSettings {
  property_default_currency: string;
  property_currency_position: string;
  property_thousands_separator: string;
  property_decimal_separator: string;
  property_price_empty_text: string;
  property_surface_unit: string;
}

function formatPriceStatic(
  value: number | string,
  settings: FormattingSettings = {
    property_default_currency: "USD",
    property_currency_position: "before",
    property_thousands_separator: ",",
    property_decimal_separator: ".",
    property_price_empty_text: "",
    property_surface_unit: "sq_ft",
  },
  currency?: string,
): string {
  const num = typeof value === "string" ? Number.parseFloat(value) : value;
  if (Number.isNaN(num)) return settings.property_price_empty_text || "";
  const formatted = num.toLocaleString("en-US", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
  const cur = currency || settings.property_default_currency || "USD";
  if (settings.property_currency_position === "before") {
    return `${cur} ${formatted}`;
  }
  return `${formatted} ${cur}`;
}

const DEFAULT_FORMATTING: FormattingSettings = {
  property_default_currency: "USD",
  property_currency_position: "before",
  property_thousands_separator: ",",
  property_decimal_separator: ".",
  property_price_empty_text: "",
  property_surface_unit: "sq_ft",
};

export interface BookingSettings {
  enable_nonrefundable_discount?: boolean;
  nonrefundable_discount_percent?: number;
  calendar_max_range?: number;
}

export interface DateSelection {
  start: string | null;
  end: string | null;
}

interface UnitInfo {
  id: string;
  name: string;
  effective_rate: string;
  remaining: number;
}

interface PricePreviewData {
  base_nightly_rate?: string;
  nightly_rate?: string;
  subtotal?: string;
  total?: string;
  nights?: number;
  cleaning_fee?: string;
  service_fee?: string;
  extra_guest_charge?: string;
  pet_fee?: string;
  length_of_stay_discount_amount?: string;
  length_of_stay_discount_percent?: string;
  promo_discount_percent?: number;
  promo_discount_amount?: number;
  promo_discount_label?: string;
  nonrefundable_discount_percent?: number;
  nonrefundable_discount_amount?: number;
  currency?: string;
  nightly_rates?: Array<{
    date: string;
    base_rate: string;
    is_weekend?: boolean;
  }>;
  guests?: number;
  base_guests?: number;
  extra_guest_fee?: string;
}

interface BookingResult {
  success?: boolean;
  booking_id?: number;
  total?: number;
  status?: string;
  price_breakdown?: PricePreviewData;
  magic_link?: string;
  error?: string;
}

export interface LogEntry {
  method: string;
  url: string;
  status: number | string;
  duration: number;
  summary: string;
  serverMs?: number;
  timing?: Record<string, number>;
}

const bookingFormSchema = z.object({
  name: z.string({ error: () => "Full name is required" }).min(1, "Full name is required"),
  email: z.string({ error: () => "Email is required" }).email("Please enter a valid email"),
  phone: z.string({ error: () => "Phone number is required" }).min(1, "Phone number is required"),
  guests: z
    .number({ error: () => "Number of guests is required" })
    .min(1, "At least 1 guest is required"),
  hasPets: z.boolean(),
  specialRequests: z.string().optional(),
});

type BookingFormValues = z.infer<typeof bookingFormSchema>;

function storageKey(pid: number) {
  return `crm_booking_dates_${pid}`;
}

function loadPersistedDates(propertyId: number): DateSelection {
  try {
    const raw = localStorage.getItem(storageKey(propertyId));
    if (raw) {
      const parsed = JSON.parse(raw) as DateSelection;
      if (parsed.start && parsed.end) {
        const todayDate = new Date();
        const today = todayDate.toISOString().slice(0, 10);
        if (parsed.start <= today) return { start: null, end: null };
        return parsed;
      }
    }
  } catch {
    /* ignore */
  }
  return { start: null, end: null };
}

function persistDates(propertyId: number, dates: DateSelection) {
  try {
    localStorage.setItem(storageKey(propertyId), JSON.stringify(dates));
  } catch {
    /* ignore */
  }
}

function dispatchDateEvent(propertyId: number, dates: DateSelection) {
  document.dispatchEvent(
    new CustomEvent("crm-booking:dates-selected", {
      detail: { propertyId, dates },
    }),
  );
}

function usePersistedDates(propertyId: number): [DateSelection, (_dates: DateSelection) => void] {
  const [dates, setDates] = useState<DateSelection>(() => loadPersistedDates(propertyId));

  const updateDates = useCallback(
    (newDates: DateSelection) => {
      setDates(newDates);
      persistDates(propertyId, newDates);
      dispatchDateEvent(propertyId, newDates);
    },
    [propertyId],
  );

  useEffect(() => {
    const handler = (e: Event) => {
      const customEvent = e as CustomEvent<{ propertyId: number; dates: DateSelection }>;
      if (customEvent.detail.propertyId === propertyId) {
        setDates((current) => {
          const incoming = customEvent.detail.dates;
          if (current.start === incoming.start && current.end === incoming.end) {
            return current;
          }
          return incoming;
        });
      }
    };
    document.addEventListener("crm-booking:dates-selected", handler);
    return () => document.removeEventListener("crm-booking:dates-selected", handler);
  }, [propertyId]);

  return [dates, updateDates];
}

interface BookingFormProps {
  propertyId: number;
  initialDates?: DateSelection;
  buttonText?: string;
  formattingSettings?: FormattingSettings;
  bookingSettings?: BookingSettings;
  baseGuests?: number;
  minNights?: number;
  skipAutoFetch?: boolean;
  onLog?: (_entry: LogEntry) => void;
  onBookingCreated?: (_bookingId: number) => void;
  onDatesChange?: (_dates: DateSelection) => void;
}

export default function BookingForm({
  propertyId,
  initialDates,
  buttonText = "Book Now",
  formattingSettings,
  bookingSettings,
  baseGuests = 1,
  minNights = 1,
  skipAutoFetch = false,
  onLog,
  onBookingCreated,
  onDatesChange,
}: BookingFormProps) {
  const [dates, setDates] = usePersistedDates(propertyId);
  const [units, setUnits] = useState<UnitInfo[]>([]);
  const [selectedUnit, setSelectedUnit] = useState("");
  const [isNonRefundable, setIsNonRefundable] = useState(false);
  const [loading, setLoading] = useState(false);
  const [checkingAvailability, setCheckingAvailability] = useState(false);
  const [pricePreview, setPricePreview] = useState<PricePreviewData | null>(null);
  const [result, setResult] = useState<BookingResult | null>(null);
  const [availabilityError, setAvailabilityError] = useState<string | null>(null);
  const hasAutoSelectedRef = useRef(false);
  const submittingRef = useRef(false);

  const form = useForm<BookingFormValues>({
    resolver: zodResolver(bookingFormSchema),
    defaultValues: {
      name: "",
      email: "",
      phone: "",
      guests: baseGuests,
      hasPets: false,
      specialRequests: "",
    },
    mode: "onBlur",
  });

  const { control } = form;
  const watchedGuests = useWatch({ control, name: "guests" });
  const watchedHasPets = useWatch({ control, name: "hasPets" });

  const [calendarOpen, setCalendarOpen] = useState(false);

  const fmt = useCallback(
    (value: number | string, currency?: string) => {
      const settings = { ...DEFAULT_FORMATTING, ...formattingSettings };
      return formatPriceStatic(value, settings, currency);
    },
    [formattingSettings],
  );

  const logApi = useCallback(
    (
      label: string,
      url: string,
      method: string,
      status: number | string,
      duration: number,
      serverMs?: number,
    ) => {
      onLog?.({
        method,
        url,
        status,
        duration,
        summary: label,
        serverMs,
      });
    },
    [onLog],
  );

  const fetchAvailability = useCallback(async () => {
    if (!dates.start || !dates.end) return;
    setCheckingAvailability(true);
    setAvailabilityError(null);
    const url = "/api/booking/check-availability";
    const params = new URLSearchParams({
      property_id: String(propertyId),
      check_in: dates.start,
      check_out: dates.end,
      guests: String(watchedGuests),
      has_pets: watchedHasPets ? "1" : "0",
    });
    if (selectedUnit) params.append("unit_id", selectedUnit);
    logApi("FETCH", url, "POST", "--", 0);
    try {
      const start = performance.now();
      const res = await fetch(url, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: params,
      });
      const ms = Math.round(performance.now() - start);
      const rawBody = await res.text();
      let serverMs: number | undefined;
      let body: Record<string, unknown> = {};
      try {
        body = JSON.parse(rawBody);
        serverMs = body._server_time_ms as number | undefined;
      } catch {
        /* ignore */
      }
      const data = body as unknown as {
        units?: UnitInfo[];
        price?: PricePreviewData;
        available?: boolean;
        error_reason?: string;
      };
      logApi(`OK (${ms}ms)`, url, "POST", res.status, ms, serverMs);
      if (data.units) {
        setUnits(data.units);
        if (!hasAutoSelectedRef.current && data.units.length === 1) {
          hasAutoSelectedRef.current = true;
          setSelectedUnit(data.units[0]!.id);
        }
      } else {
        setUnits([]);
      }
      if (data.price) setPricePreview(data.price);
      if (data.available === false) {
        setAvailabilityError(
          data.error_reason || "These dates are not available. Please try different dates.",
        );
      }
    } catch {
      logApi("FAILED", url, "POST", "ERR", 0);
    }
    setCheckingAvailability(false);
  }, [propertyId, dates, watchedGuests, watchedHasPets, selectedUnit, logApi]);

  useEffect(() => {
    if (skipAutoFetch) return;
    if (!dates.start || !dates.end) return;
    hasAutoSelectedRef.current = false;
    setPricePreview(null);
    setUnits([]);
    setAvailabilityError(null);
    const timer = setTimeout(fetchAvailability, 300);
    return () => clearTimeout(timer);
  }, [skipAutoFetch, dates.start, dates.end, watchedGuests, watchedHasPets, fetchAvailability]);

  useEffect(() => {
    onDatesChange?.(dates);
  }, [dates, onDatesChange]);

  useEffect(() => {
    if (!initialDates?.start) return;
    if (dates.start) return;

    setDates(initialDates);
  }, [initialDates, dates.start, setDates]);

  const handleDateSelect = useCallback(
    ({ start, end }: DateSelection) => {
      setDates({ start, end });
    },
    [setDates],
  );

  const handleSubmit = form.handleSubmit(async (data) => {
    if (submittingRef.current) return;
    submittingRef.current = true;
    if (!dates.start || !dates.end) {
      toast.error("Please select check-in and check-out dates");
      submittingRef.current = false;
      return;
    }
    setLoading(true);
    const url = "/api/booking";
    try {
      const params = new URLSearchParams({
        property_id: String(propertyId),
        guest_name: data.name,
        guest_email: data.email,
        guest_phone: data.phone || "",
        check_in: dates.start,
        check_out: dates.end,
        guests: String(Math.max(1, data.guests)),
        has_pets: data.hasPets ? "1" : "0",
        special_requests: data.specialRequests || "",
        is_nonrefundable_discount: isNonRefundable ? "1" : "0",
      });
      if (selectedUnit) params.append("unit_id", selectedUnit);
      logApi("FETCH", url, "POST", "--", 0);
      const start = performance.now();
      const res = await fetch(url, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: params,
      });
      const ms = Math.round(performance.now() - start);
      const rawBody = await res.text();
      let serverMs: number | undefined;
      let body: Record<string, unknown> = {};
      try {
        body = JSON.parse(rawBody);
        serverMs = body._server_time_ms as number | undefined;
      } catch {
        /* ignore */
      }
      const responseData = body as unknown as BookingResult;
      logApi(`OK (${ms}ms)`, url, "POST", res.status, ms, serverMs);
      if (responseData.success) {
        setResult(responseData);
        toast.success("Booking created!");
        onBookingCreated?.(responseData.booking_id!);
      } else {
        toast.error(responseData.error || "Failed to create booking");
      }
    } catch (error) {
      const errMsg = error instanceof Error ? error.message : String(error);
      logApi("FAILED", url, "POST", "ERR", 0);
      toast.error("Network error: " + errMsg);
    } finally {
      submittingRef.current = false;
      setLoading(false);
    }
  });

  const shortDate = (dateStr: string | null): string => {
    if (!dateStr) return "Add date";
    try {
      return format(parseISO(dateStr), "MMM d");
    } catch {
      return dateStr;
    }
  };

  const dateRangeDisplay = useMemo((): string => {
    if (!dates.start) return "Select check-in → check-out";
    if (!dates.end) return `${shortDate(dates.start)} — Select check-out`;
    return `${shortDate(dates.start)} — ${format(parseISO(dates.end), "MMM d, yyyy")}`;
  }, [dates]);

  const dateRangeValue = useMemo((): DateRange | undefined => {
    if (!dates.start) return;
    return {
      from: new Date(dates.start + "T00:00:00"),
      to: dates.end ? new Date(dates.end + "T00:00:00") : undefined,
    };
  }, [dates]);

  const handleCalendarSelect = useCallback(
    (range: DateRange | undefined) => {
      if (!range?.from) {
        handleDateSelect({ start: null, end: null });
        return;
      }
      const start = formatDate(range.from);
      let end = range.to ? formatDate(range.to) : null;
      if (!end && minNights > 1) {
        const suggestedEnd = new Date(range.from);
        suggestedEnd.setDate(suggestedEnd.getDate() + minNights);
        end = formatDate(suggestedEnd);
      }
      handleDateSelect({ start, end });
      if (end) setCalendarOpen(false);
    },
    [handleDateSelect, minNights],
  );

  const pricePerNight = useMemo(() => {
    if (pricePreview?.base_nightly_rate) return Number(pricePreview.base_nightly_rate).toFixed(2);
    if (pricePreview?.nightly_rate) return Number(pricePreview.nightly_rate).toFixed(2);
    if (units.length > 0) {
      const rates = units.map((u) => Number(u.effective_rate || "0")).filter((r) => r > 0);
      if (rates.length > 0) return Math.min(...rates).toFixed(2);
    }
    return null;
  }, [pricePreview, units]);

  const nights = useMemo(() => {
    if (!dates.start || !dates.end) return 0;
    const endDate = new Date(dates.end);
    const startDate = new Date(dates.start);
    const diff = endDate.getTime() - startDate.getTime();
    return Math.max(1, Math.round(diff / (1000 * 60 * 60 * 24)));
  }, [dates]);

  if (result) {
    return (
      <div className="booking-widget-result space-y-4 p-6 border rounded-lg bg-card">
        <div className="text-center">
          <h3
            className={`text-xl font-semibold ${
              result.status === "pending"
                ? "text-amber-600 dark:text-amber-400"
                : "text-green-600 dark:text-green-400"
            }`}
          >
            {result.status === "pending" ? "Request Submitted!" : "Booking Confirmed!"}
          </h3>
          <p className="text-muted-foreground mt-1">Booking #{result.booking_id}</p>
        </div>
        {Number(result.price_breakdown?.total || 0) > 0 && (
          <p className="text-center text-lg font-medium">
            Total:{" "}
            {fmt(result.price_breakdown?.total ?? 0, result.price_breakdown?.currency || "USD")}
          </p>
        )}
        {result.total && result.total > 0 && !result.price_breakdown && (
          <p className="text-center text-lg font-medium">Total: {fmt(result.total, "USD")}</p>
        )}
        {result.status === "pending" && (
          <p className="text-muted-foreground text-center">
            Your booking is pending host confirmation. We will notify you once confirmed.
          </p>
        )}
        <div className="text-center">
          {result.magic_link ? (
            <Button onClick={() => open(result.magic_link, "_blank")}>View Booking</Button>
          ) : (
            <p className="text-muted-foreground text-sm">Check your email for the booking link.</p>
          )}
        </div>
      </div>
    );
  }

  return (
    <div className="booking-widget form-mode">
      <Form {...form}>
        <form onSubmit={handleSubmit} className="booking-card p-5 space-y-5">
          <div>
            {pricePerNight ? (
              <>
                <span className="text-2xl font-bold">
                  {fmt(pricePerNight, pricePreview?.currency || "USD")}
                </span>
                <span className="text-muted-foreground"> / night</span>
              </>
            ) : (
              <span className="text-xl font-semibold">Book this property</span>
            )}
          </div>

          <Popover open={calendarOpen} onOpenChange={setCalendarOpen}>
            <PopoverTrigger asChild>
              <Button variant="outline" className="w-full justify-start text-left font-normal h-11">
                <CalendarIcon className="mr-2 h-4 w-4 shrink-0" />
                <span className={dates.start ? "" : "text-muted-foreground"}>
                  {dateRangeDisplay}
                </span>
              </Button>
            </PopoverTrigger>
            <PopoverContent className="w-auto p-0 z-[9999]" align="start">
              <div className="booking-widget-calendar p-1">
                <Calendar
                  mode="range"
                  selected={dateRangeValue}
                  onSelect={handleCalendarSelect}
                  numberOfMonths={1}
                  className="w-full"
                  defaultMonth={dateRangeValue?.from ? new Date(dateRangeValue.from) : new Date()}
                />
              </div>
              {dateRangeValue?.from && (
                <div className="flex items-center justify-center border-t p-2">
                  <button
                    type="button"
                    onClick={() => {
                      handleDateSelect({ start: null, end: null });
                      setCalendarOpen(false);
                    }}
                    className="text-xs text-muted-foreground hover:text-foreground underline underline-offset-2 transition-colors"
                  >
                    Clear dates
                  </button>
                </div>
              )}
            </PopoverContent>
          </Popover>

          {dates.start && dates.end && (
            <FormField
              control={control}
              name="guests"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Guests</FormLabel>
                  <Select
                    value={String(field.value)}
                    onValueChange={(v) => field.onChange(Number(v))}
                  >
                    <FormControl>
                      <SelectTrigger className="w-full">
                        <SelectValue placeholder="Number of guests" />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      {Array.from({ length: 16 }, (_, i) => i + 1).map((n) => (
                        <SelectItem key={n} value={String(n)}>
                          {n} {n === 1 ? "guest" : "guests"}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <FormMessage />
                </FormItem>
              )}
            />
          )}

          {dates.start &&
            dates.end &&
            units.length > 1 &&
            !checkingAvailability &&
            !availabilityError && (
              <Select value={selectedUnit} onValueChange={setSelectedUnit}>
                <SelectTrigger className="w-full">
                  <SelectValue placeholder="Select unit type" />
                </SelectTrigger>
                <SelectContent>
                  {units.map((unit) => (
                    <SelectItem key={unit.id} value={unit.id}>
                      {unit.name}
                      {unit.effective_rate
                        ? ` (${fmt(unit.effective_rate, pricePreview?.currency || "USD")}/night)`
                        : ""}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            )}

          {checkingAvailability && !pricePreview && (
            <div className="flex items-center gap-3 py-3 px-4 rounded-lg bg-muted/50 border">
              <Loader2 className="h-4 w-4 animate-spin text-primary shrink-0" />
              <div className="flex-1 min-w-0">
                <p className="text-sm font-medium text-foreground">Checking availability</p>
                <p className="text-xs text-muted-foreground">
                  Verifying pricing and availability for your dates
                </p>
              </div>
            </div>
          )}

          {availabilityError && !checkingAvailability && (
            <div className="p-3 border border-red-200 dark:border-red-800 rounded-lg bg-red-50 dark:bg-red-950/30">
              <p className="text-sm text-red-700 dark:text-red-400">{availabilityError}</p>
            </div>
          )}

          {pricePreview && !checkingAvailability && (
            <div className="space-y-2 pt-2">
              <hr className="border-0 border-t border-border m-0" />
              <div className="flex justify-between">
                <span>
                  {fmt(
                    pricePreview.base_nightly_rate || pricePreview.nightly_rate || "0",
                    pricePreview.currency,
                  )}{" "}
                  × {nights} {nights === 1 ? "night" : "nights"}
                </span>
                <span>{fmt(pricePreview.subtotal || 0, pricePreview.currency)}</span>
              </div>
              {Number(pricePreview.length_of_stay_discount_amount || 0) > 0 && (
                <div className="flex justify-between text-green-600 dark:text-green-400">
                  <span>
                    Length-of-stay discount ({pricePreview.length_of_stay_discount_percent}%)
                  </span>
                  <span>
                    -{fmt(pricePreview.length_of_stay_discount_amount || 0, pricePreview.currency)}
                  </span>
                </div>
              )}
              {Number(pricePreview.promo_discount_percent || 0) > 0 && (
                <div className="flex justify-between text-purple-600 dark:text-purple-400">
                  <span>{pricePreview.promo_discount_label}</span>
                  <span>
                    -{fmt(pricePreview.promo_discount_amount || 0, pricePreview.currency)}
                  </span>
                </div>
              )}
              {isNonRefundable && Number(pricePreview.nonrefundable_discount_percent || 0) > 0 && (
                <div className="flex justify-between text-rose-600 dark:text-rose-400">
                  <span>
                    Non-refundable discount ({pricePreview.nonrefundable_discount_percent}%)
                  </span>
                  <span>
                    -{fmt(pricePreview.nonrefundable_discount_amount || 0, pricePreview.currency)}
                  </span>
                </div>
              )}
              {Number(pricePreview.cleaning_fee || 0) > 0 && (
                <div className="flex justify-between">
                  <span>Cleaning fee</span>
                  <span>{fmt(pricePreview.cleaning_fee || 0, pricePreview.currency)}</span>
                </div>
              )}
              {Number(pricePreview.service_fee || "0") > 0 && (
                <div className="flex justify-between">
                  <span>Service fee</span>
                  <span>{fmt(pricePreview.service_fee || 0, pricePreview.currency)}</span>
                </div>
              )}
              {Number(pricePreview.extra_guest_charge || "0") > 0 && (
                <div className="flex justify-between">
                  <span>Extra guest charge</span>
                  <span>{fmt(pricePreview.extra_guest_charge || 0, pricePreview.currency)}</span>
                </div>
              )}
              {Number(pricePreview.pet_fee || "0") > 0 && (
                <div className="flex justify-between">
                  <span>Pet fee</span>
                  <span>{fmt(pricePreview.pet_fee || 0, pricePreview.currency)}</span>
                </div>
              )}
              <hr className="border-0 border-t border-border m-0" />
              <div className="flex justify-between font-semibold text-base">
                <span>Total</span>
                <span>{fmt(pricePreview.total || 0, pricePreview.currency)}</span>
              </div>
            </div>
          )}

          {dates.start && dates.end && !checkingAvailability && (
            <div className="space-y-4 pt-1">
              <hr className="border-0 border-t border-border m-0" />

              <FormField
                control={control}
                name="name"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>
                      Full name <span className="text-destructive">*</span>
                    </FormLabel>
                    <FormControl>
                      <Input placeholder="Your full name" {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <FormField
                control={control}
                name="email"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>
                      Email <span className="text-destructive">*</span>
                    </FormLabel>
                    <FormControl>
                      <Input type="email" placeholder="email@example.com" {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <FormField
                control={control}
                name="phone"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>
                      Phone <span className="text-destructive">*</span>
                    </FormLabel>
                    <FormControl>
                      <Input placeholder="+1 (555) 123-4567" {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <FormField
                control={control}
                name="hasPets"
                render={({ field }) => (
                  <FormItem>
                    <div className="flex items-center gap-3">
                      <Switch checked={field.value} onCheckedChange={field.onChange} />
                      <Label className="font-medium cursor-pointer">Bringing pets</Label>
                    </div>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <FormField
                control={control}
                name="specialRequests"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Special requests</FormLabel>
                    <FormControl>
                      <Textarea placeholder="Any special requests..." rows={3} {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />

              {bookingSettings?.enable_nonrefundable_discount && (
                <div className="pt-1">
                  <hr className="border-0 border-t border-border m-0" />
                  <RadioGroup
                    value={isNonRefundable ? "nonrefundable" : "refundable"}
                    onValueChange={(v) => setIsNonRefundable(v === "nonrefundable")}
                    className="space-y-2 pt-2"
                  >
                    <p className="text-sm font-medium">Cancellation policy</p>
                    <div className="flex items-center gap-2 pl-1">
                      <RadioGroupItem value="refundable" id="refundable-form" />
                      <Label
                        htmlFor="refundable-form"
                        className="text-sm font-normal cursor-pointer"
                      >
                        Flexible — free cancellation
                      </Label>
                    </div>
                    <div className="flex items-center gap-2 pl-1">
                      <RadioGroupItem value="nonrefundable" id="nonrefundable-form" />
                      <Label
                        htmlFor="nonrefundable-form"
                        className="text-sm font-normal cursor-pointer"
                      >
                        Non-refundable — save {bookingSettings.nonrefundable_discount_percent}%
                      </Label>
                    </div>
                  </RadioGroup>
                </div>
              )}

              <button
                type="submit"
                className="w-full mt-1 inline-flex items-center justify-center rounded-lg px-5 py-2.5 font-medium bg-primary text-primary-foreground hover:opacity-90 disabled:opacity-50 disabled:cursor-not-allowed transition-opacity"
                disabled={loading}
              >
                {loading ? (
                  <span className="flex items-center gap-2">
                    <Loader2 className="h-4 w-4 animate-spin" />
                    Booking...
                  </span>
                ) : (
                  buttonText
                )}
              </button>
            </div>
          )}
        </form>
      </Form>
    </div>
  );
}

function formatDate(date: Date) {
  if (!date) return "";
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, "0");
  const d = String(date.getDate()).padStart(2, "0");
  return `${y}-${m}-${d}`;
}
