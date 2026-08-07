import { Head, router, useForm, usePage } from "@inertiajs/react";
import { Plus, Pencil, Trash2, Tag } from "lucide-react";
import { useState, useMemo, useCallback } from "react";
import type { ColumnDef } from "@tanstack/react-table";
import type { AppTableFeatures } from "@/lib/table-features";
import { toast } from "@/Components/ui/toast";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { DataTable } from "@/Components/ui/data-table";
import { DataTableColumnHeader } from "@/Components/ui/data-table-column-header";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Textarea } from "@/Components/ui/textarea";
import { Label } from "@/Components/ui/label";
import { Badge } from "@/Components/ui/badge";
import {
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
  SheetDescription,
} from "@/Components/ui/sheet";
import {
  Empty,
  EmptyHeader,
  EmptyTitle,
  EmptyDescription,
  EmptyMedia,
} from "@/Components/ui/empty";

interface TagItem {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  posts_count: number;
  order: number;
}

export default function PostTagsIndex() {
  const { tags } = usePage().props as unknown as { tags: TagItem[] };
  const [editingTag, setEditingTag] = useState<TagItem | null>(null);
  const [sheetOpen, setSheetOpen] = useState(false);
  const [sheetMode, setSheetMode] = useState<"create" | "edit">("create");

  const openCreate = useCallback(() => {
    setEditingTag(null);
    setSheetMode("create");
    setSheetOpen(true);
  }, []);

  const openEdit = useCallback((tag: TagItem) => {
    setEditingTag(tag);
    setSheetMode("edit");
    setSheetOpen(true);
  }, []);

  const handleDelete = useCallback((tag: TagItem) => {
    router.delete(route("dashboard.posts.tags.destroy", tag.id), {
      preserveScroll: true,
      only: [],
      onSuccess: () => toast.add({ title: "Tag deleted.", type: "success" }),
    });
  }, []);

  const handleBulkDelete = useCallback((ids: number[]) => {
    router.post(
      route("dashboard.posts.tags.bulk"),
      { action: "delete", ids },
      {
        preserveScroll: true,
        only: [],
        onSuccess: () =>
          toast.add({
            title: `${ids.length} tag${ids.length === 1 ? "" : "s"} deleted.`,
            type: "success",
          }),
      },
    );
  }, []);

  const columns = useMemo(() => {
    const cols: ColumnDef<AppTableFeatures, TagItem>[] = [
      {
        accessorKey: "name",
        header: ({ column }) => <DataTableColumnHeader column={column} title="Tag" />,
        cell: ({ row }) => (
          <div className="flex items-center gap-2">
            <Tag className="size-4 shrink-0 text-muted-foreground" />
            <span className="font-medium">{row.original.name}</span>
          </div>
        ),
      },
      {
        accessorKey: "slug",
        header: ({ column }) => <DataTableColumnHeader column={column} title="Slug" />,
        cell: ({ row }) => (
          <code className="text-xs text-muted-foreground">{row.original.slug}</code>
        ),
      },
      {
        accessorKey: "description",
        header: ({ column }) => <DataTableColumnHeader column={column} title="Description" />,
        cell: ({ row }) => (
          <span className="max-w-[300px] truncate text-sm text-muted-foreground">
            {row.original.description ?? "\u{2014}"}
          </span>
        ),
      },
      {
        accessorKey: "posts_count",
        header: ({ column }) => <DataTableColumnHeader column={column} title="Posts" />,
        cell: ({ row }) => (
          <Badge variant={row.original.posts_count > 0 ? "secondary" : "outline"}>
            {row.original.posts_count}
          </Badge>
        ),
      },
      {
        id: "actions",
        cell: ({ row }) => (
          <div className="flex items-center gap-1">
            <Button variant="ghost" size="icon-xs" onClick={() => openEdit(row.original)}>
              <Pencil className="size-3.5" />
            </Button>
            <Button
              variant="ghost"
              size="icon-xs"
              className="text-destructive"
              onClick={() => handleDelete(row.original)}
            >
              <Trash2 className="size-3.5" />
            </Button>
          </div>
        ),
        enableSorting: false,
      },
    ];
    return cols;
  }, [openEdit, handleDelete]);

  return (
    <AuthenticatedLayout header="Post Tags">
      <Head title="Post Tags" />

      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <h2 className="text-lg font-semibold">Tags</h2>
          <Button onClick={openCreate}>
            <Plus className="mr-1 h-4 w-4" /> Add Tag
          </Button>
        </div>

        <DataTable
          columns={columns}
          data={tags}
          searchKey="name"
          searchPlaceholder="Search tags..."
          bulkActions={[
            {
              label: "Delete",
              value: "delete",
              destructive: true,
              confirmTitle: "Delete Tags",
              confirmDescription: "Are you sure you want to delete the selected tags?",
              confirmLabel: "Delete",
            },
          ]}
          onBulkAction={(action, ids) => {
            if (action === "delete") handleBulkDelete(ids);
          }}
          emptyState={
            <Empty>
              <EmptyHeader>
                <EmptyMedia variant="icon">
                  <Tag />
                </EmptyMedia>
                <EmptyTitle>No tags yet</EmptyTitle>
                <EmptyDescription>Create tags to organize your posts.</EmptyDescription>
              </EmptyHeader>
            </Empty>
          }
        />
      </div>

      <Sheet
        open={sheetOpen}
        onOpenChange={(open) => {
          setSheetOpen(open);
          if (!open) setEditingTag(null);
        }}
      >
        <SheetContent side="right" className="w-full sm:max-w-md">
          <SheetHeader>
            <SheetTitle>{sheetMode === "edit" ? "Edit Tag" : "Add Tag"}</SheetTitle>
            <SheetDescription>
              {sheetMode === "edit" ? "Update tag details." : "Create a new tag."}
            </SheetDescription>
          </SheetHeader>
          {sheetMode === "edit" && editingTag ? (
            <EditTagForm
              tag={editingTag}
              onSuccess={() => {
                setSheetOpen(false);
                setEditingTag(null);
              }}
            />
          ) : (
            <NewTagForm
              onSuccess={() => {
                setSheetOpen(false);
              }}
            />
          )}
        </SheetContent>
      </Sheet>
    </AuthenticatedLayout>
  );
}

