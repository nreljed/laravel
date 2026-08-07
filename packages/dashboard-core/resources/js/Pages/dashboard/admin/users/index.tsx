import { Head, Link, router, usePage } from "@inertiajs/react";
import AdminLayout from "@/Layouts/AdminLayout";
import { DataTable } from "@/Components/ui/data-table";
import { DataTableColumnHeader } from "@/Components/ui/data-table-column-header";
import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
import { Avatar, AvatarImage, AvatarFallback } from "@/Components/ui/avatar";
import { getUserInitials, useAuth } from "@/hooks/use-auth";
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
  EmptyContent,
  EmptyDescription,
  EmptyHeader,
  EmptyMedia,
  EmptyTitle,
} from "@/Components/ui/empty";
import { Plus, Pencil, Trash2, MoreHorizontal, LogIn, MailCheck, Send, Users } from "lucide-react";
import { useState, useMemo } from "react";
import type { ColumnDef } from "@tanstack/react-table";
import type { AppTableFeatures } from "@/lib/table-features";

interface Role {
  id: number;
  name: string;
  slug: string;
}

interface User {
  id: number;
  username: string;
  name: string;
  email: string;
  email_verified_at: string | null;
  avatar_url: string | null;
  roles: Role[];
  created_at: string;
}

interface PageProps {
  users: User[];
  roles: Role[];
}

type ConfirmAction = "impersonate" | "verify-email" | "send-reset" | "delete";

