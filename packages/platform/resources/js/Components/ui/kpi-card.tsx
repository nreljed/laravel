"use client";

import * as React from "react";
import { TrendingUp, TrendingDown, Minus } from "lucide-react";
import { cn } from "cn";
import { Card, CardContent, CardHeader, CardTitle } from "./card";
import { Sparkline } from "./sparkline";

interface KpiCardProps {
  label: string;
  value: string | number;
  change?: number;
  changeLabel?: string;
  icon?: React.ComponentType<{ className?: string }>;
  sparkline?: number[];
  sparklineColor?: string;
  className?: string;
}

function KpiCard({
  label,
  value,
  change,
  changeLabel = "vs. previous period",
  icon: Icon,
  sparkline,
  sparklineColor,
  className,
}: KpiCardProps) {
  const isPositive = change !== undefined && change > 0;
  const isNegative = change !== undefined && change < 0;

  return (
    <Card data-slot="kpi-card" className={className}>
      <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
        <CardTitle className="text-sm font-medium text-muted-foreground">{label}</CardTitle>
        {Icon && <Icon className="h-4 w-4 text-muted-foreground" />}
      </CardHeader>
      <CardContent>
        <div className="flex items-end justify-between gap-2">
          <div className="min-w-0">
            <div className="text-2xl font-bold tabular-nums">{value}</div>
            {change !== undefined && (
              <div className="mt-1 flex items-center gap-1">
                {isPositive && (
                  <TrendingUp className="h-3.5 w-3.5 shrink-0 text-emerald-600 dark:text-emerald-400" />
                )}
                {isNegative && (
                  <TrendingDown className="h-3.5 w-3.5 shrink-0 text-destructive" />
                )}
                {!isPositive && !isNegative && (
                  <Minus className="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                )}
                <span
                  className={cn(
                    "text-xs font-medium tabular-nums",
                    isPositive && "text-emerald-600 dark:text-emerald-400",
                    isNegative && "text-destructive",
                    !isPositive && !isNegative && "text-muted-foreground",
                  )}
                >
                  {change > 0 ? "+" : ""}
                  {change}%
                </span>
                <span className="text-xs text-muted-foreground">{changeLabel}</span>
              </div>
            )}
          </div>
          {sparkline && sparkline.length > 0 && (
            <Sparkline data={sparkline} color={sparklineColor} className="h-10 w-24 shrink-0" />
          )}
        </div>
      </CardContent>
    </Card>
  );
}

export { KpiCard };
export type { KpiCardProps };
