import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { BarChart3 } from "lucide-react";

interface RevenueDay {
  date: string;
  revenue: number;
  orders: number;
}

interface RevenueChartProps {
  data: RevenueDay[];
}

export default function RevenueChart({ data }: RevenueChartProps) {
  if (data.length === 0) {
    return (
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <BarChart3 className="h-4 w-4" />
            Revenue (Last 14 Days)
          </CardTitle>
        </CardHeader>
        <CardContent>
          <p className="text-sm text-muted-foreground text-center py-8">No revenue data yet.</p>
        </CardContent>
      </Card>
    );
  }

  const maxRevenue = Math.max(...data.map((d) => d.revenue), 1);
  const currency = new Intl.NumberFormat("en-US", { style: "currency", currency: "EUR" });

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <BarChart3 className="h-4 w-4" />
          Revenue (Last 14 Days)
        </CardTitle>
      </CardHeader>
      <CardContent>
        <div className="space-y-1.5">
          {data.map((day) => (
            <div key={day.date} className="flex items-center gap-2 text-sm">
              <span className="w-14 shrink-0 text-muted-foreground text-xs">{day.date}</span>
              <div className="flex-1 h-4 bg-muted rounded-sm overflow-hidden">
                <div
                  className="h-full bg-primary rounded-sm transition-all"
                  style={{ width: `${Math.max((day.revenue / maxRevenue) * 100, 1)}%` }}
                />
              </div>
              <span className="w-22 text-right shrink-0 font-medium tabular-nums text-xs">
                {currency.format(day.revenue)}
              </span>
              <span className="w-10 text-right shrink-0 text-muted-foreground tabular-nums text-xs">
                {day.orders}
              </span>
            </div>
          ))}
        </div>
      </CardContent>
    </Card>
  );
}
