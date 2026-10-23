import { Head, Link, router, usePage } from "@inertiajs/react";
import type { PageProps } from "@inertiajs/core";
import {
    AlertTriangle,
    BarChart3,
    CheckCircle2,
    Download,
    ExternalLink,
    GitBranch,
    Megaphone,
    MousePointerClick,
    Send,
    Users,
} from "lucide-react";
import { Area, AreaChart, CartesianGrid, XAxis } from "recharts";
import AuthenticatedLayout from "@nreljed/platform/Layouts/AuthenticatedLayout";
import { Badge } from "@nreljed/platform/Components/ui/badge";
import { Button } from "@nreljed/platform/Components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@nreljed/platform/Components/ui/card";
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
    type ChartConfig,
} from "@nreljed/platform/Components/ui/chart";

interface AnalyticsPeriod {
    days: number;
    start: string;
    end: string;
    label: string;
}

interface AnalyticsSummary {
    posts: number;
    scheduled: number;
    published: number;
    partial: number;
    failed: number;
    deliveries: number;
    delivered: number;
    delivery_failures: number;
    success_rate: number;
    contacts: number;
    campaigns: number;
    active_workflows: number;
    impressions: number | null;
    reach: number | null;
    engagements: number | null;
    clicks: number | null;
    engagement_rate: number | null;
    click_through_rate: number | null;
}

interface AnalyticsCoverage {
    metrics_enabled: boolean;
    delivered: number;
    with_metrics: number;
    unavailable: number;
    errors: number;
    pending: number;
    last_synced_at: string | null;
    coverage_rate: number;
}

interface PlatformRow {
    platform: string;
    label: string;
    total: number;
    delivered: number;
    failed: number;
    with_metrics: number;
    impressions: number | null;
    engagements: number | null;
    clicks: number | null;
}

interface SeriesPoint {
    date: string;
    label: string;
    delivered: number;
    impressions: number;
    engagements: number;
    clicks: number;
}

interface PostRow {
    id: number;
    body: string;
    campaign_id: number | null;
    campaign: string | null;
    status: string | null;
    status_label: string | null;
    platforms: string[];
    deliveries: number;
    delivered: number;
    failed: number;
    impressions: number | null;
    reach: number | null;
    engagements: number | null;
    clicks: number | null;
    engagement_rate: number | null;
    click_through_rate: number | null;
    published_at: string | null;
}

interface CampaignRow {
    id: number;
    name: string;
    status: string | null;
    status_label: string | null;
    objective: string | null;
    objective_label: string | null;
    posts: number;
    scheduled: number;
    deliveries: number;
    delivered: number;
    failed: number;
    delivery_rate: number;
    impressions: number | null;
    reach: number | null;
    engagements: number | null;
    clicks: number | null;
    engagement_rate: number | null;
    click_through_rate: number | null;
}

interface LinkRow {
    id: number;
    social_post_id: number | null;
    destination_url: string;
    utm_campaign: string | null;
    short_code: string;
    short_url: string;
    clicks: number;
    conversions: number;
    last_clicked_at: string | null;
}

interface WorkflowRunRow {
    id: number;
    workflow: string | null;
    event: string;
    status: string | null;
    status_label: string | null;
    message: string | null;
    ran_at: string | null;
}

interface WorkflowStats {
    active: number;
    total: number;
    runs: number;
    failures: number;
    recent: WorkflowRunRow[];
}

interface AnalyticsProps extends PageProps {
    analytics: {
        period: AnalyticsPeriod;
        summary: AnalyticsSummary;
        coverage: AnalyticsCoverage;
        series: SeriesPoint[];
        platforms: PlatformRow[];
        top_posts: PostRow[];
        campaigns: CampaignRow[];
        workflows: WorkflowStats;
        links: LinkRow[];
    };
}

const periods = [7, 30, 90];

const chartConfig = {
    impressions: { label: "Impressions", color: "hsl(var(--chart-1))" },
    engagements: { label: "Engagements", color: "hsl(var(--chart-2))" },
    clicks: { label: "Clicks", color: "hsl(var(--chart-3))" },
} satisfies ChartConfig;

const number = new Intl.NumberFormat("en-US");

function formatNumber(value: number | null | undefined) {
    return value === null || value === undefined ? "—" : number.format(value);
}

