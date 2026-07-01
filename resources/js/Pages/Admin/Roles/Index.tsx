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
import { Pencil, Plus, Shield, Trash2 } from "lucide-react";
import { useState, useMemo } from "react";
import type { ColumnDef } from "@tanstack/react-table";

interface Role {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  users_count: number;
  permissions_count: number;
}

export default function AdminRolesIndex() {
  const { roles } = usePage().props as unknown as { roles: Role[] };
  const [deleteRole, setDeleteRole] = useState<Role | null>(null);

  const columns = useMemo(() => {
    const cols: ColumnDef<Role>[] = [
      {
        accessorKey: "name",
        header: ({ column }) => <DataTableColumnHeader column={column} title="Role" />,
        cell: ({ row }) => (
          <div className="flex items-center gap-2">
            <Shield className="size-4 text-muted-foreground" />
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
        accessorKey: "users_count",
        header: ({ column }) => <DataTableColumnHeader column={column} title="Users" />,
        cell: ({ row }) => <Badge variant="secondary">{row.original.users_count}</Badge>,
      },
      {
        accessorKey: "permissions_count",
        header: ({ column }) => <DataTableColumnHeader column={column} title="Permissions" />,
        cell: ({ row }) => <Badge variant="outline">{row.original.permissions_count}</Badge>,
      },
      {
        id: "actions",
        cell: ({ row }) => (
          <div className="flex items-center gap-1">
            <Button variant="ghost" size="icon-xs" asChild>
              <Link href={route("dashboard.admin.roles.edit", { role: row.original.id })}>
                <Pencil className="size-3.5" />
              </Link>
            </Button>
            {row.original.slug !== "admin" && (
              <Button
                variant="ghost"
                size="icon-xs"
                className="text-destructive"
                onClick={() => setDeleteRole(row.original)}
              >
                <Trash2 className="size-3.5" />
              </Button>
            )}
          </div>
        ),
        enableSorting: false,
      },
    ];
    return cols;
  }, []);

  const handleDelete = () => {
    if (!deleteRole) return;
    router.delete(route("dashboard.admin.roles.destroy", { role: deleteRole.id }));
    setDeleteRole(null);
  };

  return (
    <AdminLayout title="Roles">
      <Head title="Roles" />

      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <h2 className="text-3xl font-bold tracking-tight text-foreground">Roles</h2>
          <Button asChild>
            <Link href={route("dashboard.admin.roles.create")}>
              <Plus className="mr-1 size-4" /> Create Role
            </Link>
          </Button>
        </div>

        <DataTable
          columns={columns}
          data={roles}
          searchKey="name"
          searchPlaceholder="Search roles..."
          emptyState={
            <Empty>
              <EmptyHeader>
                <EmptyMedia variant="icon">
                  <Shield />
                </EmptyMedia>
                <EmptyTitle>No roles found</EmptyTitle>
                <EmptyDescription>Create your first role to get started.</EmptyDescription>
              </EmptyHeader>
            </Empty>
          }
        />
      </div>

      <AlertDialog
        open={!!deleteRole}
        onOpenChange={(open) => {
          if (!open) setDeleteRole(null);
        }}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete Role</AlertDialogTitle>
            <AlertDialogDescription>
              {deleteRole && deleteRole.users_count > 0
                ? `"${deleteRole.name}" has ${deleteRole.users_count} user(s) assigned. Deleting it will remove the role from all users.`
                : `Permanently delete the role "${deleteRole?.name}"? This action cannot be undone.`}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction
              onClick={handleDelete}
              className="bg-destructive hover:bg-destructive/90"
            >
              Delete Role
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </AdminLayout>
  );
}
