import { Head, router, usePage } from "@inertiajs/react";
import AdminLayout from "../../../../Layouts/AdminLayout";
import { Card, CardContent } from "../../../../Components/ui/card";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "../../../../Components/ui/table";
import { StatusBadge } from "../../../../Components/ui/status-badge";
import { Button } from "../../../../Components/ui/button";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "../../../../Components/ui/select";
import {
  Pagination,
  PaginationContent,
  PaginationItem,
  PaginationLink,
  PaginationNext,
  PaginationPrevious,
} from "../../../../Components/ui/pagination";
import {
  Empty,
  EmptyHeader,
  EmptyTitle,
  EmptyDescription,
  EmptyMedia,
} from "../../../../Components/ui/empty";
import { Input } from "../../../../Components/ui/input";
import { Mail, Inbox, Search, RotateCcw, ChevronRight } from "lucide-react";
import { useState } from "react";
import { cn } from "../../../../lib/utils";

interface ContactSubmission {
  id: number;
  name: string;
  email: string;
  subject: string | null;
  excerpt: string;
  status: string;
  read_at: string | null;
  created_at: string;
}

interface PaginatedSubmissions {
  data: ContactSubmission[];
  current_page: number;
  last_page: number;
  from: number;
  to: number;
  total: number;
  links: Array<{ url: string | null; label: string; active: boolean }>;
}

interface PageProps {
  submissions: PaginatedSubmissions;
  stats: { total: number; new: number; read: number; archived: number };
  filters: { status: string | null; search: string | null };
  statuses: string[];
}

const statusMeta: Record<string, { label: string; variant: "warning" | "success" | "accentCyan" }> = {
  new: { label: "New", variant: "warning" },
  read: { label: "Read", variant: "success" },
  archived: { label: "Archived", variant: "accentCyan" },
};

const ALL = "all";

