import { Head, Link } from "@inertiajs/react";
import { Plus, FileText } from "lucide-react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Button } from "@/Components/ui/button";
import { Card, CardContent } from "@/Components/ui/card";
import { Badge } from "@/Components/ui/badge";

interface Post {
  id: number;
  title: string;
  slug: string;
  excerpt: string | null;
  status: string;
  category: string | null;
  published_at: string | null;
  user: { id: number; name: string } | null;
}

interface PostsIndexProps {
  posts: { data: Post[] };
}

export default function PostsIndex({ posts }: PostsIndexProps) {
  return (
    <AuthenticatedLayout header="Posts">
      <Head title="Posts" />
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <h2 className="text-lg font-semibold">Posts</h2>
          <Link href={route("dashboard.posts.create")}>
            <Button>
              <Plus className="mr-1 h-4 w-4" /> Add Post
            </Button>
          </Link>
        </div>

        {posts.data.length === 0 ? (
          <Card>
            <CardContent className="flex flex-col items-center justify-center py-16 text-center">
              <FileText className="mb-2 h-10 w-10 text-muted-foreground" />
              <p className="text-sm text-muted-foreground">No posts yet.</p>
              <Link href={route("dashboard.posts.create")}>
                <Button variant="link">Write your first post</Button>
              </Link>
            </CardContent>
          </Card>
        ) : (
          <div className="space-y-3">
            {posts.data.map((post) => (
              <Card key={post.id}>
                <CardContent className="flex items-start justify-between p-4">
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 mb-1">
                      <Badge variant={post.status === "published" ? "default" : "secondary"}>{post.status}</Badge>
                      {post.category && <span className="text-xs text-muted-foreground">{post.category}</span>}
                    </div>
                    <p className="font-medium truncate">{post.title}</p>
                    {post.excerpt && <p className="text-sm text-muted-foreground truncate mt-1">{post.excerpt}</p>}
                    <p className="text-xs text-muted-foreground mt-1">
                      by {post.user?.name || "Unknown"} &middot; {post.published_at ? new Date(post.published_at).toLocaleDateString() : "Not published"}
                    </p>
                  </div>
                  <Link href={route("dashboard.posts.edit", post.id)}>
                    <Button variant="ghost" size="sm">Edit</Button>
                  </Link>
                </CardContent>
              </Card>
            ))}
          </div>
        )}
      </div>
    </AuthenticatedLayout>
  );
}
