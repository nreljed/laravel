import { Head, Link } from "@inertiajs/react";
import { route } from "ziggy-js";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { useFormatDate } from "@/hooks/use-format-date";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/Components/ui/card";
import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/Components/ui/table";
import {
  FileText,
  CheckCircle2,
  FileEdit,
  Archive,
  Plus,
  TrendingUp,
  AlertTriangle,
} from "lucide-react";

interface BlogStats {
  total: number;
  published: number;
  draft: number;
  archived: number;
}

interface RecentPost {
  id: number;
  title: string;
  status: string;
  category_id: number | null;
  user_id: number;
  created_at: string;
  category?: { id: number; name: string } | null;
}

interface BlogDashboardProps {
  stats: BlogStats;
  recentPosts: RecentPost[];
}

const statusVariant: Record<string, "default" | "secondary" | "outline"> = {
  published: "default",
  draft: "secondary",
  archived: "outline",
  future: "secondary",
};

export default function BlogDashboard({ stats, recentPosts }: BlogDashboardProps) {
  const { formatDateTime } = useFormatDate();

  const statCards = [
    { label: "Total Posts", value: stats.total, icon: FileText, color: "text-foreground" },
    { label: "Published", value: stats.published, icon: CheckCircle2, color: "text-green-600" },
    { label: "Drafts", value: stats.draft, icon: FileEdit, color: "text-yellow-600" },
    { label: "Archived", value: stats.archived, icon: Archive, color: "text-muted-foreground" },
  ];

  return (
    <AuthenticatedLayout title="Blog Dashboard">
      <Head title="Blog Dashboard" />

      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h2 className="text-2xl font-bold tracking-tight">Blog Dashboard</h2>
            <p className="text-muted-foreground">Overview of your blog content.</p>
          </div>
          <Link href={route("dashboard.posts.create")}>
            <Button>
              <Plus className="mr-2 h-4 w-4" />
              Add Post
            </Button>
          </Link>
        </div>

        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
          {statCards.map((stat) => {
            const Icon = stat.icon;
            return (
              <Card key={stat.label}>
                <CardHeader className="flex flex-row items-center justify-between pb-2">
                  <CardTitle className="text-sm font-medium text-muted-foreground">
                    {stat.label}
                  </CardTitle>
                  <Icon className={`h-4 w-4 ${stat.color}`} />
                </CardHeader>
                <CardContent>
                  <div className="text-2xl font-bold">{stat.value}</div>
                </CardContent>
              </Card>
            );
          })}
        </div>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <TrendingUp className="h-5 w-5" />
              Recent Posts
            </CardTitle>
            <CardDescription>Your latest blog posts</CardDescription>
          </CardHeader>
          <CardContent>
            {recentPosts.length === 0 ? (
              <div className="flex flex-col items-center justify-center py-8 text-center">
                <FileText className="h-12 w-12 text-muted-foreground/50 mb-3" />
                <p className="text-muted-foreground">No posts yet. Create your first post!</p>
                <Link href={route("dashboard.posts.create")} className="mt-3">
                  <Button size="sm">
                    <Plus className="mr-2 h-3.5 w-3.5" />
                    Add Post
                  </Button>
                </Link>
              </div>
            ) : (
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Title</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>Category</TableHead>
                    <TableHead>Date</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {recentPosts.map((post) => (
                    <TableRow key={post.id}>
                      <TableCell>
                        <Link
                          href={route("dashboard.posts.edit", post.id)}
                          className="font-medium hover:underline"
                        >
                          {post.title}
                        </Link>
                      </TableCell>
                      <TableCell>
                        <Badge variant={statusVariant[post.status] ?? "secondary"}>
                          {post.status}
                        </Badge>
                      </TableCell>
                      <TableCell>
                        {post.category ? (
                          <Badge variant="outline">{post.category.name}</Badge>
                        ) : (
                          <span className="text-xs text-muted-foreground">-</span>
                        )}
                      </TableCell>
                      <TableCell className="text-xs text-muted-foreground">
                        {formatDateTime(post.created_at)}
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            )}
          </CardContent>
        </Card>

        {stats.draft > 0 && (
          <Card className="border-yellow-200 bg-yellow-50/50 dark:border-yellow-800 dark:bg-yellow-950/20">
            <CardContent className="flex items-center gap-3 py-4">
              <AlertTriangle className="h-5 w-5 text-yellow-600" />
              <p className="text-sm text-yellow-800 dark:text-yellow-200">
                You have {stats.draft} draft post(s).{" "}
                <Link href={route("dashboard.posts.index")} className="font-medium underline">
                  Review drafts
                </Link>
              </p>
            </CardContent>
          </Card>
        )}
      </div>
    </AuthenticatedLayout>
  );
}