export default function AdminContactIndex() {
  const { props } = usePage();
  const { submissions, stats, filters, statuses } = props as unknown as PageProps;

  const [status, setStatus] = useState(filters.status ?? ALL);
  const [search, setSearch] = useState(filters.search ?? "");

  const applyFilters = (nextStatus: string, nextSearch: string) => {
    router.get(
      route("dashboard.admin.contact.index"),
      {
        ...(nextStatus && nextStatus !== ALL && { status: nextStatus }),
        ...(nextSearch && { search: nextSearch }),
      },
      { preserveState: true, replace: true },
    );
  };

  const clearFilters = () => {
    setStatus(ALL);
    setSearch("");
    router.get(route("dashboard.admin.contact.index"), {}, { preserveState: true, replace: true });
  };

  const filtersActive = status !== ALL || search !== "";

  return (
    <AdminLayout title="Contact Messages">
      <Head title="Contact Messages" />

      <div className="grid grid-cols-2 gap-4 mb-6 sm:grid-cols-4">
        <Card>
          <CardContent className="flex items-center gap-3 pt-6">
            <div className="rounded-lg bg-blue-100 dark:bg-blue-900 p-2.5">
              <Mail className="size-5 text-blue-700 dark:text-blue-300" />
            </div>
            <div>
              <p className="text-2xl font-bold">{stats.total.toLocaleString()}</p>
              <p className="text-xs text-muted-foreground">Total Messages</p>
            </div>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="flex items-center gap-3 pt-6">
            <div className="rounded-lg bg-amber-100 dark:bg-amber-900 p-2.5">
              <Inbox className="size-5 text-amber-700 dark:text-amber-300" />
            </div>
            <div>
              <p className="text-2xl font-bold">{stats.new.toLocaleString()}</p>
              <p className="text-xs text-muted-foreground">Unread</p>
            </div>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="flex items-center gap-3 pt-6">
            <div className="rounded-lg bg-green-100 dark:bg-green-900 p-2.5">
              <Mail className="size-5 text-green-700 dark:text-green-300" />
            </div>
            <div>
              <p className="text-2xl font-bold">{stats.read.toLocaleString()}</p>
              <p className="text-xs text-muted-foreground">Read</p>
            </div>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="flex items-center gap-3 pt-6">
            <div className="rounded-lg bg-cyan-100 dark:bg-cyan-900 p-2.5">
              <Mail className="size-5 text-cyan-700 dark:text-cyan-300" />
            </div>
            <div>
              <p className="text-2xl font-bold">{stats.archived.toLocaleString()}</p>
              <p className="text-xs text-muted-foreground">Archived</p>
            </div>
          </CardContent>
        </Card>
      </div>

      <div className="flex flex-col gap-3 mb-4 sm:flex-row sm:items-center">
        <div className="flex items-center gap-2">
          <Select
            value={status}
            onValueChange={(value) => {
              setStatus(value);
              applyFilters(value, search);
            }}
          >
            <SelectTrigger className="w-[160px]">
              <SelectValue placeholder="All statuses" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value={ALL}>All statuses</SelectItem>
              {statuses.map((value) => (
                <SelectItem key={value} value={value}>
                  {statusMeta[value]?.label ?? value}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>

          <div className="relative">
            <Search className="absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              onKeyDown={(event) => {
                if (event.key === "Enter") applyFilters(status, search);
              }}
              placeholder="Search name, email or message"
              className="w-full pl-8 sm:w-72"
            />
          </div>
        </div>

        {filtersActive && (
          <Button variant="ghost" size="sm" onClick={clearFilters}>
            <RotateCcw className="size-4" />
            Clear
          </Button>
        )}
      </div>

      {submissions.data.length === 0 ? (
        <Empty>
          <EmptyHeader>
            <EmptyMedia variant="icon">
              <Inbox />
            </EmptyMedia>
            <EmptyTitle>No contact messages</EmptyTitle>
            <EmptyDescription>
              Messages submitted through the public contact page will show up here.
            </EmptyDescription>
          </EmptyHeader>
        </Empty>
      ) : (
        <div className="rounded-md border">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead className="w-[150px]">Received</TableHead>
                <TableHead>From</TableHead>
                <TableHead>Subject</TableHead>
                <TableHead>Message</TableHead>
                <TableHead className="w-[110px]">Status</TableHead>
                <TableHead className="w-[40px]" />
              </TableRow>
            </TableHeader>
            <TableBody>
              {submissions.data.map((submission) => {
                const meta = statusMeta[submission.status] ?? {
                  label: submission.status,
                  variant: "info" as const,
                };

                return (
                  <TableRow
                    key={submission.id}
                    className="cursor-pointer hover:bg-muted/50"
                    onClick={() =>
                      router.get(route("dashboard.admin.contact.show", submission.id))
                    }
                  >
                    <TableCell className="text-xs whitespace-nowrap text-muted-foreground">
                      <span className="flex items-center gap-1.5">
                        <span
                          className={cn(
                            "inline-block size-1.5 rounded-full shrink-0",
                            submission.status === "new"
                              ? "bg-amber-500"
                              : "bg-muted-foreground/40",
                          )}
                        />
                        {new Date(submission.created_at).toLocaleDateString()}
                      </span>
                      <span className="block pl-3 text-muted-foreground/70">
                        {new Date(submission.created_at).toLocaleTimeString()}
                      </span>
                    </TableCell>
                    <TableCell className="text-xs">
                      <span className="block font-medium truncate">{submission.name}</span>
                      <span className="block text-muted-foreground truncate">{submission.email}</span>
                    </TableCell>
                    <TableCell className="text-xs max-w-[180px]">
                      <span className="block truncate">{submission.subject ?? "—"}</span>
                    </TableCell>
                    <TableCell className="text-xs text-muted-foreground max-w-[280px]">
                      <span className="block truncate">{submission.excerpt}</span>
                    </TableCell>
                    <TableCell>
                      <StatusBadge variant={meta.variant}>{meta.label}</StatusBadge>
                    </TableCell>
                    <TableCell>
                      <ChevronRight className="size-4 text-muted-foreground" />
                    </TableCell>
                  </TableRow>
                );
              })}
            </TableBody>
          </Table>
        </div>
      )}

      {submissions.last_page > 1 && (
        <div className="flex items-center justify-between mt-4">
          <p className="text-xs text-muted-foreground">
            Showing {submissions.from}–{submissions.to} of {submissions.total}
          </p>
          <Pagination>
            <PaginationContent>
              {submissions.links.map((link, i) => {
                if (link.label.includes("Previous")) {
                  return (
                    <PaginationItem key={i}>
                      <PaginationPrevious href={link.url ?? "#"} />
                    </PaginationItem>
                  );
                }
                if (link.label.includes("Next")) {
                  return (
                    <PaginationItem key={i}>
                      <PaginationNext href={link.url ?? "#"} />
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
    </AdminLayout>
  );
}
