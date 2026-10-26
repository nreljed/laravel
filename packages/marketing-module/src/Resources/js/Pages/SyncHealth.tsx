import { Head, Link, router, usePage } from "@inertiajs/react";
import type { PageProps } from "@inertiajs/core";
import { Activity, AlertTriangle, CheckCircle2, Clock, Gauge, RefreshCw, ShieldAlert } from "lucide-react";
import AuthenticatedLayout from "@nreljed/platform/Layouts/AuthenticatedLayout";
import { Badge } from "@nreljed/platform/Components/ui/badge";
import { Button } from "@nreljed/platform/Components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@nreljed/platform/Components/ui/card";

interface SyncHealthConfig {
    enabled: boolean;
    max_attempts: number;
    retry_after_minutes: number;
    sync_interval_hours: number;
}

interface SyncHealthTotals {
    total: number;
    pending: number;
    ok: number;
    unavailable: number;
    error: number;
    dead_letter: number;
}

interface PlatformHealth {
    platform: string;
    label: string;
    total: number;
    pending: number;
    ok: number;
    unavailable: number;
    error: number;
    dead_letter: number;
    requests_per_minute: number;
    max_per_run: number;
    daily_request_budget: number;
    budget_used: number;
    budget_remaining: number | null;
    budget_exhausted: boolean;
    last_synced_at: string | null;
}

interface DeadLetter {
    id: number;
    platform: string;
    platform_label: string;
    post_id: number;
    body: string;
    external_url: string | null;
    attempts: number;
    error: string | null;
    attempted_at: string | null;
    published_at: string | null;
}

interface SyncHealthProps extends PageProps {
    health: {
        generated_at: string;
        config: SyncHealthConfig;
        totals: SyncHealthTotals;
        platforms: PlatformHealth[];
        dead_letters: DeadLetter[];
    };
}

const number = new Intl.NumberFormat("en-US");

