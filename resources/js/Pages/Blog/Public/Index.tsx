import { Link } from "@inertiajs/react";
import PublicLayout from "@/Layouts/PublicLayout";
import { Badge } from "@/Components/ui/badge";
import PaginationLinks from "@/Components/shared/pagination-links";

interface Author {
  id: number;
  name: string;
}

interface Category {
  id: number;
  name: string;
  slug: string;
}

interface Post {
  id: number;
  title: string;
  slug: string;
  excerpt: string | null;
  featured_image: string | null;
  published_at: string | null;
  user: Author;
  category: Category | null;
}

interface PaginatedPosts {
  data: Post[];
  links: { url: string | null; label: string; active: boolean }[];
}

interface BlogIndexProps {
  posts: PaginatedPosts;
  category: string | null;
  tag: string | null;
}

export default function BlogIndex({ posts, category, tag }: BlogIndexProps) {
  return (
    <PublicLayout title="Blog" description="Read our latest articles and insights.">
      <div className="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
        <div className="mb-12 text-center">
          <h1 className="text-4xl font-bold tracking-tight text-foreground">Blog</h1>
          <p className="mt-4 text-lg text-muted-foreground">
            Read our latest articles and insights.
          </p>
        </div>

        {category && (
          <div className="mb-8 text-center">
            <Badge variant="secondary" className="px-4 py-1.5 text-sm">
              Category: {category}
              <Link
                href={route("blog.index")}
                className="ml-1 text-muted-foreground hover:text-foreground"
              >
                &times;
              </Link>
            </Badge>
          </div>
        )}

        {tag && (
          <div className="mb-8 text-center">
            <Badge variant="secondary" className="px-4 py-1.5 text-sm">
              Tag: {tag}
              <Link
                href={route("blog.index")}
                className="ml-1 text-muted-foreground hover:text-foreground"
              >
                &times;
              </Link>
            </Badge>
          </div>
        )}

        {posts.data.length === 0 ? (
          <div className="py-16 text-center">
            <p className="text-lg text-muted-foreground">No posts published yet.</p>
          </div>
        ) : (
          <>
            <div className="grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
              {posts.data.map((post) => (
                <Link
                  key={post.id}
                  href={route("blog.show", post.slug)}
                  className="group flex flex-col overflow-hidden rounded-lg border border-border bg-card transition-colors hover:bg-accent/50"
                >
                  {post.featured_image && (
                    <div className="aspect-video overflow-hidden">
                      <img
                        src={post.featured_image}
                        alt={post.title}
                        className="h-full w-full object-cover transition-transform group-hover:scale-105"
                      />
                    </div>
                  )}
                  <div className="flex flex-1 flex-col p-6">
                    {post.category && (
                      <span className="mb-2 text-xs font-medium uppercase tracking-wider text-primary">
                        {post.category.name}
                      </span>
                    )}
                    <h2 className="mb-2 text-xl font-semibold text-foreground group-hover:text-primary">
                      {post.title}
                    </h2>
                    {post.excerpt && (
                      <p className="mb-4 flex-1 text-sm text-muted-foreground line-clamp-3">
                        {post.excerpt}
                      </p>
                    )}
                    <div className="flex items-center gap-2 text-xs text-muted-foreground">
                      <span>{post.user.name}</span>
                      {post.published_at && (
                        <>
                          <span>&middot;</span>
                          <time>{new Date(post.published_at).toLocaleDateString()}</time>
                        </>
                      )}
                    </div>
                  </div>
                </Link>
              ))}
            </div>

            {posts.links.length > 3 && (
              <div className="mt-12">
                <PaginationLinks links={posts.links} />
              </div>
            )}
          </>
        )}
      </div>
    </PublicLayout>
  );
}
