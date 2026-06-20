import { useState, useEffect, useCallback, useRef, useMemo } from "react";
import { Calendar } from "@/Components/ui/calendar";
import { Loader2 } from "lucide-react";
import type { DateRange } from "react-day-picker";

interface DateInfo {
  date: string;
  is_blocked?: boolean;
  is_booked?: boolean;
  available?: boolean;
  reason?: string;
}

interface AvailabilityResponse {
  dates?: Record<string, DateInfo>;
  min_nights?: number;
  max_nights?: number;
}

interface BookingCalendarProps {
  propertyId: number;
  onSelectDate?: (_dates: { start: string | null; end: string | null }) => void;
  onError?: (_message: string) => void;
  preloadedDates?: Record<string, DateInfo>;
  months?: number;
  initialDates?: { start: string | null; end: string | null };
  calendarMaxRange?: number;
  minNights?: number;
  blockedRanges?: { checkIn: string; checkOut: string };
  weekStartsOn?: number;
  resetTrigger?: number;
}

function findAvailableCheckout(
  from: Date,
  minNights: number,
  unavailableTimestamps: Set<number>,
): Date {
  const candidate = new Date(from);
  let nightsFound = 0;
  for (let i = 0; i < 365; i++) {
    candidate.setDate(candidate.getDate() + 1);
    if (!unavailableTimestamps.has(candidate.getTime())) {
      nightsFound++;
      if (nightsFound >= minNights) return candidate;
    }
  }
  const fallback = new Date(from);
  fallback.setDate(fallback.getDate() + minNights);
  return fallback;
}

function findFirstAvailableDate(today: Date, dates: Record<string, DateInfo>): Date | null {
  const entries = Object.values(dates).filter(
    (info) => info?.date && DATE_REGEX.test(info.date) && info.available,
  );
  entries.sort((a, b) => a.date.localeCompare(b.date));
  for (const entry of entries) {
    const d = new Date(entry.date + "T00:00:00");
    if (d.getTime() > today.getTime()) return d;
  }
  return null;
}

const DATE_REGEX = /^\d{4}-\d{2}-\d{2}$/;

const REASON_LABELS: Record<string, string> = {
  date_in_past: "Date is in the past",
  outside_booking_window: "Outside the bookable date range",
  advance_notice_required: "Advance notice required — too soon",
  invalid_checkin_day: "Check-in not available on this day",
  blocked_by_host: "Blocked by host",
  already_booked: "Already booked",
  no_available_units: "No available units",
  checkin_time_passed: "Same-day check-in time has passed",
};

function getReasonLabel(reason: string): string {
  return REASON_LABELS[reason] || reason;
}

