import { Card, CardContent, CardHeader, CardTitle } from "@nreljed/platform/Components/ui/card";
import { Badge } from "@nreljed/platform/Components/ui/badge";
import { Head, Link } from "@inertiajs/react";

interface Post {
    id: number;
    body: string;
    status: string | null;
    scheduled_at: string | null;
    published_at: string | null;
    campaign: string | null;
}

interface Brief {
    id: number;
    title: string;
    status: string | null;
    due_at: string | null;
}

interface Plan {
    id: number;
    name: string;
    status: string | null;
    starts_at: string | null;
    ends_at: string | null;
}

interface Props {
    month: string;
    posts: Post[];
    briefs: Brief[];
    plans: Plan[];
}

export default function Calendar({ month, posts, briefs, plans }: Props) {
    return (
        <>
            <Head title="Marketing calendar" />
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">Content calendar</h1>
                        <p className="text-sm text-muted-foreground">{month}</p>
                    </div>
                    <Link href={route("dashboard.marketing.posts.create")} className="text-sm underline">
                        New post
                    </Link>
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardTitle>Scheduled and published posts</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {posts.length === 0 ? (
                                <p className="text-sm text-muted-foreground">No posts this month.</p>
                            ) : (
                                posts.map((post) => (
                                    <div key={post.id} className="rounded-md border p-3">
                                        <div className="flex items-center justify-between gap-2">
                                            <span className="text-sm font-medium">{post.body || `Post #${post.id}`}</span>
                                            {post.status ? <Badge variant="secondary">{post.status}</Badge> : null}
                                        </div>
                                        <div className="mt-1 text-xs text-muted-foreground">
                                            {post.scheduled_at ?? post.published_at ?? "No date"}
                                            {post.campaign ? ` · ${post.campaign}` : ""}
                                        </div>
                                    </div>
                                ))
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Briefs due</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {briefs.length === 0 ? (
                                <p className="text-sm text-muted-foreground">No briefs due this month.</p>
                            ) : (
                                briefs.map((brief) => (
                                    <div key={brief.id} className="rounded-md border p-3">
                                        <div className="text-sm font-medium">{brief.title}</div>
                                        <div className="mt-1 text-xs text-muted-foreground">
                                            {brief.due_at ?? "No due date"} · {brief.status ?? "draft"}
                                        </div>
                                    </div>
                                ))
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Plans in window</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {plans.length === 0 ? (
                                <p className="text-sm text-muted-foreground">No plans in this month.</p>
                            ) : (
                                plans.map((plan) => (
                                    <div key={plan.id} className="rounded-md border p-3">
                                        <div className="text-sm font-medium">{plan.name}</div>
                                        <div className="mt-1 text-xs text-muted-foreground">
                                            {plan.starts_at ?? "No start"} to {plan.ends_at ?? "No end"} · {plan.status ?? "draft"}
                                        </div>
                                    </div>
                                ))
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}
