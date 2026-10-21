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
import { Checkbox } from "../../../../Components/ui/checkbox";
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
import {
  Download,
  Inbox,
  Mail,
  RotateCcw,
  Search,
  ChevronRight,
  ArrowUp,
  ArrowDown,
  Trash2,
  CheckCheck,
  Archive,
} from "lucide-react";
import { useState } from "react";
import { cn } from "../../../../lib/utils";

interface SubmissionRow {
  id: number;
  form_key: string;
  form_label: string;
  name: string;
  email: string;
  summary: string | null;
  excerpt: string;
  status: string;
  read_at: string | null;
  created_at: string;
}

interface PaginatedSubmissions {
  data: SubmissionRow[];
  current_page: number;
  last_page: number;
  from: number;
  to: number;
  total: number;
  links: Array<{ url: string | null; label: string; active: boolean }>;
}

interface FormOption {
  key: string;
  label: string;
  count: number;
}

interface PageProps {
  submissions: PaginatedSubmissions;
  stats: { total: number; new: number; read: number; archived: number };
  filters: {
    form: string | null;
    status: string | null;
    search: string | null;
    date_from: string | null;
    date_to: string | null;
    sort: string;
    direction: string;
    per_page: number;
  };
  statuses: string[];
  forms: FormOption[];
  pinnedForm: string | null;
  perPages: number[];
}

type BulkAction = "mark_read" | "archive" | "delete";

const statusMeta: Record<string, { label: string; variant: "warning" | "success" | "accentCyan" }> = {
  new: { label: "New", variant: "warning" },
  read: { label: "Read", variant: "success" },
  archived: { label: "Archived", variant: "accentCyan" },
};

const ALL = "all";

