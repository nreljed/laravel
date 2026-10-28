import { Head, Link } from "@inertiajs/react";
import { route } from "ziggy-js";
import AuthenticatedLayout from "@nreljed/platform/Layouts/AuthenticatedLayout";
import { useFormatDate } from "@nreljed/platform/hooks/use-format-date";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@nreljed/platform/Components/ui/card";
import { Badge } from "@nreljed/platform/Components/ui/badge";
import { Button } from "@nreljed/platform/Components/ui/button";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@nreljed/platform/Components/ui/table";
import {
  FileText,
  CheckCircle2,
  FileEdit,
  Archive,
  Plus,
  TrendingUp,
  AlertTriangle,
  ChartPie,
} from "lucide-react";
import {
  Area,
  AreaChart,
  CartesianGrid,
  Cell,
  Pie,
  PieChart,
  XAxis,
  YAxis,
} from "recharts";
import {
  ChartContainer,
  ChartTooltip,
  ChartTooltipContent,
  type ChartConfig,
} from "@nreljed/platform/Components/ui/chart";

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
  monthlyTrend: Array<{ month: string; total: number }>;
  categoryDistribution: Array<{ name: string; value: number }>;
  recentPosts: RecentPost[];
}

const statusVariant: Record<string, "default" | "secondary" | "outline"> = {
  published: "default",
  draft: "secondary",
  archived: "outline",
  future: "secondary",
};

const trendConfig = {
  total: { label: "Posts", color: "var(--chart-1)" },
} satisfies ChartConfig;

const pieConfig = {
  value: { label: "Count" },
} satisfies ChartConfig;

const PIE_COLORS = [
  "var(--color-chart-1)",
  "var(--color-chart-2)",
  "var(--color-chart-3)",
  "var(--color-chart-4)",
  "var(--color-chart-5)",
];

export default function BlogDashboard({
  stats,
  monthlyTrend,
  categoryDistribution,
  recentPosts,
}: BlogDashboardProps) {
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

        <div className="grid gap-4 md:grid-cols-2">
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <TrendingUp className="h-5 w-5" />
                Monthly Trend
              </CardTitle>
              <CardDescription>Posts per month (last 12 months)</CardDescription>
            </CardHeader>
            <CardContent>
              <ChartContainer config={trendConfig} className="aspect-auto h-[260px] w-full">
                <AreaChart data={monthlyTrend} margin={{ top: 10, right: 10, left: 0, bottom: 0 }}>
                  <defs>
                    <linearGradient id="fillPostTotal" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="5%" stopColor="var(--color-chart-1)" stopOpacity={0.3} />
                      <stop offset="95%" stopColor="var(--color-chart-1)" stopOpacity={0} />
                    </linearGradient>
                  </defs>
                  <CartesianGrid vertical={false} stroke="var(--border)" />
                  <XAxis dataKey="month" tickLine={false} axisLine={false} tickMargin={8} fontSize={12} />
                  <YAxis tickLine={false} axisLine={false} tickMargin={8} fontSize={12} allowDecimals={false} />
                  <ChartTooltip content={<ChartTooltipContent />} />
                  <Area
                    type="monotone"
                    dataKey="total"
                    stroke="var(--color-chart-1)"
                    fill="url(#fillPostTotal)"
                    strokeWidth={2}
                  />
                </AreaChart>
              </ChartContainer>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <ChartPie className="h-5 w-5" />
                Category Distribution
              </CardTitle>
              <CardDescription>Top categories by post count</CardDescription>
            </CardHeader>
            <CardContent>
              <ChartContainer config={pieConfig} className="aspect-auto h-[260px] w-full">
                <PieChart>
                  <Pie
                    data={
                      categoryDistribution.length > 0
                        ? categoryDistribution
                        : [{ name: "No categories", value: 1 }]
                    }
                    dataKey="value"
                    nameKey="name"
                    cx="50%"
                    cy="50%"
                    outerRadius={90}
                    innerRadius={55}
                  >
                    {categoryDistribution.map((_, index) => (
                      <Cell key={`cell-${index}`} fill={PIE_COLORS[index % PIE_COLORS.length]} />
                    ))}
                  </Pie>
                  <ChartTooltip content={<ChartTooltipContent />} />
                </PieChart>
              </ChartContainer>
            </CardContent>
          </Card>
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
