import { Head, router, usePage } from "@inertiajs/react";
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
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/Components/ui/select";
import {
  Pagination,
  PaginationContent,
  PaginationItem,
  PaginationLink,
  PaginationNext,
  PaginationPrevious,
} from "@/Components/ui/pagination";
import { Filter, ScrollText } from "lucide-react";
import {
  Empty,
  EmptyHeader,
  EmptyTitle,
  EmptyDescription,
  EmptyMedia,
} from "@/Components/ui/empty";
import { useState } from "react";

interface UserBrief {
  id: number;
  name: string;
  email: string;
}

interface AuditLog {
  id: number;
  action: string;
  user: UserBrief | null;
  ip: string;
  user_agent: string;
  context: Record<string, unknown> | null;
  created_at: string;
}

interface PaginatedLogs {
  data: AuditLog[];
  current_page: number;
  last_page: number;
  from: number;
  to: number;
  total: number;
  links: Array<{ url: string | null; label: string; active: boolean }>;
}

interface PageProps {
  logs: PaginatedLogs;
  actions: string[];
  users: UserBrief[];
  filters: { action?: string; user_id?: string; date_from?: string; date_to?: string };
}

const actionLabels: Record<string, string> = {
  login: "Login",
  logout: "Logout",
  password_change: "Password Change",
  password_reset: "Password Reset",
  email_change: "Email Change",
  account_delete: "Account Delete",
  profile_update: "Profile Update",
  booking_create: "Booking Created",
  booking_update: "Booking Updated",
  booking_cancel: "Booking Cancelled",
  booking_delete: "Booking Deleted",
  booking_guest_register: "Guest Registered",
  impersonation_start: "Impersonation Started",
  impersonation_stop: "Impersonation Stopped",
  role_assigned: "Role Assigned",
  role_revoked: "Role Revoked",
  permission_assigned: "Permission Assigned",
  permission_revoked: "Permission Revoked",
};

const actionColors: Record<string, string> = {
  login: "bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200",
  logout: "bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200",
  impersonation_start: "bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200",
  impersonation_stop: "bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200",
  password_change: "bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200",
  password_reset: "bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200",
  role_assigned: "bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200",
  role_revoked: "bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200",
  permission_assigned: "bg-teal-100 text-teal-800 dark:bg-teal-900 dark:text-teal-200",
  permission_revoked: "bg-teal-100 text-teal-800 dark:bg-teal-900 dark:text-teal-200",
};

export default function AdminAuditLogsIndex() {
  const { props } = usePage();
  const { logs, actions, users, filters } = props as unknown as PageProps;

  const [action, setAction] = useState(filters.action ?? "");
  const [userId, setUserId] = useState(filters.user_id ?? "");
  const [dateFrom, setDateFrom] = useState(filters.date_from ?? "");
  const [dateTo, setDateTo] = useState(filters.date_to ?? "");

  const applyFilters = () => {
    router.get(
      route("dashboard.admin.audit-logs.index"),
      {
        ...(action && { action }),
        ...(userId && { user_id: userId }),
        ...(dateFrom && { date_from: dateFrom }),
        ...(dateTo && { date_to: dateTo }),
      },
      { preserveState: true, replace: true },
    );
  };

  const clearFilters = () => {
    setAction("");
    setUserId("");
    setDateFrom("");
    setDateTo("");
    router.get(
      route("dashboard.admin.audit-logs.index"),
      {},
      { preserveState: true, replace: true },
    );
  };

  return (
    <AdminLayout title="Audit Logs">
      <Head title="Audit Logs" />

      <div className="flex flex-wrap items-center gap-3 mb-4">
        <Select value={action} onValueChange={setAction}>
          <SelectTrigger className="w-48">
            <SelectValue placeholder="All Actions" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="">All Actions</SelectItem>
            {actions.map((a) => (
              <SelectItem key={a} value={a}>
                {actionLabels[a] ?? a}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>

        <Select value={userId} onValueChange={setUserId}>
          <SelectTrigger className="w-48">
            <SelectValue placeholder="All Users" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="">All Users</SelectItem>
            {users.map((u) => (
              <SelectItem key={u.id} value={String(u.id)}>
                {u.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>

        <Input
          type="date"
          value={dateFrom}
          onChange={(e) => setDateFrom(e.target.value)}
          className="w-40"
          placeholder="From"
        />
        <Input
          type="date"
          value={dateTo}
          onChange={(e) => setDateTo(e.target.value)}
          className="w-40"
          placeholder="To"
        />

        <Button variant="secondary" size="sm" onClick={applyFilters}>
          <Filter className="mr-1 size-4" /> Filter
        </Button>
        <Button variant="ghost" size="sm" onClick={clearFilters}>
          Clear
        </Button>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Audit Logs ({logs.total})</CardTitle>
        </CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Time</TableHead>
                <TableHead>User</TableHead>
                <TableHead>Action</TableHead>
                <TableHead>IP</TableHead>
                <TableHead>Context</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {logs.data.map((log) => (
                <TableRow key={log.id}>
                  <TableCell className="text-xs whitespace-nowrap text-muted-foreground">
                    {new Date(log.created_at).toLocaleString()}
                  </TableCell>
                  <TableCell>
                    {log.user ? (
                      <span className="text-sm font-medium">{log.user.name}</span>
                    ) : (
                      <span className="text-sm text-muted-foreground">—</span>
                    )}
                  </TableCell>
                  <TableCell>
                    <Badge className={actionColors[log.action] ?? ""}>
                      {actionLabels[log.action] ?? log.action}
                    </Badge>
                  </TableCell>
                  <TableCell className="font-mono text-xs text-muted-foreground">
                    {log.ip}
                  </TableCell>
                  <TableCell className="text-xs text-muted-foreground max-w-xs truncate">
                    {log.context ? JSON.stringify(log.context) : "—"}
                  </TableCell>
                </TableRow>
              ))}
              {logs.data.length === 0 && (
                <TableRow>
                  <TableCell colSpan={5} className="py-12 text-center">
                    <Empty>
                      <EmptyHeader>
                        <EmptyMedia variant="icon">
                          <ScrollText />
                        </EmptyMedia>
                        <EmptyTitle>No audit logs found</EmptyTitle>
                        <EmptyDescription>
                          No records match your criteria. Try adjusting your filters.
                        </EmptyDescription>
                      </EmptyHeader>
                    </Empty>
                  </TableCell>
                </TableRow>
              )}
            </TableBody>
          </Table>

          {logs.last_page > 1 && (
            <Pagination className="mt-4">
              <PaginationContent>
                {logs.links.map((link, i) => {
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
    </AdminLayout>
  );
}
