import { usePage, Link } from "@inertiajs/react"
import { Head } from "@inertiajs/react"
import { Building2, Plus, List, Tags } from "lucide-react"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card"
import { Button } from "@/Components/ui/button"
import { Badge } from "@/Components/ui/badge"
import { PropertyStats } from "../components/PropertyStats"

interface StatData {
  total: number
  published: number
  draft: number
  totalValue: number
}

interface RecentProperty {
  id: number
  name: string
  status: string
  price: number
  created_at: string
}

interface PageProps {
  stats: StatData
  recentProperties: RecentProperty[]
}

export default function PropertiesDashboard() {
  const { props } = usePage<{ stats: StatData; recentProperties: RecentProperty[] }>()
  const { stats, recentProperties } = props

  return (
    <AuthenticatedLayout header="Properties Dashboard">
      <Head title="Properties Dashboard" />

      <div className="space-y-6">
        <PropertyStats total={stats.total} published={stats.published} draft={stats.draft} totalValue={stats.totalValue} />

        <div className="grid gap-6 lg:grid-cols-2">
          <Card>
            <CardHeader>
              <CardTitle className="text-lg">Recent Properties</CardTitle>
            </CardHeader>
            <CardContent>
              {recentProperties.length === 0 ? (
                <p className="text-sm text-muted-foreground">No properties yet.</p>
              ) : (
                <div className="space-y-3">
                  {recentProperties.map((p) => (
                    <Link
                      key={p.id}
                      href={route("dashboard.properties.edit", p.id)}
                      className="flex items-center justify-between rounded-lg border p-3 transition-colors hover:bg-muted/50"
                    >
                      <span className="text-sm font-medium truncate">{p.name}</span>
                      <Badge variant={p.status === "published" ? "default" : "secondary"}>{p.status}</Badge>
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
              <Button variant="outline" className="w-full justify-start" asChild>
                <Link href={route("dashboard.properties.create")}>
                  <Plus className="mr-2 h-4 w-4" />
                  Add Property
                </Link>
              </Button>
              <Button variant="outline" className="w-full justify-start" asChild>
                <Link href={route("dashboard.properties.index")}>
                  <List className="mr-2 h-4 w-4" />
                  All Properties
                </Link>
              </Button>
              <Button variant="outline" className="w-full justify-start" asChild>
                <Link href={route("dashboard.properties.terms.types.index")}>
                  <Tags className="mr-2 h-4 w-4" />
                  Manage Types
                </Link>
              </Button>
            </CardContent>
          </Card>
        </div>
      </div>
    </AuthenticatedLayout>
  )
}
