import React, { useState, useMemo } from "react";
import { Link, router, usePage } from "@inertiajs/react";
import { useFormatDate } from "@/hooks/use-format-date";
import { formatDate } from "@/lib/utils";
import { toast } from "sonner";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { DataTable } from "@/Components/ui/data-table";
import { DataTableColumnHeader } from "@/Components/ui/data-table-column-header";
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
import { MoreHorizontal, Eye, Calendar, CalendarDays, Home } from "lucide-react";
import { BOOKING_STATUS, STATUS_BADGE, STATUS_LABELS } from "../lib/booking-status";

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

interface BookingRecord {
  id: number;
  status?: string;
  status_label?: string;
  property_title?: string;
  property_thumbnail?: string | null;
  property_url?: string | null;
  check_in?: string;
  check_out?: string;
  nights?: number;
  guests?: number;
  total?: number;
  refund_percent?: string;
  refund_amount?: string;
  refund_policy_applied?: string;
}

interface MyBookingsProps {
  bookings?: BookingRecord[];
  filters?: { status?: string };
}

export default function MyBookings({ bookings = [], filters = {} }: MyBookingsProps) {
  const { dateFormat } = useFormatDate();
  const globalProps = usePage().props as Record<string, unknown>;
  const allowedCancelStatuses = (
    globalProps.booking_global_settings as Record<string, unknown> | undefined
  )?.allow_guest_cancellation_statuses as string[] | undefined;
  const [statusFilter, setStatusFilter] = useState(filters.status || "all");
  const [processingTransition, setProcessingTransition] = useState<string | null>(null);
  const [cancelBookingId, setCancelBookingId] = useState<number | null>(null);

  const statusCounts = useMemo(() => {
    const counts: Record<string, number> = { all: bookings.length };
    for (const t of STATUS_TABS.slice(1)) { counts[t.value] = 0; }
    for (const b of bookings) {
      if (b.status && counts[b.status] !== undefined) { counts[b.status] = (counts[b.status] as number) + 1; }
    }
    return counts;
  }, [bookings]);

  const filteredBookings = useMemo(() => {
    if (statusFilter === "all") return bookings;
    return bookings.filter((b) => b.status === statusFilter);
  }, [bookings, statusFilter]);

  const [cancelBookingRefund, setCancelBookingRefund] = React.useState<string | null>(null);

  const handleCancel = (bookingId: number) => {
    const booking = bookings.find((b) => b.id === bookingId);
    let refundText = "";
    if (booking!.refund_percent && booking!.refund_policy_applied) {
      const pct = Number(booking!.refund_percent);
      const amt = Number(booking!.refund_amount || 0);
      const policy = booking!.refund_policy_applied.replaceAll("_", " ");
      if (pct >= 100) refundText = `Full refund per "${policy}" policy.`;
      else if (pct > 0) refundText = `${pct}% refund ($${amt.toFixed(2)}) per "${policy}" policy.`;
      else refundText = `No refund per "${policy}" policy.`;
    }
    setCancelBookingRefund(refundText || null);
    setCancelBookingId(bookingId);
  };

  const confirmCancel = () => {
    if (cancelBookingId === null) return;
    setProcessingTransition(`${cancelBookingId}-cancel`);
    router.post(route("dashboard.my-bookings.cancel", cancelBookingId), { cancelled_by: "guest" }, {
      preserveScroll: true,
      onSuccess: () => toast.success("Booking cancelled"),
      onError: () => toast.error("Failed to cancel booking"),
      onFinish: () => setProcessingTransition(null),
    });
    setCancelBookingId(null);
  };

  const columns = [
    {
      accessorKey: "property_title",
      header: ({ column }: any) => <DataTableColumnHeader column={column} title="Property" />,
      cell: ({ row }: any) => {
        const thumb = row.original.property_thumbnail;
        const url = row.original.property_url;
        return (
          <div className="flex items-center gap-2">
            {thumb ? <img src={thumb} alt="" loading="lazy" className="h-8 w-8 rounded object-cover shrink-0" />
              : <Home className="h-4 w-4 text-muted-foreground shrink-0" />}
            {url ? (
              <a href={url} target="_blank" rel="noopener noreferrer" className="text-foreground truncate max-w-[180px] block hover:underline">
                {row.original.property_title || "-"}
              </a>
            ) : <span className="text-foreground truncate max-w-[200px] block">{row.original.property_title || "-"}</span>}
          </div>
        );
      },
    },
    {
      accessorKey: "check_in",
      header: ({ column }: any) => <DataTableColumnHeader column={column} title="Dates" />,
      cell: ({ row }: any) => {
        const ci = row.original.check_in ? formatDate(new Date(row.original.check_in + "T12:00:00"), dateFormat) : "-";
        const co = row.original.check_out ? formatDate(new Date(row.original.check_out + "T12:00:00"), dateFormat) : "-";
        return <span className="text-foreground whitespace-nowrap">{ci} &rarr; {co}</span>;
      },
    },
    {
      accessorKey: "nights",
      header: ({ column }: any) => <DataTableColumnHeader column={column} title="Nights" />,
      cell: ({ row }: any) => <span className="text-foreground whitespace-nowrap">{row.original.nights}</span>,
    },
    {
      accessorKey: "total",
      header: ({ column }: any) => <DataTableColumnHeader column={column} title="Total" />,
      cell: ({ row }: any) => <span className="text-foreground font-medium whitespace-nowrap">${(row.original.total ?? 0).toFixed(2)}</span>,
    },
    {
      accessorKey: "status",
      header: ({ column }: any) => <DataTableColumnHeader column={column} title="Status" />,
      cell: ({ row }: any) => (
        <span className={`inline-flex px-2 py-1 rounded-full text-xs font-medium whitespace-nowrap ${(row.original.status && STATUS_BADGE[row.original.status]) || ""}`}>
          {row.original.status_label}
        </span>
      ),
    },
    {
      id: "actions",
      cell: ({ row }: any) => {
        const booking: BookingRecord = row.original;
        return (
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="ghost" className="h-8 w-8 p-0"><MoreHorizontal className="h-4 w-4" /><span className="sr-only">Open menu</span></Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuItem>
                <Link href={route("dashboard.my-bookings.show", booking.id)} className="flex items-center w-full">
                  <Eye className="mr-2 h-4 w-4" /> View Details
                </Link>
              </DropdownMenuItem>
              <DropdownMenuSeparator />
              {(!allowedCancelStatuses || allowedCancelStatuses.includes(booking.status ?? "")) && (
                <DropdownMenuItem>
                  <button type="button" className="flex items-center w-full cursor-pointer text-destructive"
                    onClick={() => handleCancel(booking.id)}
                    disabled={processingTransition === `${booking.id}-cancel`}>
                    <Calendar className="mr-2 h-4 w-4" />
                    Cancel Booking
                  </button>
                </DropdownMenuItem>
              )}
            </DropdownMenuContent>
          </DropdownMenu>
        );
      },
    },
  ];

  return (
    <AuthenticatedLayout header="My Bookings">
      <div className="space-y-6">
        <Breadcrumb>
          <BreadcrumbList>
            <BreadcrumbItem><BreadcrumbLink href="/dashboard">Dashboard</BreadcrumbLink></BreadcrumbItem>
            <BreadcrumbSeparator />
            <BreadcrumbItem><BreadcrumbPage>My Bookings</BreadcrumbPage></BreadcrumbItem>
          </BreadcrumbList>
        </Breadcrumb>

        <div className="flex items-center justify-between">
          <div className="flex items-center gap-3">
            <CalendarDays className="h-6 w-6 text-foreground shrink-0" />
            <div>
              <h2 className="text-3xl font-bold tracking-tight text-foreground">My Bookings</h2>
              <p className="text-muted-foreground">Your upcoming and past stays</p>
            </div>
          </div>
        </div>

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

        <DataTable columns={columns} data={filteredBookings} searchKey="property_title"
          searchPlaceholder="Search by property name..." defaultPageSize={20} />
      </div>

      <AlertDialog open={cancelBookingId !== null} onOpenChange={(open) => { if (!open) setCancelBookingId(null); }}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Cancel this booking?</AlertDialogTitle>
            <AlertDialogDescription>
              This will cancel the booking and notify the host.
              {cancelBookingRefund && <span className="block mt-2 text-amber-600 dark:text-amber-400 font-medium">{cancelBookingRefund}</span>}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel onClick={() => setCancelBookingId(null)}>No, keep booking</AlertDialogCancel>
            <AlertDialogAction variant="destructive" disabled={processingTransition !== null} onClick={confirmCancel}>
              Yes, cancel booking
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </AuthenticatedLayout>
  );
}
