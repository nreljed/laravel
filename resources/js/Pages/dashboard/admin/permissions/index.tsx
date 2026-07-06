import { Head, Link, router, usePage } from "@inertiajs/react";
import AdminLayout from "@/Layouts/AdminLayout";
import { DataTable } from "@/Components/ui/data-table";
import { DataTableColumnHeader } from "@/Components/ui/data-table-column-header";
import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/Components/ui/alert-dialog";
import {
  Empty,
  EmptyHeader,
  EmptyTitle,
  EmptyDescription,
  EmptyMedia,
} from "@/Components/ui/empty";
import { Pencil, Plus, ShieldCheck, Trash2, Shield } from "lucide-react";
import { useState, useMemo } from "react";
import type { ColumnDef } from "@tanstack/react-table";

interface Permission {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  guard_name: string;
  roles_count: number;
  created_at: string;
}

export default function AdminPermissionsIndex() {
  const { permissions } = usePage().props as unknown as { permissions: Permission[] };
  const [deletePerm, setDeletePerm] = useState<Permission | null>(null);

  const columns = useMemo(() => {
    const cols: ColumnDef<Permission>[] = [
      {
        accessorKey: "name",
        header: ({ column }) => <DataTableColumnHeader column={column} title="Permission" />,
        cell: ({ row }) => (
          <div className="flex items-center gap-2">
            <ShieldCheck className="size-4 text-muted-foreground" />
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
          <span className="text-muted-foreground text-sm">{row.original.description ?? "—"}</span>
        ),
      },
      {
        accessorKey: "roles_count",
        header: ({ column }) => <DataTableColumnHeader column={column} title="Roles" />,
        cell: ({ row }) => <Badge variant="secondary">{row.original.roles_count}</Badge>,
      },
      {
        id: "actions",
        cell: ({ row }) => (
          <div className="flex items-center gap-1">
            <Button variant="ghost" size="icon-xs" asChild>
              <Link
                href={route("dashboard.admin.permissions.edit", { permission: row.original.id })}
              >
                <Pencil className="size-3.5" />
              </Link>
            </Button>
            <Button
              variant="ghost"
              size="icon-xs"
              className="text-destructive"
              onClick={() => setDeletePerm(row.original)}
            >
              <Trash2 className="size-3.5" />
            </Button>
          </div>
        ),
        enableSorting: false,
      },
    ];
    return cols;
  }, []);

  const handleDelete = () => {
    if (!deletePerm) return;
    router.delete(route("dashboard.admin.permissions.destroy", { permission: deletePerm.id }));
    setDeletePerm(null);
  };

  return (
    <AdminLayout title="Permissions">
      <Head title="Permissions" />

      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <h2 className="text-3xl font-bold tracking-tight text-foreground">Permissions</h2>
          <Button asChild>
            <Link href={route("dashboard.admin.permissions.create")}>
              <Plus className="mr-1 size-4" /> Create Permission
            </Link>
          </Button>
        </div>

        <DataTable
          columns={columns}
          data={permissions}
          searchKey="slug"
          searchPlaceholder="Search permissions..."
          emptyState={
            <Empty>
              <EmptyHeader>
                <EmptyMedia variant="icon">
                  <Shield />
                </EmptyMedia>
                <EmptyTitle>No permissions found</EmptyTitle>
                <EmptyDescription>No permissions match your criteria.</EmptyDescription>
              </EmptyHeader>
            </Empty>
          }
        />
      </div>

      <AlertDialog
        open={!!deletePerm}
        onOpenChange={(open) => {
          if (!open) setDeletePerm(null);
        }}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete Permission</AlertDialogTitle>
            <AlertDialogDescription>
              Permanently delete "{deletePerm?.name}" ({deletePerm?.slug})? This action cannot be
              undone. Roles with this permission will lose access.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction
              onClick={handleDelete}
              className="bg-destructive hover:bg-destructive/90"
            >
              Delete Permission
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </AdminLayout>
  );
}
