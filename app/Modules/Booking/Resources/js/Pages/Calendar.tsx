import { useState, useMemo, useCallback } from "react";
import { Head, Link, router } from "@inertiajs/react";
import {
  ChevronLeft, ChevronRight, CalendarDays, Download, Ban,
  CalendarX2, X, AlertTriangle, CalendarRange,
} from "lucide-react";
import { toast } from "sonner";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Button } from "@/Components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/Components/ui/select";
import { Badge } from "@/Components/ui/badge";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
} from "@/Components/ui/dialog";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/Components/ui/alert-dialog";
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "@/Components/ui/breadcrumb";
import { cn } from "@/lib/utils";
import { BOOKING_STATUS, STATUS_BADGE, STATUS_DOT, STATUS_LABELS, getStatusLabel } from "../lib/booking-status";
import WeekView from "./WeekView";
import CalendarLegend from "./CalendarLegend";
import { IcalImportDialog } from "../components/IcalImportDialog";

interface DayBooking {
  id: number;
  status: string;
}

interface CalendarData {
  year: number;
  month: number;
  days: Record<string, { date: string; booked: boolean; bookings: DayBooking[] }>;
}

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

interface CalendarProps {
  calendar: CalendarData | null;
  properties: { id: number; name: string }[];
  year: number;
  month: number;
  events?: CalendarEvent[];
  propertyColors?: Record<number, string>;
}

const dayNames = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];

const PROPERTY_COLORS_LIST = [
  "#3b82f6", "#ef4444", "#22c55e", "#f59e0b", "#8b5cf6", "#ec4899",
  "#14b8a6", "#f97316", "#6366f1", "#84cc16", "#06b6d4", "#d946ef",
];

function addMonths(date: Date, n: number): Date {
  const d = new Date(date);
  d.setMonth(d.getMonth() + n);
  return d;
}

function formatMonthYear(date: Date): string {
  return date.toLocaleDateString("en-US", { month: "long", year: "numeric" });
}

function getMonthGrid(year: number, month: number) {
  const firstDay = new Date(year, month - 1, 1);
  const daysInMonth = new Date(year, month, 0).getDate();
  const startOffset = firstDay.getDay();
  const totalCells = Math.ceil((startOffset + daysInMonth) / 7) * 7;
  const cells = [];
  for (let i = 0; i < totalCells; i++) {
    const dayNum = i - startOffset + 1;
    const valid = dayNum >= 1 && dayNum <= daysInMonth;
    const dateStr = valid
      ? `${year}-${String(month).padStart(2, "0")}-${String(dayNum).padStart(2, "0")}`
      : null;
    cells.push({ dayNum: valid ? dayNum : null, dateStr });
  }
  return cells;
}

