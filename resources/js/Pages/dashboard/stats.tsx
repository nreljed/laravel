import { Deferred, Head } from "@inertiajs/react";
import { Area, AreaChart, Bar, BarChart, CartesianGrid, XAxis } from "recharts";

import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/Components/ui/card";
import {
  ChartContainer,
  ChartTooltip,
  ChartTooltipContent,
  type ChartConfig,
} from "@/Components/ui/chart";
import { featureIcons } from "@/lib/icons";

type StatItem = {
  label: string;
  icon: string;
  value: number;
};

type StatsMap = Record<string, StatItem>;

type SeriesPoint = {
  date: string;
  count: number;
};

type AnalyticsData = Record<string, { label: string; data: SeriesPoint[] }>;

type StatsProps = {
  stats: StatsMap;
  analytics?: AnalyticsData;
};

const userChartConfig = {
  count: { label: "New Users", color: "hsl(var(--chart-1))" },
} satisfies ChartConfig;

const postChartConfig = {
  count: { label: "New Posts", color: "hsl(var(--chart-2))" },
} satisfies ChartConfig;

function iconFor(name: string) {
  const Icon = featureIcons[name] ?? featureIcons.Activity;

  if (!Icon) {
    return null;
  }

  return <Icon className="h-4 w-4 text-muted-foreground" />;
}

function formatAxisDate(value: string) {
  return new Date(`${value}T00:00:00`).toLocaleDateString("en-US", {
    month: "short",
    day: "numeric",
  });
}

function ChartSkeleton({ title }: { title: string }) {
  return (
    <Card>
      <CardHeader>
        <CardTitle>{title}</CardTitle>
        <CardDescription>Loading chart data...</CardDescription>
      </CardHeader>
      <CardContent className="aspect-auto h-72">
        <div className="h-full w-full animate-pulse rounded-md bg-muted" />
      </CardContent>
    </Card>
  );
}

function UserGrowthChart({ data }: { data: SeriesPoint[] }) {
  return (
    <Card>
      <CardHeader>
        <CardTitle>User Growth</CardTitle>
        <CardDescription>New user registrations over the last 30 days</CardDescription>
      </CardHeader>
      <CardContent>
        <ChartContainer config={userChartConfig} className="aspect-auto h-72">
          <AreaChart data={data} margin={{ left: 12, right: 12 }}>
            <defs>
              <linearGradient id="fillUsers" x1="0" y1="0" x2="0" y2="1">
                <stop offset="5%" stopColor="var(--color-count)" stopOpacity={0.8} />
                <stop offset="95%" stopColor="var(--color-count)" stopOpacity={0.1} />
              </linearGradient>
            </defs>
            <CartesianGrid vertical={false} />
            <XAxis
              dataKey="date"
              tickLine={false}
              axisLine={false}
              tickMargin={8}
              minTickGap={32}
              tickFormatter={formatAxisDate}
            />
            <ChartTooltip cursor={false} content={<ChartTooltipContent />} />
            <Area
              dataKey="count"
              type="natural"
              fill="url(#fillUsers)"
              stroke="var(--color-count)"
              strokeWidth={2}
            />
          </AreaChart>
        </ChartContainer>
      </CardContent>
    </Card>
  );
}

function PostActivityChart({ data }: { data: SeriesPoint[] }) {
  return (
    <Card>
      <CardHeader>
        <CardTitle>Post Activity</CardTitle>
        <CardDescription>Posts created over the last 30 days</CardDescription>
      </CardHeader>
      <CardContent>
        <ChartContainer config={postChartConfig} className="aspect-auto h-72">
          <BarChart data={data} margin={{ left: 12, right: 12 }}>
            <CartesianGrid vertical={false} />
            <XAxis
              dataKey="date"
              tickLine={false}
              axisLine={false}
              tickMargin={8}
              minTickGap={32}
              tickFormatter={formatAxisDate}
            />
            <ChartTooltip cursor={false} content={<ChartTooltipContent />} />
            <Bar dataKey="count" fill="var(--color-count)" radius={[4, 4, 0, 0]} />
          </BarChart>
        </ChartContainer>
      </CardContent>
    </Card>
  );
}

export default function Stats({ stats, analytics }: StatsProps) {
  const statEntries = Object.entries(stats);

  return (
    <AuthenticatedLayout
      header={<h2 className="text-xl font-semibold leading-tight text-foreground">Statistics</h2>}
    >
      <Head title="Statistics" />

      <div className="space-y-6">
        {statEntries.length > 0 ? (
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {statEntries.map(([key, item]) => (
              <Card key={key}>
                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                  <CardTitle className="text-sm font-medium">{item.label}</CardTitle>
                  {iconFor(item.icon)}
                </CardHeader>
                <CardContent>
                  <div className="text-2xl font-bold">{item.value.toLocaleString()}</div>
                </CardContent>
              </Card>
            ))}
          </div>
        ) : null}

        <div className="grid gap-6 lg:grid-cols-2">
          <Deferred data="analytics" fallback={<ChartSkeleton title="User Growth" />}>
            {() => <UserGrowthChart data={analytics?.users?.data ?? []} />}
          </Deferred>
          <Deferred data="analytics" fallback={<ChartSkeleton title="Post Activity" />}>
            {() =>
              analytics?.posts ? (
                <PostActivityChart data={analytics.posts.data} />
              ) : (
                <Card>
                  <CardHeader>
                    <CardTitle>Post Activity</CardTitle>
                  </CardHeader>
                  <CardContent className="flex aspect-auto h-72 items-center justify-center text-muted-foreground">
                    No post activity yet.
                  </CardContent>
                </Card>
              )
            }
          </Deferred>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
