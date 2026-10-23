import { Head, Link, usePage } from "@inertiajs/react";
import type { PageProps } from "@inertiajs/core";
import { AlertTriangle, BarChart3, CheckCircle2, GitBranch, Megaphone, Send, Users } from "lucide-react";
import AuthenticatedLayout from "@nreljed/platform/Layouts/AuthenticatedLayout";
import { Badge } from "@nreljed/platform/Components/ui/badge";
import { Button } from "@nreljed/platform/Components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@nreljed/platform/Components/ui/card";

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
}

interface PlatformRow {
    platform: string;
    label: string;
    total: number;
    delivered: number;
    failed: number;
}

interface SeriesPoint {
    date: string;
    count: number;
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
        summary: AnalyticsSummary;
        platforms: PlatformRow[];
        series: SeriesPoint[];
        campaigns: CampaignRow[];
        workflows: WorkflowStats;
    };
}

const statusVariant = (status: string | null) => {
    switch (status) {
        case "completed":
        case "success":
            return "success" as const;
        case "active":
            return "default" as const;
        case "archived":
            return "outline" as const;
        case "failed":
            return "destructive" as const;
        default:
            return "secondary" as const;
    }
};

/**
 * Delivery, campaign and workflow reporting.
 *
 * The page reports what the engine recorded. It deliberately does not claim
 * attribution the module cannot observe: revenue still belongs to the shop.
 */
export default function MarketingAnalyticsPage() {
    const { analytics } = usePage<AnalyticsProps>().props;
    const { summary, platforms, series, campaigns, workflows } = analytics;

    const peak = Math.max(1, ...series.map((point) => point.count));

    const stats = [
        { label: "Delivered", value: summary.delivered, detail: `${summary.deliveries} attempts`, icon: Send },
        { label: "Success rate", value: `${summary.success_rate}%`, detail: `${summary.delivery_failures} failed`, icon: CheckCircle2 },
        { label: "Contacts", value: summary.contacts, detail: "audience members", icon: Users },
        { label: "Active workflows", value: summary.active_workflows, detail: `${workflows.runs} runs in 30 days`, icon: GitBranch },
    ];

    return (
        <AuthenticatedLayout header="Marketing analytics" title="Marketing analytics">
            <Head title="Marketing analytics" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 className="text-lg font-semibold">What the engine recorded</h2>
                        <p className="text-sm text-muted-foreground">
                            Deliveries, campaign grouping and automation activity. Revenue attribution stays in the shop.
                        </p>
                    </div>
                    <Button variant="outline" render={<Link href={route("dashboard.marketing.dashboard")} />}>
                        Back to dashboard
                    </Button>
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
                                <p className="text-2xl font-bold">{stat.value}</p>
                                <p className="text-xs text-muted-foreground">{stat.detail}</p>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-lg">
                                <BarChart3 className="size-4" />
                                Published deliveries
                            </CardTitle>
                            <CardDescription>Successful deliveries per day over the last 14 days.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="flex h-40 items-end gap-1.5" role="img" aria-label="Deliveries per day">
                                {series.map((point) => (
                                    <div key={point.date} className="flex h-full min-w-0 flex-1 flex-col justify-end gap-2">
                                        <div
                                            className="w-full rounded-t bg-primary/80"
                                            style={{
                                                height: `${Math.max(4, (point.count / peak) * 100)}%`,
                                                maxHeight: "calc(100% - 1.25rem)",
                                            }}
                                            title={`${point.date}: ${point.count}`}
                                        />
                                        <span className="text-center text-[10px] text-muted-foreground">
                                            {point.date}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-lg">Platforms</CardTitle>
                            <CardDescription>Where each delivery went and how it fared.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {platforms.length === 0 ? (
                                <p className="text-sm text-muted-foreground">No deliveries yet.</p>
                            ) : (
                                <ul className="space-y-3">
                                    {platforms.map((platform) => (
                                        <li key={platform.platform} className="flex items-center justify-between gap-3">
                                            <div className="flex items-center gap-2">
                                                <Megaphone className="size-4 text-muted-foreground" />
                                                <span className="text-sm font-medium">{platform.label}</span>
                                            </div>
                                            <div className="flex items-center gap-3 text-sm">
                                                <span className="text-muted-foreground">{platform.total} total</span>
                                                <span className="text-emerald-600 dark:text-emerald-400">
                                                    {platform.delivered} delivered
                                                </span>
                                                {platform.failed > 0 && (
                                                    <span className="text-destructive">{platform.failed} failed</span>
                                                )}
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
                        <CardTitle className="text-lg">Campaigns</CardTitle>
                        <CardDescription>Posts grouped for planning, with their delivery outcome.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {campaigns.length === 0 ? (
                            <p className="text-sm text-muted-foreground">No campaigns yet.</p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b text-left text-muted-foreground">
                                            <th className="py-2 pr-4 font-medium">Campaign</th>
                                            <th className="py-2 pr-4 font-medium">Status</th>
                                            <th className="py-2 pr-4 font-medium">Posts</th>
                                            <th className="py-2 pr-4 font-medium">Delivered</th>
                                            <th className="py-2 font-medium">Rate</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {campaigns.map((campaign) => (
                                            <tr key={campaign.id} className="border-b last:border-0">
                                                <td className="py-2 pr-4">
                                                    <p className="font-medium">{campaign.name}</p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {campaign.objective_label ?? campaign.objective ?? "No objective"}
                                                    </p>
                                                </td>
                                                <td className="py-2 pr-4">
                                                    <Badge variant={statusVariant(campaign.status)}>
                                                        {campaign.status_label ?? campaign.status ?? "draft"}
                                                    </Badge>
                                                </td>
                                                <td className="py-2 pr-4">
                                                    {campaign.posts}
                                                    {campaign.scheduled > 0 && (
                                                        <span className="text-xs text-muted-foreground">
                                                            {" "}
                                                            ({campaign.scheduled} scheduled)
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="py-2 pr-4">
                                                    {campaign.delivered} / {campaign.deliveries}
                                                    {campaign.failed > 0 && (
                                                        <span className="text-xs text-destructive"> · {campaign.failed} failed</span>
                                                    )}
                                                </td>
                                                <td className="py-2">{campaign.delivery_rate}%</td>
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
                            <GitBranch className="size-4" />
                            Workflow activity
                        </CardTitle>
                        <CardDescription>
                            {workflows.runs} runs in the last 30 days, {workflows.failures} failed.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {workflows.recent.length === 0 ? (
                            <p className="text-sm text-muted-foreground">No workflow runs yet.</p>
                        ) : (
                            <ul className="space-y-3">
                                {workflows.recent.map((run) => (
                                    <li key={run.id} className="flex items-start justify-between gap-3 rounded-lg border px-3 py-2">
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
        </AuthenticatedLayout>
    );
}
