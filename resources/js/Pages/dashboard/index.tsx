import { useMemo } from "react";
import { Link, Head } from "@inertiajs/react";
import {
  Users,
  TrendingUp,
  FileText,
  UserPlus,
  DollarSign,
  ShoppingCart,
  BarChart3,
  Package,
} from "lucide-react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Button } from "@/Components/ui/button";
import { useAuth } from "@/hooks/use-auth";
import KpiCard from "@/Components/dashboard/KpiCard";
import RevenueChart from "@/Components/dashboard/RevenueChart";
import RecentOrdersTable from "@/Components/dashboard/RecentOrdersTable";
import TopProductsTable from "@/Components/dashboard/TopProductsTable";

interface StatData {
  label: string;
  icon?: string;
  total?: number;
  published?: number;
  draft?: number;
  value?: number;
}

interface AnalyticsData {
  kpis: Array<{
    label: string;
    value: number;
    format?: string;
    change?: number;
    icon: string;
  }>;
  revenue_over_time: Array<{
    date: string;
    revenue: number;
    orders: number;
  }>;
  recent_orders: Array<{
    id: number;
    order_number: string;
    customer_name: string;
    total: number;
    status: string;
    items_count: number;
    created_at: string;
  }>;
  top_products: Array<{
    product_id: number;
    product_name: string;
    total_qty: number;
    total_revenue: number;
  }>;
  order_status_counts: Record<string, number>;
  total_revenue: number;
  total_orders: number;
  total_products: number;
  total_customers: number;
  abandoned_carts: number;
  recovered_carts: number;
}

interface DashboardIndexProps {
  stats?: Record<string, StatData>;
  analytics?: AnalyticsData;
}

const iconMap: Record<string, React.ComponentType<{ className?: string }>> = {
  Users,
  TrendingUp,
  FileText,
  UserPlus,
  DollarSign,
  ShoppingCart,
  BarChart3,
  Package,
};

export default function DashboardIndex({ stats = {}, analytics }: DashboardIndexProps) {
  const { user } = useAuth();

  const statCards = useMemo(
    () =>
      Object.entries(stats).map(([key, data]) => {
        const Icon = iconMap[data.icon || ""] || FileText;
        return {
          key,
          title: data.label || key,
          icon: Icon,
          value: data.total ?? data.value ?? 0,
        };
      }),
    [stats],
  );

  const analyticsKpis = analytics?.kpis ?? [];

  const formatKpiValue = (val: number, fmt?: string) => {
    if (fmt === "currency")
      return new Intl.NumberFormat("en-US", { style: "currency", currency: "EUR" }).format(val);
    if (fmt === "percent") return `${val}%`;
    return val.toLocaleString();
  };

  return (
    <AuthenticatedLayout header="Dashboard">
      <Head title="Dashboard" />

      <div className="space-y-6">
        <div>
          <h2 className="text-2xl font-bold tracking-tight">
            Welcome back, {user?.name || "User"}!
          </h2>
          <p className="text-muted-foreground">Here's what's happening with your platform today.</p>
        </div>

        {/* Analytics KPIs */}
        {analyticsKpis.length > 0 && (
          <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
            {analyticsKpis.map((kpi, i) => {
              const Icon = iconMap[kpi.icon] || DollarSign;
              return (
                <KpiCard
                  key={i}
                  title={kpi.label}
                  value={formatKpiValue(kpi.value, kpi.format)}
                  change={kpi.change}
                  icon={Icon}
                />
              );
            })}
          </div>
        )}

        {/* Module Stats */}
        {statCards.length > 0 && (
          <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            {statCards.map((card) => {
              const Icon = card.icon;
              return (
                <Card key={card.key}>
                  <CardHeader className="flex flex-row items-center justify-between pb-2">
                    <CardTitle className="text-sm font-medium">{card.title}</CardTitle>
                    <Icon className="h-4 w-4 text-muted-foreground" />
                  </CardHeader>
                  <CardContent>
                    <p className="text-3xl font-bold">{card.value}</p>
                  </CardContent>
                </Card>
              );
            })}
          </div>
        )}

        {/* Revenue Chart + Order Status */}
        {analytics && analytics.revenue_over_time.length > 0 && (
          <div className="grid gap-6 lg:grid-cols-2">
            <RevenueChart data={analytics.revenue_over_time} />
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <BarChart3 className="h-4 w-4" />
                  Order Status
                </CardTitle>
              </CardHeader>
              <CardContent>
                {Object.keys(analytics.order_status_counts).length === 0 ? (
                  <p className="text-sm text-muted-foreground text-center py-8">No orders yet.</p>
                ) : (
                  <div className="space-y-2">
                    {Object.entries(analytics.order_status_counts).map(([status, count]) => (
                      <div key={status} className="flex items-center justify-between text-sm">
                        <span className="capitalize text-muted-foreground">{status}</span>
                        <span className="font-medium tabular-nums">{count}</span>
                      </div>
                    ))}
                  </div>
                )}
              </CardContent>
            </Card>
          </div>
        )}

        {/* Recent Orders + Top Products */}
        {analytics && (
          <div className="grid gap-6 lg:grid-cols-2">
            <RecentOrdersTable orders={analytics.recent_orders} />
            <TopProductsTable products={analytics.top_products} />
          </div>
        )}

        {/* Platform Summary + Quick Actions */}
        <div className="grid gap-4 md:grid-cols-2">
          <Card>
            <CardHeader>
              <CardTitle>Platform Summary</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2">
              {analytics && (
                <>
                  <div className="flex justify-between text-sm">
                    <span className="text-muted-foreground">Total Revenue</span>
                    <span className="font-medium tabular-nums">
                      {new Intl.NumberFormat("en-US", {
                        style: "currency",
                        currency: "EUR",
                      }).format(analytics.total_revenue)}
                    </span>
                  </div>
                  <div className="flex justify-between text-sm">
                    <span className="text-muted-foreground">Total Orders</span>
                    <span className="font-medium tabular-nums">{analytics.total_orders}</span>
                  </div>
                  <div className="flex justify-between text-sm">
                    <span className="text-muted-foreground">Total Products</span>
                    <span className="font-medium tabular-nums">{analytics.total_products}</span>
                  </div>
                  <div className="flex justify-between text-sm">
                    <span className="text-muted-foreground">Total Customers</span>
                    <span className="font-medium tabular-nums">{analytics.total_customers}</span>
                  </div>
                </>
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Quick Actions</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2">
              <Button asChild className="w-full">
                <Link href={route("dashboard.shop.products.create")}>
                  <Package className="mr-2 h-4 w-4" />
                  Add Product
                </Link>
              </Button>
              <Button asChild variant="outline" className="w-full">
                <Link href={route("dashboard.shop.orders.index")}>
                  <ShoppingCart className="mr-2 h-4 w-4" />
                  View Orders
                </Link>
              </Button>
              <Button asChild variant="outline" className="w-full">
                <Link href={route("dashboard.shop.reports")}>
                  <BarChart3 className="mr-2 h-4 w-4" />
                  View Reports
                </Link>
              </Button>
            </CardContent>
          </Card>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
