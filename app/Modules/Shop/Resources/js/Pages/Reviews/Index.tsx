import { Head, router } from "@inertiajs/react";
import { toast } from "sonner";
import { useState } from "react";
import { Check, X, Trash2, MessageSquare } from "lucide-react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Button } from "@/Components/ui/button";
import { Badge } from "@/Components/ui/badge";
import { StarRating } from "@modules-shop/components/StarRating";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/Components/ui/dialog";
import { PageHeader, FilterBar, PaginationLinks, TableList } from "../../components/templates";

interface Review {
  id: number; rating: number; title: string | null; content: string | null;
  is_approved: boolean; created_at: string;
  product: { id: number; name: string }; user: { id: number; name: string } | null;
}

interface PaginatedData {
  data: Review[]; current_page: number; last_page: number;
  links: { url: string | null; label: string; active: boolean }[];
}

interface Props {
  reviews: PaginatedData;
  filters: { status?: string; search?: string };
}

const STATUS_FILTERS = [
  { value: "pending", label: "Pending" },
  { value: "approved", label: "Approved" },
];

export default function ReviewsIndex({ reviews, filters }: Props) {
  const [search, setSearch] = useState(filters.search || "");
  const [showDelete, setShowDelete] = useState<Review | null>(null);

  const applyFilter = (key: string, value: string) => {
    router.get(route("dashboard.shop.reviews.index"), { ...filters, [key]: value || undefined }, {
      preserveState: true, preserveScroll: true,
    });
  };

  const clearFilters = () => {
    setSearch("");
    router.get(route("dashboard.shop.reviews.index"), {}, { preserveState: true, replace: true });
  };

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    applyFilter("search", search);
  };

  const approve = (review: Review) => {
    router.post(route("dashboard.shop.reviews.approve", review.id), {}, {
      preserveScroll: true,
      onSuccess: () => toast.success("Review approved."),
    });
  };

  const reject = (review: Review) => {
    router.post(route("dashboard.shop.reviews.reject", review.id), {}, {
      preserveScroll: true,
      onSuccess: () => toast.success("Review rejected."),
    });
  };

  const handleDelete = () => {
    router.delete(route("dashboard.shop.reviews.destroy", showDelete!.id), {
      preserveScroll: true,
      onSuccess: () => { setShowDelete(null); toast.success("Review deleted."); },
    });
  };

  const hasFilters = !!(filters.search || filters.status);

  return (
    <AuthenticatedLayout header="Product Reviews">
      <Head title="Product Reviews" />

      <div className="space-y-6">
        <PageHeader title="Product Reviews" subtitle="Moderate customer reviews" />

        <FilterBar
          searchPlaceholder="Search reviews..."
          searchValue={search}
          onSearchChange={setSearch}
          onSubmit={handleSearch}
          filters={[{ key: "status", label: "Status", placeholder: "All statuses", options: STATUS_FILTERS }]}
          filterValues={filters}
          onFilterChange={applyFilter}
          onClear={clearFilters}
          showClear={hasFilters}
        />

        <TableList
          data={reviews.data}
          emptyTitle="No reviews found"
          emptyDescription="Reviews submitted by customers will appear here."
          emptyIcon={<MessageSquare />}
          columns={[
            { key: "product", header: "Product", cell: (r) => <span className="font-medium text-xs">{r.product.name}</span> },
            { key: "customer", header: "Customer", cell: (r) => <span className="text-xs">{r.user?.name || "Anonymous"}</span> },
            { key: "rating", header: "Rating", cell: (r) => <StarRating value={r.rating} readonly size="sm" /> },
            {
              key: "review", header: "Review", className: "max-w-xs",
              cell: (r) => (
                <>
                  {r.title && <p className="font-medium text-xs">{r.title}</p>}
                  {r.content && <p className="text-xs text-muted-foreground truncate">{r.content}</p>}
                </>
              ),
            },
            { key: "date", header: "Date", cell: (r) => <span className="text-xs text-muted-foreground">{new Date(r.created_at).toLocaleDateString()}</span> },
            {
              key: "status", header: "Status",
              cell: (r) => <Badge variant={r.is_approved ? "default" : "secondary"} className="text-xs">{r.is_approved ? "Approved" : "Pending"}</Badge>,
            },
            {
              key: "actions", header: "", className: "w-28",
              cell: (r) => (
                <div className="flex items-center gap-1">
                  {!r.is_approved && (
                    <Button variant="ghost" size="icon-sm" onClick={() => approve(r)} title="Approve">
                      <Check className="h-3.5 w-3.5 text-green-500" />
                    </Button>
                  )}
                  {r.is_approved && (
                    <Button variant="ghost" size="icon-sm" onClick={() => reject(r)} title="Reject">
                      <X className="h-3.5 w-3.5 text-destructive" />
                    </Button>
                  )}
                  <Button variant="ghost" size="icon-sm" onClick={() => setShowDelete(r)} title="Delete">
                    <Trash2 className="h-3.5 w-3.5 text-destructive" />
                  </Button>
                </div>
              ),
            },
          ]}
        />

        <PaginationLinks links={reviews.links} />

        <Dialog open={!!showDelete} onOpenChange={(o) => { if (!o) setShowDelete(null); }}>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>Delete Review</DialogTitle>
              <DialogDescription>Are you sure you want to delete this review?</DialogDescription>
            </DialogHeader>
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setShowDelete(null)}>Cancel</Button>
              <Button type="button" variant="destructive" onClick={handleDelete}>Delete</Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      </div>
    </AuthenticatedLayout>
  );
}