function EditTagForm({ tag, onSuccess }: { tag: TagItem; onSuccess: () => void }) {
  const { data, setData, put, processing, errors } = useForm({
    name: tag.name,
    description: tag.description || "",
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    put(route("dashboard.posts.tags.update", tag.id), {
      preserveScroll: true,
      only: [],
      onSuccess: () => {
        onSuccess();
        toast.add({ title: "Tag updated.", type: "success" });
      },
    });
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-4 p-4">
      <div className="grid gap-2">
        <Label htmlFor="edit-tag-name">Name</Label>
        <Input
          id="edit-tag-name"
          value={data.name}
          onChange={(e) => setData("name", e.target.value)}
          required
        />
        {errors.name && <p className="text-xs text-destructive">{errors.name}</p>}
      </div>
      <div className="grid gap-2">
        <Label htmlFor="edit-tag-desc">Description</Label>
        <Textarea
          id="edit-tag-desc"
          rows={3}
          placeholder="Optional \u{2014} displayed on tag archive page"
          value={data.description}
          onChange={(e) => setData("description", e.target.value)}
        />
      </div>
      <Button type="submit" className="w-full" disabled={processing}>
        {processing ? "Saving..." : "Save"}
      </Button>
    </form>
  );
}

function NewTagForm({ onSuccess }: { onSuccess: () => void }) {
  const { data, setData, post, processing, errors, reset } = useForm({
    name: "",
    description: "",
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    post(route("dashboard.posts.tags.store"), {
      preserveScroll: true,
      only: [],
      onSuccess: () => {
        reset();
        onSuccess();
        toast.add({ title: "Tag created.", type: "success" });
      },
    });
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-4 p-4">
      <div className="grid gap-2">
        <Label htmlFor="new-tag-name">Name</Label>
        <Input
          id="new-tag-name"
          value={data.name}
          onChange={(e) => setData("name", e.target.value)}
          required
        />
        {errors.name && <p className="text-xs text-destructive">{errors.name}</p>}
      </div>
      <div className="grid gap-2">
        <Label htmlFor="new-tag-desc">Description</Label>
        <Textarea
          id="new-tag-desc"
          rows={3}
          placeholder="Optional \u{2014} displayed on tag archive page"
          value={data.description}
          onChange={(e) => setData("description", e.target.value)}
        />
      </div>
      <Button type="submit" className="w-full" disabled={processing}>
        {processing ? "Creating..." : "Create Tag"}
      </Button>
    </form>
  );
}
