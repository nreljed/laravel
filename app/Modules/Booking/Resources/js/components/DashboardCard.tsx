import { Link } from "@inertiajs/react";
import { ArrowDownToLine, ArrowUpFromLine, Users, CalendarClock } from "lucide-react";

export interface DashboardBooking {
  id: number;
  guest_name: string;
  property_title: string;
  check_in_date?: string;
  check_out_date?: string;
}

export interface DashboardCardData {
  module: string;
  key: string;
  icon: string;
  label: string;
  count: number;
  href: string;
  items?: DashboardBooking[];
}

export const DASHBOARD_CARD_ICONS: Record<string, React.ElementType> = {
  ArrowDownToLine,
  ArrowUpFromLine,
  Users,
  CalendarClock,
};

export function DashboardCard({
  icon: Icon,
  label,
  count,
  href = "",
  bookings = [],
}: {
  icon: React.ElementType;
  label: string;
  count: number;
  href?: string;
  bookings?: DashboardBooking[];
}) {
  const content = (
    <div className="flex items-center justify-between">
      <Icon className="h-5 w-5 text-muted-foreground" />
      <span className="text-2xl font-bold tracking-tight text-foreground">{count}</span>
    </div>
  );
  return (
    <div className="relative flex flex-col gap-1.5 rounded-lg border bg-card p-4 shadow-sm transition-shadow hover:shadow-md">
      {href ? (
        <Link href={href} className="block after:absolute after:inset-0">
          {content}
        </Link>
      ) : (
        content
      )}
      <p className="text-xs font-medium text-muted-foreground">{label}</p>
      {bookings.length > 0 && (
        <div className="mt-1.5 space-y-1 border-t pt-1.5 relative z-10">
          {bookings.slice(0, 3).map((b) => (
            <Link
              key={b.id}
              href={`/dashboard/manage-bookings/${b.id}`}
              className="block truncate text-[11px] text-muted-foreground hover:text-foreground hover:underline"
            >
              {b.guest_name} — {b.property_title || `#${b.id}`}
            </Link>
          ))}
          {bookings.length > 3 && (
            <p className="text-[11px] text-muted-foreground">+{bookings.length - 3} more</p>
          )}
        </div>
      )}
    </div>
  );
}