export default function AdminSubmissionsIndex() {
  const { props } = usePage();
  const {
    submissions,
    stats,
    filters,
    statuses,
    forms,
    pinnedForm,
    perPages,
  } = props as unknown as PageProps;

  const [status, setStatus] = useState(filters.status ?? ALL);
  const [form, setForm] = useState(filters.form ?? ALL);
  const [search, setSearch] = useState(filters.search ?? "");
  const [dateFrom, setDateFrom] = useState(filters.date_from ?? "");
  const [dateTo, setDateTo] = useState(filters.date_to ?? "");
  const [selected, setSelected] = useState<number[]>([]);
  const [busy, setBusy] = useState(false);

  const basePath = pinnedForm
    ? route("dashboard.admin.contact.index")
    : route("dashboard.admin.submissions.index");
  const showPath = (id: number) =>
    pinnedForm
      ? route("dashboard.admin.contact.show", id)
      : route("dashboard.admin.submissions.show", id);
  const exportPath = pinnedForm
    ? route("dashboard.admin.contact.export")
    : route("dashboard.admin.submissions.export");
  const bulkPath = pinnedForm
    ? route("dashboard.admin.contact.bulk")
    : route("dashboard.admin.submissions.bulk");

  const filterParams = (
    overrides: Record<string, string | number> = {},
  ): Record<string, string | number> => {
    const params: Record<string, string | number> = { ...overrides };
    const nextForm = overrides.form ?? form;
    const nextStatus = overrides.status ?? status;
    const nextSearch = overrides.search ?? search;
    const nextFrom = overrides.date_from ?? dateFrom;
    const nextTo = overrides.date_to ?? dateTo;

    if (!pinnedForm && nextForm && nextForm !== ALL) params.form = nextForm;
    if (nextStatus && nextStatus !== ALL) params.status = nextStatus;
    if (nextSearch) params.search = nextSearch;
    if (nextFrom) params.date_from = nextFrom;
    if (nextTo) params.date_to = nextTo;

    return params;
  };

  const applyFilters = (overrides: Record<string, string | number> = {}) => {
    setSelected([]);
    router.get(basePath, filterParams(overrides), { preserveState: true, replace: true });
  };

  const changeSort = (column: string) => {
    const direction =
      filters.sort === column && filters.direction === "desc" ? "asc" : "desc";
    applyFilters({ sort: column, direction });
  };

  const changePerPage = (value: string) => applyFilters({ per_page: Number(value) });

  const clearFilters = () => {
    setStatus(ALL);
    setForm(ALL);
    setSearch("");
    setDateFrom("");
    setDateTo("");
    setSelected([]);
    router.get(basePath, {}, { preserveState: true, replace: true });
  };

  const filtersActive =
    status !== ALL || form !== ALL || search !== "" || dateFrom !== "" || dateTo !== "";

  const allSelected =
    submissions.data.length > 0 && selected.length === submissions.data.length;

  const toggleAll = () => {
    setSelected(allSelected ? [] : submissions.data.map((row) => row.id));
  };

  const toggleOne = (id: number) => {
    setSelected((current) =>
      current.includes(id) ? current.filter((value) => value !== id) : [...current, id],
    );
  };

  const runBulk = (action: BulkAction) => {
    if (selected.length === 0) return;
    if (
      action === "delete" &&
      !window.confirm(`Delete ${selected.length} submission(s) permanently?`)
    ) {
      return;
    }

    setBusy(true);
    router.post(
      bulkPath,
      { action, ids: selected },
      {
        preserveScroll: true,
        onFinish: () => {
          setBusy(false);
          setSelected([]);
        },
      },
    );
  };

  const exportParams = new URLSearchParams();
  for (const [key, value] of Object.entries(filterParams())) {
    if (value !== undefined && value !== null && value !== "") {
      exportParams.set(key, String(value));
    }
  }
  const exportQuery = exportParams.toString();
  const exportUrl = exportQuery ? `${exportPath}?${exportQuery}` : exportPath;

  const SortHeader = ({ column, label, className }: { column: string; label: string; className?: string }) => (
    <TableHead className={cn("cursor-pointer select-none hover:text-foreground", className)}>
      <button
        type="button"
        onClick={() => changeSort(column)}
        className="inline-flex items-center gap-1 font-medium"
      >
        {label}
        {filters.sort === column &&
          (filters.direction === "asc" ? (
            <ArrowUp className="size-3" />
          ) : (
            <ArrowDown className="size-3" />
          ))}
      </button>
    </TableHead>
  );

  return (
    <AdminLayout title="Form Submissions">
      <Head title="Form Submissions" />

      <div className="grid grid-cols-2 gap-4 mb-6 sm:grid-cols-4">
        <Card>
          <CardContent className="flex items-center gap-3 pt-6">
            <div className="rounded-lg bg-blue-100 dark:bg-blue-900 p-2.5">
              <Mail className="size-5 text-blue-700 dark:text-blue-300" />
            </div>
            <div>
              <p className="text-2xl font-bold">{stats.total.toLocaleString()}</p>
              <p className="text-xs text-muted-foreground">Total</p>
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
              <Archive className="size-5 text-cyan-700 dark:text-cyan-300" />
            </div>
            <div>
              <p className="text-2xl font-bold">{stats.archived.toLocaleString()}</p>
              <p className="text-xs text-muted-foreground">Archived</p>
            </div>
          </CardContent>
        </Card>
      </div>

      <div className="flex flex-col gap-3 mb-4">
        <div className="flex flex-wrap items-center gap-2">
          {!pinnedForm && (
            <Select
              value={form}
              onValueChange={(value) => {
                setForm(value);
                applyFilters({ form: value });
              }}
            >
              <SelectTrigger className="w-[180px]">
                <SelectValue placeholder="All forms" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value={ALL}>All forms</SelectItem>
                {forms.map((option) => (
                  <SelectItem key={option.key} value={option.key}>
                    {option.label} ({option.count})
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          )}

          <Select
            value={status}
            onValueChange={(value) => {
              setStatus(value);
              applyFilters({ status: value });
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
                if (event.key === "Enter") applyFilters();
              }}
              placeholder="Search submissions"
              aria-label="Search submissions"
              className="w-full pl-8 sm:w-64"
            />
          </div>

          <Input
            type="date"
            value={dateFrom}
            onChange={(event) => setDateFrom(event.target.value)}
            onBlur={() => applyFilters()}
            aria-label="From date"
            className="w-[150px]"
          />
          <Input
            type="date"
            value={dateTo}
            onChange={(event) => setDateTo(event.target.value)}
            onBlur={() => applyFilters()}
            aria-label="To date"
            className="w-[150px]"
          />

          {filtersActive && (
            <Button variant="ghost" size="sm" onClick={clearFilters}>
              <RotateCcw className="size-4" />
              Clear
            </Button>
          )}
        </div>

        <div className="flex flex-wrap items-center justify-between gap-2">
          <div className="flex items-center gap-2">
            <Select value={String(filters.per_page)} onValueChange={changePerPage}>
              <SelectTrigger className="w-[110px]" aria-label="Rows per page">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {perPages.map((value) => (
                  <SelectItem key={value} value={String(value)}>
                    {value} / page
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>

            {selected.length > 0 && (
              <div className="flex items-center gap-1.5 rounded-md border bg-muted/40 px-2 py-1">
                <span className="text-xs text-muted-foreground">
                  {selected.length} selected
                </span>
                <Button
                  variant="ghost"
                  size="sm"
                  disabled={busy}
                  onClick={() => runBulk("mark_read")}
                >
                  <CheckCheck className="size-4" />
                  Mark read
                </Button>
                <Button
                  variant="ghost"
                  size="sm"
                  disabled={busy}
                  onClick={() => runBulk("archive")}
                >
                  <Archive className="size-4" />
                  Archive
                </Button>
                <Button
                  variant="destructive"
                  size="sm"
                  disabled={busy}
                  onClick={() => runBulk("delete")}
                >
                  <Trash2 className="size-4" />
                  Delete
                </Button>
              </div>
            )}
          </div>

          <Button
            variant="outline"
            size="sm"
            render={<a href={exportUrl} download aria-label="Export CSV" />}
          >
            <Download className="size-4" />
            Export CSV
          </Button>
        </div>
      </div>

      {submissions.data.length === 0 ? (
        <Empty>
          <EmptyHeader>
            <EmptyMedia variant="icon">
              <Inbox />
            </EmptyMedia>
            <EmptyTitle>No submissions</EmptyTitle>
            <EmptyDescription>
              {filtersActive
                ? "Nothing matches the current filters."
                : "Submissions from every public form will show up here."}
            </EmptyDescription>
          </EmptyHeader>
        </Empty>
      ) : (
        <div className="rounded-md border">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead className="w-[40px]">
                  <Checkbox
                    checked={allSelected}
                    onCheckedChange={toggleAll}
                    aria-label="Select all submissions"
                  />
                </TableHead>
                <SortHeader column="created_at" label="Received" className="w-[150px]" />
                {!pinnedForm && <TableHead className="w-[130px]">Form</TableHead>}
                <SortHeader column="name" label="From" />
                <SortHeader column="summary" label="Summary" />
                <SortHeader column="status" label="Status" className="w-[110px]" />
                <TableHead className="w-[40px]" />
              </TableRow>
            </TableHeader>
            <TableBody>
              {submissions.data.map((submission) => {
                const meta = statusMeta[submission.status] ?? {
                  label: submission.status,
                  variant: "info" as const,
                };
                const isSelected = selected.includes(submission.id);

                return (
                  <TableRow
                    key={submission.id}
                    className={cn(
                      "cursor-pointer hover:bg-muted/50",
                      isSelected && "bg-muted/30",
                    )}
                    onClick={() => router.get(showPath(submission.id))}
                  >
                    <TableCell onClick={(event) => event.stopPropagation()}>
                      <Checkbox
                        checked={isSelected}
                        onCheckedChange={() => toggleOne(submission.id)}
                        aria-label={`Select submission ${submission.id}`}
                      />
                    </TableCell>
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
                    {!pinnedForm && (
                      <TableCell className="text-xs text-muted-foreground whitespace-nowrap">
                        {submission.form_label}
                      </TableCell>
                    )}
                    <TableCell className="text-xs">
                      <span className="block font-medium truncate">{submission.name}</span>
                      <span className="block text-muted-foreground truncate">
                        {submission.email}
                      </span>
                    </TableCell>
                    <TableCell className="text-xs text-muted-foreground max-w-[280px]">
                      <span className="block truncate">{submission.summary || "—"}</span>
                      <span className="block truncate text-muted-foreground/70">
                        {submission.excerpt}
                      </span>
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
