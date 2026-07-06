import { Head, Link, router } from "@inertiajs/react";
import { Eye, Users, ShoppingCart, Download } from "lucide-react";
import { useState } from "react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Button } from "@/Components/ui/button";
import { PageHeader, FilterBar, PaginationLinks, TableList } from "../../components/templates";

interface Customer {
  id: number;
  name: string;
  email: string;
  orders_count: number;
  total_spent: number;
  created_at: string;
}

interface Props {
  customers: { data: Customer[]; current_page: number; last_page: number; links: { url: string | null; label: string; active: boolean }[] };
  filters: { search?: string };
}

export default function CustomersIndex({ customers, filters }: Props) {
  const [search, setSearch] = useState(filters.search || "");

  const applyFilter = (key: string, value: string) => {
    router.get(route("dashboard.shop.customers.index"), { [key]: value || undefined }, { preserveState: true, replace: true });
  };

  const clearFilters = () => {
    setSearch("");
    router.get(route("dashboard.shop.customers.index"), {}, { preserveState: true, replace: true });
  };

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    applyFilter("search", search);
  };

  return (
    <AuthenticatedLayout header="Customers">
      <Head title="Customers" />

      <div className="space-y-6">
        <PageHeader
          title="Customers"
          subtitle="Manage your customer base"
          actions={
            <a href={route("dashboard.shop.customers.export")}>
              <Button variant="outline" size="sm">
                <Download className="mr-1 h-3 w-3" /> Export
              </Button>
            </a>
          }
        />

        <FilterBar
          searchPlaceholder="Search customers..."
          searchValue={search}
          onSearchChange={setSearch}
          onSubmit={handleSearch}
          filters={[]}
          filterValues={filters}
          onFilterChange={applyFilter}
          onClear={clearFilters}
          showClear={!!filters.search}
        />

        <TableList
          columns={[
            {
              key: "name",
              header: "Name",
              cell: (c: Customer) => <span className="font-medium">{c.name}</span>,
            },
            {
              key: "email",
              header: "Email",
              cell: (c: Customer) => <span className="text-muted-foreground">{c.email}</span>,
            },
            {
              key: "orders",
              header: "Orders",
              className: "text-center",
              cell: (c: Customer) => <span>{c.orders_count}</span>,
            },
            {
              key: "total_spent",
              header: "Total Spent",
              className: "text-right",
              cell: (c: Customer) => (
                <span className="font-medium tabular-nums">
                  {new Intl.NumberFormat("en-US", { style: "currency", currency: "EUR" }).format(c.total_spent)}
                </span>
              ),
            },
            {
              key: "created_at",
              header: "Customer Since",
              cell: (c: Customer) => (
                <span className="text-xs text-muted-foreground">{new Date(c.created_at).toLocaleDateString()}</span>
              ),
            },
            {
              key: "actions",
              header: "",
              className: "w-12",
              cell: (c: Customer) => (
                <Link href={route("dashboard.shop.customers.show", c.id)}>
                  <Button variant="ghost" size="icon-sm"><Eye className="h-3.5 w-3.5" /></Button>
                </Link>
              ),
            },
          ]}
          data={customers.data}
          emptyTitle="No customers yet"
          emptyDescription="Customers will appear once orders are placed."
          emptyIcon={<Users />}
        />

        <PaginationLinks links={customers.links} />
      </div>
    </AuthenticatedLayout>
  );
}
