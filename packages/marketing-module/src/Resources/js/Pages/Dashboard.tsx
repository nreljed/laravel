import { Head, Link, usePage } from "@inertiajs/react";
import type { PageProps } from "@inertiajs/core";
import { Layers, Megaphone, Plus, Tags, Users } from "lucide-react";
import AuthenticatedLayout from "@nreljed/platform/Layouts/AuthenticatedLayout";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@nreljed/platform/Components/ui/card";
import { Button } from "@nreljed/platform/Components/ui/button";
import { Badge } from "@nreljed/platform/Components/ui/badge";

interface MarketingAccount {
    id: number;
    platform: string;
    account_name: string;
    username: string | null;
    status: string | null;
    last_validated_at: string | null;
}

interface MarketingSummary {
    accounts: number;
    contacts: number;
    tags: number;
    segments: number;
}

interface MarketingDashboardProps extends PageProps {
    summary: MarketingSummary;
    accounts: MarketingAccount[];
    platforms: string[];
}

const platformLabels: Record<string, string> = {
    meta: "Meta",
    instagram: "Instagram",
    linkedin: "LinkedIn",
    x: "X",
    pinterest: "Pinterest",
};

/**
 * The marketing landing page.
 *
 * It answers one question — "what can this install actually publish to?" —
 * so the connected accounts come first and the audience sizes second.
 */
export default function MarketingDashboard() {
    const { summary, accounts, platforms } = usePage<MarketingDashboardProps>().props;

    const connected = new Set(accounts.map((account) => account.platform));

    const stats = [
        { label: "Connected accounts", value: summary.accounts, icon: Megaphone },
        { label: "Contacts", value: summary.contacts, icon: Users },
        { label: "Tags", value: summary.tags, icon: Tags },
        { label: "Segments", value: summary.segments, icon: Layers },
    ];

    return (
        <AuthenticatedLayout header="Marketing" title="Marketing">
            <Head title="Marketing" />

            <div className="space-y-6">
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
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-lg">Connected accounts</CardTitle>
                            <CardDescription>
                                The social accounts this workspace can publish to.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {accounts.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No accounts are connected yet. Connect one to start publishing.
                                </p>
                            ) : (
                                <ul className="space-y-3">
                                    {accounts.map((account) => (
                                        <li
                                            key={account.id}
                                            className="flex items-center justify-between gap-3 rounded-lg border px-3 py-2"
                                        >
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-medium">
                                                    {account.account_name}
                                                </p>
                                                <p className="truncate text-xs text-muted-foreground">
                                                    {platformLabels[account.platform] ?? account.platform}
                                                    {account.username ? ` · @${account.username}` : ""}
                                                </p>
                                            </div>
                                            <Badge variant={account.status === "active" ? "success" : "warning"}>
                                                {account.status ?? "unknown"}
                                            </Badge>
                                        </li>
                                    ))}
                                </ul>
                            )}

                            {platforms.length > 0 && (
                                <div className="flex flex-wrap gap-2 border-t pt-4">
                                    {platforms.map((platform) => (
                                        <Button
                                            key={platform}
                                            variant={connected.has(platform) ? "outline" : "default"}
                                            size="sm"
                                            render={
                                                <Link
                                                    href={route("dashboard.marketing.accounts.connect", {
                                                        platform,
                                                    })}
                                                />
                                            }
                                        >
                                            <Plus />
                                            {connected.has(platform)
                                                ? `Add ${platformLabels[platform] ?? platform}`
                                                : `Connect ${platformLabels[platform] ?? platform}`}
                                        </Button>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-lg">Audience</CardTitle>
                            <CardDescription>
                                The contacts your campaigns and automations reach.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            <Button
                                variant="outline"
                                className="w-full justify-between"
                                render={<Link href={route("dashboard.marketing.contacts.index")} />}
                            >
                                Contacts
                                <span className="text-muted-foreground">{summary.contacts}</span>
                            </Button>
                            <Button
                                variant="outline"
                                className="w-full justify-between"
                                render={<Link href={route("dashboard.marketing.tags.index")} />}
                            >
                                Tags
                                <span className="text-muted-foreground">{summary.tags}</span>
                            </Button>
                            <Button
                                variant="outline"
                                className="w-full justify-between"
                                render={<Link href={route("dashboard.marketing.segments.index")} />}
                            >
                                Segments
                                <span className="text-muted-foreground">{summary.segments}</span>
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
