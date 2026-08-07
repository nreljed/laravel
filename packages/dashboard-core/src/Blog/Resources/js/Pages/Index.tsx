import { Head, Link, router, usePage } from "@inertiajs/react";
import { jsonHeaders } from "@/lib/gallery-utils";
import { DataTable } from "@/Components/ui/data-table";
import { DataTableColumnHeader } from "@/Components/ui/data-table-column-header";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
import { ActionCell } from "@/Components/shared/action-cell";
import {
  Empty,
  EmptyHeader,
  EmptyTitle,
  EmptyDescription,
  EmptyMedia,
} from "@/Components/ui/empty";
import { toast } from "@/Components/ui/toast";
import { useMemo, useState } from "react";
import { FileText, Plus, Sparkles } from "lucide-react";
import type { ColumnDef } from "@tanstack/react-table";
import type { AppTableFeatures } from "@/lib/table-features";

interface Tag {
  id: number;
  name: string;
}

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
  featured_image: string | null;
  published_at: string | null;
  created_at: string;
  user: { id: number; name: string } | null;
  category: Category | null;
  tags: Tag[];
  meta_title?: string | null;
}

interface BlogIndexProps {
  posts: Post[];
  categories: Category[];
}

export default function BlogIndex({ posts, categories }: BlogIndexProps) {
  const flash = usePage().props.flash as Record<string, string> | undefined;
  const [categoryFilter, setCategoryFilter] = useState<string>("");

  useMemo(() => {
    if (flash?.status) toast.add({ title: flash.status, type: "success" });
    if (flash?.["flash.success"])
      toast.add({ title: String(flash["flash.success"]), type: "success" });
    if (flash?.["flash.error"]) toast.add({ title: String(flash["flash.error"]), type: "error" });
  }, [flash]);

  const statusVariant = (status: string) => {
    switch (status) {
      case "published": {
        return "default" as const;
      }
      case "draft": {
        return "secondary" as const;
      }
      case "archived": {
        return "outline" as const;
      }
      default: {
        return "secondary" as const;
      }
    }
  };

  const columns = useMemo<ColumnDef<AppTableFeatures, Post>[]>(() => {
    const cols: ColumnDef<AppTableFeatures, Post>[] = [
      {
        id: "image",
        header: () => null,
        enableSorting: false,
        enableHiding: false,
        cell: ({ row }) =>
          row.original.featured_image ? (
            <img
              src={row.original.featured_image}
              alt=""
              className="h-10 w-10 rounded-md object-cover"
            />
          ) : (
            <div className="flex h-10 w-10 items-center justify-center rounded-md bg-muted">
              <FileText className="h-4 w-4 text-muted-foreground" />
            </div>
          ),
      },
      {
        accessorKey: "title",
        header: ({ column }) => <DataTableColumnHeader column={column} title="Title" />,
        cell: ({ row }) => (
          <div className="flex flex-col min-w-0">
            <Link
              href={route("dashboard.posts.edit", row.original.id)}
              className="font-medium hover:underline truncate"
            >
              {row.original.title}
            </Link>
            {row.original.excerpt && (
              <span className="text-xs text-muted-foreground truncate max-w-[300px]">
                {row.original.excerpt}
              </span>
            )}
          </div>
        ),
      },
      {
        accessorKey: "status",
        header: ({ column }) => <DataTableColumnHeader column={column} title="Status" />,
        cell: ({ row }) => (
          <Badge variant={statusVariant(row.original.status)}>{row.original.status}</Badge>
        ),
        filterFn: "equals",
      },
      {
        id: "category",
        accessorFn: (row) => row.category?.name ?? "",
        header: ({ column }) => <DataTableColumnHeader column={column} title="Category" />,
        cell: ({ row }) => (
          <span className="text-sm text-muted-foreground">
            {row.original.category?.name ?? "—"}
          </span>
        ),
      },
      {
        id: "seo",
        accessorFn: (row) => (row.meta_title ? "Set" : "Missing"),
        header: ({ column }) => <DataTableColumnHeader column={column} title="SEO" />,
        cell: ({ row }) => (
          <Badge variant={row.original.meta_title ? "default" : "secondary"} className="gap-1">
            <Sparkles className="h-3 w-3" />
            {row.original.meta_title ? "Set" : "Missing"}
          </Badge>
        ),
        filterFn: "equals",
      },
      {
        id: "author",
        accessorFn: (row) => row.user?.name ?? "",
        header: ({ column }) => <DataTableColumnHeader column={column} title="Author" />,
        cell: ({ row }) => (
          <span className="text-sm text-muted-foreground">
            {row.original.user?.name ?? "Unknown"}
          </span>
        ),
      },
      {
        id: "date",
        accessorFn: (row) => row.published_at ?? row.created_at,
        header: ({ column }) => <DataTableColumnHeader column={column} title="Date" />,
        cell: ({ row }) => {
          const date = row.original.published_at ?? row.original.created_at;
          return (
            <span className="text-xs text-muted-foreground whitespace-nowrap">
              {new Date(date).toLocaleDateString()}
            </span>
          );
        },
      },
      {
        id: "actions",
        cell: ({ row }) => (
          <ActionCell
            item={row.original}
            baseRoute={route("dashboard.posts.index").replace(/\/+$/, "")}
            itemLabel="Post"
          />
        ),
        enableSorting: false,
      },
    ];
    return cols;
  }, []);

  const filteredPosts = useMemo(() => {
    if (!categoryFilter) return posts;
    return posts.filter((p) => {
      if (categoryFilter === "none") return !p.category;
      return String(p.category?.id) === categoryFilter;
    });
  }, [posts, categoryFilter]);

  const handleBulkAction = async (action: string, ids: number[]) => {
    if (action === "seo") {
      try {
        const res = await fetch(route("dashboard.posts.seo.bulk") as string, {
          method: "POST",
          headers: jsonHeaders(),
          body: JSON.stringify({ post_ids: ids }),
        });
        const data = await res.json();
        toast.add({ title: data.message || "SEO generation started", type: "success" });
      } catch {
        toast.add({ title: "Failed to generate SEO metadata", type: "error" });
      }
      return;
    }

    router.post(route("dashboard.posts.bulk.action"), { ids, action }, { preserveScroll: true });
  };

  const statusOptions = [
    { label: "Draft", value: "draft" },
    { label: "Published", value: "published" },
    { label: "Archived", value: "archived" },
  ];

  return (
    <AuthenticatedLayout header="Posts">
      <Head title="Posts" />

      <div className="space-y-4">
        <div className="flex items-center justify-between">
          <div>
            <h2 className="text-2xl font-bold tracking-tight">Posts</h2>
            <p className="text-muted-foreground">Manage your blog posts and SEO metadata.</p>
          </div>
          <Link href={route("dashboard.posts.create")}>
            <Button>
              <Plus className="mr-1 size-4" /> Add Post
            </Button>
          </Link>
        </div>

        {categories.length > 0 && (
          <div className="flex items-center gap-2">
            <span className="text-sm text-muted-foreground">Category:</span>
            <select
              value={categoryFilter}
              onChange={(e) => setCategoryFilter(e.target.value)}
              className="h-8 rounded-md border border-input bg-background px-2 text-sm"
            >
              <option value="">All categories</option>
              {categories.map((cat) => (
                <option key={cat.id} value={String(cat.id)}>
                  {cat.name}
                </option>
              ))}
              <option value="none">Uncategorized</option>
            </select>
          </div>
        )}

        <DataTable
          columns={columns}
          data={filteredPosts}
          searchKey="title"
          searchPlaceholder="Search posts..."
          filterKey="status"
          filterTitle="All statuses"
          filterOptions={statusOptions}
          bulkActions={[
            { label: "Publish", value: "publish" },
            { label: "Move to Draft", value: "draft" },
            {
              label: "Generate SEO",
              value: "seo",
              show: (statuses) => statuses.some((s) => s !== "archived"),
            },
            {
              label: "Delete",
              value: "delete",
              destructive: true,
              confirmTitle: "Delete Posts",
              confirmDescription:
                "Are you sure you want to delete the selected posts? This action cannot be undone.",
              confirmLabel: "Delete",
            },
          ]}
          onBulkAction={handleBulkAction}
          emptyState={
            <Empty>
              <EmptyHeader>
                <EmptyMedia variant="icon">
                  <FileText />
                </EmptyMedia>
                <EmptyTitle>No posts yet</EmptyTitle>
                <EmptyDescription>Get started by writing your first post.</EmptyDescription>
              </EmptyHeader>
            </Empty>
          }
        />
      </div>
    </AuthenticatedLayout>
  );
}
