import React, { useState, useMemo, useCallback } from "react";
import { Head, Link, router } from "@inertiajs/react";
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "@/Components/ui/empty";
import { toast } from "sonner";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { DataTable } from "@/Components/ui/data-table";
import { DataTableColumnHeader } from "@/Components/ui/data-table-column-header";
import { Button } from "@/Components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/Components/ui/dropdown-menu";
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "@/Components/ui/breadcrumb";
import {
  Plus,
  MoreHorizontal,
  Eye,
  Building2,
  Home,
  ArrowRight,
  FilterX,
  SlidersHorizontal,
  CalendarDays,
  Mail,
  Phone,
  Archive,
  ArchiveRestore,
  DollarSign,
  TrendingUp,
  Clock,
  CalendarCheck,
  CalendarPlus,
} from "lucide-react";
import {
  BOOKING_STATUS,
  STATUS_BADGE,
  STATUS_LABELS,
  STATUS_VARIANT,
  getStatusLabel,
} from "../lib/booking-status";
import { BookingStats } from "../components/BookingStats";
import { BookingFilterBar } from "../components/BookingFilterBar";

const STATUS_TABS = [
  { value: "all", label: "All" },
  { value: BOOKING_STATUS.Pending, label: STATUS_LABELS[BOOKING_STATUS.Pending] },
  { value: BOOKING_STATUS.Confirmed, label: STATUS_LABELS[BOOKING_STATUS.Confirmed] },
  { value: BOOKING_STATUS.CheckedIn, label: STATUS_LABELS[BOOKING_STATUS.CheckedIn] },
  { value: BOOKING_STATUS.CheckedOut, label: STATUS_LABELS[BOOKING_STATUS.CheckedOut] },
  { value: BOOKING_STATUS.Cancelled, label: STATUS_LABELS[BOOKING_STATUS.Cancelled] },
] as const;

const TAB_BADGE: Record<string, string> = {
  [BOOKING_STATUS.Pending]: "bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300",
  [BOOKING_STATUS.Confirmed]: "bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-200",
  [BOOKING_STATUS.CheckedIn]: "bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-200",
  [BOOKING_STATUS.CheckedOut]: "bg-gray-100 text-gray-800 dark:bg-gray-700/30 dark:text-gray-200",
  [BOOKING_STATUS.Cancelled]: "bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-200",
};

const sanitizeThumbnailUrl = (url: string | null | undefined): string | null => {
  if (!url) return null;
  let processedUrl = url;
  processedUrl = url.startsWith("file://") ? url.replace("file://", "") : url;
  processedUrl = processedUrl.replaceAll(/\\+/g, "/");
  processedUrl = processedUrl.replace(/^([A-Za-z]):([^/])/, "$1:/$2");
  const uploadsMatch = processedUrl.match(/wp-content[\/](?:uploads[\/])?(.+)/i);
  if (uploadsMatch) {
    const pathPart = uploadsMatch[1]?.replace(/\?.*$/, "");
    if (pathPart) return `/wp-content/uploads/${pathPart}`;
  }
  const fallbackMatch = processedUrl.match(/uploads[\/](.+)/i);
  if (fallbackMatch) {
    const pathPart = fallbackMatch[1]?.replace(/\?.*$/, "");
    if (pathPart) return `/wp-content/uploads/${pathPart}`;
  }
  return url;
};

interface BookingRecord {
  id: number;
  status?: string;
  status_label?: string;
  property_thumbnail?: string | null;
  property_title?: string;
  property?: { id: number; name: string } | null;
  guest_name?: string;
  guest_email?: string;
  guest_phone?: string;
  guest_name_field?: string | null;
  user?: { id: number; name: string } | null;
  payment_status?: string | null;
  archived?: boolean;
  start_date?: string;
  end_date?: string;
  check_in?: string;
  check_out?: string;
  nights?: number;
  guests?: number;
  guest_count?: number;
  total?: number;
  total_amount?: number | null;
  total_amount_formatted?: string;
  currency?: string;
  allowed_transitions?: string[];
}

