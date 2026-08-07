import { Head, Link, router, usePage } from "@inertiajs/react";
import AdminLayout from "@/Layouts/AdminLayout";
import { DataTable } from "@/Components/ui/data-table";
import { DataTableColumnHeader } from "@/Components/ui/data-table-column-header";
import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/Components/ui/dropdown-menu";
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
import { Pencil, Plus, Shield, Trash2, MoreHorizontal, Copy, Crown } from "lucide-react";
import { useState, useMemo } from "react";
import type { ColumnDef } from "@tanstack/react-table";
import type { AppTableFeatures } from "@/lib/table-features";

interface Role {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  users_count: number;
  permissions_count: number;
  is_system: boolean;
  is_default: boolean;
  updated_at: string | null;
}

export default function AdminRolesIndex() {
  const { roles } = usePage().props as unknown as { roles: Role[] };
  const [deleteRole, setDeleteRole] = useState<Role | null>(null);

  const deleteDisabledReason = (role: Role): string | null => {
    if (role.is_system) return "System roles are managed by config and cannot be deleted";
    if (role.is_default) return "The default registration role cannot be deleted";
    if (role.users_count > 0)
      return `Reassign or remove ${role.users_count} user(s) before deleting`;
    return null;
  };

  const handleDuplicate = (role: Role) => {
    router.post(route("dashboard.admin.roles.duplicate", { role: role.id }));
  };

  const columns = useMemo(() => {
    const cols: ColumnDef<AppTableFeatures, Role>[] = [
      {
        accessorKey: "name",
        header: ({ column }) => <DataTableColumnHeader column={column} title="Role" />,
        cell: ({ row }) => (
          <div className="flex items-center gap-2">
            <Shield className="size-4 text-muted-foreground shrink-0" />
            <span className="font-medium">{row.original.name}</span>
            {row.original.is_default && (
              <Badge variant="secondary" className="gap-1">
                <Crown className="size-3" /> Default
              </Badge>
            )}
            {row.original.is_system && (
              <Badge variant="outline" className="text-muted-foreground">
                System
              </Badge>
            )}
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
          <span
            className="text-muted-foreground text-sm max-w-[260px] truncate inline-block align-middle"
            title={row.original.description ?? undefined}
          >
            {row.original.description ?? "—"}
          </span>
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
        accessorKey: "updated_at",
        header: ({ column }) => <DataTableColumnHeader column={column} title="Updated" />,
        cell: ({ row }) => (
          <span className="text-muted-foreground text-sm">
            {row.original.updated_at ? new Date(row.original.updated_at).toLocaleDateString() : "—"}
          </span>
        ),
        enableSorting: false,
      },
      {
        id: "actions",
        cell: ({ row }) => {
          const role = row.original;
          const reason = deleteDisabledReason(role);

          return (
            <DropdownMenu>
              <DropdownMenuTrigger render={<Button variant="ghost" size="icon-xs" />}>
                <MoreHorizontal className="size-4" />
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" className="w-48">
                <DropdownMenuItem
                  render={<Link href={route("dashboard.admin.roles.edit", { role: role.id })} />}
                >
                  <Pencil className="mr-2 size-4" />
                  Edit
                </DropdownMenuItem>

                <DropdownMenuItem onClick={() => handleDuplicate(role)}>
                  <Copy className="mr-2 size-4" />
                  Duplicate
                </DropdownMenuItem>

                <DropdownMenuSeparator />

                <DropdownMenuItem
                  className={reason === null ? "text-destructive" : undefined}
                  disabled={reason !== null}
                  title={reason ?? undefined}
                  onClick={() => {
                    if (reason === null) setDeleteRole(role);
                  }}
                >
                  <Trash2 className="mr-2 size-4" />
                  Delete
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          );
        },
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
          <Link href={route("dashboard.admin.roles.create")}>
            <Button>
              <Plus className="mr-1 size-4" /> Create Role
            </Button>
          </Link>
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
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
            >
              Delete Role
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </AdminLayout>
  );
}
