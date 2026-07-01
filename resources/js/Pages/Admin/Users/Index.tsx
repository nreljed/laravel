import { Head, Link, router, usePage } from "@inertiajs/react";
import AdminLayout from "@/Layouts/AdminLayout";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/Components/ui/table";
import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Avatar, AvatarFallback } from "@/Components/ui/avatar";
import { getUserInitials, useAuth } from "@/hooks/use-auth";
import {
  Pagination,
  PaginationContent,
  PaginationItem,
  PaginationLink,
  PaginationNext,
  PaginationPrevious,
} from "@/Components/ui/pagination";
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
  Plus,
  Search,
  Pencil,
  Trash2,
  MoreHorizontal,
  LogIn,
  MailCheck,
  Send,
  Users,
} from "lucide-react";
import {
  Empty,
  EmptyContent,
  EmptyDescription,
  EmptyHeader,
  EmptyMedia,
  EmptyTitle,
} from "@/Components/ui/empty";
import { FormEventHandler, useState } from "react";

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
  roles: Role[];
  created_at: string;
}

interface PaginatedResponse {
  data: User[];
  current_page: number;
  last_page: number;
  from: number;
  to: number;
  total: number;
  links: Array<{ url: string | null; label: string; active: boolean }>;
}

interface PageProps {
  users: PaginatedResponse;
  roles: Role[];
  filters: { search?: string };
}

type ConfirmAction = "impersonate" | "verify-email" | "send-reset" | "delete";

export default function AdminUsersIndex() {
  const { props } = usePage();
  const { users, filters } = props as unknown as PageProps;
  const { isAdmin } = useAuth();
  const [search, setSearch] = useState(filters.search ?? "");
  const [confirmAction, setConfirmAction] = useState<{ type: ConfirmAction; user: User } | null>(
    null,
  );

  const handleSearch: FormEventHandler = (e) => {
    e.preventDefault();
    router.get(
      route("dashboard.admin.users.index"),
      { search },
      { preserveState: true, replace: true },
    );
  };

  const hasFilters = !!filters.search;

  if (users.data.length === 0 && !hasFilters) {
    return (
      <AdminLayout title="Users">
        <Head title="Users" />
        <div className="space-y-6">
          <div className="flex items-center justify-between">
            <div>
              <h2 className="text-3xl font-bold tracking-tight text-foreground">Users</h2>
              <p className="text-muted-foreground">Manage user accounts</p>
            </div>
            <Button asChild>
              <Link href={route("dashboard.admin.users.create")}>
                <Plus className="mr-1 size-4" /> Add User
              </Link>
            </Button>
          </div>
          <Empty>
            <EmptyHeader>
              <EmptyMedia variant="icon">
                <Users />
              </EmptyMedia>
              <EmptyTitle>No users found</EmptyTitle>
              <EmptyDescription>Create your first user to get started.</EmptyDescription>
            </EmptyHeader>
            <EmptyContent>
              <Button asChild>
                <Link href={route("dashboard.admin.users.create")}>
                  <Plus className="mr-1 size-4" /> Create User
                </Link>
              </Button>
            </EmptyContent>
          </Empty>
        </div>
      </AdminLayout>
    );
  }

  const executeAction = () => {
    if (!confirmAction) return;
    const { type, user } = confirmAction;
    setConfirmAction(null);

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
        router.delete(route("dashboard.admin.users.destroy", { user: user.id }));
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

  return (
    <AdminLayout title="Users">
      <Head title="Users" />

      <div className="flex items-center justify-between mb-4">
        <form onSubmit={handleSearch} className="flex items-center gap-2">
          <div className="relative">
            <Search className="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
            <Input
              placeholder="Search users..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="w-64 pl-8"
            />
          </div>
          <Button type="submit" variant="secondary" size="sm">
            Search
          </Button>
        </form>
        <Button asChild>
          <Link href={route("dashboard.admin.users.create")}>
            <Plus className="mr-1 size-4" /> Add User
          </Link>
        </Button>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>All Users ({users.total})</CardTitle>
        </CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>User</TableHead>
                <TableHead>Email</TableHead>
                <TableHead>Role</TableHead>
                <TableHead>Joined</TableHead>
                <TableHead>Status</TableHead>
                <TableHead className="w-16">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {users.data.map((user) => (
                <TableRow key={user.id}>
                  <TableCell>
                    <div className="flex items-center gap-3">
                      <Avatar className="size-8">
                        <AvatarFallback>{getUserInitials(user.name)}</AvatarFallback>
                      </Avatar>
                      <div className="flex flex-col">
                        <span className="font-medium">{user.name}</span>
                        <span className="text-xs text-muted-foreground font-mono">
                          {user.username}
                        </span>
                      </div>
                    </div>
                  </TableCell>
                  <TableCell className="text-muted-foreground">{user.email}</TableCell>
                  <TableCell>
                    {user.roles.map((role) => (
                      <Badge key={role.id} variant="secondary">
                        {role.name}
                      </Badge>
                    ))}
                    {user.roles.length === 0 && (
                      <span className="text-muted-foreground text-sm">—</span>
                    )}
                  </TableCell>
                  <TableCell className="text-muted-foreground text-sm">
                    {new Date(user.created_at).toLocaleDateString()}
                  </TableCell>
                  <TableCell>
                    {user.email_verified_at ? (
                      <Badge className="bg-green-600">Verified</Badge>
                    ) : (
                      <Badge variant="secondary">Unverified</Badge>
                    )}
                  </TableCell>
                  <TableCell>
                    <DropdownMenu>
                      <DropdownMenuTrigger asChild>
                        <Button variant="ghost" size="icon-xs">
                          <MoreHorizontal className="size-4" />
                        </Button>
                      </DropdownMenuTrigger>
                      <DropdownMenuContent align="end" className="w-48">
                        <DropdownMenuItem asChild>
                          <Link href={route("dashboard.admin.users.edit", { user: user.id })}>
                            <Pencil className="mr-2 size-4" />
                            Edit
                          </Link>
                        </DropdownMenuItem>

                        {isAdmin && (
                          <DropdownMenuItem
                            onClick={() => setConfirmAction({ type: "impersonate", user })}
                          >
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

                        <DropdownMenuItem
                          onClick={() => setConfirmAction({ type: "send-reset", user })}
                        >
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
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>

          {users.last_page > 1 && (
            <Pagination className="mt-4">
              <PaginationContent>
                {users.links.map((link, i) => {
                  if (link.label.includes("Previous")) {
                    return (
                      <PaginationItem key={i}>
                        {link.url ? (
                          <PaginationPrevious href={link.url} />
                        ) : (
                          <PaginationPrevious href="#" />
                        )}
                      </PaginationItem>
                    );
                  }
                  if (link.label.includes("Next")) {
                    return (
                      <PaginationItem key={i}>
                        {link.url ? (
                          <PaginationNext href={link.url} />
                        ) : (
                          <PaginationNext href="#" />
                        )}
                      </PaginationItem>
                    );
                  }
                  return (
                    <PaginationItem key={i}>
                      <PaginationLink href={link.url ?? "#"} isActive={link.active}>
                        {link.label}
                      </PaginationLink>
                    </PaginationItem>
                  );
                })}
              </PaginationContent>
            </Pagination>
          )}
        </CardContent>
      </Card>

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
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction
              onClick={executeAction}
              className={
                dialogConfig?.variant === "destructive"
                  ? "bg-destructive hover:bg-destructive/90"
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
