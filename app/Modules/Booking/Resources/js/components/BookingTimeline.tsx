import { CheckCircle2, XCircle, Clock, ArrowUpRight, LogIn, LogOut, HelpCircle } from "lucide-react";
import { cn } from "@/lib/utils";

interface StatusEvent {
  status: string;
  timestamp: string | null;
  label: string;
}

interface BookingTimelineProps {
  events: StatusEvent[];
}

const statusIcons: Record<string, typeof Clock> = {
  pending: Clock,
  confirmed: CheckCircle2,
  checked_in: LogIn,
  checked_out: LogOut,
  cancelled: XCircle,
  no_show: XCircle,
  completed: CheckCircle2,
};

const statusColors: Record<string, string> = {
  pending: "text-amber-500 border-amber-300",
  confirmed: "text-green-500 border-green-300",
  checked_in: "text-blue-500 border-blue-300",
  checked_out: "text-gray-500 border-gray-300",
  cancelled: "text-red-500 border-red-300",
  no_show: "text-red-500 border-red-300",
  completed: "text-green-500 border-green-300",
};

const statusBg: Record<string, string> = {
  pending: "bg-amber-100 dark:bg-amber-950",
  confirmed: "bg-green-100 dark:bg-green-950",
  checked_in: "bg-blue-100 dark:bg-blue-950",
  checked_out: "bg-gray-100 dark:bg-gray-800",
  cancelled: "bg-red-100 dark:bg-red-950",
  no_show: "bg-red-100 dark:bg-red-950",
  completed: "bg-green-100 dark:bg-green-950",
};

export function BookingTimeline({ events }: BookingTimelineProps) {
  const sorted = [...events]
    .filter((e) => e.timestamp)
    .sort((a, b) => new Date(a.timestamp!).getTime() - new Date(b.timestamp!).getTime());

  if (sorted.length === 0) {
    return (
      <div className="flex items-center justify-center py-8 text-sm text-muted-foreground">
        <HelpCircle className="mr-2 h-4 w-4" />
        No status history available.
      </div>
    );
  }

  return (
    <div className="space-y-0">
      {sorted.map((event, index) => {
        const Icon = statusIcons[event.status] || Clock;
        const isLast = index === sorted.length - 1;

        return (
          <div key={event.status} className="relative flex items-start gap-3 pb-4">
            {!isLast && (
              <div className="absolute left-[15px] top-8 h-full w-px bg-border" />
            )}
            <div className={cn("rounded-full border p-1.5", statusColors[event.status], statusBg[event.status])}>
              <Icon className="h-3.5 w-3.5" />
            </div>
            <div className="flex-1 min-w-0 pt-0.5">
              <p className="text-sm font-medium">{event.label}</p>
              {event.timestamp && (
                <p className="text-xs text-muted-foreground">
                  {new Date(event.timestamp).toLocaleString()}
                </p>
              )}
            </div>
          </div>
        );
      })}
    </div>
  );
}
