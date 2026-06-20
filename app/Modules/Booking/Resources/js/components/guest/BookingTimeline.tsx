import { CalendarDays, ArrowRight } from "lucide-react";

const formatTimeStr = (time?: string) => {
  if (!time) return "";
  const [h, m] = time.split(":");
  if (!h || !m) return time;
  const hour = Number(h);
  const ampm = hour >= 12 ? "PM" : "AM";
  const hour12 = hour === 0 ? 12 : hour > 12 ? hour - 12 : hour;
  return `${hour12}:${m} ${ampm}`;
};

const getTimezoneLabel = (tz: string) => {
  try {
    const formatter = new Intl.DateTimeFormat("en", {
      timeZone: tz,
      timeZoneName: "longOffset",
    });
    const parts = formatter.formatToParts(new Date());
    const offsetName = parts.find((p) => p.type === "timeZoneName")?.value || "";
    return `${tz} (${offsetName.replace("GMT", "UTC")})`;
  } catch {
    return tz;
  }
};

interface BookingTimelineProps {
  checkInDate: string;
  checkOutDate: string;
  checkInTime: string;
  checkOutTime: string;
  timezone: string;
  nights: number;
}

export const BookingTimeline = ({
  checkInDate,
  checkOutDate,
  checkInTime,
  checkOutTime,
  timezone,
  nights,
}: BookingTimelineProps) => {
  return (
    <div className="relative">
      <div className="absolute left-[13px] top-7 bottom-7 w-px bg-border" />
      <div className="space-y-5">
        <div className="flex items-start gap-4">
          <div className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary/10">
            <CalendarDays className="h-4 w-4 text-primary" />
          </div>
          <div className="min-w-0 flex-1">
            <p className="text-muted-foreground text-sm">Check-in</p>
            <p className="text-base font-medium">{checkInDate}</p>
            <p className="text-muted-foreground text-sm">
              {formatTimeStr(checkInTime)} {getTimezoneLabel(timezone)}
            </p>
          </div>
        </div>
        <div className="flex items-start gap-4">
          <div className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-muted">
            <ArrowRight className="h-4 w-4 text-muted-foreground" />
          </div>
          <div className="min-w-0 flex-1">
            <p className="text-sm font-medium text-muted-foreground">
              {nights} night{nights !== 1 ? "s" : ""}
            </p>
          </div>
        </div>
        <div className="flex items-start gap-4">
          <div className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary/10">
            <CalendarDays className="h-4 w-4 text-primary" />
          </div>
          <div className="min-w-0 flex-1">
            <p className="text-muted-foreground text-sm">Check-out</p>
            <p className="text-base font-medium">{checkOutDate}</p>
            <p className="text-muted-foreground text-sm">
              {formatTimeStr(checkOutTime)} {getTimezoneLabel(timezone)}
            </p>
          </div>
        </div>
      </div>
    </div>
  );
};
