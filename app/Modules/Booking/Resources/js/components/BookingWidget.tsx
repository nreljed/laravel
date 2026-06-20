import React, {
  useState,
  useCallback,
  useEffect,
  useRef,
  useOptimistic,
  startTransition,
} from "react";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Textarea } from "@/Components/ui/textarea";
import { Switch } from "@/Components/ui/switch";
import { Label } from "@/Components/ui/label";
import { RadioGroup, RadioGroupItem } from "@/Components/ui/radio-group";
import { Skeleton } from "@/Components/ui/skeleton";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/Components/ui/select";
import { toast } from "sonner";
import BookingCalendar from "./BookingCalendar";
import BookingForm from "./BookingForm";

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

export type BookingWidgetMode = "full" | "calendar" | "form";

export interface DateSelection {
  start: string | null;
  end: string | null;
}

interface GuestInfo {
  name: string;
  email: string;
  phone: string;
  guests: number;
  hasPets: boolean;
  specialRequests: string;
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

export interface LogEntry {
  method: string;
  url: string;
  status: number | string;
  duration: number;
  summary: string;
  serverMs?: number;
  timing?: Record<string, number>;
}

interface BookingWidgetProps {
  propertyId: number;
  showPrice?: boolean;
  buttonText?: string;
  mode?: BookingWidgetMode;
  formattingSettings?: FormattingSettings;
  bookingSettings?: BookingSettings;
  baseGuests?: number;
  minNights?: number;
  weekStartsOn?: number;
  onLog?: (_entry: LogEntry) => void;
  onBookingCreated?: (_bookingId: number) => void;
  onDatesChange?: (_dates: DateSelection) => void;
}

export default function BookingWidget({
  propertyId,
  showPrice = true,
  buttonText = "Book Now",
  mode = "full",
  formattingSettings,
  bookingSettings,
  baseGuests = 1,
  minNights,
  weekStartsOn,
  onLog,
  onBookingCreated,
  onDatesChange,
}: BookingWidgetProps) {
  const [dates, setDates] = usePersistedDates(propertyId);
  const [units, setUnits] = useState<UnitInfo[]>([]);
  const [selectedUnit, setSelectedUnit] = useState("");
  const [isNonRefundable, setIsNonRefundable] = useState(false);
  const [blockedRange, setBlockedRange] = useState<
    undefined | { checkIn: string; checkOut: string }
  >(undefined);
  const [guestInfo, setGuestInfo] = useState<GuestInfo>({
    name: "",
    email: "",
    phone: "",
    guests: 1,
    hasPets: false,
    specialRequests: "",
  });
  const [loading, setLoading] = useState(false);
  const [pricePreview, setPricePreview] = useState<PricePreviewData | null>(null);
  const [result, setResult] = useState<BookingResult | null>(null);
  const [optimisticResult, setOptimisticResult] = useOptimistic(result);
  const [availabilityError, setAvailabilityError] = useState<string | null>(null);
  const hasAutoSelectedRef = useRef(false);
  const submittingRef = useRef(false);

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
      onLog?.({ method, url, status, duration, summary: label, serverMs });
    },
    [onLog],
  );

  const fetchAvailability = useCallback(async () => {
    if (!dates.start || !dates.end) return;
    setLoading(true);
    setAvailabilityError(null);
    const url = "/api/booking/check-availability";
    const params = new URLSearchParams({
      property_id: String(propertyId),
      check_in: dates.start,
      check_out: dates.end,
      guests: String(guestInfo.guests),
      has_pets: guestInfo.hasPets ? "1" : "0",
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
        setBlockedRange({ checkIn: dates.start!, checkOut: dates.end! });
      }
    } catch (error) {
      logApi("FAILED", url, "POST", "ERR", (error as Error).message.length);
    } finally {
      setLoading(false);
    }
  }, [propertyId, dates, guestInfo.guests, guestInfo.hasPets, selectedUnit, logApi]);

  useEffect(() => {
    if (mode === "form") return;
    if (!dates.start || !dates.end) return;
    hasAutoSelectedRef.current = false;
    setPricePreview(null);
    setUnits([]);
    setAvailabilityError(null);
    const timer = setTimeout(fetchAvailability, 300);
    return () => clearTimeout(timer);
  }, [mode, dates.start, dates.end, guestInfo.guests, guestInfo.hasPets, fetchAvailability]);

  useEffect(() => {
    onDatesChange?.(dates);
  }, [dates, onDatesChange]);

  const handleDateSelect = useCallback(
    ({ start, end }: DateSelection) => {
      setDates({ start, end });
      if (!start) setBlockedRange(undefined);
    },
    [setDates],
  );

  const handleSubmit = useCallback(async () => {
    if (submittingRef.current) return;
    submittingRef.current = true;
    if (!guestInfo.name || !guestInfo.email || !dates.start || !dates.end) {
      toast.error("Please fill in all required fields");
      submittingRef.current = false;
      return;
    }
    if (!guestInfo.email.includes("@")) {
      toast.error("Please enter a valid email");
      submittingRef.current = false;
      return;
    }

    startTransition(() => {
      setOptimisticResult({
        success: true,
        booking_id: 0,
        status: "pending",
        total: 0,
      } as BookingResult);
    });

    setLoading(true);
    const url = "/api/booking";
    try {
      const params = new URLSearchParams({
        property_id: String(propertyId),
        guest_name: guestInfo.name,
        guest_email: guestInfo.email,
        guest_phone: guestInfo.phone,
        check_in: dates.start,
        check_out: dates.end,
        guests: String(Math.max(1, guestInfo.guests)),
        has_pets: guestInfo.hasPets ? "1" : "0",
        special_requests: guestInfo.specialRequests,
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
      const data = body as unknown as BookingResult;
      logApi(`OK (${ms}ms)`, url, "POST", res.status, ms, serverMs);
      if (data.success) {
        setResult(data);
        toast.success("Booking created!");
        onBookingCreated?.(data.booking_id!);
      } else {
        setResult(null);
        toast.error(data.error || "Failed to create booking");
      }
    } catch (error) {
      setResult(null);
      const errMsg = error instanceof Error ? error.message : String(error);
      logApi("FAILED", url, "POST", "ERR", 0);
      toast.error("Network error: " + errMsg);
    } finally {
      submittingRef.current = false;
      setLoading(false);
    }
  }, [
    propertyId,
    guestInfo,
    dates,
    selectedUnit,
    isNonRefundable,
    logApi,
    onBookingCreated,
    setOptimisticResult,
  ]);

  const [resetKey, setResetKey] = useState(0);

  const handleClear = useCallback(() => {
    setResetKey((k) => k + 1);
    setPricePreview(null);
    setBlockedRange(undefined);
    setUnits([]);
    setAvailabilityError(null);
  }, []);

  const incrementGuests = useCallback(() => {
    setGuestInfo((g) => ({ ...g, guests: Math.min((g.guests || 1) + 1, 50) }));
  }, []);

  const decrementGuests = useCallback(() => {
    setGuestInfo((g) => ({ ...g, guests: Math.max(1, (g.guests || 1) - 1) }));
  }, []);

  if (optimisticResult) {
    const isOptimistic = optimisticResult.booking_id === 0;
    return (
      <div className="booking-widget-result space-y-4 p-6 border rounded-lg bg-card">
        <div className="text-center">
          <h3
            className={`text-xl font-semibold ${
              isOptimistic
                ? "text-blue-600 dark:text-blue-400"
                : optimisticResult.status === "pending"
                  ? "text-amber-600 dark:text-amber-400"
                  : "text-green-600 dark:text-green-400"
            }`}
          >
            {isOptimistic
              ? "Processing..."
              : optimisticResult.status === "pending"
                ? "Request Submitted!"
                : "Booking Confirmed!"}
          </h3>
          {!isOptimistic && (
            <p className="text-muted-foreground mt-1">Booking #{optimisticResult.booking_id}</p>
          )}
        </div>
        {!isOptimistic && Number(optimisticResult.price_breakdown?.total || 0) > 0 && (
          <p className="text-center text-lg font-medium">
            Total:{" "}
            {fmt(
              optimisticResult.price_breakdown?.total ?? 0,
              optimisticResult.price_breakdown?.currency || "USD",
            )}
          </p>
        )}
        {!isOptimistic &&
          optimisticResult.total &&
          optimisticResult.total > 0 &&
          !optimisticResult.price_breakdown && (
            <p className="text-center text-lg font-medium">
              Total: {fmt(optimisticResult.total, "USD")}
            </p>
          )}
        {!isOptimistic && optimisticResult.status === "pending" && (
          <p className="text-muted-foreground text-center">
            Your booking is pending host confirmation. We will notify you once confirmed.
          </p>
        )}
        {isOptimistic && (
          <p className="text-muted-foreground text-center">Submitting your booking request...</p>
        )}
        <div className="text-center">
          {!isOptimistic && optimisticResult.magic_link ? (
            <Button onClick={() => open(optimisticResult.magic_link, "_blank")}>
              View Booking
            </Button>
          ) : isOptimistic ? (
            <div className="animate-pulse h-10 w-40 bg-muted rounded-md mx-auto" />
          ) : (
            <p className="text-muted-foreground text-sm">Check your email for the booking link.</p>
          )}
        </div>
      </div>
    );
  }

  if (mode === "calendar") {
    return (
      <div className="booking-widget space-y-6">
        <div className="booking-widget-calendar">
          <BookingCalendar
            key={propertyId}
            propertyId={propertyId}
            onSelectDate={handleDateSelect}
            onError={(msg) => toast.error(msg)}
            initialDates={dates}
            calendarMaxRange={bookingSettings?.calendar_max_range}
            minNights={minNights}
            blockedRanges={blockedRange}
            weekStartsOn={weekStartsOn}
            resetTrigger={resetKey}
          />
        </div>

        {dates.start && dates.end && (
          <div className="booking-widget-summary p-4 bg-muted rounded-xl">
            <div className="flex items-center justify-between">
              <p className="font-medium">
                Selected: <strong>{dates.start}</strong> → <strong>{dates.end}</strong>
              </p>
              <button
                type="button"
                onClick={handleClear}
                className="cursor-pointer text-muted-foreground underline underline-offset-2 hover:text-foreground transition-colors"
              >
                Clear
              </button>
            </div>
          </div>
        )}
      </div>
    );
  }

  if (mode === "form") {
    return (
      <BookingForm
        propertyId={propertyId}
        buttonText={buttonText}
        formattingSettings={formattingSettings}
        bookingSettings={bookingSettings}
        baseGuests={baseGuests}
        minNights={minNights}
        onLog={onLog}
        onBookingCreated={onBookingCreated}
        onDatesChange={onDatesChange}
      />
    );
  }

  return (
    <div className="booking-widget space-y-6">
      <div className="booking-widget-calendar">
        <BookingCalendar
          key={propertyId}
          propertyId={propertyId}
          onSelectDate={handleDateSelect}
          onError={(msg) => toast.error(msg)}
          initialDates={dates}
          calendarMaxRange={bookingSettings?.calendar_max_range}
          minNights={minNights}
          blockedRanges={blockedRange}
          weekStartsOn={weekStartsOn}
          resetTrigger={resetKey}
        />
      </div>

      {dates.start && dates.end && units.length > 0 && !loading && (
        <div className="space-y-2">
          <label className="font-medium block">Select Unit Type</label>
          <Select value={selectedUnit} onValueChange={setSelectedUnit}>
            <SelectTrigger>
              <SelectValue placeholder="Choose a unit type..." />
            </SelectTrigger>
            <SelectContent>
              {units.map((unit) => (
                <SelectItem key={unit.id} value={unit.id}>
                  {unit.name}{" "}
                  {unit.effective_rate
                    ? `(${fmt(unit.effective_rate, pricePreview?.currency || "USD")}/night)`
                    : ""}
                  {unit.remaining > 1 ? ` - ${unit.remaining} available` : ""}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      )}

      {dates.start && dates.end && (
        <div className="booking-widget-summary p-4 bg-muted rounded-xl">
          <div className="flex items-center justify-between">
            <p className="font-medium">
              <strong>{dates.start}</strong> → <strong>{dates.end}</strong>
            </p>
            <button
              type="button"
              onClick={handleClear}
              className="cursor-pointer text-muted-foreground underline underline-offset-2 hover:text-foreground transition-colors"
            >
              Clear
            </button>
          </div>
          {loading && !pricePreview && <Skeleton className="h-9 w-full mt-2 rounded-md" />}
        </div>
      )}

      {availabilityError && !loading && (
        <div className="booking-widget-error p-4 border border-red-200 dark:border-red-800 rounded-xl bg-red-50 dark:bg-red-950/30">
          <p className="text-sm text-red-700 dark:text-red-400">{availabilityError}</p>
        </div>
      )}

      {pricePreview && showPrice && (
        <div className="booking-widget-price space-y-2 p-5 border rounded-xl bg-card">
          <h4 className="font-semibold text-lg">Price Breakdown</h4>
          {pricePreview.nightly_rates && pricePreview.nightly_rates.length > 0 ? (
            pricePreview.nightly_rates.map((night, i) => (
              <div key={i} className="flex justify-between">
                <span>
                  {night.date}
                  {night.is_weekend ? " (weekend)" : ""}
                </span>
                <span>{fmt(night.base_rate, pricePreview.currency)}</span>
              </div>
            ))
          ) : (
            <div className="flex justify-between">
              <span>
                {fmt(
                  pricePreview.base_nightly_rate || pricePreview.nightly_rate || "0",
                  pricePreview.currency,
                )}{" "}
                × {pricePreview.nights} nights
              </span>
              <span>{fmt(pricePreview.subtotal || 0, pricePreview.currency)}</span>
            </div>
          )}
          {Number(pricePreview.length_of_stay_discount_amount || 0) > 0 && (
            <div className="flex justify-between text-green-600 dark:text-green-400">
              <span>Length-of-stay discount ({pricePreview.length_of_stay_discount_percent}%)</span>
              <span>
                -{fmt(pricePreview.length_of_stay_discount_amount || 0, pricePreview.currency)}
              </span>
            </div>
          )}
          {Number(pricePreview.promo_discount_percent || 0) > 0 && (
            <div className="flex justify-between text-purple-600 dark:text-purple-400">
              <span>{pricePreview.promo_discount_label}</span>
              <span>-{fmt(pricePreview.promo_discount_amount || 0, pricePreview.currency)}</span>
            </div>
          )}
          {isNonRefundable && Number(pricePreview.nonrefundable_discount_percent || 0) > 0 && (
            <div className="flex justify-between text-rose-600 dark:text-rose-400">
              <span>Non-refundable discount ({pricePreview.nonrefundable_discount_percent}%)</span>
              <span>
                -{fmt(pricePreview.nonrefundable_discount_amount || 0, pricePreview.currency)}
              </span>
            </div>
          )}
          {Number(pricePreview.extra_guest_charge || 0) > 0 && (
            <div className="flex justify-between">
              <span>
                Extra guest ({pricePreview.guests! - (pricePreview.base_guests || 2)} ×{" "}
                {fmt(pricePreview.extra_guest_fee || 0, pricePreview.currency)} ×{" "}
                {pricePreview.nights} nights)
              </span>
              <span>{fmt(pricePreview.extra_guest_charge || 0, pricePreview.currency)}</span>
            </div>
          )}
          {Number(pricePreview.cleaning_fee || "0") > 0 && (
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

      {dates.start && dates.end && (
        <div className="booking-widget-form space-y-5 p-5 border rounded-xl bg-card">
          <div>
            <label className="font-medium block mb-1">Name *</label>
            <Input
              value={guestInfo.name}
              onChange={(e: React.ChangeEvent<HTMLInputElement>) =>
                setGuestInfo((g) => ({ ...g, name: e.target.value }))
              }
              placeholder="Your full name"
            />
          </div>
          <div>
            <label className="font-medium block mb-1">Email *</label>
            <Input
              type="email"
              value={guestInfo.email}
              onChange={(e: React.ChangeEvent<HTMLInputElement>) =>
                setGuestInfo((g) => ({ ...g, email: e.target.value }))
              }
              placeholder="email@example.com"
            />
          </div>
          <div>
            <label className="font-medium block mb-1">Phone</label>
            <Input
              value={guestInfo.phone}
              onChange={(e: React.ChangeEvent<HTMLInputElement>) =>
                setGuestInfo((g) => ({ ...g, phone: e.target.value }))
              }
              placeholder="+1 (555) 123-4567"
            />
          </div>
          <div className="guest-counter">
            <div className="guest-counter-inner">
              <span className="font-medium">Guests</span>
              <div className="flex items-center gap-3">
                <button
                  type="button"
                  onClick={decrementGuests}
                  disabled={guestInfo.guests <= 1}
                  className="guest-btn"
                >
                  −
                </button>
                <span className="w-6 text-center font-medium">{guestInfo.guests}</span>
                <button type="button" onClick={incrementGuests} className="guest-btn">
                  +
                </button>
              </div>
            </div>
          </div>
          <div className="flex items-center gap-3">
            <Switch
              checked={guestInfo.hasPets}
              onCheckedChange={(v: boolean) => setGuestInfo((g) => ({ ...g, hasPets: v }))}
            />
            <span>Bringing pets</span>
          </div>
          <div>
            <label className="font-medium block mb-1">Special requests</label>
            <Textarea
              value={guestInfo.specialRequests}
              onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) =>
                setGuestInfo((g) => ({ ...g, specialRequests: e.target.value }))
              }
              placeholder="Any special requests..."
              rows={3}
            />
          </div>
          {bookingSettings?.enable_nonrefundable_discount && (
            <div className="space-y-2">
              <hr className="border-border/60" />
              <RadioGroup
                value={isNonRefundable ? "nonrefundable" : "refundable"}
                onValueChange={(v) => setIsNonRefundable(v === "nonrefundable")}
                className="space-y-2"
              >
                <p className="text-sm font-medium">Cancellation policy</p>
                <div className="flex items-center gap-2">
                  <RadioGroupItem value="refundable" id="refundable-full" />
                  <Label htmlFor="refundable-full" className="text-sm font-normal cursor-pointer">
                    Flexible — free cancellation
                  </Label>
                </div>
                <div className="flex items-center gap-2">
                  <RadioGroupItem value="nonrefundable" id="nonrefundable-full" />
                  <Label
                    htmlFor="nonrefundable-full"
                    className="text-sm font-normal cursor-pointer"
                  >
                    Non-refundable — save {bookingSettings.nonrefundable_discount_percent}%
                  </Label>
                </div>
              </RadioGroup>
            </div>
          )}
          <button
            type="button"
            className="w-full inline-flex items-center justify-center rounded-lg px-5 py-2.5 font-medium bg-primary text-primary-foreground hover:opacity-90 disabled:opacity-50 disabled:cursor-not-allowed transition-opacity"
            onClick={handleSubmit}
            disabled={loading}
          >
            {loading ? "Booking..." : buttonText}
          </button>
        </div>
      )}
    </div>
  );
}
