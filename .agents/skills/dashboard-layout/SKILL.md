---
name: dashboard-layout
description: "Dashboard layout patterns and responsive grid systems for Cost Breaker 360. Activate when building dashboards, admin panels, analytics pages, KPI cards, metric grids, or when user mentions dashboard layout, grid, responsive dashboard, card arrangement, sidebar, or analytics page structure. Covers grid patterns, responsive breakpoints, card arrangements, sidebar navigation, and Google Analytics-style dashboard layouts."
license: MIT
metadata:
  author: laravel
---

# Dashboard Layout

## Architecture

- **Framework**: React 19 + Inertia.js 3 + Tailwind CSS 4
- **Layout**: `AuthenticatedLayout` (platform) for dashboard pages
- **Grid system**: Tailwind CSS grid utilities
- **Cards**: `@nreljed/platform/Components/ui/card` (Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter)
- **Icons**: `lucide-react`
- **Responsive**: Mobile-first with `sm:`, `md:`, `lg:`, `xl:` breakpoints

## Dashboard Grid Patterns

### KPI Row (4 metrics)

```tsx
<div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
  <KpiCard label="Total Users" value="12,345" change={12.5} />
  <KpiCard label="Revenue" value="$45,678" change={-3.2} />
  <KpiCard label="Orders" value="1,234" change={8.1} />
  <KpiCard label="Conversion" value="3.2%" change={0.5} />
</div>
```

### Chart + Sidebar (2/3 + 1/3)

```tsx
<div className="grid gap-6 lg:grid-cols-[2fr_1fr]">
  <Card>
    <CardHeader>
      <CardTitle>Revenue Over Time</CardTitle>
    </CardHeader>
    <CardContent>
      <ChartContainer config={config} className="aspect-auto h-80">
        <AreaChart data={data}>...</AreaChart>
      </ChartContainer>
    </CardContent>
  </Card>

  <Card>
    <CardHeader>
      <CardTitle>Traffic Sources</CardTitle>
    </CardHeader>
    <CardContent>
      <ChartContainer config={pieConfig} className="aspect-square h-64">
        <PieChart>...</PieChart>
      </ChartContainer>
    </CardContent>
  </Card>
</div>
```

### Two Charts Side by Side

```tsx
<div className="grid gap-6 lg:grid-cols-2">
  <Card>...</Card>
  <Card>...</Card>
</div>
```

### Full-Width Table

```tsx
<Card>
  <CardHeader>
    <CardTitle>Top Pages</CardTitle>
    <CardDescription>Most visited pages</CardDescription>
  </CardHeader>
  <CardContent>
    <Table>...</Table>
  </CardContent>
</Card>
```

### Mixed Layout (Google Analytics Style)

```tsx
<div className="space-y-6">
  {/* KPI Row */}
  <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    {/* 4 KPI cards */}
  </div>

  {/* Chart + Breakdown */}
  <div className="grid gap-6 lg:grid-cols-[2fr_1fr]">
    <Card>{/* Main chart */}</Card>
    <Card>{/* Breakdown list */}</Card>
  </div>

  {/* Two Charts */}
  <div className="grid gap-6 lg:grid-cols-2">
    <Card>{/* Chart 1 */}</Card>
    <Card>{/* Chart 2 */}</Card>
  </div>

  {/* Full-Width Table */}
  <Card>{/* Data table */}</Card>
</div>
```

## KPI Card Pattern

```tsx
import { TrendingUp, TrendingDown, Minus } from "lucide-react";
import { Card, CardContent, CardHeader, CardTitle } from "@nreljed/platform/Components/ui/card";
import { cn } from "cn";

interface KpiCardProps {
  label: string;
  value: string | number;
  change?: number;
  changeLabel?: string;
  icon?: React.ComponentType<{ className?: string }>;
  sparkline?: number[];
}

function KpiCard({ label, value, change, changeLabel, icon: Icon, sparkline }: KpiCardProps) {
  const isPositive = change !== undefined && change > 0;
  const isNegative = change !== undefined && change < 0;

  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
        <CardTitle className="text-sm font-medium text-muted-foreground">{label}</CardTitle>
        {Icon && <Icon className="h-4 w-4 text-muted-foreground" />}
      </CardHeader>
      <CardContent>
        <div className="flex items-end justify-between">
          <div>
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
                {changeLabel && <span className="text-xs text-muted-foreground ml-1">{changeLabel}</span>}
              </div>
            )}
          </div>
          {sparkline && <Sparkline data={sparkline} />}
        </div>
      </CardContent>
    </Card>
  );
}
```