export default function AdminUsersIndex() {
  const { users } = usePage().props as unknown as PageProps;
  const { isAdmin } = useAuth();
  const [confirmAction, setConfirmAction] = useState<{ type: ConfirmAction; user: User } | null>(
    null,
  );
  const [notifyDelete, setNotifyDelete] = useState(false);

  const executeAction = () => {
    if (!confirmAction) return;
    const { type, user } = confirmAction;
    setConfirmAction(null);
    setNotifyDelete(false);

    switch (type) {
      case "impersonate": {
        router.post(route("dashboard.admin.impersonate.start", { user: user.id }));
        break;
      }
      case "verify-email": {
        router.post(route("dashboard.admin.users.verify-email", { user: user.id }));
        break;
      }
      case "send-reset": {
        router.post(route("dashboard.admin.users.send-reset-link", { user: user.id }));
        break;
      }
      case "delete": {
        router.delete(route("dashboard.admin.users.destroy", { user: user.id }), {
          data: { notify_user: notifyDelete },
        });
        break;
      }
    }
  };

  const dialogConfig = (() => {
    if (!confirmAction) return null;
    const { type, user } = confirmAction;
    switch (type) {
      case "impersonate": {
        return {
          title: "Impersonate User",
          description: `You are about to log in as ${user.name} (${user.email}). You will act on their behalf until you stop impersonating.`,
          confirmLabel: "Login as User",
          variant: "default" as const,
        };
      }
      case "verify-email": {
        return {
          title: "Verify Email",
          description: `Mark ${user.name}'s email (${user.email}) as verified?`,
          confirmLabel: "Verify Email",
          variant: "default" as const,
        };
      }
      case "send-reset": {
        return {
          title: "Send Password Reset Link",
          description: `Send a password reset email to ${user.name} at ${user.email}?`,
          confirmLabel: "Send Reset Link",
          variant: "default" as const,
        };
      }
      case "delete": {
        return {
          title: "Delete User",
          description: `Permanently delete "${user.name}" (${user.email})? This action cannot be undone.`,
          confirmLabel: "Delete",
          variant: "destructive" as const,
        };
      }
    }
  })();

  const columns = useMemo(() => {
    const cols: ColumnDef<AppTableFeatures, User>[] = [
      {
        accessorKey: "id",
        header: ({ column }) => <DataTableColumnHeader column={column} title="ID" />,
        cell: ({ row }) => (
          <span className="text-muted-foreground font-mono text-xs">{row.original.id}</span>
        ),
      },
      {
        id: "avatar",
        header: ({ column }) => <DataTableColumnHeader column={column} title="Avatar" />,
        cell: ({ row }) => (
          <Avatar className="size-8">
            {row.original.avatar_url ? (
              <AvatarImage src={row.original.avatar_url} alt={row.original.name} />
            ) : null}
            <AvatarFallback>{getUserInitials(row.original.name)}</AvatarFallback>
          </Avatar>
        ),
        enableSorting: false,
      },
      {
        accessorKey: "name",
        header: ({ column }) => <DataTableColumnHeader column={column} title="User" />,
        cell: ({ row }) => <span className="font-medium">{row.original.name}</span>,
      },
      {
        accessorKey: "username",
        header: ({ column }) => <DataTableColumnHeader column={column} title="Username" />,
        cell: ({ row }) => (
          <span className="text-xs text-muted-foreground font-mono">{row.original.username}</span>
        ),
      },
      {
        accessorKey: "email",
        header: ({ column }) => <DataTableColumnHeader column={column} title="Email" />,
        cell: ({ row }) => <span className="text-muted-foreground">{row.original.email}</span>,
      },
      {
        accessorKey: "role",
        accessorFn: (row) => row.roles.map((r) => r.name).join(", "),
        header: ({ column }) => <DataTableColumnHeader column={column} title="Role" />,
        cell: ({ row }) =>
          row.original.roles.length > 0 ? (
            row.original.roles.map((role) => (
              <Badge key={role.id} variant="secondary">
                {role.name}
              </Badge>
            ))
          ) : (
            <span className="text-muted-foreground text-sm">—</span>
          ),
      },
      {
        accessorKey: "created_at",
        header: ({ column }) => <DataTableColumnHeader column={column} title="Joined" />,
        cell: ({ row }) => (
          <span className="text-muted-foreground text-sm">
            {new Date(row.original.created_at).toLocaleDateString()}
          </span>
        ),
      },
      {
        accessorKey: "email_verified_at",
        header: ({ column }) => <DataTableColumnHeader column={column} title="Status" />,
        cell: ({ row }) =>
          row.original.email_verified_at ? (
            <Badge variant="success">Verified</Badge>
          ) : (
            <Badge variant="warning">Unverified</Badge>
          ),
      },
      {
        id: "actions",
        cell: ({ row }) => {
          const user = row.original;
          return (
            <DropdownMenu>
              <DropdownMenuTrigger render={<Button variant="ghost" size="icon-xs" />}>
                <MoreHorizontal className="size-4" />
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" className="w-48">
                <DropdownMenuItem
                  render={<Link href={route("dashboard.admin.users.edit", { user: user.id })} />}
                >
                  <Pencil className="mr-2 size-4" />
                  Edit
                </DropdownMenuItem>

                {isAdmin && (
                  <DropdownMenuItem onClick={() => setConfirmAction({ type: "impersonate", user })}>
                    <LogIn className="mr-2 size-4" />
                    Login as User
                  </DropdownMenuItem>
                )}

                <DropdownMenuSeparator />

                {!user.email_verified_at && (
                  <DropdownMenuItem
                    onClick={() => setConfirmAction({ type: "verify-email", user })}
                  >
                    <MailCheck className="mr-2 size-4" />
                    Verify Email
                  </DropdownMenuItem>
                )}

                <DropdownMenuItem onClick={() => setConfirmAction({ type: "send-reset", user })}>
                  <Send className="mr-2 size-4" />
                  Send Reset Link
                </DropdownMenuItem>

                <DropdownMenuSeparator />

                <DropdownMenuItem
                  className="text-destructive"
                  onClick={() => setConfirmAction({ type: "delete", user })}
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
  }, [isAdmin]);

  return (
    <AdminLayout title="Users">
      <Head title="Users" />

      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <h2 className="text-3xl font-bold tracking-tight text-foreground">Users</h2>
          <Link href={route("dashboard.admin.users.create")}>
            <Button>
              <Plus className="mr-1 size-4" /> Add User
            </Button>
          </Link>
        </div>

        <DataTable
          columns={columns}
          data={users}
          searchKey="name"
          searchPlaceholder="Search by name..."
          pageSize={20}
          bulkActions={[
            {
              label: "Delete",
              value: "delete",
              destructive: true,
              confirmTitle: "Delete Users",
              confirmDescription: "Permanently delete the selected users? This cannot be undone.",
              confirmLabel: "Delete",
            },
            {
              label: "Verify Email",
              value: "verify-email",
              confirmTitle: "Verify Email",
              confirmDescription: "Mark selected users' email as verified?",
              confirmLabel: "Verify",
            },
            {
              label: "Send Reset Link",
              value: "send-reset",
              confirmTitle: "Send Password Reset",
              confirmDescription: "Send a password reset email to selected users?",
              confirmLabel: "Send",
            },
          ]}
          onBulkAction={(action, ids) => {
            switch (action) {
              case "delete": {
                router.post(route("dashboard.admin.users.bulk.delete"), { ids });
                break;
              }
              case "verify-email": {
                router.post(route("dashboard.admin.users.bulk.verify-email"), { ids });
                break;
              }
              case "send-reset": {
                router.post(route("dashboard.admin.users.bulk.send-reset"), { ids });
                break;
              }
            }
          }}
          emptyState={
            <Empty>
              <EmptyHeader>
                <EmptyMedia variant="icon">
                  <Users />
                </EmptyMedia>
                <EmptyTitle>No users found</EmptyTitle>
                <EmptyDescription>Create your first user to get started.</EmptyDescription>
              </EmptyHeader>
              <EmptyContent>
                <Link href={route("dashboard.admin.users.create")}>
                  <Button>
                    <Plus className="mr-1 size-4" /> Create User
                  </Button>
                </Link>
              </EmptyContent>
            </Empty>
          }
        />
      </div>

      <AlertDialog
        open={!!confirmAction}
        onOpenChange={(open) => {
          if (!open) setConfirmAction(null);
        }}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{dialogConfig?.title}</AlertDialogTitle>
            <AlertDialogDescription>{dialogConfig?.description}</AlertDialogDescription>
          </AlertDialogHeader>
          {confirmAction?.type === "delete" && (
            <div className="flex items-start gap-3 px-2">
              <input
                id="notify_delete"
                type="checkbox"
                className="mt-0.5 size-4 accent-foreground"
                checked={notifyDelete}
                onChange={(e) => setNotifyDelete(e.target.checked)}
              />
              <label
                htmlFor="notify_delete"
                className="text-sm text-muted-foreground leading-snug cursor-pointer"
              >
                <span className="font-medium text-foreground">Notify user via email</span>
                <br />
                Send a notification email to inform them their account has been deleted.
              </label>
            </div>
          )}
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction
              onClick={executeAction}
              className={
                dialogConfig?.variant === "destructive"
                  ? "bg-destructive text-destructive-foreground hover:bg-destructive/90"
                  : undefined
              }
            >
              {dialogConfig?.confirmLabel}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </AdminLayout>
  );
}
