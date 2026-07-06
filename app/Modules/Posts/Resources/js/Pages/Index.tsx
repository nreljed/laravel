import { Head, Link, router } from "@inertiajs/react";
import { Plus, FileText, Search as SearchIcon, X } from "lucide-react";
import { useState } from "react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Button } from "@/Components/ui/button";
import { Card, CardContent } from "@/Components/ui/card";
import { Input } from "@/Components/ui/input";
import { Empty, EmptyHeader, EmptyTitle, EmptyDescription, EmptyContent, EmptyMedia } from "@/Components/ui/empty";
import { Badge } from "@/Components/ui/badge";
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from "@/Components/ui/select";
import {
  Pagination, PaginationContent, PaginationItem, PaginationLink, PaginationNext, PaginationPrevious,
} from "@/Components/ui/pagination";

interface Category {
  id: number;
  name: string;
}

interface Post {
  id: number;
  title: string;
  slug: string;
  excerpt: string | null;
  status: string;
  published_at: string | null;
  user: { id: number; name: string } | null;
  category: Category | null;
}

interface PostsIndexProps {
  posts: { data: Post[]; current_page: number; last_page: number; links: { url: string | null; label: string; active: boolean }[] };
  categories: Category[];
  filters: { search?: string; status?: string; category_id?: string };
}

export default function PostsIndex({ posts, categories, filters }: PostsIndexProps) {
  const [search, setSearch] = useState(filters.search || "");

  const applyFilter = (key: string, value: string) => {
    const params = { ...filters, [key]: value || undefined };
    router.get(route("dashboard.posts.index"), params, { preserveState: true, replace: true });
  };

  const clearFilters = () => {
    setSearch("");
    router.get(route("dashboard.posts.index"), {}, { preserveState: true, replace: true });
  };

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    applyFilter("search", search);
  };

  const hasFilters = filters.search || filters.status || filters.category_id;

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

        <div className="flex flex-wrap items-center gap-3">
          <form onSubmit={handleSearch} className="flex items-center gap-2">
            <div className="relative">
              <SearchIcon className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
              <Input placeholder="Search posts..." value={search} onChange={(e) => setSearch(e.target.value)} className="w-60 pl-8" />
            </div>
            <Button type="submit" variant="secondary" size="sm">Search</Button>
          </form>

          <Select value={filters.status || ""} onValueChange={(v) => applyFilter("status", v)}>
            <SelectTrigger className="w-32">
              <SelectValue placeholder="All statuses" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="">All statuses</SelectItem>
              <SelectItem value="draft">Draft</SelectItem>
              <SelectItem value="published">Published</SelectItem>
              <SelectItem value="archived">Archived</SelectItem>
            </SelectContent>
          </Select>

          <Select value={filters.category_id || ""} onValueChange={(v) => applyFilter("category_id", v)}>
            <SelectTrigger className="w-40">
              <SelectValue placeholder="All categories" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="">All categories</SelectItem>
              {categories.map((cat) => (
                <SelectItem key={cat.id} value={String(cat.id)}>{cat.name}</SelectItem>
              ))}
            </SelectContent>
          </Select>

          {hasFilters && (
            <Button variant="ghost" size="sm" onClick={clearFilters}>
              <X className="mr-1 h-3 w-3" /> Clear
            </Button>
          )}
        </div>

        {posts.data.length === 0 ? (
          <Empty>
            <EmptyHeader>
              <EmptyMedia variant="icon"><FileText /></EmptyMedia>
              <EmptyTitle>{hasFilters ? "No posts found" : "No posts yet"}</EmptyTitle>
              <EmptyDescription>
                {hasFilters ? "No posts match your filters." : "Get started by writing your first post."}
              </EmptyDescription>
            </EmptyHeader>
            {!hasFilters && (
              <EmptyContent>
                <Button onClick={() => router.visit(route("dashboard.posts.create"))}>
                  <Plus className="mr-2 h-4 w-4" /> Write Your First Post
                </Button>
              </EmptyContent>
            )}
          </Empty>
        ) : (
          <div className="space-y-3">
            {posts.data.map((post) => (
              <Card key={post.id}>
                <CardContent className="flex items-start justify-between p-4">
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 mb-1">
                      <Badge variant={post.status === "published" ? "default" : "secondary"}>{post.status}</Badge>
                      {post.category && <span className="text-xs text-muted-foreground">{post.category.name}</span>}
                    </div>
                    <Link href={route("dashboard.posts.edit", post.id)} className="font-medium hover:underline truncate block">
                      {post.title}
                    </Link>
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

            {posts.last_page > 1 && (
              <Pagination>
                <PaginationContent>
                  {posts.links.map((link, i) => {
                    if (link.label.includes("Previous")) {
                      return <PaginationItem key={i}><PaginationPrevious href={link.url ?? "#"} /></PaginationItem>;
                    }
                    if (link.label.includes("Next")) {
                      return <PaginationItem key={i}><PaginationNext href={link.url ?? "#"} /></PaginationItem>;
                    }
                    return (
                      <PaginationItem key={i}>
                        <PaginationLink href={link.url ?? "#"} isActive={link.active}>{link.label}</PaginationLink>
                      </PaginationItem>
                    );
                  })}
                </PaginationContent>
              </Pagination>
            )}
          </div>
        )}
      </div>
    </AuthenticatedLayout>
  );
}
