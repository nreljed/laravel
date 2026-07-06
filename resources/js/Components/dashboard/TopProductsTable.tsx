import { Link } from "@inertiajs/react";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Package } from "lucide-react";

interface TopProduct {
  product_id: number;
  product_name: string;
  total_qty: number;
  total_revenue: number;
}

interface TopProductsTableProps {
  products: TopProduct[];
}

const currency = new Intl.NumberFormat("en-US", { style: "currency", currency: "EUR" });

export default function TopProductsTable({ products }: TopProductsTableProps) {
  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <Package className="h-4 w-4" />
          Top Products
        </CardTitle>
      </CardHeader>
      <CardContent className="p-0">
        {products.length === 0 ? (
          <div className="py-8 text-center text-sm text-muted-foreground">
            No products sold yet.
          </div>
        ) : (
          <div className="divide-y">
            {products.map((product, i) => (
              <div
                key={product.product_id}
                className="flex items-center justify-between px-6 py-3 text-sm"
              >
                <div className="flex items-center gap-3 min-w-0">
                  <span className="text-xs text-muted-foreground w-4 shrink-0">{i + 1}</span>
                  <Link
                    href={route("dashboard.shop.products.edit", product.product_id)}
                    className="font-medium hover:underline truncate"
                  >
                    {product.product_name}
                  </Link>
                </div>
                <div className="flex items-center gap-4 shrink-0">
                  <span className="text-muted-foreground text-xs">{product.total_qty} sold</span>
                  <span className="font-medium tabular-nums">
                    {currency.format(product.total_revenue)}
                  </span>
                </div>
              </div>
            ))}
          </div>
        )}
      </CardContent>
    </Card>
  );
}
