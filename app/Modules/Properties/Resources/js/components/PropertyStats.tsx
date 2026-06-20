import { Building2, CheckCircle2, FileEdit, DollarSign } from "lucide-react";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { cn } from "@/lib/utils";

interface PropertyStatsProps {
  total: number;
  published: number;
  draft: number;
  totalValue?: number | null;
  currency?: string;
}

export function PropertyStats({
  total,
  published,
  draft,
  totalValue,
  currency = "USD",
}: PropertyStatsProps) {
  const stats = [
    {
      label: "Total",
      value: total,
      icon: Building2,
      color: "text-blue-600 dark:text-blue-400",
      bg: "bg-blue-100 dark:bg-blue-950",
    },
    {
      label: "Published",
      value: published,
      icon: CheckCircle2,
      color: "text-green-600 dark:text-green-400",
      bg: "bg-green-100 dark:bg-green-950",
    },
    {
      label: "Draft",
      value: draft,
      icon: FileEdit,
      color: "text-amber-600 dark:text-amber-400",
      bg: "bg-amber-100 dark:bg-amber-950",
    },
    {
      label: "Total Value",
      value: totalValue != null ? formatCurrency(totalValue, currency) : "-",
      icon: DollarSign,
      color: "text-purple-600 dark:text-purple-400",
      bg: "bg-purple-100 dark:bg-purple-950",
    },
  ];

  return (
    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
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
