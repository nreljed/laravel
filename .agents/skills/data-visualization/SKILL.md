---
name: data-visualization
description: "Data visualization and chart patterns for Cost Breaker 360. Activate when building charts, graphs, KPI cards, metrics, sparklines, dashboards, or analytics UI. Covers Recharts integration with shadcn Chart component, all chart types (Area, Bar, Line, Pie, Radar, RadialBar, Scatter, Composed, Funnel), chart theming with CSS variables, KPI cards with trend indicators, sparklines, date range filtering, and Google Analytics-style dashboard patterns."
license: MIT
metadata:
  author: laravel
---

# Data Visualization

## Architecture

- **Chart library**: Recharts (via shadcn `ChartContainer` wrapper)
- **Chart component**: `@nreljed/platform/Components/ui/chart` (ChartContainer, ChartTooltip, ChartTooltipContent, ChartLegend, ChartLegendContent, ChartStyle)
- **Card component**: `@nreljed/platform/Components/ui/card` (Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter)
- **Date filtering**: `@nreljed/platform/Components/shared/date-range-picker` (DateRangePicker)
- **Table**: `@nreljed/platform/Components/ui/table` (Table, TableBody, TableCell, TableHead, TableHeader, TableRow)
- **Badge**: `@nreljed/platform/Components/ui/badge`
- **Button**: `@nreljed/platform/Components/ui/button`

## Chart Types Available (Recharts)

| Type | Import | Best For |
|---|---|---|
| Area | `AreaChart`, `Area` | Trends over time, cumulative data |
| Bar | `BarChart`, `Bar` | Comparisons, categories |
| Line | `LineChart`, `Line` | Continuous data, trends |
| Pie/Donut | `PieChart`, `Pie`, `Cell` | Proportions, distribution |
| Radar | `RadarChart`, `Radar`, `PolarGrid` | Multi-dimensional comparison |
| RadialBar | `RadialBarChart`, `RadialBar` | Progress, completion rates |
| Scatter | `ScatterChart`, `Scatter` | Correlation, distribution |
| Composed | `ComposedChart` | Mixed chart types |
| Funnel | `FunnelChart`, `Funnel` | Conversion funnels |
| Treemap | `Treemap` | Hierarchical data |

## Basic Chart Pattern

```tsx
import { Area, AreaChart, CartesianGrid, XAxis } from "recharts";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@nreljed/platform/Components/ui/card";
import { ChartContainer, ChartTooltip, ChartTooltipContent, type ChartConfig } from "@nreljed/platform/Components/ui/chart";

const chartConfig = {
  visitors: { label: "Visitors", color: "hsl(var(--chart-1))" },
  pageViews: { label: "Page Views", color: "hsl(var(--chart-2))" },
} satisfies ChartConfig;

function VisitorsChart({ data }: { data: { date: string; visitors: number; pageViews: number }[] }) {
  return (
    <Card>
      <CardHeader>
        <CardTitle>Visitors Over Time</CardTitle>
        <CardDescription>Last 30 days</CardDescription>
      </CardHeader>
      <CardContent>
        <ChartContainer config={chartConfig} className="aspect-auto h-72 w-full">
          <AreaChart data={data} margin={{ left: 0, right: 8, top: 8 }}>
            <defs>
              <linearGradient id="fillVisitors" x1="0" y1="0" x2="0" y2="1">
                <stop offset="5%" stopColor="var(--color-visitors)" stopOpacity={0.35} />
                <stop offset="95%" stopColor="var(--color-visitors)" stopOpacity={0.02} />
              </linearGradient>
            </defs>
            <CartesianGrid vertical={false} />
            <XAxis dataKey="date" tickLine={false} axisLine={false} tickMargin={8} minTickGap={32} />
            <ChartTooltip cursor={false} content={<ChartTooltipContent indicator="line" />} />
            <Area dataKey="visitors" type="natural" fill="url(#fillVisitors)" stroke="var(--color-visitors)" strokeWidth={2} />
          </AreaChart>
        </ChartContainer>
      </CardContent>
    </Card>
  );
}
```

## KPI / Metric Card Pattern (Google Analytics Style)

