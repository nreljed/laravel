import { usePage, Link } from "@inertiajs/react"
import { Head } from "@inertiajs/react"
import { Plus, Calendar, List } from "lucide-react"
import type { PageProps as AppPageProps } from "@nreljed/platform/types"
import AuthenticatedLayout from "@nreljed/platform/Layouts/AuthenticatedLayout"
import { Card, CardContent, CardHeader, CardTitle } from "@nreljed/platform/Components/ui/card"
import { Button } from "@nreljed/platform/Components/ui/button"
import { Badge } from "@nreljed/platform/Components/ui/badge"
import { BookingStats } from "../components/BookingStats"

interface BookingStatsData {
  total: number
  pending: number
  confirmed: number
  checked_in: number
  cancelled: number
  total_revenue: number
  upcoming: number
}

interface RecentBooking {
  id: number
  status: string
  guest_name: string
  start_date: string
  end_date: string
  total_amount: number
  currency: string
  property_id: number
  property?: { name: string }
  created_at: string
}

type PageProps = AppPageProps<{
  stats: BookingStatsData
  todayCheckIns: number
  todayCheckOuts: number
  propertiesCount: number
  recentBookings: RecentBooking[]
}>

export default function BookingsDashboard() {
  const { stats, todayCheckIns, todayCheckOuts, propertiesCount, recentBookings } = usePage<PageProps>().props

  return (
    <AuthenticatedLayout header="Bookings Dashboard">
      <Head title="Bookings Dashboard" />

      <div className="space-y-6">
        <BookingStats
          total={stats.total}
          pending={stats.pending}
          confirmed={stats.confirmed}
          checked_in={stats.checked_in}
          cancelled={stats.cancelled}
          total_revenue={stats.total_revenue}
          upcoming={stats.upcoming}
        />

        <div className="grid gap-4 sm:grid-cols-3">
          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-medium text-muted-foreground">Today Check-ins</CardTitle>
            </CardHeader>
            <CardContent>
              <p className="text-2xl font-bold">{todayCheckIns}</p>
            </CardContent>
          </Card>
          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-medium text-muted-foreground">Today Check-outs</CardTitle>
            </CardHeader>
            <CardContent>
              <p className="text-2xl font-bold">{todayCheckOuts}</p>
            </CardContent>
          </Card>
          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-medium text-muted-foreground">Properties</CardTitle>
            </CardHeader>
            <CardContent>
              <p className="text-2xl font-bold">{propertiesCount}</p>
            </CardContent>
          </Card>
        </div>

        <div className="grid gap-6 lg:grid-cols-2">
          <Card>
            <CardHeader>
              <CardTitle className="text-lg">Recent Bookings</CardTitle>
            </CardHeader>
            <CardContent>
              {recentBookings.length === 0 ? (
                <p className="text-sm text-muted-foreground">No bookings yet.</p>
              ) : (
                <div className="space-y-3">
                  {recentBookings.map((b) => (
                    <Link
                      key={b.id}
                      href={route("dashboard.bookings.show", b.id)}
                      className="flex items-center justify-between rounded-lg border p-3 transition-colors hover:bg-muted/50"
                    >
                      <div className="min-w-0 flex-1">
                        <p className="text-sm font-medium truncate">{b.guest_name}</p>
                        <p className="text-xs text-muted-foreground">{b.property?.name ?? `Property #${b.property_id}`}</p>
                      </div>
                      <Badge variant={b.status === "confirmed" ? "default" : b.status === "cancelled" ? "destructive" : "secondary"}>
                        {b.status}
                      </Badge>
                    </Link>
                  ))}
                </div>
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle className="text-lg">Quick Actions</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
              <Button render={<Link href={route("dashboard.bookings.create")} />} variant="outline" className="w-full justify-start">
                <Plus className="mr-2 h-4 w-4" />
                New Booking
              </Button>
              <Button render={<Link href={route("dashboard.bookings.calendar")} />} variant="outline" className="w-full justify-start">
                <Calendar className="mr-2 h-4 w-4" />
                View Calendar
              </Button>
              <Button render={<Link href={route("dashboard.bookings.index")} />} variant="outline" className="w-full justify-start">
                <List className="mr-2 h-4 w-4" />
                All Bookings
              </Button>
            </CardContent>
          </Card>
        </div>
      </div>
    </AuthenticatedLayout>
  )
}