interface BookingFilters {
  check_in_from?: string;
  check_in_to?: string;
  check_out_from?: string;
  check_out_to?: string;
  payment_status?: string;
  show_archived?: string;
}

interface BookingMetrics {
  total_bookings?: number;
  total_revenue?: number;
  average_value?: number;
  pending_revenue?: number;
}

interface BookingsIndexProps {
  bookings?: BookingRecord[];
  stats?: {
    total: number;
    pending: number;
    confirmed: number;
    checked_in: number;
    cancelled: number;
    total_revenue: number;
    upcoming: number;
  };
  filters?: BookingFilters;
  metrics?: BookingMetrics;
  properties?: { id: number; name: string }[];
}

const PERIOD_OPTIONS = [
  { value: "all", label: "All Time" },
  { value: "upcoming", label: "Active" },
  { value: "past", label: "Past" },
] as const;

const PAYMENT_STATUS_OPTIONS = [
  { value: "", label: "All Payments" },
  { value: "paid", label: "Paid" },
  { value: "unpaid", label: "Unpaid" },
  { value: "pending", label: "Pending" },
  { value: "refunded", label: "Refunded" },
  { value: "failed", label: "Failed" },
] as const;

export default function BookingsIndex({
  bookings = [],
  stats,
  filters = {},
  metrics = {},
  properties = [],
}: BookingsIndexProps) {
  const [statusFilter, setStatusFilter] = useState("all");
  const [periodFilter, setPeriodFilter] = useState<string>("all");
  const [paymentStatusFilter, setPaymentStatusFilter] = useState(filters.payment_status || "");
  const [showArchived, setShowArchived] = useState(filters.show_archived === "1");

  const hasFilters = ["status", "period", "payment_status", "check_in_from", "check_in_to", "check_out_from", "check_out_to"].some((k) => (filters as Record<string, string>)[k]);
  const [filterOpen, setFilterOpen] = useState(false);
  const [processingTransition, setProcessingTransition] = useState<string | null>(null);

  const now = new Date();
  const todayStr = now.toISOString().slice(0, 10);

  const statusCounts = useMemo(() => {
    const counts: Record<string, number> = { all: bookings.length };
    for (const t of STATUS_TABS.slice(1)) { counts[t.value] = 0; }
    for (const b of bookings) {
      if (b.status && counts[b.status] !== undefined) { counts[b.status] = (counts[b.status] as number) + 1; }
    }
    return counts;
  }, [bookings]);

  const periodFiltered = useMemo(() => {
    if (periodFilter === "all") return bookings;
    return bookings.filter((b) => {
      const checkOut = b.check_out || b.end_date || "";
      if (periodFilter === "upcoming") return checkOut >= todayStr;
      if (periodFilter === "past") return checkOut < todayStr;
      return true;
    });
  }, [bookings, periodFilter, todayStr]);

  const filteredBookings = useMemo(() => {
    const source = periodFilter !== "all" ? periodFiltered : bookings;
    if (statusFilter === "all") return source;
    return source.filter((b) => b.status === statusFilter);
  }, [periodFilter, periodFiltered, bookings, statusFilter]);

  const handleTransition = useCallback((bookingId: number, newStatus: string) => {
    const key = `${bookingId}-${newStatus}`;
    setProcessingTransition(key);
    router.post(route("dashboard.bookings.transition", bookingId), {
      status: newStatus,
    }, {
      preserveScroll: true,
      onSuccess: () => toast.success(`Status changed to ${newStatus.replace("_", " ")}`),
      onError: (errors: Record<string, string>) => toast.error(errors.transition || "Failed to update status"),
      onFinish: () => setProcessingTransition(null),
    });
  }, []);

  const handleArchive = useCallback((bookingId: number) => {
    router.post(route("dashboard.bookings.archive", bookingId), {}, {
      preserveScroll: true,
      onSuccess: () => toast.success("Booking archive toggled"),
      onError: () => toast.error("Failed to toggle archive"),
    });
  }, []);

  const handleDelete = useCallback((id: number) => {
    router.delete(route("dashboard.bookings.destroy", id), {
      preserveScroll: true,
      onSuccess: () => toast.success("Booking deleted."),
      onError: () => toast.error("Failed to delete booking."),
    });
  }, []);

  const handlePaymentStatusChange = (value: string) => {
    setPaymentStatusFilter(value);
    router.get(route("dashboard.bookings.index"), {
      ...filters,
      payment_status: value || undefined,
      show_archived: showArchived ? "1" : undefined,
    }, { preserveScroll: true, preserveState: true });
  };

  const toggleShowArchived = () => {
    const next = !showArchived;
    setShowArchived(next);
    router.get(route("dashboard.bookings.index"), {
      ...filters,
      show_archived: next ? "1" : undefined,
    }, { preserveScroll: true, preserveState: true });
  };

  const resetFilters = () => {
    router.get(route("dashboard.bookings.index"), {}, { preserveScroll: true, preserveState: true });
  };

  const BULK_ACTIONS = [
    { label: "Mark Pending", value: BOOKING_STATUS.Pending, show: (statuses: string[]) => statuses.some((s) => s !== BOOKING_STATUS.Pending && s !== BOOKING_STATUS.CheckedOut && s !== BOOKING_STATUS.Cancelled) },
    { label: "Confirm", value: BOOKING_STATUS.Confirmed, show: (statuses: string[]) => statuses.includes(BOOKING_STATUS.Pending) },
    { label: "Check In", value: BOOKING_STATUS.CheckedIn, show: (statuses: string[]) => statuses.includes(BOOKING_STATUS.Confirmed) },
    { label: "Check Out", value: BOOKING_STATUS.CheckedOut, show: (statuses: string[]) => statuses.includes(BOOKING_STATUS.CheckedIn) },
    { label: "Cancel", value: BOOKING_STATUS.Cancelled, show: (statuses: string[]) => statuses.some((s) => s !== BOOKING_STATUS.Cancelled && s !== BOOKING_STATUS.CheckedOut), destructive: true },
    { label: "Archive", value: "archive", show: (statuses: string[]) => true },
    { label: "Unarchive", value: "unarchive", show: (statuses: string[]) => true },
    { label: "Delete", value: "delete", show: () => true, destructive: true },
  ];

  const handleBulkAction = (action: string, ids: number[]) => {
    if (action === "delete") {
      router.post(route("dashboard.bookings.bulk"), { ids, status: "delete" }, {
        preserveScroll: true,
        onSuccess: () => toast.success("Bookings deleted"),
        onError: (errors: Record<string, string>) => toast.error(errors.error || "Failed to delete bookings"),
      });
    } else if (action === "archive" || action === "unarchive") {
      router.post(route("dashboard.bookings.bulk"), { ids, status: action }, {
        preserveScroll: true,
        onSuccess: () => toast.success(`Bookings ${action}d`),
        onError: (errors: Record<string, string>) => toast.error(errors.error || `Failed to ${action} bookings`),
      });
    } else {
      router.post(route("dashboard.bookings.bulk"), { ids, status: action }, {
        preserveScroll: true,
        onSuccess: () => toast.success(`Bookings updated to ${action.replace("_", " ")}`),
        onError: (errors: Record<string, string>) => toast.error(errors.error || "Failed to update bookings"),
      });
    }
  };

  const columns = useMemo(() => [
    {
      accessorKey: "property_thumbnail",
      header: "Image",
      cell: ({ row }: any) => {
        const thumbnailUrl = sanitizeThumbnailUrl(row.original.property_thumbnail);
        return (
          <div>
            {thumbnailUrl ? (
              <img src={thumbnailUrl} alt="" loading="lazy" className="!w-[60px] !h-[60px] object-cover rounded-md max-w-[60px]"
                onError={(e: React.SyntheticEvent<HTMLImageElement>) => { (e.target as HTMLImageElement).style.display = "none"; }} />
            ) : (
              <div className="!w-[60px] !h-[60px] bg-muted rounded flex items-center justify-center">
                <Building2 className="h-6 w-6 text-muted-foreground" />
              </div>
            )}
          </div>
        );
      },
      enableSorting: false,
    },
    {
      accessorKey: "id",
      header: ({ column }: any) => <DataTableColumnHeader column={column} title="ID" />,
      cell: ({ row }: any) => (
        <Link href={route("dashboard.bookings.show", row.original.id)} className="text-foreground hover:underline whitespace-nowrap font-mono text-sm">
          #{row.original.id}
        </Link>
      ),
    },
    {
      accessorKey: "guest_name",
      header: ({ column }: any) => <DataTableColumnHeader column={column} title="Guest" />,
      cell: ({ row }: any) => {
        const name = row.original.guest_name || row.original.guest_name_field || row.original.user?.name || "N/A";
        return (
          <div className="min-w-[140px]">
            <div className="flex items-center gap-1.5">
              <Link href={route("dashboard.bookings.show", row.original.id)}
                className="font-medium text-foreground hover:underline truncate max-w-[150px] block" title={name}>{name}</Link>
              {row.original.guest_email && (
                <a href={`mailto:${row.original.guest_email}`} className="text-muted-foreground hover:text-foreground shrink-0" title="Send email" onClick={(e) => e.stopPropagation()}>
                  <Mail className="h-3.5 w-3.5" />
                </a>
              )}
              {row.original.guest_phone && (
                <a href={`tel:${row.original.guest_phone}`} className="text-muted-foreground hover:text-foreground shrink-0" title="Call" onClick={(e) => e.stopPropagation()}>
                  <Phone className="h-3.5 w-3.5" />
                </a>
              )}
            </div>
            {row.original.guest_email && (
              <p className="text-xs text-muted-foreground truncate max-w-[180px]" title={row.original.guest_email}>{row.original.guest_email}</p>
            )}
          </div>
        );
      },
    },
    {
      accessorKey: "property_title",
      header: ({ column }: any) => <DataTableColumnHeader column={column} title="Property" />,
      cell: ({ row }: any) => (
        <span className="text-foreground truncate max-w-[200px] block" title={row.original.property_title || row.original.property?.name}>
          {row.original.property_title || row.original.property?.name || "-"}
        </span>
      ),
    },
    {
      accessorKey: "check_in",
      header: ({ column }: any) => <DataTableColumnHeader column={column} title="Check-in" />,
      cell: ({ row }: any) => {
        const checkIn: string | undefined = row.original.check_in || row.original.start_date;
        const checkOut: string | undefined = row.original.check_out || row.original.end_date;
        const ci = checkIn ? new Date(checkIn + "T12:00:00").toLocaleDateString() : "-";
        const co = checkOut ? new Date(checkOut + "T12:00:00").toLocaleDateString() : "-";
        return <span className="text-foreground whitespace-nowrap">{ci} &rarr; <span className="text-xs text-muted-foreground">{co}</span></span>;
      },
    },
    {
      accessorKey: "nights",
      header: ({ column }: any) => <DataTableColumnHeader column={column} title="Nights" />,
      cell: ({ row }: any) => <span className="text-foreground whitespace-nowrap">{row.original.nights || "-"}</span>,
    },
    {
      accessorKey: "guests",
      header: ({ column }: any) => <DataTableColumnHeader column={column} title="Guests" />,
      cell: ({ row }: any) => <span className="text-foreground whitespace-nowrap">{row.original.guests || row.original.guest_count || "-"}</span>,
    },
    {
      accessorKey: "total",
      header: ({ column }: any) => <DataTableColumnHeader column={column} title="Total" />,
      cell: ({ row }: any) => (
        <span className="text-foreground font-medium whitespace-nowrap">
          {row.original.total_amount_formatted || (row.original.total_amount ? `$${Number(row.original.total_amount).toFixed(2)}` : "$0.00")}
        </span>
      ),
    },
    {
      accessorKey: "status",
      header: ({ column }: any) => <DataTableColumnHeader column={column} title="Status" />,
      cell: ({ row }: any) => (
        <div className="flex items-center gap-1.5">
          <span className={`inline-flex px-2 py-1 rounded-full text-xs font-medium whitespace-nowrap ${(row.original.status && STATUS_BADGE[row.original.status]) || ""}`}>
            {row.original.status_label || getStatusLabel(row.original.status)}
          </span>
          {row.original.archived && <span title="Archived"><Archive className="h-3.5 w-3.5 text-muted-foreground" /></span>}
        </div>
      ),
    },
    {
      id: "actions",
      cell: ({ row }: any) => {
        const booking: BookingRecord = row.original;
        const transitions = booking.allowed_transitions || [];
        return (
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="ghost" className="h-8 w-8 p-0"><MoreHorizontal className="h-4 w-4" /><span className="sr-only">Open menu</span></Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuItem>
                <Link href={route("dashboard.bookings.show", booking.id)} className="flex items-center w-full"><Eye className="mr-2 h-4 w-4" /> View</Link>
              </DropdownMenuItem>
              {(transitions.length > 0 || booking.archived !== undefined) && <DropdownMenuSeparator />}
              {transitions.map((transition: string) => (
                <DropdownMenuItem key={transition}>
                  <button type="button" className="flex items-center w-full cursor-pointer" onClick={() => handleTransition(booking.id, transition)}
                    disabled={processingTransition === `${booking.id}-${transition}`}>
                    <ArrowRight className="mr-2 h-4 w-4" />
                    {processingTransition === `${booking.id}-${transition}` ? "..." : `Mark ${transition.replace("_", " ")}`}
                  </button>
                </DropdownMenuItem>
              ))}
              <DropdownMenuSeparator />
              <DropdownMenuItem>
                <button type="button" className="flex items-center w-full cursor-pointer" onClick={() => handleArchive(booking.id)}>
                  {booking.archived ? <><ArchiveRestore className="mr-2 h-4 w-4" /> Unarchive</> : <><Archive className="mr-2 h-4 w-4" /> Archive</>}
                </button>
              </DropdownMenuItem>
              <DropdownMenuSeparator />
              <DropdownMenuItem>
                <button type="button" className="flex items-center w-full cursor-pointer text-destructive" onClick={() => handleDelete(booking.id)}>
                  Delete
                </button>
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        );
      },
    },
  ], [handleTransition, handleArchive, handleDelete, processingTransition]);

  if (properties.length === 0) {
    return (
      <AuthenticatedLayout header="Bookings">
        <Head title="Bookings" />
        <div className="space-y-6">
          <Breadcrumb>
            <BreadcrumbList>
              <BreadcrumbItem><BreadcrumbLink href="/dashboard">Dashboard</BreadcrumbLink></BreadcrumbItem>
              <BreadcrumbSeparator />
              <BreadcrumbItem><BreadcrumbPage>Bookings</BreadcrumbPage></BreadcrumbItem>
            </BreadcrumbList>
          </Breadcrumb>
          <div className="flex items-center gap-3">
            <CalendarCheck className="h-6 w-6 text-foreground shrink-0" />
            <div>
              <h2 className="text-3xl font-bold tracking-tight text-foreground">Bookings</h2>
              <p className="text-muted-foreground">Manage your property bookings</p>
            </div>
          </div>
          <Empty>
            <EmptyHeader>
              <EmptyMedia variant="icon"><Home /></EmptyMedia>
              <EmptyTitle>No properties yet</EmptyTitle>
              <EmptyDescription>Post your first property to start managing bookings.</EmptyDescription>
            </EmptyHeader>
            <EmptyContent>
              <Button onClick={() => router.visit(route("dashboard.properties.create"))}>
                <Plus className="mr-2 h-4 w-4" /> Add Property
              </Button>
            </EmptyContent>
          </Empty>
        </div>
      </AuthenticatedLayout>
    )
  }

  const emptyState = (
    <Empty>
      {hasFilters ? (
        <>
          <EmptyHeader>
            <EmptyMedia variant="icon"><Building2 /></EmptyMedia>
            <EmptyTitle>No bookings match your filters</EmptyTitle>
            <EmptyDescription>Try adjusting your search or filter criteria.</EmptyDescription>
          </EmptyHeader>
          <EmptyContent>
            <Button variant="outline" onClick={resetFilters}>
              <FilterX className="mr-1.5 h-3.5 w-3.5" /> Clear Filters
            </Button>
          </EmptyContent>
        </>
      ) : (
        <>
          <EmptyHeader>
            <EmptyMedia variant="icon"><Building2 /></EmptyMedia>
            <EmptyTitle>No bookings yet</EmptyTitle>
            <EmptyDescription>Share your property page to start receiving reservations.</EmptyDescription>
          </EmptyHeader>
        </>
      )}
    </Empty>
  );

  return (
    <AuthenticatedLayout header="Bookings">
      <div className="space-y-6">
        <Breadcrumb>
          <BreadcrumbList>
            <BreadcrumbItem><BreadcrumbLink href="/dashboard">Dashboard</BreadcrumbLink></BreadcrumbItem>
            <BreadcrumbSeparator />
            <BreadcrumbItem><BreadcrumbPage>Bookings</BreadcrumbPage></BreadcrumbItem>
          </BreadcrumbList>
        </Breadcrumb>

        <div className="flex items-center justify-between">
          <div className="flex items-center gap-3">
            <CalendarCheck className="h-6 w-6 text-foreground shrink-0" />
            <div>
              <h2 className="text-3xl font-bold tracking-tight text-foreground">Bookings</h2>
              <p className="text-muted-foreground">Manage your property bookings</p>
            </div>
          </div>
          <div className="flex items-center gap-2">
            <Link href={route("dashboard.bookings.calendar")}><Button variant="outline"><CalendarDays className="mr-1 h-4 w-4" /> Calendar</Button></Link>
            <Link href={route("dashboard.properties.index")}><Button variant="outline"><Building2 className="mr-2 h-4 w-4" /> Properties</Button></Link>
            <Link href={route("dashboard.bookings.create")}><Button><CalendarPlus className="mr-2 h-4 w-4" /> Add Booking</Button></Link>
          </div>
        </div>

        {stats && (
          <BookingStats
            total={stats.total}
            pending={stats.pending}
            confirmed={stats.confirmed}
            checked_in={stats.checked_in}
            cancelled={stats.cancelled}
            total_revenue={stats.total_revenue}
            upcoming={stats.upcoming}
          />
        )}

        <div className="flex flex-wrap gap-1.5">
          {STATUS_TABS.map((tab) => {
            const count = statusCounts[tab.value] ?? 0;
            const badgeClass = TAB_BADGE[tab.value] || "";
            return (
              <button key={tab.value} type="button" onClick={() => setStatusFilter(tab.value)}
                className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium transition-colors ${
                  statusFilter === tab.value ? "bg-primary text-primary-foreground" : "bg-accent text-muted-foreground hover:bg-accent/80"
                }`}>
                {tab.label}
                <span className={`inline-flex items-center justify-center min-w-[18px] h-[18px] rounded-full px-1 text-[10px] font-semibold ${
                  statusFilter === tab.value ? "bg-primary-foreground/20 text-primary-foreground" : badgeClass
                }`}>{count}</span>
              </button>
            );
          })}
        </div>

        <div className="flex flex-wrap gap-1.5">
          {PERIOD_OPTIONS.map((opt) => (
            <button key={opt.value} type="button" onClick={() => setPeriodFilter(opt.value)}
              className={`inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium transition-colors ${
                periodFilter === opt.value ? "bg-secondary text-secondary-foreground" : "bg-muted text-muted-foreground hover:bg-muted/80"
              }`}>
              {opt.label}
            </button>
          ))}
        </div>

        <div className="flex flex-col gap-4">
          <div className="flex items-center gap-2">
            <BookingFilterBar
              statuses={[{ value: "", label: "All Statuses" }, ...STATUS_TABS.slice(1).map((s) => ({ value: s.value, label: s.label }))]}
              properties={properties}
              onFiltersChange={(newFilters) => {
                router.get(route("dashboard.bookings.index"), newFilters, { preserveState: true, preserveScroll: true, replace: true });
              }}
              initialFilters={filters as Record<string, string>}
            />
            {(filters.check_in_from || filters.check_in_to || filters.check_out_from || filters.check_out_to) && (
              <Button variant="ghost" size="sm" onClick={resetFilters}>
                <FilterX className="mr-1.5 h-3.5 w-3.5" /> Clear
              </Button>
            )}
          </div>

          <div className="flex flex-wrap items-center justify-between gap-2">
            <div className="flex flex-wrap gap-1.5">
              {PAYMENT_STATUS_OPTIONS.map((opt) => (
                <button key={opt.value} type="button" onClick={() => handlePaymentStatusChange(opt.value)}
                  className={`inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium transition-colors ${
                    paymentStatusFilter === opt.value ? "bg-secondary text-secondary-foreground" : "bg-muted text-muted-foreground hover:bg-muted/80"
                  }`}>
                  {opt.label}
                </button>
              ))}
            </div>
            <Button variant={showArchived ? "secondary" : "outline"} size="sm" onClick={toggleShowArchived}>
              <Archive className="mr-1.5 h-3.5 w-3.5" /> {showArchived ? "Hide" : "Show"} Archived
            </Button>
          </div>
        </div>

        {metrics.total_bookings !== undefined && metrics.total_bookings > 0 && (
          <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div className="rounded-lg border border-border p-3 space-y-1">
              <div className="flex items-center gap-1.5 text-xs text-muted-foreground"><CalendarCheck className="h-3.5 w-3.5" /> Total</div>
              <p className="text-xl font-bold text-foreground">{metrics.total_bookings}</p>
            </div>
            <div className="rounded-lg border border-border p-3 space-y-1">
              <div className="flex items-center gap-1.5 text-xs text-muted-foreground"><DollarSign className="h-3.5 w-3.5" /> Revenue</div>
              <p className="text-xl font-bold text-foreground">${(metrics.total_revenue ?? 0).toLocaleString()}</p>
            </div>
            <div className="rounded-lg border border-border p-3 space-y-1">
              <div className="flex items-center gap-1.5 text-xs text-muted-foreground"><TrendingUp className="h-3.5 w-3.5" /> Avg Value</div>
              <p className="text-xl font-bold text-foreground">${(metrics.average_value ?? 0).toLocaleString()}</p>
            </div>
            <div className="rounded-lg border border-border p-3 space-y-1">
              <div className="flex items-center gap-1.5 text-xs text-muted-foreground"><Clock className="h-3.5 w-3.5" /> Pending Rev</div>
              <p className="text-xl font-bold text-foreground">${(metrics.pending_revenue ?? 0).toLocaleString()}</p>
            </div>
          </div>
        )}

        <DataTable
          columns={columns}
          data={filteredBookings}
          searchKey="guest_name"
          searchPlaceholder="Search by guest name..."
          pageSize={20}
          bulkActions={BULK_ACTIONS}
          onBulkAction={handleBulkAction}
          emptyState={emptyState}
        />
      </div>
    </AuthenticatedLayout>
  );
}