```tsx
import { TrendingUp, TrendingDown, Minus } from "lucide-react";
import { Card, CardContent, CardHeader, CardTitle } from "@nreljed/platform/Components/ui/card";
import { cn } from "cn";

interface KpiCardProps {
  label: string;
  value: string | number;
  change?: number; // percentage change
  changeLabel?: string; // e.g. "vs. previous period"
  icon?: React.ComponentType<{ className?: string }>;
}

function KpiCard({ label, value, change, changeLabel, icon: Icon }: KpiCardProps) {
  const isPositive = change !== undefined && change > 0;
  const isNegative = change !== undefined && change < 0;

  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between pb-2">
        <CardTitle className="text-sm font-medium text-muted-foreground">{label}</CardTitle>
        {Icon && <Icon className="h-4 w-4 text-muted-foreground" />}
      </CardHeader>
      <CardContent>
        <div className="text-2xl font-bold tabular-nums">{value}</div>
        {change !== undefined && (
          <div className="flex items-center gap-1 mt-1">
            {isPositive && <TrendingUp className="h-3.5 w-3.5 text-emerald-600 dark:text-emerald-400" />}
            {isNegative && <TrendingDown className="h-3.5 w-3.5 text-destructive" />}
            {!isPositive && !isNegative && <Minus className="h-3.5 w-3.5 text-muted-foreground" />}
            <span className={cn(
              "text-xs font-medium",
              isPositive && "text-emerald-600 dark:text-emerald-400",
              isNegative && "text-destructive",
              !isPositive && !isNegative && "text-muted-foreground",
            )}>
              {change > 0 ? "+" : ""}{change}%
            </span>
            {changeLabel && <span className="text-xs text-muted-foreground">{changeLabel}</span>}
          </div>
        )}
      </CardContent>
    </Card>
  );
}

// Usage in a dashboard grid:
<div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
  <KpiCard label="Total Users" value="12,345" change={12.5} changeLabel="vs. last month" icon={Users} />
  <KpiCard label="Revenue" value="$45,678" change={-3.2} changeLabel="vs. last month" icon={DollarSign} />
  <KpiCard label="Orders" value="1,234" change={8.1} changeLabel="vs. last month" icon={ShoppingCart} />
  <KpiCard label="Conversion Rate" value="3.2%" change={0.5} changeLabel="vs. last month" icon={TrendingUp} />
</div>
```

## Sparkline (Mini Chart in Card)

```tsx
import { Line, LineChart, ResponsiveContainer } from "recharts";
import { ChartContainer, type ChartConfig } from "@nreljed/platform/Components/ui/chart";

const sparkConfig = { value: { color: "hsl(var(--chart-1))" } } satisfies ChartConfig;

function Sparkline({ data, color }: { data: number[]; color?: string }) {
  return (
    <ChartContainer config={sparkConfig} className="h-10 w-24">
      <LineChart data={data.map((v, i) => ({ i, value: v }))}>
        <Line
          dataKey="value"
          type="monotone"
          stroke={color ?? "var(--color-value)"}
          strokeWidth={1.5}
          dot={false}
        />
      </LineChart>
    </ChartContainer>
  );
}

// Usage inside KPI card:
<CardContent className="flex items-end justify-between">
  <div>
    <div className="text-2xl font-bold tabular-nums">{value}</div>
    <div className="text-xs text-muted-foreground">{change}% vs last period</div>
  </div>
  <Sparkline data={sparkData} />
</CardContent>
```

## Pie / Donut Chart

```tsx
import { Pie, PieChart, Cell } from "recharts";
import { ChartContainer, ChartTooltip, ChartTooltipContent, type ChartConfig } from "@nreljed/platform/Components/ui/chart";

const pieConfig = {
  desktop: { label: "Desktop", color: "hsl(var(--chart-1))" },
  mobile: { label: "Mobile", color: "hsl(var(--chart-2))" },
  tablet: { label: "Tablet", color: "hsl(var(--chart-3))" },
} satisfies ChartConfig;

function TrafficSourcesChart({ data }: { data: { name: string; value: number }[] }) {
  return (
    <Card>
      <CardHeader>
        <CardTitle>Traffic Sources</CardTitle>
      </CardHeader>
      <CardContent>
        <ChartContainer config={pieConfig} className="aspect-square h-64">
          <PieChart>
            <ChartTooltip content={<ChartTooltipContent nameKey="name" />} />
            <Pie data={data} dataKey="value" nameKey="name" innerRadius={60} outerRadius={80} strokeWidth={2}>
              {data.map((entry, index) => (
                <Cell key={index} fill={`var(--color-${entry.name.toLowerCase()})`} />
              ))}
            </Pie>
          </PieChart>
        </ChartContainer>
      </CardContent>
    </Card>
  );
}
```

## Bar Chart (Horizontal)

```tsx
import { Bar, BarChart, CartesianGrid, XAxis, YAxis } from "recharts";

function TopPagesChart({ data }: { data: { page: string; views: number }[] }) {
  return (
    <Card>
      <CardHeader>
        <CardTitle>Top Pages</CardTitle>
      </CardHeader>
      <CardContent>
        <ChartContainer config={chartConfig} className="aspect-auto h-72">
          <BarChart data={data} layout="vertical" margin={{ left: 0, right: 8 }}>
            <CartesianGrid horizontal={false} />
            <YAxis dataKey="page" type="category" tickLine={false} axisLine={false} width={120} />
            <XAxis type="number" tickLine={false} axisLine={false} />
            <ChartTooltip content={<ChartTooltipContent />} />
            <Bar dataKey="views" fill="var(--color-views)" radius={[0, 4, 4, 0]} />
          </BarChart>
        </ChartContainer>
      </CardContent>
    </Card>
  );
}
```

