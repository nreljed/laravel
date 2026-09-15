import { Link, router } from "@inertiajs/react";
import { formatDate } from "@nreljed/platform/lib/utils";
import { useFormatDate } from "@nreljed/platform/hooks/use-format-date";
import AuthenticatedLayout from "@nreljed/platform/Layouts/AuthenticatedLayout";
import { Card, CardContent, CardHeader, CardTitle } from "@nreljed/platform/Components/ui/card";
import { Button } from "@nreljed/platform/Components/ui/button";
import { DataTable } from "@nreljed/platform/Components/ui/data-table";
import { DataTableColumnHeader } from "@nreljed/platform/Components/ui/data-table-column-header";
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "@nreljed/platform/Components/ui/breadcrumb";
import { User, CalendarCheck, DollarSign, TrendingUp, Home, ArrowLeft, Repeat, History } from "lucide-react";

const STATUS_BADGE: Record<string, string> = {
  pending: "bg-amber-50 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300",
  confirmed: "bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200",
  checked_in: "bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200",
  checked_out: "bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200",
  cancelled: "bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200",
};

interface GuestData {
  email: string;
  name: string;
  total_stays: number;
  total_spend: number;
  total_nights: number;
  average_value: number;
  avg_rating: number | null;
}

interface BookingRow {
  id: number;
  status: string;
  status_label: string;
  property_title: string;
  check_in: string;
  check_out: string;
  nights: number;
  total: number;
  created_at: string;
}

interface GuestProfileProps {
  guest: GuestData;
  bookings: BookingRow[];
}

export default function GuestProfile({ guest, bookings }: GuestProfileProps) {
  const { dateFormat } = useFormatDate();
  const columns: any[] = [
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
      accessorKey: "property_title",
      header: ({ column }: any) => <DataTableColumnHeader column={column} title="Property" />,
      cell: ({ row }: any) => <span className="text-foreground truncate max-w-[200px] block">{row.original.property_title || "-"}</span>,
    },
    {
      accessorKey: "check_in",
      header: ({ column }: any) => <DataTableColumnHeader column={column} title="Check-in" />,
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
      cell: ({ row }: any) => <span className="text-foreground font-medium whitespace-nowrap">${Number(row.original.total || 0).toFixed(2)}</span>,
    },
    {
      accessorKey: "status",
      header: ({ column }: any) => <DataTableColumnHeader column={column} title="Status" />,
      cell: ({ row }: any) => (
        <span className={`inline-flex px-2 py-1 rounded-full text-xs font-medium whitespace-nowrap ${STATUS_BADGE[row.original.status] || ""}`}>
          {row.original.status_label}
        </span>
      ),
    },
  ];

  return (
    <AuthenticatedLayout header="Guest Profile">
      <div className="space-y-6">
        <Breadcrumb>
          <BreadcrumbList>
            <BreadcrumbItem><BreadcrumbLink href="/dashboard">Dashboard</BreadcrumbLink></BreadcrumbItem>
            <BreadcrumbSeparator />
            <BreadcrumbItem><BreadcrumbLink href={route("dashboard.bookings.index")}>Bookings</BreadcrumbLink></BreadcrumbItem>
            <BreadcrumbSeparator />
            <BreadcrumbItem><BreadcrumbPage>Guest: {guest.email}</BreadcrumbPage></BreadcrumbItem>
          </BreadcrumbList>
        </Breadcrumb>

        <Button variant="outline" size="sm" onClick={() => router.visit(route("dashboard.bookings.index"))}>
          <ArrowLeft className="mr-1.5 h-4 w-4" /> Back to Bookings
        </Button>

        <Card>
          <CardHeader className="pb-3">
            <CardTitle className="flex items-center gap-2 text-lg">
              <User className="h-5 w-5 text-muted-foreground" /> Guest Overview
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="flex items-center gap-3 mb-4">
              <div className="size-12 rounded-full bg-muted flex items-center justify-center text-lg font-semibold text-foreground">
                {(guest.name || guest.email).charAt(0).toUpperCase()}
              </div>
              <div>
                <p className="text-base font-semibold text-foreground">{guest.name}</p>
                <p className="text-sm text-muted-foreground">{guest.email}</p>
              </div>
            </div>

            <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
              <div className="rounded-lg border border-border p-3 space-y-1">
                <div className="flex items-center gap-1.5 text-xs text-muted-foreground"><CalendarCheck className="h-3.5 w-3.5" />Total Stays</div>
                <p className="text-xl font-bold text-foreground">{guest.total_stays}</p>
              </div>
              <div className="rounded-lg border border-border p-3 space-y-1">
                <div className="flex items-center gap-1.5 text-xs text-muted-foreground"><DollarSign className="h-3.5 w-3.5" />Total Spend</div>
                <p className="text-xl font-bold text-foreground">${(guest.total_spend || 0).toLocaleString()}</p>
              </div>
              <div className="rounded-lg border border-border p-3 space-y-1">
                <div className="flex items-center gap-1.5 text-xs text-muted-foreground"><TrendingUp className="h-3.5 w-3.5" />Avg Booking</div>
                <p className="text-xl font-bold text-foreground">${(guest.average_value || 0).toLocaleString()}</p>
              </div>
              <div className="rounded-lg border border-border p-3 space-y-1">
                <div className="flex items-center gap-1.5 text-xs text-muted-foreground"><Home className="h-3.5 w-3.5" />Total Nights</div>
                <p className="text-xl font-bold text-foreground">{guest.total_nights}</p>
              </div>
            </div>

            {guest.total_stays > 1 && (
              <div className="mt-4 flex items-center gap-2 text-sm text-muted-foreground">
                <Repeat className="h-4 w-4 text-purple-500" /> Returning guest &mdash; stayed {guest.total_stays} times
              </div>
            )}
          </CardContent>
        </Card>

        <div className="space-y-3">
          <h3 className="text-lg font-semibold text-foreground flex items-center gap-2">
            <History className="h-4 w-4 text-muted-foreground" /> Booking History
          </h3>
          <DataTable columns={columns} data={bookings} searchKey="property_title" searchPlaceholder="Search by property..." defaultPageSize={20} />
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
