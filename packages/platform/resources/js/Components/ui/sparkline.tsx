"use client";

import * as React from "react";
import { Line, LineChart, ResponsiveContainer } from "recharts";
import { cn } from "cn";

interface SparklineProps {
  data: number[];
  color?: string;
  className?: string;
  height?: number;
  width?: number;
}

function Sparkline({ data, color = "hsl(var(--chart-1))", className, height = 40, width = 96 }: SparklineProps) {
  if (!data || data.length === 0) {
    return null;
  }

  const chartData = data.map((value, index) => ({ index, value }));

  return (
    <div className={cn("shrink-0", className)} style={{ height, width }}>
      <ResponsiveContainer width="100%" height="100%">
        <LineChart data={chartData} margin={{ top: 2, right: 2, bottom: 2, left: 2 }}>
          <Line
            type="monotone"
            dataKey="value"
            stroke={color}
            strokeWidth={1.5}
            dot={false}
            isAnimationActive={false}
          />
        </LineChart>
      </ResponsiveContainer>
    </div>
  );
}

export { Sparkline };
export type { SparklineProps };