## Dashboard Layout Pattern (Google Analytics Style)

```tsx
function AnalyticsDashboard() {
  return (
    <div className="space-y-6">
      {/* KPI Row */}
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <KpiCard label="Users" value="12,345" change={12.5} />
        <KpiCard label="Sessions" value="45,678" change={-3.2} />
        <KpiCard label="Bounce Rate" value="42.3%" change={-1.5} />
        <KpiCard label="Avg. Duration" value="2m 34s" change={5.1} />
      </div>

      {/* Chart Row */}
      <div className="grid gap-6 lg:grid-cols-[2fr_1fr]">
        <Card>
          <CardHeader>
            <CardTitle>Users Over Time</CardTitle>
            <CardDescription>Last 30 days</CardDescription>
          </CardHeader>
          <CardContent>
            <ChartContainer config={chartConfig} className="aspect-auto h-80">
              <AreaChart data={data}>
                {/* ... */}
              </AreaChart>
            </ChartContainer>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Traffic Sources</CardTitle>
          </CardHeader>
          <CardContent>
            <ChartContainer config={pieConfig} className="aspect-square h-64">
              <PieChart>
                {/* ... */}
              </PieChart>
            </ChartContainer>
          </CardContent>
        </Card>
      </div>

      {/* Data Table Row */}
      <Card>
        <CardHeader>
          <CardTitle>Top Pages</CardTitle>
          <CardDescription>Most visited pages in this period</CardDescription>
        </CardHeader>
        <CardContent>
          <DataTable data={topPages} columns={columns} />
        </CardContent>
      </Card>
    </div>
  );
}
```

## Date Range Filtering

```tsx
import { DateRangePicker, type DateRange } from "@nreljed/platform/Components/shared/date-range-picker";

function AnalyticsPage() {
  const [dateRange, setDateRange] = useState<DateRange>({ from: "", to: "" });

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h2 className="text-lg font-semibold">Analytics</h2>
        <DateRangePicker value={dateRange} onChange={setDateRange} />
      </div>
      {/* Charts and tables filtered by dateRange */}
    </div>
  );
}
```

## Chart Theming

Charts use CSS variables for colors. The platform defines `--chart-1` through `--chart-5`:

```css
/* In core.css or theme */
--chart-1: 12 76% 61%;   /* Orange */
--chart-2: 173 58% 39%;  /* Teal */
--chart-3: 197 37% 24%;  /* Dark blue */
--chart-4: 43 74% 66%;   /* Yellow */
--chart-5: 27 87% 67%;   /* Light orange */
```

Usage in chart config:
```tsx
const chartConfig = {
  visitors: { label: "Visitors", color: "hsl(var(--chart-1))" },
  pageViews: { label: "Page Views", color: "hsl(var(--chart-2))" },
} satisfies ChartConfig;
```

## Responsive Chart Patterns

```tsx
// Aspect ratio for responsive charts
<ChartContainer config={config} className="aspect-auto h-72 w-full">
  <AreaChart data={data}>...</AreaChart>
</ChartContainer>

// Square for pie/donut
<ChartContainer config={config} className="aspect-square h-64">
  <PieChart>...</PieChart>
</ChartContainer>

// Full width for bar charts
<ChartContainer config={config} className="aspect-auto h-96 w-full">
  <BarChart data={data}>...</BarChart>
</ChartContainer>
```

## Loading States

```tsx
function ChartSkeleton({ title }: { title: string }) {
  return (
    <Card>
      <CardHeader>
        <CardTitle>{title}</CardTitle>
        <CardDescription>Loading...</CardDescription>
      </CardHeader>
      <CardContent className="aspect-auto h-72">
        <div className="h-full w-full animate-pulse rounded-md bg-muted" />
      </CardContent>
    </Card>
  );
}

// Usage with Inertia deferred props
<Deferred data="analytics" fallback={<ChartSkeleton title="Analytics" />}>
  <AnalyticsChart data={analytics} />
</Deferred>
```

## Empty States

```tsx
function EmptyChart({ message }: { message: string }) {
  return (
    <div className="flex h-72 items-center justify-center rounded-lg border border-dashed text-sm text-muted-foreground">
      {message}
    </div>
  );
}

// Usage
{data.length === 0 ? (
  <EmptyChart message="No data for this period" />
) : (
  <ChartContainer config={config} className="aspect-auto h-72">
    <AreaChart data={data}>...</AreaChart>
  </ChartContainer>
)}
```

