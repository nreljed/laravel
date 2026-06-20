import { useMemo } from "react";
import { router } from "@inertiajs/react";
import { cn } from "@/lib/utils";
import { Button } from "@/Components/ui/button";
import { ChevronLeft, ChevronRight } from "lucide-react";
import { BOOKING_STATUS, STATUS_DOT, STATUS_BG } from "../lib/booking-status";

const STATUS_BG_BORDER: Record<string, string> = {
  [BOOKING_STATUS.Pending]: `${STATUS_BG[BOOKING_STATUS.Pending]} border-amber-200/50 dark:border-amber-800/50`,
  [BOOKING_STATUS.Confirmed]: `${STATUS_BG[BOOKING_STATUS.Confirmed]} border-green-200/50 dark:border-green-800/50`,
  [BOOKING_STATUS.CheckedOut]: `${STATUS_BG[BOOKING_STATUS.CheckedOut]} border-gray-200/50 dark:border-gray-700/50`,
  [BOOKING_STATUS.CheckedIn]: `${STATUS_BG[BOOKING_STATUS.CheckedIn]} border-blue-200/50 dark:border-blue-800/50`,
  [BOOKING_STATUS.Cancelled]: `${STATUS_BG[BOOKING_STATUS.Cancelled]} border-red-200/50 dark:border-red-800/50`,
};

interface CalendarEvent {
  id: number;
  status: string;
  status_label?: string;
  check_in?: string;
  check_out?: string;
  guest_name?: string;
  property_title?: string;
  property_id?: number;
  total?: string;
  is_overdue?: boolean;
}

interface WeekViewProps {
  events: CalendarEvent[];
  currentWeekStart: Date;
  onWeekChange: (_direction: number) => void;
  onToday: () => void;
  propertyId?: string;
  weekStartsOn?: number;
}

function formatDateStr(d: Date): string {
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, "0");
  const day = String(d.getDate()).padStart(2, "0");
  return `${y}-${m}-${day}`;
}

function getWeekDays(start: Date): Date[] {
  const days: Date[] = [];
  for (let i = 0; i < 7; i++) {
    const d = new Date(start);
    d.setDate(start.getDate() + i);
    days.push(d);
  }
  return days;
}

function getWeekStart(date: Date, weekStartsOn: number): Date {
  const d = new Date(date);
  const day = d.getDay();
  const diff = (day - weekStartsOn + 7) % 7;
  d.setDate(d.getDate() - diff);
  return d;
}

export default function WeekView({
  events,
  currentWeekStart,
  onWeekChange,
  onToday,
  propertyId,
  weekStartsOn: weekStartsOnProp,
}: WeekViewProps) {
  const weekStartsOn = weekStartsOnProp ?? 1;
  const weekDays = useMemo(() => {
    const start = getWeekStart(currentWeekStart, weekStartsOn);
    return getWeekDays(start);
  }, [currentWeekStart, weekStartsOn]);

  const now = new Date();
  const todayStr = now.toISOString().slice(0, 10);

  const eventMap = useMemo(() => {
    const map: Record<string, CalendarEvent[]> = {};
    const filtered = propertyId
      ? events.filter((ev) => String(ev.property_id) === propertyId)
      : events;

    for (const ev of filtered) {
      const checkIn = ev.check_in || "";
      if (!checkIn) continue;
      const checkOut = ev.check_out || "";

      const start = new Date(checkIn + "T12:00:00");
      if (Number.isNaN(start.getTime())) continue;
      const end = new Date(checkOut + "T12:00:00");

      for (const day of weekDays) {
        const dayStr = formatDateStr(day);
        const dayDate = new Date(day);
        dayDate.setHours(0, 0, 0, 0);

        if (dayDate >= start && dayDate < end) {
          if (!Object.hasOwn(map, dayStr)) map[dayStr] = [];
          map[dayStr]!.push(ev);
        }
      }
    }
    return map;
  }, [events, propertyId, weekDays]);

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-1">
          <Button variant="outline" size="sm" onClick={() => onWeekChange(-1)} className="h-8 w-8 p-0">
            <ChevronLeft className="h-4 w-4" />
          </Button>
          <Button variant="outline" size="sm" onClick={onToday} className="h-8 text-xs px-3">
            Today
          </Button>
          <Button variant="outline" size="sm" onClick={() => onWeekChange(1)} className="h-8 w-8 p-0">
            <ChevronRight className="h-4 w-4" />
          </Button>
        </div>
        <p className="text-sm font-medium text-foreground">
          {weekDays[0]!.toLocaleDateString("en-US", { month: "long", day: "numeric" })} &ndash;{" "}
          {weekDays[6]!.toLocaleDateString("en-US", { month: "long", day: "numeric", year: "numeric" })}
        </p>
      </div>

      <div className="grid grid-cols-7 gap-1.5">
        {weekDays.map((day, idx) => {
          const dayStr = formatDateStr(day);
          const dayEvents = eventMap[dayStr] || [];
          const dayLabel = day.toLocaleDateString("en-US", { weekday: "short", day: "numeric" });
          const isToday = dayStr === todayStr;

          return (
            <div key={idx} className="min-w-0">
              <div
                className={cn(
                  "text-center text-xs font-medium py-1.5 rounded-t-md",
                  isToday ? "bg-primary text-primary-foreground" : "bg-muted text-muted-foreground",
                )}
              >
                {dayLabel}
              </div>
              <div className="space-y-1 pt-1 min-h-[120px]">
                {dayEvents.length === 0 ? (
                  <p className="text-[10px] text-muted-foreground/50 text-center py-4">No events</p>
                ) : (
                  dayEvents.map((ev) => (
                    <button
                      key={ev.id}
                      type="button"
                      onClick={() => router.visit(route("dashboard.bookings.show", ev.id))}
                      className={cn(
                        "w-full text-left p-1.5 rounded border text-[10px] leading-tight transition-colors hover:brightness-95",
                        STATUS_BG_BORDER[ev.status] || "bg-muted/50",
                      )}
                    >
                      <div className="flex items-center gap-1 font-medium text-foreground truncate">
                        <span className={cn("w-1.5 h-1.5 rounded-full shrink-0", STATUS_DOT[ev.status] || "bg-gray-400")} />
                        {ev.guest_name || "N/A"}
                      </div>
                      <p className="text-[9px] text-muted-foreground truncate mt-0.5">
                        {ev.property_title || ""}
                      </p>
                    </button>
                  ))
                )}
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}