export default function BookingCalendar({
  propertyId,
  onSelectDate,
  onError,
  preloadedDates,
  months,
  initialDates,
  calendarMaxRange,
  minNights: minNightsProp,
  blockedRanges,
  weekStartsOn: weekStartsOnProp,
  resetTrigger,
}: BookingCalendarProps) {
  const defaultSelected: DateRange | undefined = initialDates?.start
    ? {
        from: new Date(initialDates.start + "T00:00:00"),
        to: initialDates.end ? new Date(initialDates.end + "T00:00:00") : undefined,
      }
    : undefined;
  const [month, setMonth] = useState<Date>(new Date());
  const [selected, setSelected] = useState<DateRange | undefined>(defaultSelected);
  const [dates, setDates] = useState<Record<string, DateInfo>>(preloadedDates || {});
  const [loading, setLoading] = useState(false);
  const [calendarError, setCalendarError] = useState<string | null>(null);
  const [numberOfMonths, setNumberOfMonths] = useState(months ?? 2);
  const [apiMinNights, setApiMinNights] = useState(1);
  const [maxNights, setMaxNights] = useState(0);
  const [loadVersion, setLoadVersion] = useState(0);

  const minNights = minNightsProp ?? apiMinNights;

  useEffect(() => {
    if (months !== undefined) return;
    const mobile = matchMedia("(max-width: 767px)");
    const update = () => setNumberOfMonths(mobile.matches ? 1 : 2);
    update();
    mobile.addEventListener("change", update);
    return () => mobile.removeEventListener("change", update);
  }, [months]);

  const loadedMonthsRef = useRef(new Set<string>());
  const latestFetchRef = useRef(0);
  const initializedRef = useRef(false);
  const initialLoadDoneRef = useRef(false);
  const hasUserInteractedRef = useRef(false);
  const cacheVersionRef = useRef(0);

  useEffect(() => {
    if (!blockedRanges) return;
    initializedRef.current = false;
    setDates((prev) => {
      const updated = { ...prev };
      let changed = false;
      const start = new Date(blockedRanges.checkIn + "T00:00:00");
      const end = new Date(blockedRanges.checkOut + "T00:00:00");
      const current = new Date(start);
      while (current < end) {
        const str = formatDate(current);
        if (updated[str]?.available !== false) {
          updated[str] = { date: str, available: false, is_booked: true };
          changed = true;
        }
        current.setDate(current.getDate() + 1);
      }
      return changed ? updated : prev;
    });
  }, [blockedRanges]);

  useEffect(() => {
    if (resetTrigger === undefined || resetTrigger === 0) return;
    hasUserInteractedRef.current = false;
    initializedRef.current = false;
    setSelected(undefined);
  }, [resetTrigger]);

  const getMonthKey = (date: Date) => {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}`;
  };

  useEffect(() => {
    loadedMonthsRef.current = new Set();
    if (preloadedDates) {
      const seen = new Set<string>();
      for (const key of Object.keys(preloadedDates)) {
        const month = key.slice(0, 7);
        if (!seen.has(month)) {
          seen.add(month);
          loadedMonthsRef.current.add(month);
        }
      }
    }
  }, [preloadedDates]);

  const fetchMonthRange = useCallback(
    async (startMonth: Date, count: number) => {
      if (!propertyId) return;

      interface Needed {
        date: Date;
        key: string;
      }
      const needed: Needed[] = [];
      for (let i = 0; i < count; i++) {
        const m = new Date(startMonth.getFullYear(), startMonth.getMonth() + i, 1);
        const key = getMonthKey(m);
        if (!loadedMonthsRef.current.has(key)) {
          needed.push({ date: m, key });
          loadedMonthsRef.current.add(key);
        }
      }
      if (needed.length === 0) {
        if (!initialLoadDoneRef.current) {
          initialLoadDoneRef.current = true;
          setLoadVersion((v) => v + 1);
        }
        return;
      }

      const fetchId = ++latestFetchRef.current;
      setLoading(true);
      setCalendarError(null);
      try {
        const delays: number[] = [];
        const promises = needed.map(async ({ date }) => {
          const y = date.getFullYear();
          const mi = date.getMonth();
          const url = `/dashboard/booking/availability/${propertyId}?month=${y}-${String(mi + 1).padStart(2, "0")}`;
          const start = performance.now();
          try {
            const res = await fetch(url);
            if (!res.ok) {
              throw new Error(`HTTP ${res.status}`);
            }
            const data = await res.json();
            const ms = Math.round(performance.now() - start);
            delays.push(ms);
            return data as AvailabilityResponse;
          } catch (error: unknown) {
            throw error;
          }
        });
        const results = await Promise.all(promises);
        if (fetchId !== latestFetchRef.current) return;
        for (const data of results) {
          if (data.min_nights) setApiMinNights(data.min_nights);
          if (data.max_nights) setMaxNights(data.max_nights);
        }
        setDates((prev) => {
          const next = { ...prev };
          for (const data of results) {
            if (data.dates) Object.assign(next, data.dates);
          }
          return next;
        });
        initialLoadDoneRef.current = true;
      } catch (error) {
        onError?.("Failed to load availability");
      }
      setLoading(false);
    },
    [propertyId, onError],
  );

  useEffect(() => {
    if (!propertyId) return;
    const poll = async () => {
      try {
        const res = await fetch(`/api/booking/cache-version/${propertyId}`);
        const data = await res.json();
        const newVersion = data.version;
        if (cacheVersionRef.current !== 0 && cacheVersionRef.current !== newVersion) {
          loadedMonthsRef.current = new Set();
          cacheVersionRef.current = newVersion;
          fetchMonthRange(month, numberOfMonths);
        }
        cacheVersionRef.current = newVersion;
      } catch {
        /* ignore polling errors */
      }
    };
    poll();
    const interval = setInterval(poll, 30_000);
    return () => clearInterval(interval);
  }, [propertyId, month, numberOfMonths, fetchMonthRange]);

  useEffect(() => {
    const timer = setTimeout(() => {
      fetchMonthRange(month, numberOfMonths);
    }, 0);
    return () => clearTimeout(timer);
  }, [month, numberOfMonths, fetchMonthRange]);

  useEffect(() => {
    if (!initialLoadDoneRef.current) {
      return;
    }
    if (Object.keys(dates).length === 0) {
      return;
    }

    if (selected?.from) {
      const checkInStr = formatDate(selected.from);
      const info = dates[checkInStr];
      if (info !== undefined) {
        if (info.available) {
          initializedRef.current = true;
          return;
        }
        setSelected(undefined);
        initializedRef.current = false;
        return;
      }
      initializedRef.current = true;
      return;
    }

    if (initializedRef.current || hasUserInteractedRef.current) {
      return;
    }

    const todayLocal = new Date();
    todayLocal.setHours(0, 0, 0, 0);
    const firstAvailable = findFirstAvailableDate(todayLocal, dates);
    if (!firstAvailable) {
      return;
    }

    const blockedTimestamps = new Set([todayLocal.getTime()]);
    for (const info of Object.values(dates)) {
      if (info?.date && DATE_REGEX.test(info.date) && !info.available) {
        const blockedDate = new Date(info.date + "T00:00:00");
        blockedTimestamps.add(blockedDate.getTime());
      }
    }

    const suggestedEnd = findAvailableCheckout(firstAvailable, minNights, blockedTimestamps);
    const fullRange: DateRange = { from: firstAvailable, to: suggestedEnd };
    setSelected(fullRange);
    onSelectDate?.({ start: formatDate(firstAvailable), end: formatDate(suggestedEnd) });
    initializedRef.current = true;
  }, [dates, minNights, onSelectDate, selected?.from, loadVersion]);

  const maxRange = calendarMaxRange ?? 12;
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const minDate = new Date(today.getFullYear(), today.getMonth(), 1);
  const maxDate = new Date(today.getFullYear(), today.getMonth() + maxRange, 1);

  const {
    blockedDates,
    bookedDates,
    unavailableDates,
    pastOrPrepDates,
    timeBlockedDates,
    dateReasonMap,
  } = useMemo(() => {
    const blocked: Date[] = [];
    const booked: Date[] = [];
    const unavailable: Date[] = [];
    const pastOrPrep: Date[] = [];
    const timeBlocked: Date[] = [];
    const reasonMap = new Map<string, string>();

    for (const info of Object.values(dates)) {
      if (!info?.date || !DATE_REGEX.test(info.date)) continue;
      const d = new Date(info.date + "T00:00:00");
      if (info.is_blocked) blocked.push(d);
      if (info.is_booked) booked.push(d);
      if (info.reason) reasonMap.set(info.date, info.reason);
      if (!info.available) {
        unavailable.push(d);
        if (!info.is_blocked && !info.is_booked) {
          pastOrPrep.push(d);
        }
      }
      if (info.reason === "checkin_time_passed") {
        timeBlocked.push(d);
      }
    }

    return {
      blockedDates: blocked,
      bookedDates: booked,
      unavailableDates: unavailable,
      pastOrPrepDates: pastOrPrep,
      timeBlockedDates: timeBlocked,
      dateReasonMap: reasonMap,
    };
  }, [dates]);

  const isDayDisabled = (date: Date) => {
    if (date < today) return true;
    return unavailableDates.some((d) => d.getTime() === date.getTime());
  };

  const modifiers = {
    blocked: blockedDates,
    booked: bookedDates,
    unavailable: pastOrPrepDates,
    todayTimeBlocked: timeBlockedDates,
  };

  const modifiersClassNames: Record<string, string> = {
    blocked: "line-through opacity-50 [&_*]:text-orange-500 dark:[&_*]:text-orange-400",
    booked: "line-through opacity-50 [&_*]:text-rose-500 dark:[&_*]:text-rose-400",
    unavailable: "opacity-40 line-through text-muted-foreground",
    todayTimeBlocked: "opacity-50 cursor-not-allowed aria-disabled",
  };

  const handleSelect = (range: DateRange | undefined) => {
    hasUserInteractedRef.current = true;
    if (range?.from && !range?.to && minNights > 1) {
      const blockedTimestamps = new Set([
        today.getTime(),
        ...unavailableDates.map((d) => d.getTime()),
      ]);
      const suggestedEnd = findAvailableCheckout(range.from, minNights, blockedTimestamps);
      const fullRange: DateRange = { from: range.from, to: suggestedEnd };
      setSelected(fullRange);
      onSelectDate?.({ start: formatDate(range.from), end: formatDate(suggestedEnd) });
    } else {
      setSelected(range);
      if (range?.from) {
        const fromStr = formatDate(range.from);
        if (range.to) {
          const toStr = formatDate(range.to);
          const toDate = new Date(toStr);
          const fromDate = new Date(fromStr);
          const nights = Math.round((toDate.getTime() - fromDate.getTime()) / 86_400_000);
          if (nights >= minNights) {
            onSelectDate?.({ start: fromStr, end: toStr });
          } else {
            onSelectDate?.({ start: fromStr, end: null });
          }
        } else {
          onSelectDate?.({ start: fromStr, end: null });
        }
      } else {
        onSelectDate?.({ start: null, end: null });
      }
    }
  };

  const selectedFromStr = selected?.from ? formatDate(selected.from) : null;
  const selectedToStr = selected?.to ? formatDate(selected.to) : null;
  const selectedFromDate = selectedFromStr ? new Date(selectedFromStr) : null;
  const selectedToDate = selectedToStr ? new Date(selectedToStr) : null;
  const selectedNights =
    selectedFromDate && selectedToDate
      ? Math.round((selectedToDate.getTime() - selectedFromDate.getTime()) / 86_400_000)
      : 0;
  const nightsTooShort = selectedNights > 0 && selectedNights < minNights;

  const selectedStartStr = selected?.from ? formatDate(selected.from) : null;
  const selectedEndStr = selected?.to ? formatDate(selected.to) : null;

  const selectedUnavailableReasons: string[] = [];
  if (selectedStartStr) {
    const r = dateReasonMap.get(selectedStartStr);
    if (r) selectedUnavailableReasons.push(getReasonLabel(r));
  }
  if (selectedEndStr && selectedEndStr !== selectedStartStr) {
    const r = dateReasonMap.get(selectedEndStr);
    if (r) selectedUnavailableReasons.push(getReasonLabel(r));
  }

  return (
    <div className="booking-calendar">
      <Calendar
        mode="range"
        selected={selected}
        onSelect={handleSelect}
        month={month}
        onMonthChange={(newMonth: Date) => {
          const firstMonth = new Date(newMonth.getFullYear(), newMonth.getMonth(), 1);
          const lastMonth = new Date(
            newMonth.getFullYear(),
            newMonth.getMonth() + numberOfMonths - 1,
            1,
          );
          if (firstMonth < minDate || lastMonth > maxDate) return;
          setMonth(newMonth);
        }}
        className="w-full"
        disabled={isDayDisabled}
        modifiers={modifiers}
        modifiersClassNames={modifiersClassNames}
        numberOfMonths={numberOfMonths}
        startMonth={minDate}
        endMonth={maxDate}
        formatters={{
          formatWeekdayName: (date: Date) =>
            date.toLocaleDateString("en-US", { weekday: "narrow" }),
        }}
        weekStartsOn={(weekStartsOnProp ?? 1) as 0 | 1 | 2 | 3 | 4 | 5 | 6}
      />

      {loading && (
        <div className="flex items-center justify-center gap-2 px-1 pt-4 text-sm text-muted-foreground">
          <Loader2 className="h-4 w-4 animate-spin" />
          Loading availability…
        </div>
      )}

      {!loading && calendarError && (
        <div className="px-1 pt-2">
          <p className="text-sm text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-800 rounded-md px-3 py-2">
            {calendarError}
          </p>
        </div>
      )}

      {!loading && selectedUnavailableReasons.length > 0 && (
        <div className="px-1 pt-2">
          <div className="text-sm text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 rounded-md px-3 py-2 space-y-1">
            {selectedUnavailableReasons.map((msg, i) => (
              <p key={i}>{msg}</p>
            ))}
          </div>
        </div>
      )}

      {selectedFromStr && !selectedToStr && !loading && (
        <div className="px-1 pt-2">
          <p className="text-xs text-muted-foreground bg-muted/50 rounded-md px-3 py-2 border">
            Minimum stay: <strong>{minNights}</strong> {minNights === 1 ? "night" : "nights"}
            {maxNights > 0 && (
              <>
                {" "}
                · Maximum: <strong>{maxNights}</strong> {maxNights === 1 ? "night" : "nights"}
              </>
            )}{" "}
            — select your check-out date
          </p>
        </div>
      )}

      {nightsTooShort && (
        <div className="px-1 pt-2">
          <p className="text-xs text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 rounded-md px-3 py-2">
            Minimum stay is <strong>{minNights}</strong> {minNights === 1 ? "night" : "nights"} —
            please choose a later check-out date
          </p>
        </div>
      )}

      <div className="flex flex-wrap gap-3 px-1 pt-3 text-xs text-muted-foreground">
        <span className="flex items-center gap-1">
          <span className="inline-block size-2.5 rounded-full border border-rose-400 bg-rose-100 dark:bg-rose-950" />
          Booked
        </span>
        <span className="flex items-center gap-1">
          <span className="inline-block size-2.5 rounded-full border border-orange-400 bg-orange-100 dark:bg-orange-950" />
          Blocked
        </span>
        <span className="flex items-center gap-1">
          <span className="inline-block size-2.5 rounded-full border border-muted-foreground bg-muted" />
          Unavailable
        </span>
        <span className="flex items-center gap-1">
          <span className="inline-block size-2.5 rounded-full border border-green-500 bg-green-100 dark:bg-green-950" />
          Available
        </span>
      </div>
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