## Google Analytics-Style Patterns

### 1. Real-time metric with sparkline
```tsx
<Card>
  <CardHeader className="flex flex-row items-center justify-between pb-2">
    <CardTitle className="text-sm font-medium text-muted-foreground">Active Users</CardTitle>
    <span className="flex items-center gap-1 text-xs text-emerald-600">
      <span className="h-2 w-2 rounded-full bg-emerald-500 animate-pulse" />
      Live
    </span>
  </CardHeader>
  <CardContent className="flex items-end justify-between">
    <div>
      <div className="text-3xl font-bold tabular-nums">1,234</div>
      <p className="text-xs text-muted-foreground">Last 30 minutes</p>
    </div>
    <Sparkline data={sparkData} color="hsl(var(--chart-1))" />
  </CardContent>
</Card>
```

### 2. Comparison period (vs previous)
```tsx
<div className="flex items-center gap-2">
  <span className="text-2xl font-bold tabular-nums">{currentValue}</span>
  <span className={cn(
    "text-xs font-medium",
    change > 0 ? "text-emerald-600" : change < 0 ? "text-destructive" : "text-muted-foreground",
  )}>
    {change > 0 ? "↑" : change < 0 ? "↓" : "→"} {Math.abs(change)}%
  </span>
  <span className="text-xs text-muted-foreground">vs. previous period</span>
</div>
```

### 3. Drill-down table
```tsx
<Card>
  <CardHeader>
    <CardTitle>Top Pages</CardTitle>
    <CardDescription>Most visited pages in this period</CardDescription>
  </CardHeader>
  <CardContent>
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead>Page</TableHead>
          <TableHead className="text-right">Views</TableHead>
          <TableHead className="text-right">Users</TableHead>
          <TableHead className="text-right">Bounce Rate</TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        {topPages.map((page) => (
          <TableRow key={page.path}>
            <TableCell className="font-medium">{page.path}</TableCell>
            <TableCell className="text-right tabular-nums">{page.views.toLocaleString()}</TableCell>
            <TableCell className="text-right tabular-nums">{page.users.toLocaleString()}</TableCell>
            <TableCell className="text-right tabular-nums">{page.bounceRate}%</TableCell>
          </TableRow>
        ))}
      </TableBody>
    </Table>
  </CardContent>
</Card>
```

## Key Files

| File | Purpose |
|---|---|
| `packages/platform/resources/js/Components/ui/chart.tsx` | ChartContainer, ChartTooltip, ChartLegend, ChartStyle |
| `packages/platform/resources/js/Components/ui/card.tsx` | Card, CardHeader, CardTitle, CardDescription, CardContent |
| `packages/platform/resources/js/Components/ui/table.tsx` | Table, TableHeader, TableBody, TableRow, TableHead, TableCell |
| `packages/platform/resources/js/Components/ui/badge.tsx` | Badge with variants |
| `packages/platform/resources/js/Components/shared/date-range-picker.tsx` | DateRangePicker |
| `packages/platform/resources/js/Components/ui/calendar.tsx` | Calendar (used by date picker) |
| `packages/platform/resources/js/Pages/dashboard/stats.tsx` | Stats page example |
| `packages/marketing-module/src/Resources/js/Pages/Analytics.tsx` | Analytics page example |
| `packages/shop-module/src/Resources/js/Pages/Reports/Index.tsx` | Reports page example |

## Common Pitfalls

- **Chart height**: Always set a height on ChartContainer (`h-72`, `h-80`). Without it, charts collapse to 0 height.
- **ChartConfig colors**: Use `hsl(var(--chart-N))` not raw hex. The ChartStyle component injects CSS variables.
- **ResponsiveContainer**: ChartContainer wraps ResponsiveContainer. Don't nest another one.
- **Empty data**: Always handle empty data with an EmptyChart component, not a broken chart.
- **Loading state**: Use skeleton placeholders while data loads. Inertia deferred props are ideal.
- **Number formatting**: Use `Intl.NumberFormat` for large numbers. Use `tabular-nums` for alignment.
- **Chart axis**: Set `tickLine={false}` and `axisLine={false}` for cleaner look. Use `minTickGap` for dense data.
- **Tooltip**: Always include ChartTooltip with ChartTooltipContent. Set `cursor={false}` for line/area charts.
- **Dark mode**: Charts inherit theme colors via CSS variables. Test in both light and dark modes.
- **Performance**: For large datasets, aggregate data server-side. Don't send 10,000 points to the client.
- **Accessibility**: Add `aria-label` to chart containers. Provide text alternatives for screen readers.
- **Date ranges**: Use the DateRangePicker component. Default to last 30 days.
- **Comparison**: Always show "vs. previous period" for KPI metrics.
