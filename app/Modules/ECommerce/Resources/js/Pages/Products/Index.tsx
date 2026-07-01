import { Head, Link, router } from "@inertiajs/react";
import { Plus, Edit, ShoppingBag, Search, X } from "lucide-react";
import { useState } from "react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Button } from "@/Components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Empty, EmptyHeader, EmptyTitle, EmptyDescription, EmptyContent, EmptyMedia } from "@/Components/ui/empty";
import { Badge } from "@/Components/ui/badge";
import { Input } from "@/Components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/Components/ui/select";
import {
  Pagination,
  PaginationContent,
  PaginationItem,
  PaginationLink,
  PaginationNext,
  PaginationPrevious,
} from "@/Components/ui/pagination";

interface Category {
  id: number;
  name: string;
}

interface Product {
  id: number;
  name: string;
  slug: string;
  sku: string | null;
  price: number;
  compare_price: number | null;
  stock_quantity: number;
  low_stock_threshold: number;
  track_stock: boolean;
  status: string;
  type: string;
  is_featured: boolean;
  thumbnail_url: string | null;
  images: { url: string; path: string }[] | null;
  categories: Category[];
}

interface ProductsIndexProps {
  products: { data: Product[]; current_page: number; last_page: number; links: { url: string | null; label: string; active: boolean }[] };
  categories: Category[];
  filters: { search?: string; status?: string; category_id?: string };
}

function statusBadgeVariant(status: string) {
  switch (status) {
    case "published": return "default" as const;
    case "draft": return "secondary" as const;
    case "archived": return "outline" as const;
    default: return "secondary" as const;
  }
}

export default function ProductsIndex({ products, categories, filters }: ProductsIndexProps) {
  const [search, setSearch] = useState(filters.search || "");

  const applyFilter = (key: string, value: string) => {
    const params = { ...filters, [key]: value || undefined };
    router.get(route("dashboard.ecommerce.products.index"), params, { preserveState: true, replace: true });
  };

  const clearFilters = () => {
    setSearch("");
    router.get(route("dashboard.ecommerce.products.index"), {}, { preserveState: true, replace: true });
  };

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    applyFilter("search", search);
  };

  const hasFilters = filters.search || filters.status || filters.category_id;

  return (
    <AuthenticatedLayout header="Products">
      <Head title="Products" />

      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <h2 className="text-lg font-semibold">Products</h2>
          <Link href={route("dashboard.ecommerce.products.create")}>
            <Button>
              <Plus className="mr-1 h-4 w-4" /> Add Product
            </Button>
          </Link>
        </div>

        <div className="flex flex-wrap items-center gap-3">
          <form onSubmit={handleSearch} className="flex items-center gap-2">
            <div className="relative">
              <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
              <Input
                placeholder="Search name or SKU..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                className="w-60 pl-8"
              />
            </div>
            <Button type="submit" variant="secondary" size="sm">Search</Button>
          </form>

          <Select
            value={filters.status || ""}
            onValueChange={(v) => applyFilter("status", v)}
          >
            <SelectTrigger className="w-36">
              <SelectValue placeholder="All statuses" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="">All statuses</SelectItem>
              <SelectItem value="draft">Draft</SelectItem>
              <SelectItem value="published">Published</SelectItem>
              <SelectItem value="archived">Archived</SelectItem>
            </SelectContent>
          </Select>

          <Select
            value={filters.category_id || ""}
            onValueChange={(v) => applyFilter("category_id", v)}
          >
            <SelectTrigger className="w-44">
              <SelectValue placeholder="All categories" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="">All categories</SelectItem>
              {categories.map((cat) => (
                <SelectItem key={cat.id} value={String(cat.id)}>{cat.name}</SelectItem>
              ))}
            </SelectContent>
          </Select>

          {hasFilters && (
            <Button variant="ghost" size="sm" onClick={clearFilters}>
              <X className="mr-1 h-3 w-3" /> Clear
            </Button>
          )}
        </div>

        {products.data.length === 0 ? (
          <Empty>
            <EmptyHeader>
              <EmptyMedia variant="icon"><ShoppingBag /></EmptyMedia>
              <EmptyTitle>{hasFilters ? "No products found" : "No products yet"}</EmptyTitle>
              <EmptyDescription>
                {hasFilters
                  ? "No products match your filters. Try adjusting your search or filter criteria."
                  : "Get started by creating your first product."}
              </EmptyDescription>
            </EmptyHeader>
            {!hasFilters && (
              <EmptyContent>
                <Button onClick={() => router.visit(route("dashboard.ecommerce.products.create"))}>
                  <Plus className="mr-2 h-4 w-4" /> Create Product
                </Button>
              </EmptyContent>
            )}
          </Empty>
        ) : (
          <>
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
              {products.data.map((product) => (
                <Card key={product.id} className="overflow-hidden">
                  <div className="aspect-video bg-muted flex items-center justify-center">
                    {product.thumbnail_url ? (
                      <img src={product.thumbnail_url} alt={product.name} className="h-full w-full object-cover" />
                    ) : (
                      <ShoppingBag className="h-8 w-8 text-muted-foreground/50" />
                    )}
                  </div>
                  <CardHeader className="pb-2">
                    <div className="flex items-start justify-between gap-2">
                      <CardTitle className="text-sm font-medium leading-tight line-clamp-2">{product.name}</CardTitle>
                      <Link href={route("dashboard.ecommerce.products.edit", product.id)}>
                        <Button variant="ghost" size="icon-sm" className="shrink-0">
                          <Edit className="h-3.5 w-3.5" />
                        </Button>
                      </Link>
                    </div>
                    {product.sku && (
                      <p className="text-xs text-muted-foreground">SKU: {product.sku}</p>
                    )}
                  </CardHeader>
                  <CardContent>
                    <div className="flex items-center justify-between">
                      <div className="flex flex-wrap items-center gap-1.5">
                        <Badge variant={statusBadgeVariant(product.status)} className="text-xs">
                          {product.status}
                        </Badge>
                        {product.is_featured && (
                          <Badge variant="outline" className="text-xs">Featured</Badge>
                        )}
                      </div>
                      <div className="text-right">
                        <span className="text-sm font-medium">
                          {new Intl.NumberFormat("en-US", { style: "currency", currency: "EUR" }).format(product.price)}
                        </span>
                        {product.track_stock && (
                          <p className={`text-xs ${product.stock_quantity <= product.low_stock_threshold ? "text-destructive" : "text-muted-foreground"}`}>
                            {product.stock_quantity} in stock
                          </p>
                        )}
                      </div>
                    </div>
                    {product.categories.length > 0 && (
                      <div className="mt-2 flex flex-wrap gap-1">
                        {product.categories.slice(0, 2).map((cat) => (
                          <span key={cat.id} className="text-xs text-muted-foreground bg-muted px-1.5 py-0.5 rounded">{cat.name}</span>
                        ))}
                        {product.categories.length > 2 && (
                          <span className="text-xs text-muted-foreground">+{product.categories.length - 2}</span>
                        )}
                      </div>
                    )}
                  </CardContent>
                </Card>
              ))}
            </div>

            {products.last_page > 1 && (
              <Pagination>
                <PaginationContent>
                  {products.links.map((link, i) => {
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
            )}
          </>
        )}
      </div>
    </AuthenticatedLayout>
  );
}