function formatNumber(value: number | null | undefined) {
    return value === null || value === undefined ? "—" : number.format(value);
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

export default function SyncHealthPage() {
    const { health } = usePage<SyncHealthProps>().props;
    const { config, totals, platforms, dead_letters: deadLetters } = health;

    const replay = (url: string, data: Record<string, number> = {}) => {
        router.post(url, data, { preserveScroll: true });
    };

    const stats = [
        {
            label: "Deliveries tracked",
            value: formatNumber(totals.total),
            detail: `${formatNumber(totals.ok)} synced`,
            icon: Activity,
        },
        {
            label: "Awaiting sync",
            value: formatNumber(totals.pending),
            detail: `${formatNumber(totals.unavailable)} unavailable`,
            icon: Clock,
        },
        {
            label: "Retrying",
            value: formatNumber(totals.error),
            detail: `Up to ${config.max_attempts} attempts`,
            icon: RefreshCw,
        },
        {
            label: "Dead letters",
            value: formatNumber(totals.dead_letter),
            detail: "Replay once fixed",
            icon: ShieldAlert,
        },
    ];

    return (
        <AuthenticatedLayout header="Metric sync health" title="Metric sync health">
            <Head title="Metric sync health" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 className="text-lg font-semibold">Metric collection</h2>
                        <p className="max-w-2xl text-sm text-muted-foreground">
                            What each provider returned, where retries stand, and how much of today&apos;s request budget is
                            spent. Dead letters only move when they are replayed.
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge variant={config.enabled ? "success" : "outline"}>
                            {config.enabled ? "Collection on" : "Collection off"}
                        </Badge>
                        {deadLetters.length > 0 && (
                            <Button
                                size="sm"
                                onClick={() => replay(route("dashboard.marketing.sync-health.replay"), { limit: 500 })}
                            >
                                <RefreshCw className="size-4" />
                                Replay all dead letters
                            </Button>
                        )}
                        <Button variant="outline" render={<Link href={route("dashboard.marketing.dashboard")} />}>
                            Back to dashboard
                        </Button>
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

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-lg">
                            <Gauge className="size-4" />
                            Platforms
                        </CardTitle>
                        <CardDescription>
                            Per-platform delivery state and the request limits applied to the next run. A budget of “—” is
                            uncapped.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {platforms.length === 0 ? (
                            <p className="text-sm text-muted-foreground">No deliveries are tracked yet.</p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b text-left text-muted-foreground">
                                            <th className="py-2 pr-4 font-medium">Platform</th>
                                            <th className="py-2 pr-4 font-medium">Deliveries</th>
                                            <th className="py-2 pr-4 font-medium">Synced</th>
                                            <th className="py-2 pr-4 font-medium">Pending</th>
                                            <th className="py-2 pr-4 font-medium">Retrying</th>
                                            <th className="py-2 pr-4 font-medium">Dead letters</th>
                                            <th className="py-2 pr-4 font-medium">Today&apos;s budget</th>
                                            <th className="py-2 pr-4 font-medium">Per minute</th>
                                            <th className="py-2 font-medium">Last sync</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {platforms.map((platform) => (
                                            <tr key={platform.platform} className="border-b align-top last:border-0">
                                                <td className="py-3 pr-4 font-medium">{platform.label}</td>
                                                <td className="py-3 pr-4 tabular-nums">{platform.total}</td>
                                                <td className="py-3 pr-4 tabular-nums">{platform.ok}</td>
                                                <td className="py-3 pr-4 tabular-nums">{platform.pending}</td>
                                                <td className="py-3 pr-4 tabular-nums">
                                                    {platform.error > 0 ? (
                                                        <Badge variant="secondary">{platform.error}</Badge>
                                                    ) : (
                                                        "0"
                                                    )}
                                                </td>
                                                <td className="py-3 pr-4 tabular-nums">
                                                    {platform.dead_letter > 0 ? (
                                                        <Badge variant="destructive">{platform.dead_letter}</Badge>
                                                    ) : (
                                                        "0"
                                                    )}
                                                </td>
                                                <td className="py-3 pr-4 text-xs">
                                                    {platform.daily_request_budget === 0 ? (
                                                        <span className="text-muted-foreground">—</span>
                                                    ) : (
                                                        <span
                                                            className={
                                                                platform.budget_exhausted ? "font-medium text-destructive" : ""
                                                            }
                                                        >
                                                            {formatNumber(platform.budget_used)} /{" "}
                                                            {formatNumber(platform.daily_request_budget)}
                                                            {platform.budget_remaining !== null && (
                                                                <span className="ml-1 text-muted-foreground">
                                                                    ({formatNumber(platform.budget_remaining)} left)
                                                                </span>
                                                            )}
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="py-3 pr-4 tabular-nums">{platform.requests_per_minute || "—"}</td>
                                                <td className="py-3 text-muted-foreground">{formatDateTime(platform.last_synced_at)}</td>
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
                            <AlertTriangle className="size-4" />
                            Dead letters
                        </CardTitle>
                        <CardDescription>
                            Deliveries the sync loop stopped retrying. Replaying one clears its attempt count and puts it
                            back in front of the next run.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {deadLetters.length === 0 ? (
                            <div className="flex items-center gap-2 rounded-lg border border-dashed px-4 py-6 text-sm text-muted-foreground">
                                <CheckCircle2 className="size-4 text-emerald-600 dark:text-emerald-400" />
                                No dead letters. Collection is retrying everything it can.
                            </div>
                        ) : (
                            <ul className="space-y-3">
                                {deadLetters.map((item) => (
                                    <li
                                        key={item.id}
                                        className="flex flex-wrap items-start justify-between gap-3 rounded-lg border px-3 py-2"
                                    >
                                        <div className="min-w-0 space-y-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <Badge variant="outline">{item.platform_label}</Badge>
                                                <Link
                                                    href={route("dashboard.marketing.posts.edit", item.post_id)}
                                                    className="truncate text-sm font-medium hover:underline"
                                                >
                                                    {item.body}
                                                </Link>
                                            </div>
                                            {item.error && (
                                                <p className="text-xs text-muted-foreground">{item.error}</p>
                                            )}
                                            <p className="text-xs text-muted-foreground">
                                                {item.attempts} attempt{item.attempts === 1 ? "" : "s"} · last tried{" "}
                                                {formatDateTime(item.attempted_at)}
                                            </p>
                                        </div>
                                        <div className="flex shrink-0 items-center gap-2">
                                            {item.external_url && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    render={
                                                        <a href={item.external_url} target="_blank" rel="noreferrer" />
                                                    }
                                                >
                                                    Open
                                                </Button>
                                            )}
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    replay(
                                                        route(
                                                            "dashboard.marketing.sync-health.dead-letters.replay",
                                                            item.id,
                                                        ),
                                                    )
                                                }
                                            >
                                                <RefreshCw className="size-4" />
                                                Replay
                                            </Button>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>

                <p className="text-xs text-muted-foreground">
                    Retry window: failures are retried after {config.retry_after_minutes} minutes, and a synced delivery is
                    refreshed every {config.sync_interval_hours} hours.
                </p>
            </div>
        </AuthenticatedLayout>
    );
}
