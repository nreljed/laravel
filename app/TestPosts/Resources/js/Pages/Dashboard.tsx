import { usePage, Link, Head } from "@inertiajs/react";
import { Plus, List, FileCheck, CheckCircle2 } from "lucide-react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Button } from "@/Components/ui/button";
import { Badge } from "@/Components/ui/badge";
import type { PageProps } from "@/types";

interface TestPostStats {
  total: number;
  published: number;
  draft: number;
}

interface RecentTestPost {
  id: number;
  title: string;
  status: string;
  user_id: number;
  user?: { name: string };
  created_at: string;
}

export default function TestPostsDashboard() {
  const { stats, recentPosts } =
    usePage<PageProps<{ stats: TestPostStats; recentPosts: RecentTestPost[] }>>().props;

  const statCards = [
    {
      label: "Total",
      value: stats.total,
      icon: FileCheck,
      color: "text-blue-600 dark:text-blue-400",
      bg: "bg-blue-100 dark:bg-blue-950",
    },
    {
      label: "Published",
      value: stats.published,
      icon: CheckCircle2,
      color: "text-green-600 dark:text-green-400",
      bg: "bg-green-100 dark:bg-green-950",
    },
    {
      label: "Draft",
      value: stats.draft,
      icon: FileCheck,
      color: "text-amber-600 dark:text-amber-400",
      bg: "bg-amber-100 dark:bg-amber-950",
    },
  ];

  return (
    <AuthenticatedLayout header="Test Dashboard">
      <Head title="Test Dashboard" />

      <div className="space-y-6">
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {statCards.map((s) => (
            <Card key={s.label}>
              <CardHeader className="flex flex-row items-center justify-between pb-2">
                <CardTitle className="text-sm font-medium text-muted-foreground">
                  {s.label}
                </CardTitle>
                <div className={`rounded-lg p-2 ${s.bg}`}>
                  <s.icon className={`h-4 w-4 ${s.color}`} />
                </div>
              </CardHeader>
              <CardContent>
                <p className="text-2xl font-bold">{s.value}</p>
              </CardContent>
            </Card>
          ))}
        </div>

        <div className="grid gap-6 lg:grid-cols-2">
          <Card>
            <CardHeader>
              <CardTitle className="text-lg">Recent Test</CardTitle>
            </CardHeader>
            <CardContent>
              {recentPosts.length === 0 ? (
                <p className="text-sm text-muted-foreground">No tests yet.</p>
              ) : (
                <div className="space-y-3">
                  {recentPosts.map((p) => (
                    <Link
                      key={p.id}
                      href={route("dashboard.test-posts.edit", p.id)}
                      className="flex items-center justify-between rounded-lg border p-3 transition-colors hover:bg-muted/50"
                    >
                      <div className="min-w-0 flex-1">
                        <p className="text-sm font-medium truncate">{p.title}</p>
                        <p className="text-xs text-muted-foreground">{p.user?.name}</p>
                      </div>
                      <Badge variant={p.status === "published" ? "default" : "secondary"}>
                        {p.status}
                      </Badge>
                    </Link>
                  ))}
                </div>
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle className="text-lg">Quick Actions</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
              <Button variant="outline" className="w-full justify-start" asChild>
                <Link href={route("dashboard.test-posts.create")}>
                  <Plus className="mr-2 h-4 w-4" />
                  New Test
                </Link>
              </Button>
              <Button variant="outline" className="w-full justify-start" asChild>
                <Link href={route("dashboard.test-posts.index")}>
                  <List className="mr-2 h-4 w-4" />
                  All Test
                </Link>
              </Button>
            </CardContent>
          </Card>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