## Responsive Breakpoints

| Breakpoint | Width | Usage |
|---|---|---|
| `sm` | 640px | 2-column KPI grid |
| `md` | 768px | Sidebar visible |
| `lg` | 1024px | 4-column KPI, 2-col charts |
| `xl` | 1280px | 3-col charts, wider tables |

### Common Responsive Patterns

```tsx
// KPI cards: 1 → 2 → 4 columns
<div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">

// Charts: 1 → 2 columns
<div className="grid gap-6 lg:grid-cols-2">

// Main chart + sidebar: stacked → side-by-side
<div className="grid gap-6 lg:grid-cols-[2fr_1fr]">

// Table: horizontal scroll on mobile
<div className="overflow-x-auto">
  <Table>...</Table>
</div>
```

## Page Structure Pattern

```tsx
export default function AnalyticsPage({ data }: Props) {
  return (
    <AuthenticatedLayout header="Analytics">
      <Head title="Analytics" />

      <div className="space-y-6">
        {/* Page header with filters */}
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div>
            <h2 className="text-lg font-semibold">Page Title</h2>
            <p className="text-sm text-muted-foreground">Description</p>
          </div>
          <div className="flex items-center gap-2">
            <DateRangePicker value={dateRange} onChange={setDateRange} />
            <Button variant="outline" size="sm">
              <Download className="size-4" /> Export
            </Button>
          </div>
        </div>

        {/* KPI Row */}
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {/* KPI cards */}
        </div>

        {/* Charts */}
        <div className="grid gap-6 lg:grid-cols-[2fr_1fr]">
          {/* Main chart + breakdown */}
        </div>

        {/* Data Table */}
        <Card>
          <CardHeader>
            <CardTitle>Details</CardTitle>
          </CardHeader>
          <CardContent>
            <Table>...</Table>
          </CardContent>
        </Card>
      </div>
    </AuthenticatedLayout>
  );
}
```

## Card Patterns

### Metric Card (simple)
```tsx
<Card>
  <CardHeader className="pb-2">
    <CardTitle className="text-sm font-medium text-muted-foreground">Label</CardTitle>
  </CardHeader>
  <CardContent>
    <p className="text-2xl font-bold tabular-nums">1,234</p>
    <p className="text-xs text-muted-foreground">+12% vs last period</p>
  </CardContent>
</Card>
```

### Chart Card
```tsx
<Card>
  <CardHeader>
    <CardTitle className="flex items-center gap-2 text-lg">
      <BarChart3 className="size-4" />
      Chart Title
    </CardTitle>
    <CardDescription>Chart description</CardDescription>
  </CardHeader>
  <CardContent>
    <ChartContainer config={config} className="aspect-auto h-72 w-full">
      <AreaChart data={data}>...</AreaChart>
    </ChartContainer>
  </CardContent>
</Card>
```

### List Card (breakdown)
```tsx
<Card>
  <CardHeader>
    <CardTitle>Breakdown</CardTitle>
  </CardHeader>
  <CardContent className="space-y-3">
    {items.map((item) => (
      <div key={item.name} className="flex items-center justify-between">
        <span className="text-sm">{item.name}</span>
        <span className="text-sm font-medium tabular-nums">{item.value}</span>
      </div>
    ))}
  </CardContent>
</Card>
```