export default function BookingCalendar({
  calendar,
  properties,
  year,
  month,
  events = [],
  propertyColors: propColorsProp,
}: CalendarProps) {
  const [selectedPropertyId, setSelectedPropertyId] = useState<string>("");
  const [viewMode, setViewMode] = useState<"month" | "week">(() => {
    try { const saved = localStorage.getItem("booking_calendar_view"); if (saved === "week" || saved === "month") return saved; } catch {}
    return "month";
  });
  const [detailDay, setDetailDay] = useState<{ dateStr: string; bookings: DayBooking[] } | null>(null);

  const [blockMode, setBlockMode] = useState(false);
  const [blockStart, setBlockStart] = useState<Date | null>(null);
  const [blockEnd, setBlockEnd] = useState<Date | null>(null);
  const [blockReason, setBlockReason] = useState("");
  const [blockConfirmOpen, setBlockConfirmOpen] = useState(false);
  const [blocking, setBlocking] = useState(false);

  const [attentionOnly, setAttentionOnly] = useState(false);
  const [range, setRange] = useState(() => {
    try { const saved = localStorage.getItem("booking_calendar_range"); if (saved) return Number(saved); } catch {}
    return 1;
  });

  const propertyColors = useMemo(() => {
    if (propColorsProp) return propColorsProp;
    const colors: Record<number, string> = {};
    properties.forEach((p, idx) => {
      colors[p.id] = PROPERTY_COLORS_LIST[idx % PROPERTY_COLORS_LIST.length];
    });
    return colors;
  }, [propColorsProp, properties]);

  const today = new Date();
  const todayStr = today.toISOString().slice(0, 10);
  const normalizedMonth = month;
  const normalizedYear = year;

  const prevMonth = useMemo(() => addMonths(new Date(normalizedYear, normalizedMonth - 1, 1), -1), [normalizedYear, normalizedMonth]);
  const nextMonth = useMemo(() => addMonths(new Date(normalizedYear, normalizedMonth - 1, 1), 1), [normalizedYear, normalizedMonth]);

  const navigate = useCallback((y: number, m: number) => {
    const params: Record<string, string> = { year: String(y), month: String(m) };
    if (selectedPropertyId) params.property_id = selectedPropertyId;
    router.get(route("dashboard.bookings.calendar"), params, { preserveState: true, replace: true });
  }, [selectedPropertyId]);

  const handlePropertyChange = (value: string) => {
    setSelectedPropertyId(value);
    const params: Record<string, string> = { year: String(normalizedYear), month: String(normalizedMonth) };
    if (value) params.property_id = value;
    router.get(route("dashboard.bookings.calendar"), params, { preserveState: true, replace: true });
  };

  const handleDayClick = (dateStr: string) => {
    const date = new Date(dateStr + "T12:00:00");
    if (blockMode) {
      if (!blockStart) {
        setBlockStart(date);
        return;
      }
      if (blockStart && !blockEnd) {
        const orderedStart = new Date(Math.min(date.getTime(), blockStart.getTime()));
        const orderedEnd = new Date(Math.max(date.getTime(), blockStart.getTime()));
        setBlockStart(orderedStart);
        setBlockEnd(orderedEnd);
        setBlockConfirmOpen(true);
        return;
      }
      return;
    }
    if (!calendar?.days?.[dateStr]) return;
    setDetailDay({ dateStr, bookings: calendar.days[dateStr].bookings });
  };

  const executeBlock = useCallback(async () => {
    if (!blockStart || !blockEnd) return;
    setBlocking(true);
    try {
      const res = await fetch(route("dashboard.bookings.block-dates"), {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          property_id: selectedPropertyId || undefined,
          start: blockStart.toISOString().slice(0, 10),
          end: blockEnd.toISOString().slice(0, 10),
          reason: blockReason,
        }),
      });
      const data = await res.json();
      if (data.success) {
        toast.success("Dates blocked successfully");
        setBlockConfirmOpen(false);
        setBlockMode(false);
        setBlockStart(null);
        setBlockEnd(null);
        setBlockReason("");
        router.reload({ preserveScroll: true });
      } else {
        toast.error(data.error || "Failed to block dates");
      }
    } catch {
      toast.error("Failed to block dates");
    } finally {
      setBlocking(false);
    }
  }, [blockStart, blockEnd, blockReason, selectedPropertyId]);

  const handleExitBlockMode = useCallback(() => {
    setBlockMode(false);
    setBlockStart(null);
    setBlockEnd(null);
    setBlockReason("");
  }, []);

  const handleViewToggle = useCallback(() => {
    setViewMode((prev) => {
      const next = prev === "month" ? "week" : "month";
      try { localStorage.setItem("booking_calendar_view", next); } catch {}
      return next;
    });
  }, []);

  const handleRangeChange = useCallback((newRange: number) => {
    setRange(newRange);
    try { localStorage.setItem("booking_calendar_range", String(newRange)); } catch {}
    navigate(normalizedYear, normalizedMonth);
  }, [navigate, normalizedYear, normalizedMonth]);

  const cells = useMemo(() => getMonthGrid(normalizedYear, normalizedMonth), [normalizedYear, normalizedMonth]);

  const legendProperties = useMemo(() =>
    properties.map((p) => ({ id: p.id, title: p.name })),
    [properties],
  );

  const totalByStatus = useMemo(() => {
    const counts: Record<string, number> = {};
    if (!calendar?.days) return counts;
    for (const day of Object.values(calendar.days)) {
      for (const b of day.bookings) {
        counts[b.status] = (counts[b.status] || 0) + 1;
      }
    }
    return counts;
  }, [calendar]);

  const statusSummary = [
    { status: BOOKING_STATUS.Pending, label: STATUS_LABELS[BOOKING_STATUS.Pending], count: totalByStatus[BOOKING_STATUS.Pending] || 0 },
    { status: BOOKING_STATUS.Confirmed, label: STATUS_LABELS[BOOKING_STATUS.Confirmed], count: totalByStatus[BOOKING_STATUS.Confirmed] || 0 },
    { status: BOOKING_STATUS.CheckedIn, label: STATUS_LABELS[BOOKING_STATUS.CheckedIn], count: totalByStatus[BOOKING_STATUS.CheckedIn] || 0 },
    { status: BOOKING_STATUS.CheckedOut, label: STATUS_LABELS[BOOKING_STATUS.CheckedOut], count: totalByStatus[BOOKING_STATUS.CheckedOut] || 0 },
    { status: BOOKING_STATUS.Cancelled, label: STATUS_LABELS[BOOKING_STATUS.Cancelled], count: totalByStatus[BOOKING_STATUS.Cancelled] || 0 },
  ];

  const pendingOverdueCount = totalByStatus[BOOKING_STATUS.Pending] || 0;

  return (
    <AuthenticatedLayout header="Calendar">
      <Head title="Booking Calendar" />

      <div className="space-y-6">
        <Breadcrumb>
          <BreadcrumbList>
            <BreadcrumbItem><BreadcrumbLink href="/dashboard">Dashboard</BreadcrumbLink></BreadcrumbItem>
            <BreadcrumbSeparator />
            <BreadcrumbItem><BreadcrumbLink href={route("dashboard.bookings.index")}>Bookings</BreadcrumbLink></BreadcrumbItem>
            <BreadcrumbSeparator />
            <BreadcrumbItem><BreadcrumbPage>Calendar</BreadcrumbPage></BreadcrumbItem>
          </BreadcrumbList>
        </Breadcrumb>

        <div className="flex flex-wrap items-center justify-between gap-3">
          <div className="flex items-center gap-3">
            <CalendarDays className="h-6 w-6 text-foreground shrink-0" />
            <div>
              <h2 className="text-2xl font-bold tracking-tight text-foreground">Calendar</h2>
              <p className="text-sm text-muted-foreground">Manage availability and bookings</p>
            </div>
          </div>
          <div className="flex items-center gap-2 flex-wrap">
            {[1, 2, 3, 4].filter((r) => r <= 4).map((r, idx) => (
              <button key={r} type="button" onClick={() => handleRangeChange(r)}
                className={[
                  "px-2.5 py-1 text-xs font-medium transition-colors border rounded",
                  range === r ? "bg-primary text-primary-foreground border-primary" : "bg-background text-muted-foreground hover:bg-accent border-border",
                ].join(" ")}
              >{r}m</button>
            ))}
            <Select value={selectedPropertyId || "__all__"} onValueChange={handlePropertyChange}>
              <SelectTrigger className="w-[140px] h-8 text-xs">
                <SelectValue placeholder="All Properties" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="__all__">All Properties</SelectItem>
                {properties.map((p) => (
                  <SelectItem key={p.id} value={String(p.id)}>{p.name}</SelectItem>
                ))}
              </SelectContent>
            </Select>
            {pendingOverdueCount > 0 && (
              <Button variant={attentionOnly ? "default" : "outline"} size="sm" className="text-xs h-8" onClick={() => setAttentionOnly((v) => !v)}>
                <AlertTriangle className="h-3.5 w-3.5 mr-1" />
                Needs attention
                {!attentionOnly && <span className="ml-1 inline-flex items-center justify-center size-4 rounded-full bg-amber-500 text-[10px] text-white font-medium">{pendingOverdueCount}</span>}
              </Button>
            )}
            <Button variant={blockMode ? "default" : "outline"} size="sm" className="text-xs h-8" onClick={() => { if (blockMode) handleExitBlockMode(); else setBlockMode(true); }}>
              <Ban className="h-3.5 w-3.5 mr-1" />
              {blockMode ? "Done blocking" : "Block dates"}
            </Button>
            <IcalImportDialog propertyId={selectedPropertyId} />
            <Button variant="outline" size="sm" className="text-xs h-8" asChild>
              <Link href={route("dashboard.bookings.export.csv")}><Download className="h-3.5 w-3.5 mr-1" /> CSV</Link>
            </Button>
            <Button variant="outline" size="sm" className="text-xs h-8" onClick={handleViewToggle}>
              {viewMode === "month" ? <CalendarRange className="h-3.5 w-3.5 mr-1" /> : <CalendarDays className="h-3.5 w-3.5 mr-1" />}
              {viewMode === "month" ? "Week" : "Month"}
            </Button>
          </div>
        </div>

        {blockMode && (
          <div className="flex items-center gap-3 px-4 py-2 rounded-lg bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/40 text-sm">
            <CalendarX2 className="size-4 text-amber-600 dark:text-amber-400 shrink-0" />
            <span className="text-amber-800 dark:text-amber-300 flex-1">
              {!blockStart ? "Click a start date to begin blocking" : !blockEnd ? "Click an end date to complete the range" : "Range selected"}
            </span>
            {blockStart && (
              <span className="text-xs text-amber-600 dark:text-amber-400 font-medium">
                {blockStart.toLocaleDateString("en-US", { month: "short", day: "numeric" })}
                {blockEnd && ` — ${blockEnd.toLocaleDateString("en-US", { month: "short", day: "numeric" })}`}
              </span>
            )}
            <Button variant="ghost" size="sm" className="text-xs h-7 px-2 text-amber-600 dark:text-amber-400" onClick={handleExitBlockMode}>
              <X className="size-3.5" />
            </Button>
          </div>
        )}

        <CalendarLegend colors={propertyColors} properties={legendProperties} />

        {viewMode === "week" ? (
          events.length > 0 ? (
            <WeekView
              events={events}
              currentWeekStart={new Date(today.getFullYear(), today.getMonth(), today.getDate() - today.getDay() + 1)}
              onWeekChange={(dir) => { /* week nav handled internally */ }}
              onToday={() => {}}
              propertyId={selectedPropertyId || undefined}
            />
          ) : (
            <Card>
              <CardContent className="py-8 text-center">
                <CalendarDays className="h-12 w-12 text-muted-foreground/40 mx-auto mb-3" />
                <p className="text-muted-foreground">Week view requires additional event data from the server.</p>
                <p className="text-xs text-muted-foreground/60 mt-1">Select a property and ensure the calendar data includes events.</p>
              </CardContent>
            </Card>
          )
        ) : (
          <>
            <Card>
              <CardHeader className="pb-2">
                <div className="flex items-center justify-between">
                  <Button variant="outline" size="sm" onClick={() => navigate(prevMonth.getFullYear(), prevMonth.getMonth() + 1)}>
                    <ChevronLeft className="h-4 w-4" />
                  </Button>
                  <CardTitle className="text-lg">{formatMonthYear(new Date(normalizedYear, normalizedMonth - 1, 1))}</CardTitle>
                  <div className="flex items-center gap-1">
                    <Button variant="outline" size="sm" onClick={() => navigate(today.getFullYear(), today.getMonth() + 1)}>
                      Today
                    </Button>
                    <Button variant="outline" size="sm" onClick={() => navigate(nextMonth.getFullYear(), nextMonth.getMonth() + 1)}>
                      <ChevronRight className="h-4 w-4" />
                    </Button>
                  </div>
                </div>
              </CardHeader>
              <CardContent>
                {!selectedPropertyId && properties.length > 0 && (
                  <div className="bg-muted/40 rounded-lg p-4 text-center text-sm text-muted-foreground mb-4">
                    Select a property to view its calendar
                  </div>
                )}
                <div className="grid grid-cols-7 gap-px rounded-md border bg-muted">
                  {dayNames.map((name) => (
                    <div key={name} className="bg-background p-2 text-center text-xs font-medium text-muted-foreground">
                      {name}
                    </div>
                  ))}
                  {cells.map((cell, i) => {
                    const isToday = cell.dateStr === todayStr;
                    const dayData = cell.dateStr ? calendar?.days?.[cell.dateStr] : null;
                    return (
                      <div
                        key={i}
                        className={cn(
                          "bg-background p-2 text-sm min-h-[90px] transition-colors",
                          !cell.dayNum && "opacity-30",
                          isToday && "ring-1 ring-primary/30 ring-inset",
                          dayData?.booked && "cursor-pointer hover:bg-accent/30",
                        )}
                        onClick={() => cell.dateStr && handleDayClick(cell.dateStr)}
                      >
                        {cell.dayNum && (
                          <>
                            <span className={cn(
                              "text-xs font-medium inline-flex items-center justify-center size-6 rounded-full",
                              isToday && "bg-primary text-primary-foreground",
                            )}>
                              {cell.dayNum}
                            </span>
                            {dayData?.bookings.map((b) => (
                              <div
                                key={b.id}
                                className={cn(
                                  "mt-1 rounded px-1 py-0.5 text-[10px] leading-tight cursor-pointer hover:opacity-80",
                                  STATUS_BADGE[b.status] || "bg-muted text-muted-foreground",
                                )}
                                onClick={(e) => {
                                  e.stopPropagation();
                                  router.get(route("dashboard.bookings.show", b.id));
                                }}
                              >
                                #{b.id}
                              </div>
                            ))}
                          </>
                        )}
                      </div>
                    );
                  })}
                </div>
              </CardContent>
            </Card>

            <div className="grid grid-cols-2 gap-4">
              <Card>
                <CardHeader className="pb-2">
                  <CardTitle className="text-sm">{formatMonthYear(prevMonth)}</CardTitle>
                </CardHeader>
                <CardContent>
                  <Button variant="outline" size="sm" onClick={() => navigate(prevMonth.getFullYear(), prevMonth.getMonth() + 1)}>
                    <ChevronLeft className="mr-1 h-3 w-3" /> View
                  </Button>
                </CardContent>
              </Card>
              <Card>
                <CardHeader className="pb-2">
                  <CardTitle className="text-sm">{formatMonthYear(nextMonth)}</CardTitle>
                </CardHeader>
                <CardContent>
                  <Button variant="outline" size="sm" onClick={() => navigate(nextMonth.getFullYear(), nextMonth.getMonth() + 1)}>
                    View <ChevronRight className="ml-1 h-3 w-3" />
                  </Button>
                </CardContent>
              </Card>
            </div>

            <Card>
              <CardContent className="pt-4">
                <p className="text-xs font-medium text-muted-foreground mb-2">
                  {attentionOnly ? "Attention-needed" : "Month"} summary
                </p>
                <div className="flex flex-wrap gap-x-4 gap-y-1 items-center">
                  {statusSummary.map(({ status, label, count }) => (
                    <div key={status} className="flex items-center gap-1.5 text-xs">
                      <span className={`w-2 h-2 rounded-full ${STATUS_DOT[status] || "bg-muted"}`} />
                      <span className="text-muted-foreground">{label}</span>
                      <span className="font-medium">{count}</span>
                    </div>
                  ))}
                </div>
              </CardContent>
            </Card>
          </>
        )}
      </div>

      <DetailDayDialog
        detailDay={detailDay}
        onClose={() => setDetailDay(null)}
      />

      <AlertDialog open={blockConfirmOpen} onOpenChange={(open) => { if (!open) setBlockConfirmOpen(false); }}>
        <AlertDialogContent className="sm:max-w-sm">
          <AlertDialogHeader>
            <AlertDialogTitle>Block dates</AlertDialogTitle>
            <AlertDialogDescription>This will mark these dates as unavailable for booking.</AlertDialogDescription>
          </AlertDialogHeader>
          {blockStart && blockEnd && (
            <div className="py-3 space-y-3">
              <div className="flex items-center justify-between text-sm">
                <span className="text-muted-foreground">From</span>
                <span className="font-medium">{blockStart.toLocaleDateString("en-US", { weekday: "short", month: "long", day: "numeric", year: "numeric" })}</span>
              </div>
              <div className="flex items-center justify-between text-sm">
                <span className="text-muted-foreground">To</span>
                <span className="font-medium">{blockEnd.toLocaleDateString("en-US", { weekday: "short", month: "long", day: "numeric", year: "numeric" })}</span>
              </div>
              <div className="flex items-center justify-between text-sm pt-2 border-t">
                <span className="text-muted-foreground">Nights</span>
                <span className="font-medium">{Math.max(1, Math.round((blockEnd.getTime() - blockStart.getTime()) / (1000 * 60 * 60 * 24)))}</span>
              </div>
              <div className="pt-1">
                <label className="text-xs text-muted-foreground block mb-1">Reason (optional)</label>
                <input type="text" value={blockReason} onChange={(e) => setBlockReason(e.target.value)}
                  placeholder="Maintenance, owner stay, personal use..."
                  className="w-full h-9 px-3 rounded-md border border-border bg-background text-sm text-foreground placeholder:text-muted-foreground/50 focus:outline-none focus:ring-2 focus:ring-ring" />
              </div>
            </div>
          )}
          <AlertDialogFooter>
            <AlertDialogCancel disabled={blocking}>Cancel</AlertDialogCancel>
            <AlertDialogAction disabled={blocking} onClick={executeBlock}>
              {blocking ? "Blocking..." : "Block dates"}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </AuthenticatedLayout>
  );
}

