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
import { DateRangePicker } from "@/Components/shared/date-range-picker";
import {
  Filter,
  ScrollText,
  ChevronDown,
  ChevronRight,
  Download,
  Search,
  ShieldAlert,
  Info,
} from "lucide-react";
import {
  Empty,
  EmptyHeader,
  EmptyTitle,
  EmptyDescription,
  EmptyMedia,
} from "@/Components/ui/empty";
import { useState } from "react";
import { cn } from "@/lib/utils";
import { Input } from "@/Components/ui/input";

interface RoleBrief {
  slug: string;
  name: string;
}

interface UserBrief {
  id: number;
  name: string;
  email: string;
  username: string | null;
  display_name: string | null;
  roles?: RoleBrief[];
}

interface TargetBrief extends UserBrief {
  deleted?: boolean;
}

interface AuditLog {
  id: number;
  action: string;
  severity: string;
  description: string;
  user: UserBrief | null;
  target: TargetBrief | null;
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

interface LogStats {
  total: number;
  today: number;
  critical: number;
}

interface PageProps {
  logs: PaginatedLogs;
  actions: string[];
  users: UserBrief[];
  stats: LogStats;
  filters: {
    action?: string;
    severity?: string;
    user_id?: string;
    search?: string;
    date_from?: string;
    date_to?: string;
    per_page?: string;
  };
}

const actionLabels: Record<string, string> = {
  login: "Login",
  logout: "Logout",
  password_change: "Password Change",
  password_reset: "Password Reset",
  email_change: "Email Change",
  account_created: "Account Created",
  account_delete: "Account Delete",
  email_verified: "Email Verified",
  password_reset_sent: "Password Reset Sent",
  settings_changed: "Settings Changed",
  profile_update: "Profile Update",
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
  password_reset_sent: "bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200",
  email_change: "bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200",
  email_verified: "bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200",
  account_created: "bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200",
  account_delete: "bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200",
  settings_changed: "bg-slate-100 text-slate-800 dark:bg-slate-900 dark:text-slate-200",
  role_assigned: "bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200",
  role_revoked: "bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200",
  permission_assigned: "bg-teal-100 text-teal-800 dark:bg-teal-900 dark:text-teal-200",
  permission_revoked: "bg-teal-100 text-teal-800 dark:bg-teal-900 dark:text-teal-200",
};

const severityColors: Record<string, string> = {
  critical: "bg-red-500",
  warning: "bg-amber-500",
  info: "bg-blue-500",
};

const severityLabels: Record<string, string> = {
  critical: "Critical",
  warning: "Warning",
  info: "Info",
};

function userLabel(u: {
  name: string;
  display_name?: string | null;
  username?: string | null;
}): string {
  if (u.display_name) return u.display_name;
  if (u.username) return `@${u.username}`;
  return u.name;
}

function DetailRow({ log }: { log: AuditLog }) {
  const [open, setOpen] = useState(false);

  const sections: Array<{ label: string; rows: Array<{ label: string; value: string }> }> = [];

  if (log.user) {
    const actor: Array<{ label: string; value: string }> = [
      { label: "Name", value: log.user.name },
      { label: "Email", value: log.user.email },
    ];
    if (log.user.username) actor.push({ label: "Username", value: log.user.username });
    if (log.user.display_name) actor.push({ label: "Display Name", value: log.user.display_name });
    if (log.user.roles && log.user.roles.length > 0) {
      actor.push({ label: "Roles", value: log.user.roles.map((r) => r.name).join(", ") });
    }
    sections.push({ label: "Actor", rows: actor });
  }

  if (log.target) {
    const target: Array<{ label: string; value: string }> = [
      {
        label: "Name",
        value: log.target.deleted ? `${log.target.name} [deleted]` : log.target.name,
      },
      { label: "Email", value: log.target.email ?? "—" },
    ];
    if (log.target.username) target.push({ label: "Username", value: log.target.username });
    if (log.target.display_name)
      target.push({ label: "Display Name", value: log.target.display_name });
    if (log.target.roles && log.target.roles.length > 0) {
      target.push({ label: "Roles", value: log.target.roles.map((r) => r.name).join(", ") });
    }
    sections.push({ label: "Target", rows: target });
  }

  const request: Array<{ label: string; value: string }> = [
    { label: "IP Address", value: log.ip },
    { label: "User Agent", value: log.user_agent || "—" },
  ];
  sections.push({ label: "Request", rows: request });

  if (log.context) {
    const ctxLabel: Record<string, string> = {
      role_id: "Role ID",
      role_slug: "Role",
      permission_ids: "Permission IDs",
      changed_fields: "Changed Fields",
      changed_keys: "Changed Settings",
      notified: "Notified",
      target_user_ids: "Affected User IDs",
    };
    const skip = new Set([
      "target_user_id",
      "target_name",
      "target_email",
      "target_username",
      "target_display_name",
      "role",
      "permission_ids",
    ]);
    const changes: Array<{ label: string; value: string }> = [];
    for (const [key, val] of Object.entries(log.context)) {
      if (skip.has(key)) continue;
      if (key === "permission_ids" && Array.isArray(val) && val.length === 0) continue;
      changes.push({
        label:
          ctxLabel[key] ?? key.replaceAll("_", " ").replaceAll(/\b\w/g, (c) => c.toUpperCase()),
        value: typeof val === "object" ? JSON.stringify(val) : String(val),
      });
    }
    if (changes.length > 0) {
      sections.push({ label: "Changes", rows: changes });
    }
  }

  return (
    <>
      <TableRow className="cursor-pointer hover:bg-muted/50" onClick={() => setOpen(!open)}>
        <TableCell className="text-xs whitespace-nowrap text-muted-foreground">
          <div className="flex items-center gap-1.5">
            <span
              className={cn(
                "inline-block size-1.5 rounded-full shrink-0",
                severityColors[log.severity] ?? "bg-blue-500",
              )}
            />
            {new Date(log.created_at).toLocaleDateString()}
          </div>
        </TableCell>
        <TableCell className="text-xs whitespace-nowrap text-muted-foreground">
          {new Date(log.created_at).toLocaleTimeString()}
        </TableCell>
        <TableCell className="text-xs max-w-[140px]">
          {log.user ? (
            <span className="font-medium truncate">{userLabel(log.user)}</span>
          ) : (
            <span className="text-sm text-muted-foreground">—</span>
          )}
        </TableCell>
        <TableCell className="text-xs text-muted-foreground">
          {log.user
            ? (() => {
                const roles = (log.user.roles ?? []).filter((r) => r.slug !== "user");
                return roles.length > 0 ? roles.map((r) => r.name).join(", ") : "—";
              })()
            : "—"}
        </TableCell>
        <TableCell>
          <Badge className={actionColors[log.action] ?? ""}>
            {actionLabels[log.action] ?? log.action}
          </Badge>
        </TableCell>
        <TableCell className="text-sm max-w-md">
          <div className="flex items-center gap-1">
            <span className="truncate">{log.description}</span>
            <button className="shrink-0 text-muted-foreground hover:text-foreground">
              {open ? <ChevronDown className="size-3" /> : <ChevronRight className="size-3" />}
            </button>
          </div>
        </TableCell>
        <TableCell className="text-xs max-w-[180px]">
          {log.target ? (
            <div className="flex items-center gap-1.5">
              <span
                className={cn(
                  "font-medium truncate",
                  log.target.deleted && "italic text-muted-foreground",
                )}
              >
                {userLabel(log.target)}
              </span>
              {log.target.deleted && (
                <Badge variant="outline" className="text-[10px] leading-none px-1 py-0 shrink-0">
                  deleted
                </Badge>
              )}
            </div>
          ) : (
            <span className="text-sm text-muted-foreground">—</span>
          )}
        </TableCell>
        <TableCell className="font-mono text-xs text-muted-foreground">{log.ip}</TableCell>
      </TableRow>
      {open && (
        <TableRow>
          <TableCell colSpan={8} className="bg-muted/30 p-0">
            <div className="px-6 py-3 space-y-4 text-sm">
              {sections.length === 0 ? (
                <span className="text-muted-foreground">No additional details</span>
              ) : (
                sections.map((section) => (
                  <div key={section.label}>
                    <div className="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-1.5">
                      {section.label}
                    </div>
                    <div className="grid grid-cols-[120px_1fr] gap-x-4 gap-y-1">
                      {section.rows.map((r) => (
                        <div key={r.label} className="contents">
                          <span className="text-muted-foreground">{r.label}</span>
                          <span className="font-mono text-xs break-all">{r.value}</span>
                        </div>
                      ))}
                    </div>
                  </div>
                ))
              )}
            </div>
          </TableCell>
        </TableRow>
      )}
    </>
  );
}

export default function AdminAuditLogsIndex() {
  const { props } = usePage();
  const { logs, actions, users, stats, filters } = props as unknown as PageProps;

  const [action, setAction] = useState(filters.action ?? "");
  const [severity, setSeverity] = useState(filters.severity ?? "");
  const [userId, setUserId] = useState(filters.user_id ?? "");
  const [search, setSearch] = useState(filters.search ?? "");
  const [dateFrom, setDateFrom] = useState(filters.date_from ?? "");
  const [dateTo, setDateTo] = useState(filters.date_to ?? "");
  const [perPage, setPerPage] = useState(filters.per_page ?? "50");

  const applyFilters = () => {
    router.get(
      route("dashboard.admin.audit-logs.index"),
      {
        ...(action && { action }),
        ...(severity && { severity }),
        ...(userId && { user_id: userId }),
        ...(search && { search }),
        ...(dateFrom && { date_from: dateFrom }),
        ...(dateTo && { date_to: dateTo }),
        ...(perPage && perPage !== "50" && { per_page: perPage }),
      },
      { preserveState: true, replace: true },
    );
  };

  const clearFilters = () => {
    setAction("");
    setSeverity("");
    setUserId("");
    setSearch("");
    setDateFrom("");
    setDateTo("");
    setPerPage("50");
    router.get(
      route("dashboard.admin.audit-logs.index"),
      {},
      { preserveState: true, replace: true },
    );
  };

  const filtersActive =
    action || severity || userId || search || dateFrom || dateTo || perPage !== "50";

  return (
    <AdminLayout title="Audit Logs">
      <Head title="Audit Logs" />

      <div className="grid grid-cols-3 gap-4 mb-6">
        <Card>
          <CardContent className="flex items-center gap-3 pt-6">
            <div className="rounded-lg bg-blue-100 dark:bg-blue-900 p-2.5">
              <ScrollText className="size-5 text-blue-700 dark:text-blue-300" />
            </div>
            <div>
              <p className="text-2xl font-bold">{stats.total.toLocaleString()}</p>
              <p className="text-xs text-muted-foreground">Total Events</p>
            </div>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="flex items-center gap-3 pt-6">
            <div className="rounded-lg bg-green-100 dark:bg-green-900 p-2.5">
              <Info className="size-5 text-green-700 dark:text-green-300" />
            </div>
            <div>
              <p className="text-2xl font-bold">{stats.today.toLocaleString()}</p>
              <p className="text-xs text-muted-foreground">Today</p>
            </div>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="flex items-center gap-3 pt-6">
            <div className="rounded-lg bg-red-100 dark:bg-red-900 p-2.5">
              <ShieldAlert className="size-5 text-red-700 dark:text-red-300" />
            </div>
            <div>
              <p className="text-2xl font-bold">{stats.critical.toLocaleString()}</p>
              <p className="text-xs text-muted-foreground">Critical Events</p>
            </div>
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <div className="flex flex-wrap items-center gap-3">
            <CardTitle>Audit Logs ({logs.total})</CardTitle>
            <div className="flex items-center gap-2 ml-auto">
              <div className="relative">
                <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 size-4 text-muted-foreground" />
                <Input
                  placeholder="Search logs..."
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                  onKeyDown={(e) => e.key === "Enter" && applyFilters()}
                  className="pl-8 h-9 w-56 text-sm"
                />
              </div>

              <Select value={severity} onValueChange={(value) => setSeverity(value ?? "")}>
                <SelectTrigger className="w-32 h-9 text-xs">
                  <SelectValue placeholder="Severity">
                    {(v: string | null) => (v ? (severityLabels[v] ?? v) : "All Severity")}
                  </SelectValue>
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="">All Severity</SelectItem>
                  {["critical", "warning", "info"].map((s) => (
                    <SelectItem key={s} value={s}>
                      <div className="flex items-center gap-2">
                        <span
                          className={cn("inline-block size-2 rounded-full", severityColors[s])}
                        />
                        {severityLabels[s]}
                      </div>
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>

              <Select value={action} onValueChange={(value) => setAction(value ?? "")}>
                <SelectTrigger className="w-40 h-9 text-xs">
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

              <Select value={userId} onValueChange={(value) => setUserId(value ?? "")}>
                <SelectTrigger className="w-40 h-9 text-xs">
                  <SelectValue placeholder="All Users">
                    {(value: string | null) =>
                      value ? userLabel(users.find((u) => String(u.id) === value)!) : "All Users"
                    }
                  </SelectValue>
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="">All Users</SelectItem>
                  {users.map((u) => (
                    <SelectItem key={u.id} value={String(u.id)}>
                      {u.name}
                      {u.display_name ? ` (${u.display_name})` : ""}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>

              <DateRangePicker
                value={{ from: dateFrom, to: dateTo }}
                onChange={({ from, to }) => {
                  setDateFrom(from);
                  setDateTo(to);
                }}
              />

              <Button variant="secondary" size="sm" onClick={applyFilters}>
                <Filter className="mr-1 size-4" /> Filter
              </Button>
              {filtersActive && (
                <Button variant="ghost" size="sm" onClick={clearFilters}>
                  Clear
                </Button>
              )}

              <div className="flex items-center gap-1 border-l pl-3 ml-1">
                <Select value={perPage} onValueChange={(value) => setPerPage(value ?? "50")}>
                  <SelectTrigger className="w-20 h-9 text-xs">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {["25", "50", "100"].map((n) => (
                      <SelectItem key={n} value={n}>
                        {n}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <span className="text-xs text-muted-foreground">per page</span>
              </div>

              <Button
                variant="outline"
                size="sm"
                onClick={() => {
                  const params = new URLSearchParams();
                  if (action) params.set("action", action);
                  if (severity) params.set("severity", severity);
                  if (userId) params.set("user_id", userId);
                  if (search) params.set("search", search);
                  if (dateFrom) params.set("date_from", dateFrom);
                  if (dateTo) params.set("date_to", dateTo);
                  const qs = params.toString();
                  location.assign(
                    route("dashboard.admin.audit-logs.export") + (qs ? `?${qs}` : ""),
                  );
                }}
              >
                <Download className="mr-1 size-4" /> Export CSV
              </Button>
            </div>
          </div>
        </CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Date</TableHead>
                <TableHead>Time</TableHead>
                <TableHead>Actor</TableHead>
                <TableHead>Role</TableHead>
                <TableHead>Action</TableHead>
                <TableHead>Event</TableHead>
                <TableHead>Target</TableHead>
                <TableHead>IP</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {logs.data.map((log) => (
                <DetailRow key={log.id} log={log} />
              ))}
              {logs.data.length === 0 && (
                <TableRow>
                  <TableCell colSpan={8} className="py-12 text-center">
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
            <div className="flex items-center justify-between mt-4">
              <p className="text-xs text-muted-foreground">
                Showing {logs.from}–{logs.to} of {logs.total}
              </p>
              <Pagination>
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
            </div>
          )}
        </CardContent>
      </Card>
    </AdminLayout>
  );
}
