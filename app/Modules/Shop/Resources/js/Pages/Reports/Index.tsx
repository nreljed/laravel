import { Head, Link, router } from "@inertiajs/react";
import { DollarSign, ShoppingCart, TrendingUp, Package, BarChart3 } from "lucide-react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Button } from "@/Components/ui/button";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/Components/ui/table";

interface SalesSummary {
  total_revenue: number;
  total_orders: number;
  avg_order_value: number;
  total_products_sold: number;
}

interface SalesDay {
  date: string;
  orders: number;
  revenue: number;
}

interface TopProduct {
  product_id: number;
  product_name: string;
  total_qty: number;
  total_revenue: number;
}

interface Props {
  summary: SalesSummary;
  salesOverTime: SalesDay[];
  topProducts: TopProduct[];
  range: string;
}

const ranges = [
  { value: "7", label: "7 Days" },
  { value: "30", label: "30 Days" },
  { value: "90", label: "90 Days" },
  { value: "365", label: "1 Year" },
];

export default function ReportsIndex({ summary, salesOverTime, topProducts, range }: Props) {
  const maxRevenue = Math.max(...salesOverTime.map((d) => d.revenue), 1);

  return (
    <AuthenticatedLayout header="Reports">
      <Head title="Shop Reports" />

      <div className="space-y-6">
        <div className="flex gap-2">
          {ranges.map((r) => (
            <Link key={r.value} href={route("dashboard.shop.reports.index", { range: r.value })}>
              <Button variant={range === r.value ? "default" : "outline"} size="sm">{r.label}</Button>
            </Link>
          ))}
        </div>

        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <Card>
            <CardHeader className="flex flex-row items-center justify-between pb-2">
              <CardTitle className="text-sm font-medium">Total Revenue</CardTitle>
              <DollarSign className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">
                {new Intl.NumberFormat("en-US", { style: "currency", currency: "EUR" }).format(summary.total_revenue)}
              </div>
            </CardContent>
          </Card>
          <Card>
            <CardHeader className="flex flex-row items-center justify-between pb-2">
              <CardTitle className="text-sm font-medium">Total Orders</CardTitle>
              <ShoppingCart className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{summary.total_orders}</div>
            </CardContent>
          </Card>
          <Card>
            <CardHeader className="flex flex-row items-center justify-between pb-2">
              <CardTitle className="text-sm font-medium">Avg Order Value</CardTitle>
              <TrendingUp className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">
                {new Intl.NumberFormat("en-US", { style: "currency", currency: "EUR" }).format(summary.avg_order_value)}
              </div>
            </CardContent>
          </Card>
          <Card>
            <CardHeader className="flex flex-row items-center justify-between pb-2">
              <CardTitle className="text-sm font-medium">Products Sold</CardTitle>
              <Package className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{summary.total_products_sold}</div>
            </CardContent>
          </Card>
        </div>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <BarChart3 className="h-4 w-4" />
              Sales Over Time
            </CardTitle>
          </CardHeader>
          <CardContent>
            {salesOverTime.length === 0 ? (
              <p className="text-sm text-muted-foreground text-center py-8">No sales data for this period.</p>
            ) : (
              <div className="space-y-2">
                {salesOverTime.map((day) => (
                  <div key={day.date} className="flex items-center gap-3 text-sm">
                    <span className="w-20 shrink-0 text-muted-foreground">{day.date}</span>
                    <div className="flex-1 h-5 bg-muted rounded-sm overflow-hidden">
                      <div
                        className="h-full bg-primary rounded-sm transition-all"
                        style={{ width: `${(day.revenue / maxRevenue) * 100}%` }}
                      />
                    </div>
                    <span className="w-20 text-right shrink-0 font-medium tabular-nums">
                      {new Intl.NumberFormat("en-US", { style: "currency", currency: "EUR" }).format(day.revenue)}
                    </span>
                    <span className="w-12 text-right shrink-0 text-muted-foreground tabular-nums">
                      {day.orders} orders
                    </span>
                  </div>
                ))}
              </div>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Top Products</CardTitle>
          </CardHeader>
          <CardContent className="p-0">
            {topProducts.length === 0 ? (
              <div className="py-8 text-center text-sm text-muted-foreground">No products sold in this period.</div>
            ) : (
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>#</TableHead>
                    <TableHead>Product</TableHead>
                    <TableHead className="text-right">Qty Sold</TableHead>
                    <TableHead className="text-right">Revenue</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {topProducts.map((p, i) => (
                    <TableRow key={p.product_id}>
                      <TableCell className="text-muted-foreground text-xs">{i + 1}</TableCell>
                      <TableCell className="font-medium">
                        <Link href={route("dashboard.shop.products.edit", p.product_id)} className="hover:underline">
                          {p.product_name}
                        </Link>
                      </TableCell>
                      <TableCell className="text-right tabular-nums">{p.total_qty}</TableCell>
                      <TableCell className="text-right tabular-nums font-medium">
                        {new Intl.NumberFormat("en-US", { style: "currency", currency: "EUR" }).format(p.total_revenue)}
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            )}
          </CardContent>
        </Card>
      </div>
    </AuthenticatedLayout>
  );
}
