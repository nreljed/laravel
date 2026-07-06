import { usePage, Link } from "@inertiajs/react"
import { Head } from "@inertiajs/react"
import {
  Package, ShoppingCart, DollarSign, TrendingUp, BarChart3, Users, AlertTriangle,
  Plus, List, RefreshCw, FileText
} from "lucide-react"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card"
import { Button } from "@/Components/ui/button"
import { Badge } from "@/Components/ui/badge"
import { useFormatting } from "@/hooks/use-formatting"

interface Analytics {
  kpis: Array<{ label: string; value: number; format: string; change: number; icon: string }>
  recent_orders: Array<{ id: number; order_number: string; customer_name: string; total: number; status: string; items_count: number; created_at: string }>
  total_revenue: number
  total_orders: number
  total_products: number
  total_customers: number
  abandoned_carts: number
  recovered_carts: number
}

export default function ShopDashboard() {
  const { analytics, lowStockCount } = usePage<{ analytics: Analytics; lowStockCount: number }>().props
  const { formatPrice } = useFormatting()

  return (
    <AuthenticatedLayout header="Shop Dashboard">
      <Head title="Shop Dashboard" />

      <div className="space-y-6">
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
          {analytics.kpis.map((kpi) => (
            <Card key={kpi.label}>
              <CardHeader className="pb-2">
                <CardTitle className="text-sm font-medium text-muted-foreground">{kpi.label}</CardTitle>
              </CardHeader>
              <CardContent>
                <p className="text-2xl font-bold">
                  {kpi.format === "currency" ? formatPrice(kpi.value) :
                   kpi.format === "percent" ? `${kpi.value}%` :
                   kpi.value}
                </p>
                <p className={`text-xs ${kpi.change >= 0 ? "text-green-600" : "text-red-600"}`}>
                  {kpi.change >= 0 ? "+" : ""}{kpi.change.toFixed(1)}%
                </p>
              </CardContent>
            </Card>
          ))}
        </div>

        {lowStockCount > 0 && (
          <Card className="border-amber-300 bg-amber-50 dark:border-amber-800 dark:bg-amber-950">
            <CardContent className="flex items-center gap-3 pt-4">
              <AlertTriangle className="h-5 w-5 text-amber-600" />
              <span className="text-sm font-medium text-amber-800 dark:text-amber-200">
                {lowStockCount} product(s) running low on stock.
              </span>
              <Button variant="outline" size="sm" asChild className="ml-auto">
                <Link href={route("dashboard.shop.products.index")}>View Products</Link>
              </Button>
            </CardContent>
          </Card>
        )}

        <div className="grid gap-6 lg:grid-cols-2">
          <Card>
            <CardHeader>
              <CardTitle className="text-lg">Recent Orders</CardTitle>
            </CardHeader>
            <CardContent>
              {analytics.recent_orders.length === 0 ? (
                <p className="text-sm text-muted-foreground">No orders yet.</p>
              ) : (
                <div className="space-y-3">
                  {analytics.recent_orders.map((o) => (
                    <Link
                      key={o.id}
                      href={route("dashboard.shop.orders.show", o.id)}
                      className="flex items-center justify-between rounded-lg border p-3 transition-colors hover:bg-muted/50"
                    >
                      <div className="min-w-0 flex-1">
                        <p className="text-sm font-medium">{o.customer_name}</p>
                        <p className="text-xs text-muted-foreground">
                          {o.order_number} · {o.items_count} item(s)
                        </p>
                      </div>
                      <div className="text-right">
                        <p className="text-sm font-medium tabular-nums">{formatPrice(o.total)}</p>
                        <Badge variant="secondary" className="text-xs">{o.status}</Badge>
                      </div>
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
                <Link href={route("dashboard.shop.products.create")}>
                  <Plus className="mr-2 h-4 w-4" />
                  Add Product
                </Link>
              </Button>
              <Button variant="outline" className="w-full justify-start" asChild>
                <Link href={route("dashboard.shop.products.index")}>
                  <Package className="mr-2 h-4 w-4" />
                  All Products
                </Link>
              </Button>
              <Button variant="outline" className="w-full justify-start" asChild>
                <Link href={route("dashboard.shop.orders.index")}>
                  <ShoppingCart className="mr-2 h-4 w-4" />
                  All Orders
                </Link>
              </Button>
              <Button variant="outline" className="w-full justify-start" asChild>
                <Link href={route("dashboard.shop.customers.index")}>
                  <Users className="mr-2 h-4 w-4" />
                  Customers
                </Link>
              </Button>
            </CardContent>
          </Card>
        </div>

        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-xs font-medium text-muted-foreground">Total Revenue</CardTitle>
            </CardHeader>
            <CardContent>
              <p className="text-xl font-bold tabular-nums">{formatPrice(analytics.total_revenue)}</p>
            </CardContent>
          </Card>
          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-xs font-medium text-muted-foreground">Total Orders</CardTitle>
            </CardHeader>
            <CardContent>
              <p className="text-xl font-bold">{analytics.total_orders}</p>
            </CardContent>
          </Card>
          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-xs font-medium text-muted-foreground">Total Products</CardTitle>
            </CardHeader>
            <CardContent>
              <p className="text-xl font-bold">{analytics.total_products}</p>
            </CardContent>
          </Card>
          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-xs font-medium text-muted-foreground">Total Customers</CardTitle>
            </CardHeader>
            <CardContent>
              <p className="text-xl font-bold">{analytics.total_customers}</p>
            </CardContent>
          </Card>
        </div>
      </div>
    </AuthenticatedLayout>
  )
}
