import { Badge } from "@nreljed/platform/Components/ui/badge";
import { Button } from "@nreljed/platform/Components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@nreljed/platform/Components/ui/card";
import { Head, router } from "@inertiajs/react";

interface Insight {
    id: number;
    type: string | null;
    type_label: string | null;
    title: string;
    summary: string;
    recommendation: string;
    evidence: string[];
    confidence: string | null;
    confidence_label: string | null;
    status: string | null;
    status_label: string | null;
    generated_by: string;
    applied_at: string | null;
    period_start: string | null;
    period_end: string | null;
}

interface Props {
    period: number;
    insights: Insight[];
}

export default function Insights({ period, insights }: Props) {
    return (
        <>
            <Head title="Marketing insights" />
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">Insights & recommendations</h1>
                        <p className="text-sm text-muted-foreground">
                            Evidence-backed suggestions for the last {period} days. Apply to create a draft only.
                        </p>
                    </div>
                    <Button
                        onClick={() => router.post(route("dashboard.marketing.insights.refresh"), { days: period })}
                    >
                        Refresh
                    </Button>
                    <Button
                        variant="outline"
                        onClick={() => router.post(route("dashboard.marketing.insights.analyze"), { days: period })}
                    >
                        Analyze with AI
                    </Button>
                </div>

                {insights.length === 0 ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>No insights yet</CardTitle>
                            <CardDescription>Refresh to generate recommendations from the current period.</CardDescription>
                        </CardHeader>
                    </Card>
                ) : (
                    <div className="grid gap-4">
                        {insights.map((insight) => (
                            <Card key={insight.id}>
                                <CardHeader className="flex flex-row items-start justify-between gap-4">
                                    <div className="space-y-1">
                                        <CardTitle>{insight.title}</CardTitle>
                                        <CardDescription>{insight.summary}</CardDescription>
                                    </div>
                                    <div className="flex gap-2">
                                        {insight.type_label ? <Badge variant="secondary">{insight.type_label}</Badge> : null}
                                        {insight.confidence_label ? <Badge variant="outline">{insight.confidence_label}</Badge> : null}
                                        {insight.status_label ? <Badge>{insight.status_label}</Badge> : null}
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div>
                                        <div className="text-sm font-medium">Recommendation</div>
                                        <p className="text-sm text-muted-foreground">{insight.recommendation}</p>
                                    </div>
                                    {insight.evidence.length > 0 ? (
                                        <div>
                                            <div className="text-sm font-medium">Evidence</div>
                                            <ul className="mt-1 list-disc space-y-1 pl-5 text-sm text-muted-foreground">
                                                {insight.evidence.map((item, index) => (
                                                    <li key={index}>{item}</li>
                                                ))}
                                            </ul>
                                        </div>
                                    ) : null}
                                    {insight.status === "new" ? (
                                        <Button
                                            size="sm"
                                            onClick={() =>
                                                router.post(route("dashboard.marketing.insights.apply", insight.id))
                                            }
                                        >
                                            Mark applied
                                        </Button>
                                    ) : insight.applied_at ? (
                                        <p className="text-xs text-muted-foreground">Applied {insight.applied_at}</p>
                                    ) : null}
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