function DetailDayDialog({
  detailDay,
  onClose,
}: {
  detailDay: { dateStr: string; bookings: DayBooking[] } | null;
  onClose: () => void;
}) {
  if (!detailDay) return null;
  const dateObj = new Date(detailDay.dateStr + "T12:00:00");
  const displayDate = dateObj.toLocaleDateString("en-US", {
    weekday: "long", month: "long", day: "numeric", year: "numeric",
  });

  return (
    <Dialog open={!!detailDay} onOpenChange={(open) => !open && onClose()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{displayDate}</DialogTitle>
          <DialogDescription>{detailDay.bookings.length} booking{detailDay.bookings.length !== 1 ? "s" : ""} on this date</DialogDescription>
        </DialogHeader>
        <div className="space-y-2">
          {detailDay.bookings.length === 0 ? (
            <p className="text-sm text-muted-foreground text-center py-4">No bookings on this date.</p>
          ) : (
            detailDay.bookings.map((b) => (
              <Link
                key={b.id}
                href={route("dashboard.bookings.show", b.id)}
                className="flex items-center justify-between p-3 rounded-lg border border-border hover:bg-accent/50 transition-colors"
              >
                <span className="font-mono text-sm font-medium text-foreground">#{b.id}</span>
                <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${STATUS_BADGE[b.status] || ""}`}>
                  {getStatusLabel(b.status)}
                </span>
              </Link>
            ))
          )}
        </div>
      </DialogContent>
    </Dialog>
  );
}