function formatRate(value: number | null | undefined) {
    return value === null || value === undefined ? "—" : `${value}%`;
}

function formatDateTime(value: string | null | undefined) {
    if (!value) {
        return "Never";
    }

    return new Date(value).toLocaleString("en-US", {
        month: "short",
        day: "numeric",
        hour: "numeric",
        minute: "2-digit",
    });
}

const statusVariant = (status: string | null) => {
    switch (status) {
        case "completed":
        case "success":
        case "published":
            return "success" as const;
        case "active":
            return "default" as const;
        case "archived":
        case "cancelled":
            return "outline" as const;
        case "failed":
        case "partial":
            return "destructive" as const;
        default:
            return "secondary" as const;
    }
};

export default function MarketingAnalyticsPage() {
    const { analytics } = usePage<AnalyticsProps>().props;
    const { period, summary, coverage, series, platforms, top_posts, campaigns, workflows, links } = analytics;

    const changePeriod = (days: number) => {
        router.get(
            route("dashboard.marketing.analytics"),
            { days },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const stats = [
        {
            label: "Delivered",
            value: formatNumber(summary.delivered),
            detail: `${formatNumber(summary.deliveries)} attempts · ${summary.success_rate}% success`,
            icon: Send,
        },
        {
            label: "Impressions",
            value: formatNumber(summary.impressions),
            detail: `${formatNumber(summary.reach)} reach`,
            icon: BarChart3,
        },
        {
            label: "Engagements",
            value: formatNumber(summary.engagements),
            detail: `${formatRate(summary.engagement_rate)} rate`,
            icon: Users,
        },
        {
            label: "Clicks",
            value: formatNumber(summary.clicks),
            detail: `${formatRate(summary.click_through_rate)} CTR`,
            icon: MousePointerClick,
        },
    ];

    return (
        <AuthenticatedLayout header="Marketing analytics" title="Marketing analytics">
            <Head title="Marketing analytics" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 className="text-lg font-semibold">What the engine recorded</h2>
                        <p className="max-w-2xl text-sm text-muted-foreground">
                            Deliveries, audience response and campaign grouping for {period.label}. Revenue attribution stays in the shop.
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Button variant="outline" render={<Link href={route("dashboard.marketing.dashboard")} />}>
                            Back to dashboard
                        </Button>
                        {(["posts", "campaigns", "links"] as const).map((dataset) => (
                            <Button
                                key={dataset}
                                variant="outline"
                                size="sm"
                                render={
                                    <a
                                        href={route("dashboard.marketing.analytics.export", {
                                            dataset,
                                            days: period.days,
                                        })}
                                    />
                                }
                            >
                                <Download className="size-4" />
                                {dataset}
                            </Button>
                        ))}
                    </div>
                </div>

                <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border bg-muted/30 px-3 py-2">
                    <div className="flex items-center gap-2">
                        <span className="text-sm text-muted-foreground">Reporting period</span>
                        <div className="flex gap-1">
                            {periods.map((days) => (
                                <Button
                                    key={days}
                                    size="sm"
                                    variant={period.days === days ? "default" : "ghost"}
                                    onClick={() => changePeriod(days)}
                                >
                                    {days} days
                                </Button>
                            ))}
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                        <Badge variant={coverage.metrics_enabled ? "success" : "outline"}>
                            {coverage.metrics_enabled ? "Metrics on" : "Metrics off"}
                        </Badge>
                        <span>
                            {coverage.with_metrics} / {coverage.delivered} deliveries measured
                        </span>
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {stats.map((stat) => (
                        <Card key={stat.label}>
                            <CardHeader className="pb-2">
                                <CardTitle className="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                                    <stat.icon className="size-4" />
                                    {stat.label}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-2xl font-bold tabular-nums">{stat.value}</p>
                                <p className="text-xs text-muted-foreground">{stat.detail}</p>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <div className="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-lg">
                                <BarChart3 className="size-4" />
                                Audience response
                            </CardTitle>
                            <CardDescription>
                                Latest cumulative snapshot per delivery, grouped by the day it was delivered.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {coverage.with_metrics === 0 ? (
                                <div className="flex h-72 items-center justify-center rounded-lg border border-dashed text-sm text-muted-foreground">
                                    No engagement snapshots for this period yet.
                                </div>
                            ) : (
                                <ChartContainer config={chartConfig} className="aspect-auto h-72 w-full">
                                    <AreaChart data={series} margin={{ left: 0, right: 8, top: 8 }}>
                                        <defs>
                                            <linearGradient id="fillImpressions" x1="0" y1="0" x2="0" y2="1">
                                                <stop offset="5%" stopColor="var(--color-impressions)" stopOpacity={0.35} />
                                                <stop offset="95%" stopColor="var(--color-impressions)" stopOpacity={0.02} />
                                            </linearGradient>
                                            <linearGradient id="fillEngagements" x1="0" y1="0" x2="0" y2="1">
                                                <stop offset="5%" stopColor="var(--color-engagements)" stopOpacity={0.35} />
                                                <stop offset="95%" stopColor="var(--color-engagements)" stopOpacity={0.02} />
                                            </linearGradient>
                                            <linearGradient id="fillClicks" x1="0" y1="0" x2="0" y2="1">
                                                <stop offset="5%" stopColor="var(--color-clicks)" stopOpacity={0.35} />
                                                <stop offset="95%" stopColor="var(--color-clicks)" stopOpacity={0.02} />
                                            </linearGradient>
                                        </defs>
                                        <CartesianGrid vertical={false} />
                                        <XAxis
                                            dataKey="label"
                                            tickLine={false}
                                            axisLine={false}
                                            tickMargin={8}
                                            minTickGap={32}
                                        />
                                        <ChartTooltip cursor={false} content={<ChartTooltipContent indicator="line" />} />
                                        <Area
                                            dataKey="impressions"
                                            type="natural"
                                            fill="url(#fillImpressions)"
                                            stroke="var(--color-impressions)"
                                            strokeWidth={2}
                                        />
                                        <Area
                                            dataKey="engagements"
                                            type="natural"
                                            fill="url(#fillEngagements)"
                                            stroke="var(--color-engagements)"
                                            strokeWidth={2}
                                        />
                                        <Area
                                            dataKey="clicks"
                                            type="natural"
                                            fill="url(#fillClicks)"
                                            stroke="var(--color-clicks)"
                                            strokeWidth={2}
                                        />
                                    </AreaChart>
                                </ChartContainer>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-lg">Metric coverage</CardTitle>
                            <CardDescription>How much of the period has provider data.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <div className="flex items-end justify-between">
                                    <p className="text-3xl font-bold tabular-nums">{coverage.coverage_rate}%</p>
                                    <p className="text-xs text-muted-foreground">
                                        {coverage.with_metrics} of {coverage.delivered}
                                    </p>
                                </div>
                                <div className="mt-2 h-2 overflow-hidden rounded-full bg-muted">
                                    <div
                                        className="h-full rounded-full bg-primary"
                                        style={{ width: `${Math.min(100, Math.max(0, coverage.coverage_rate))}%` }}
                                    />
                                </div>
                            </div>

                            <dl className="grid grid-cols-2 gap-3 text-sm">
                                <div className="rounded-lg border px-3 py-2">
                                    <dt className="text-xs text-muted-foreground">Pending</dt>
                                    <dd className="font-medium tabular-nums">{coverage.pending}</dd>
                                </div>
                                <div className="rounded-lg border px-3 py-2">
                                    <dt className="text-xs text-muted-foreground">Unavailable</dt>
                                    <dd className="font-medium tabular-nums">{coverage.unavailable}</dd>
                                </div>
                                <div className="rounded-lg border px-3 py-2">
                                    <dt className="text-xs text-muted-foreground">Errors</dt>
                                    <dd className="font-medium tabular-nums">{coverage.errors}</dd>
                                </div>
                                <div className="rounded-lg border px-3 py-2">
                                    <dt className="text-xs text-muted-foreground">Success rate</dt>
                                    <dd className="font-medium tabular-nums">{summary.success_rate}%</dd>
                                </div>
                            </dl>

                            <p className="text-xs text-muted-foreground">
                                Last sync: {formatDateTime(coverage.last_synced_at)}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-lg">
                                <Megaphone className="size-4" />
                                Platforms
                            </CardTitle>
                            <CardDescription>Where each delivery went and what came back.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {platforms.length === 0 ? (
                                <p className="text-sm text-muted-foreground">No deliveries in this period.</p>
                            ) : (
                                <ul className="space-y-3">
                                    {platforms.map((platform) => (
                                        <li
                                            key={platform.platform}
                                            className="flex flex-wrap items-center justify-between gap-3 rounded-lg border px-3 py-2"
                                        >
                                            <div className="flex items-center gap-2">
                                                <Megaphone className="size-4 text-muted-foreground" />
                                                <span className="text-sm font-medium">{platform.label}</span>
                                                <span className="text-xs text-muted-foreground">
                                                    {platform.delivered} / {platform.total} delivered
                                                </span>
                                                {platform.failed > 0 && (
                                                    <Badge variant="destructive">{platform.failed} failed</Badge>
                                                )}
                                            </div>
                                            <div className="flex items-center gap-3 text-xs text-muted-foreground">
                                                <span>{formatNumber(platform.impressions)} impressions</span>
                                                <span>{formatNumber(platform.engagements)} engagements</span>
                                                <span>{formatNumber(platform.clicks)} clicks</span>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-lg">
                                <GitBranch className="size-4" />
                                Workflow activity
                            </CardTitle>
                            <CardDescription>
                                {workflows.runs} runs in this period, {workflows.failures} failed · {workflows.active} active workflows.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {workflows.recent.length === 0 ? (
                                <p className="text-sm text-muted-foreground">No workflow runs in this period.</p>
                            ) : (
                                <ul className="space-y-3">
                                    {workflows.recent.map((run) => (
                                        <li
                                            key={run.id}
                                            className="flex items-start justify-between gap-3 rounded-lg border px-3 py-2"
                                        >
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-medium">
                                                    {run.workflow ?? "Deleted workflow"}
                                                    <span className="ml-2 text-xs font-normal text-muted-foreground">
                                                        {run.event}
                                                    </span>
                                                </p>
                                                {run.message && (
                                                    <p className="truncate text-xs text-muted-foreground">{run.message}</p>
                                                )}
                                            </div>
                                            <div className="flex shrink-0 items-center gap-2">
                                                {run.status === "failed" ? (
                                                    <AlertTriangle className="size-4 text-destructive" />
                                                ) : (
                                                    <CheckCircle2 className="size-4 text-emerald-600 dark:text-emerald-400" />
                                                )}
                                                <Badge variant={statusVariant(run.status)}>
                                                    {run.status_label ?? run.status ?? "success"}
                                                </Badge>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-lg">Top posts</CardTitle>
                        <CardDescription>Posts with the strongest measured response in this period.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {top_posts.length === 0 ? (
                            <p className="text-sm text-muted-foreground">No delivered posts with metrics in this period.</p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b text-left text-muted-foreground">
                                            <th className="py-2 pr-4 font-medium">Post</th>
                                            <th className="py-2 pr-4 font-medium">Platforms</th>
                                            <th className="py-2 pr-4 font-medium">Delivered</th>
                                            <th className="py-2 pr-4 font-medium">Impressions</th>
                                            <th className="py-2 pr-4 font-medium">Engagements</th>
                                            <th className="py-2 pr-4 font-medium">Clicks</th>
                                            <th className="py-2 pr-4 font-medium">Eng. rate</th>
                                            <th className="py-2 font-medium">CTR</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {top_posts.map((post) => (
                                            <tr key={post.id} className="border-b align-top last:border-0">
                                                <td className="max-w-xs py-3 pr-4">
                                                    <Link
                                                        href={route("dashboard.marketing.posts.edit", post.id)}
                                                        className="line-clamp-2 font-medium hover:underline"
                                                    >
                                                        {post.body}
                                                    </Link>
                                                    <p className="text-xs text-muted-foreground">
                                                        {post.campaign ?? "No campaign"}
                                                    </p>
                                                </td>
                                                <td className="py-3 pr-4">
                                                    <div className="flex flex-wrap gap-1">
                                                        {post.platforms.map((platform) => (
                                                            <Badge key={platform} variant="outline">
                                                                {platform}
                                                            </Badge>
                                                        ))}
                                                    </div>
                                                </td>
                                                <td className="py-3 pr-4 tabular-nums">
                                                    {post.delivered} / {post.deliveries}
                                                </td>
                                                <td className="py-3 pr-4 tabular-nums">{formatNumber(post.impressions)}</td>
                                                <td className="py-3 pr-4 tabular-nums">{formatNumber(post.engagements)}</td>
                                                <td className="py-3 pr-4 tabular-nums">{formatNumber(post.clicks)}</td>
                                                <td className="py-3 pr-4 tabular-nums">{formatRate(post.engagement_rate)}</td>
                                                <td className="py-3 tabular-nums">{formatRate(post.click_through_rate)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-lg">Campaigns</CardTitle>
                        <CardDescription>Posts grouped for planning, with their delivery and audience outcome.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {campaigns.length === 0 ? (
                            <p className="text-sm text-muted-foreground">No campaign activity in this period.</p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b text-left text-muted-foreground">
                                            <th className="py-2 pr-4 font-medium">Campaign</th>
                                            <th className="py-2 pr-4 font-medium">Status</th>
                                            <th className="py-2 pr-4 font-medium">Posts</th>
                                            <th className="py-2 pr-4 font-medium">Delivered</th>
                                            <th className="py-2 pr-4 font-medium">Impressions</th>
                                            <th className="py-2 pr-4 font-medium">Engagements</th>
                                            <th className="py-2 pr-4 font-medium">Clicks</th>
                                            <th className="py-2 font-medium">Delivery rate</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {campaigns.map((campaign) => (
                                            <tr key={campaign.id} className="border-b align-top last:border-0">
                                                <td className="py-3 pr-4">
                                                    <p className="font-medium">{campaign.name}</p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {campaign.objective_label ?? campaign.objective ?? "No objective"}
                                                    </p>
                                                </td>
                                                <td className="py-3 pr-4">
                                                    <Badge variant={statusVariant(campaign.status)}>
                                                        {campaign.status_label ?? campaign.status ?? "draft"}
                                                    </Badge>
                                                </td>
                                                <td className="py-3 pr-4 tabular-nums">
                                                    {campaign.posts}
                                                    {campaign.scheduled > 0 && (
                                                        <span className="text-xs text-muted-foreground">
                                                            {" "}
                                                            ({campaign.scheduled} scheduled)
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="py-3 pr-4 tabular-nums">
                                                    {campaign.delivered} / {campaign.deliveries}
                                                    {campaign.failed > 0 && (
                                                        <span className="text-xs text-destructive"> · {campaign.failed} failed</span>
                                                    )}
                                                </td>
                                                <td className="py-3 pr-4 tabular-nums">{formatNumber(campaign.impressions)}</td>
                                                <td className="py-3 pr-4 tabular-nums">{formatNumber(campaign.engagements)}</td>
                                                <td className="py-3 pr-4 tabular-nums">{formatNumber(campaign.clicks)}</td>
                                                <td className="py-3 tabular-nums">{campaign.delivery_rate}%</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-lg">
                            <MousePointerClick className="size-4" />
                            Tracked links
                        </CardTitle>
                        <CardDescription>Clicks recorded on links created by the module in this period.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {links.length === 0 ? (
                            <p className="text-sm text-muted-foreground">No tracked link clicks in this period.</p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b text-left text-muted-foreground">
                                            <th className="py-2 pr-4 font-medium">Destination</th>
                                            <th className="py-2 pr-4 font-medium">Campaign</th>
                                            <th className="py-2 pr-4 font-medium">Clicks</th>
                                            <th className="py-2 pr-4 font-medium">Conversions</th>
                                            <th className="py-2 font-medium">Last click</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {links.map((link) => (
                                            <tr key={link.id} className="border-b last:border-0">
                                                <td className="max-w-md py-3 pr-4">
                                                    <a
                                                        href={link.short_url}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        className="inline-flex items-center gap-1 font-medium hover:underline"
                                                    >
                                                        <span className="line-clamp-1">{link.destination_url}</span>
                                                        <ExternalLink className="size-3 shrink-0" />
                                                    </a>
                                                    <p className="text-xs text-muted-foreground">{link.short_url}</p>
                                                </td>
                                                <td className="py-3 pr-4">{link.utm_campaign ?? "—"}</td>
                                                <td className="py-3 pr-4 tabular-nums">{link.clicks}</td>
                                                <td className="py-3 pr-4 tabular-nums">{link.conversions}</td>
                                                <td className="py-3 text-muted-foreground">{formatDateTime(link.last_clicked_at)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
