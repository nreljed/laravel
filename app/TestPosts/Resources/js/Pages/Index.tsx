import { Head, Link, router } from "@inertiajs/react";
import { Plus, FileText } from "lucide-react";
import { useMemo, useCallback } from "react";
import { toast } from "@/Components/ui/toast";
import type { ColumnDef } from "@tanstack/react-table";
import type { AppTableFeatures } from "@/lib/table-features";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { DataTable } from "@/Components/ui/data-table";
import { DataTableColumnHeader } from "@/Components/ui/data-table-column-header";
import { Button } from "@/Components/ui/button";
import { Badge } from "@/Components/ui/badge";
import { ActionCell } from "@/Components/shared/action-cell";
import {
  Empty,
  EmptyContent,
  EmptyDescription,
  EmptyHeader,
  EmptyMedia,
  EmptyTitle,
} from "@/Components/ui/empty";

interface TestPost {
  id: number;
  title: string;
  slug: string;
  excerpt: string | null;
  status: string;
  featured_image: string | null;
  published_at: string | null;
  created_at: string;
  user: { id: number; name: string } | null;
}

interface TestPostsIndexProps {
  testPosts: TestPost[];
}

export default function TestPostsIndex({ testPosts }: TestPostsIndexProps) {
  const handleBulkAction = useCallback((action: string, ids: number[]) => {
    router.post(
      route("dashboard.test-posts.bulk.action"),
      { ids, action },
      {
        preserveScroll: true,
        onSuccess: () => {
          toast.add({ title: `Bulk action completed.`, type: "success" });
          router.reload({ only: ["testPosts"] });
        },
        onError: () => toast.add({ title: "Bulk action failed.", type: "error" }),
      },
    );
  }, []);

  const bulkActions = useMemo(
    () => [
      {
        label: "Publish",
        value: "publish",
        show: (statuses: string[]) => statuses.some((s) => s !== "published"),
      },
      {
        label: "Draft",
        value: "draft",
        show: (statuses: string[]) => statuses.some((s) => s !== "draft"),
      },
      { label: "Delete", value: "delete", destructive: true },
    ],
    [],
  );

  const columns = useMemo(() => {
    const cols: ColumnDef<AppTableFeatures, TestPost>[] = [
      {
        id: "featured_image",
        header: "Image",
        cell: ({ row }) => (
          <div className="size-10 shrink-0 rounded-md bg-muted flex items-center justify-center overflow-hidden">
            {row.original.featured_image ? (
              <img
                src={row.original.featured_image}
                alt=""
                className="h-full w-full object-cover"
              />
            ) : (
              <FileText className="h-4 w-4 text-muted-foreground/50" />
            )}
          </div>
        ),
        enableSorting: false,
      },
      {
        accessorKey: "title",
        header: ({ column }) => <DataTableColumnHeader column={column} title="Title" />,
        cell: ({ row }) => (
          <Link
            href={route("dashboard.test-posts.edit", row.original.id)}
            className="font-medium hover:underline"
          >
            {row.original.title}
          </Link>
        ),
      },
      {
        accessorKey: "status",
        header: ({ column }) => <DataTableColumnHeader column={column} title="Status" />,
        cell: ({ row }) => (
          <Badge variant={row.original.status === "published" ? "default" : "secondary"}>
            {row.original.status}
          </Badge>
        ),
      },
      {
        id: "date",
        accessorFn: (row) => row.published_at ?? row.created_at,
        header: ({ column }) => <DataTableColumnHeader column={column} title="Date" />,
        cell: ({ row }) => {
          const date = row.original.published_at ?? row.original.created_at;
          return (
            <span className="text-sm text-muted-foreground">
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
            baseRoute={route("dashboard.test-posts.index").replace(/\/+$/, "")}
            itemLabel="Test"
          />
        ),
        enableSorting: false,
      },
    ];
    return cols;
  }, []);

  return (
    <AuthenticatedLayout header="Test">
      <Head title="Test" />
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <h2 className="text-lg font-semibold">Test</h2>
          <Link href={route("dashboard.test-posts.create")}>
            <Button>
              <Plus className="mr-1 h-4 w-4" /> Add Test
            </Button>
          </Link>
        </div>

        <DataTable
          columns={columns}
          data={testPosts}
          searchKey="title"
          searchPlaceholder="Search tests..."
          filterKey="status"
          filterTitle="Status"
          filterOptions={[
            { label: "Draft", value: "draft" },
            { label: "Published", value: "published" },
          ]}
          bulkActions={bulkActions}
          onBulkAction={handleBulkAction}
          emptyState={
            <Empty>
              <EmptyHeader>
                <EmptyMedia variant="icon">
                  <FileText />
                </EmptyMedia>
                <EmptyTitle>No tests yet</EmptyTitle>
                <EmptyDescription>Create your first test to get started.</EmptyDescription>
              </EmptyHeader>
              <EmptyContent>
                <Link href={route("dashboard.test-posts.create")}>
                  <Button>
                    <Plus className="mr-2 h-4 w-4" /> Create Test
                  </Button>
                </Link>
              </EmptyContent>
            </Empty>
          }
        />
      </div>
    </AuthenticatedLayout>
  );
}
