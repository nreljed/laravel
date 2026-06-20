import { CalendarCheck, Clock, Ban, DollarSign, ArrowUpRight } from "lucide-react";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { cn } from "@/lib/utils";

interface BookingStatsProps {
  total: number;
  pending: number;
  confirmed: number;
  checked_in: number;
  cancelled: number;
  total_revenue: number;
  upcoming: number;
}

export function BookingStats({
  total,
  pending,
  confirmed,
  checked_in,
  cancelled,
  total_revenue,
  upcoming,
}: BookingStatsProps) {
  const stats = [
    {
      label: "Total",
      value: total,
      icon: CalendarCheck,
      color: "text-blue-600 dark:text-blue-400",
      bg: "bg-blue-100 dark:bg-blue-950",
    },
    {
      label: "Pending",
      value: pending,
      icon: Clock,
      color: "text-amber-600 dark:text-amber-400",
      bg: "bg-amber-100 dark:bg-amber-950",
    },
    {
      label: "Confirmed",
      value: confirmed,
      icon: ArrowUpRight,
      color: "text-green-600 dark:text-green-400",
      bg: "bg-green-100 dark:bg-green-950",
    },
    {
      label: "Checked In",
      value: checked_in,
      icon: ArrowUpRight,
      color: "text-teal-600 dark:text-teal-400",
      bg: "bg-teal-100 dark:bg-teal-950",
    },
    {
      label: "Cancelled",
      value: cancelled,
      icon: Ban,
      color: "text-red-600 dark:text-red-400",
      bg: "bg-red-100 dark:bg-red-950",
    },
    {
      label: "Revenue",
      value: formatCurrency(total_revenue, "USD"),
      icon: DollarSign,
      color: "text-purple-600 dark:text-purple-400",
      bg: "bg-purple-100 dark:bg-purple-950",
    },
    {
      label: "Upcoming",
      value: upcoming,
      icon: CalendarCheck,
      color: "text-cyan-600 dark:text-cyan-400",
      bg: "bg-cyan-100 dark:bg-cyan-950",
    },
  ];

  return (
    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
      {stats.map((stat) => (
        <Card key={stat.label}>
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-sm font-medium">{stat.label}</CardTitle>
            <div className={cn("rounded-md p-2", stat.bg)}>
              <stat.icon className={cn("h-4 w-4", stat.color)} />
            </div>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{stat.value}</div>
          </CardContent>
        </Card>
      ))}
    </div>
  );
}

function formatCurrency(amount: number, currencyCode: string): string {
  try {
    return new Intl.NumberFormat("en-US", {
      style: "currency",
      currency: currencyCode,
      maximumFractionDigits: 0,
    }).format(amount);
  } catch {
    return `${currencyCode} ${amount.toLocaleString()}`;
  }
}