### Empty State Card
```tsx
<Card>
  <CardContent className="flex h-72 items-center justify-center">
    <div className="text-center">
      <p className="text-sm text-muted-foreground">No data for this period</p>
      <Button variant="outline" size="sm" className="mt-2">Adjust filters</Button>
    </div>
  </CardContent>
</Card>
```

## Loading States

```tsx
// Skeleton for KPI card
<Card>
  <CardHeader className="pb-2">
    <div className="h-4 w-24 animate-pulse rounded bg-muted" />
  </CardHeader>
  <CardContent>
    <div className="h-8 w-20 animate-pulse rounded bg-muted" />
    <div className="mt-2 h-3 w-32 animate-pulse rounded bg-muted" />
  </CardContent>
</Card>

// Skeleton for chart
<Card>
  <CardHeader>
    <div className="h-5 w-32 animate-pulse rounded bg-muted" />
  </CardHeader>
  <CardContent className="aspect-auto h-72">
    <div className="h-full w-full animate-pulse rounded-md bg-muted" />
  </CardContent>
</Card>
```

## Color & Theming

### Chart Colors
```css
/* In core.css or theme */
--chart-1: 12 76% 61%;   /* Orange */
--chart-2: 173 58% 39%;  /* Teal */
--chart-3: 197 37% 24%;  /* Dark blue */
--chart-4: 43 74% 66%;   /* Yellow */
--chart-5: 27 87% 67%;   /* Light orange */
```

### Status Colors
- Success: `text-emerald-600 dark:text-emerald-400`
- Error/Destructive: `text-destructive`
- Warning: `text-amber-600 dark:text-amber-400`
- Info: `text-blue-600 dark:text-blue-400`
- Muted: `text-muted-foreground`

### Number Formatting
```tsx
const number = new Intl.NumberFormat("en-US");

// Large numbers: 1,234,567
number.format(1234567)

// Currency: $1,234.56
new Intl.NumberFormat("en-US", { style: "currency", currency: "USD" }).format(1234.56)

// Percentage: 12.5%
new Intl.NumberFormat("en-US", { style: "percent", minimumFractionDigits: 1 }).format(0.125)

// Compact: 1.2K, 3.4M
new Intl.NumberFormat("en-US", { notation: "compact" }).format(1234)
```

## Google Analytics-Style Patterns

### Real-time metric with live indicator
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
    <Sparkline data={sparkData} />
  </CardContent>
</Card>
```

### Comparison period (vs previous)
```tsx
<div className="flex items-center gap-2">
  <span className="text-2xl font-bold tabular-nums">{currentValue}</span>
  <span className={cn(
    "text-xs font-medium",
    change > 0 ? "text-emerald-600 dark:text-emerald-400" : change < 0 ? "text-destructive" : "text-muted-foreground",
  )}>
    {change > 0 ? "↑" : change < 0 ? "↓" : "→"} {Math.abs(change)}%
  </span>
  <span className="text-xs text-muted-foreground">vs. previous period</span>
</div>
```

### Drill-down with period selector
```tsx
<div className="flex items-center gap-2">
  {["7d", "30d", "90d", "1y"].map((period) => (
    <Button
      key={period}
      size="sm"
      variant={selectedPeriod === period ? "default" : "ghost"}
      onClick={() => setSelectedPeriod(period)}
    >
      {period}
    </Button>
  ))}
</div>
```

## Key Files

| File | Purpose |
|---|---|
| `packages/platform/resources/js/Components/ui/chart.tsx` | ChartContainer, ChartTooltip, ChartLegend, ChartStyle |
| `packages/platform/resources/js/Components/ui/card.tsx` | Card, CardHeader, CardTitle, CardDescription, CardContent |
| `packages/platform/resources/js/Components/ui/table.tsx` | Table components |
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
- **Grid gaps**: Use `gap-4` for KPI rows, `gap-6` for chart rows. Consistent spacing.
- **Card spacing**: Use `space-y-6` for vertical section spacing in dashboards.
- **Tabular nums**: Always use `tabular-nums` for numeric values to prevent layout shift.
- **Truncation**: Use `truncate` for long labels in tables. Add `max-w-*` constraints.
