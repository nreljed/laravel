import { usePage, Link } from "@inertiajs/react"
import { Head } from "@inertiajs/react"
import { Plus, List, FileEdit, CheckCircle2, Archive } from "lucide-react"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card"
import { Button } from "@/Components/ui/button"
import { Badge } from "@/Components/ui/badge"

interface PostStats {
  total: number
  published: number
  draft: number
  archived: number
}

interface RecentPost {
  id: number
  title: string
  status: string
  category_id: number | null
  user_id: number
  user?: { name: string }
  category?: { name: string }
  created_at: string
}

export default function PostsDashboard() {
  const { stats, recentPosts } = usePage<{ stats: PostStats; recentPosts: RecentPost[] }>().props

  const statCards = [
    { label: "Total", value: stats.total, icon: FileEdit, color: "text-blue-600 dark:text-blue-400", bg: "bg-blue-100 dark:bg-blue-950" },
    { label: "Published", value: stats.published, icon: CheckCircle2, color: "text-green-600 dark:text-green-400", bg: "bg-green-100 dark:bg-green-950" },
    { label: "Draft", value: stats.draft, icon: FileEdit, color: "text-amber-600 dark:text-amber-400", bg: "bg-amber-100 dark:bg-amber-950" },
    { label: "Archived", value: stats.archived, icon: Archive, color: "text-gray-600 dark:text-gray-400", bg: "bg-gray-100 dark:bg-gray-950" },
  ]

  return (
    <AuthenticatedLayout header="Posts Dashboard">
      <Head title="Posts Dashboard" />

      <div className="space-y-6">
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {statCards.map((s) => (
            <Card key={s.label}>
              <CardHeader className="flex flex-row items-center justify-between pb-2">
                <CardTitle className="text-sm font-medium text-muted-foreground">{s.label}</CardTitle>
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
              <CardTitle className="text-lg">Recent Posts</CardTitle>
            </CardHeader>
            <CardContent>
              {recentPosts.length === 0 ? (
                <p className="text-sm text-muted-foreground">No posts yet.</p>
              ) : (
                <div className="space-y-3">
                  {recentPosts.map((p) => (
                    <Link
                      key={p.id}
                      href={route("dashboard.posts.edit", p.id)}
                      className="flex items-center justify-between rounded-lg border p-3 transition-colors hover:bg-muted/50"
                    >
                      <div className="min-w-0 flex-1">
                        <p className="text-sm font-medium truncate">{p.title}</p>
                        <p className="text-xs text-muted-foreground">
                          {p.category?.name && `${p.category.name} · `}
                          {p.user?.name}
                        </p>
                      </div>
                      <Badge variant={p.status === "published" ? "default" : p.status === "draft" ? "secondary" : "outline"}>
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
                <Link href={route("dashboard.posts.create")}>
                  <Plus className="mr-2 h-4 w-4" />
                  New Post
                </Link>
              </Button>
              <Button variant="outline" className="w-full justify-start" asChild>
                <Link href={route("dashboard.posts.index")}>
                  <List className="mr-2 h-4 w-4" />
                  All Posts
                </Link>
              </Button>
            </CardContent>
          </Card>
        </div>
      </div>
    </AuthenticatedLayout>
  )
}
