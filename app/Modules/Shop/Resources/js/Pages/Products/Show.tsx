import { Head, useForm, router } from "@inertiajs/react";
import { toast } from "sonner";
import { useState } from "react";
import { ArrowLeft, Star, MessageSquare, BadgeCheck, Clock, Check, X } from "lucide-react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Textarea } from "@/Components/ui/textarea";
import { Label } from "@/Components/ui/label";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Badge } from "@/Components/ui/badge";
import { Separator } from "@/Components/ui/separator";
import { Empty, EmptyHeader, EmptyTitle, EmptyDescription, EmptyMedia } from "@/Components/ui/empty";
import { StarRating } from "@modules-shop/components/StarRating";

interface ReviewUser { id: number; name: string }
interface Review {
  id: number; rating: number; title: string | null; content: string | null;
  is_approved: boolean; order_id: number | null; created_at: string;
  user: ReviewUser | null;
}

interface Category { id: number; name: string }
interface ProductData {
  id: number; name: string; slug: string; sku: string | null;
  price: number; status: string; thumbnail_url: string | null;
  description: string | null; average_rating: number; reviews_count: number;
  categories: Category[];
  reviews: Review[];
}

interface Props {
  product: ProductData;
  userReview: Review | null;
}

export default function ProductShow({ product, userReview }: Props) {
  const hasReviewed = !!userReview;
  const [showForm, setShowForm] = useState(!hasReviewed);

  const form = useForm({ rating: 0, title: "", content: "" });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (form.data.rating === 0) { toast.error("Please select a rating."); return; }
    form.post(route("dashboard.shop.reviews.store", product.id), {
      preserveScroll: true,
      onSuccess: () => {
        toast.success("Review submitted and pending approval.");
        setShowForm(false);
        router.reload({ only: ["product", "userReview"] });
      },
      onError: () => toast.error("Failed to submit review."),
    });
  };

  return (
    <AuthenticatedLayout header={product.name}>
      <Head title={product.name} />

      <div className="space-y-6">
        <div className="flex items-center gap-4">
          <Button variant="ghost" size="sm" onClick={() => window.history.back()}>
            <ArrowLeft className="mr-1 h-4 w-4" /> Back
          </Button>
          <div className="flex items-center gap-2 ml-auto">
            <StarRating value={Math.round(product.average_rating)} readonly />
            <span className="text-sm text-muted-foreground">
              {product.average_rating > 0 ? product.average_rating.toFixed(1) : "—"} ({product.reviews_count})
            </span>
          </div>
        </div>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              {product.name}
              <Badge variant="outline">{product.status}</Badge>
            </CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-sm text-muted-foreground">
              SKU: {product.sku || "—"} | Price: {new Intl.NumberFormat("en-US", { style: "currency", currency: "EUR" }).format(product.price)}
            </p>
            {product.description && (
              <p className="text-sm mt-2 whitespace-pre-wrap">{product.description}</p>
            )}
          </CardContent>
        </Card>

        {/* Review Form */}
        {!hasReviewed && showForm && (
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <MessageSquare className="h-4 w-4" /> Write a Review
              </CardTitle>
            </CardHeader>
            <CardContent>
              <form onSubmit={handleSubmit} className="space-y-4">
                <div className="grid gap-2">
                  <Label>Rating</Label>
                  <StarRating
                    value={form.data.rating}
                    onChange={(v) => form.setData("rating", v)}
                    size="lg"
                  />
                </div>
                <div className="grid gap-2">
                  <Label htmlFor="review-title">Title (optional)</Label>
                  <Input
                    id="review-title"
                    value={form.data.title}
                    onChange={(e) => form.setData("title", e.target.value)}
                    placeholder="Great product!"
                  />
                </div>
                <div className="grid gap-2">
                  <Label htmlFor="review-content">Review</Label>
                  <Textarea
                    id="review-content"
                    rows={4}
                    value={form.data.content}
                    onChange={(e) => form.setData("content", e.target.value)}
                    placeholder="Share your experience with this product..."
                  />
                </div>
                <Button type="submit" disabled={form.processing}>
                  {form.processing ? "Submitting..." : "Submit Review"}
                </Button>
              </form>
            </CardContent>
          </Card>
        )}

        {hasReviewed && (
          <Card>
            <CardContent className="p-4 text-sm text-muted-foreground flex items-center gap-2">
              <Check className="h-4 w-4 text-green-500" />
              You reviewed this product. {userReview.is_approved ? "Your review is approved." : "Your review is pending approval."}
            </CardContent>
          </Card>
        )}

        {/* Reviews List */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <MessageSquare className="h-4 w-4" /> Reviews
              {product.reviews_count > 0 && (
                <Badge variant="secondary">{product.reviews_count}</Badge>
              )}
            </CardTitle>
          </CardHeader>
          <CardContent>
            {product.reviews.length === 0 ? (
              <Empty>
                <EmptyHeader>
                  <EmptyMedia variant="icon"><MessageSquare /></EmptyMedia>
                  <EmptyTitle>No reviews yet</EmptyTitle>
                  <EmptyDescription>Be the first to review this product.</EmptyDescription>
                </EmptyHeader>
              </Empty>
            ) : (
              <div className="space-y-4">
                {product.reviews.map((review) => (
                  <div key={review.id}>
                    <div className="flex items-start justify-between">
                      <div>
                        <div className="flex items-center gap-2">
                          <StarRating value={review.rating} readonly size="sm" />
                          {review.order_id && review.is_approved && (
                            <BadgeCheck className="h-3.5 w-3.5 text-blue-500" title="Verified Purchase" />
                          )}
                          {!review.is_approved && (
                            <Badge variant="outline" className="text-xs">Pending</Badge>
                          )}
                        </div>
                        {review.title && (
                          <p className="font-medium text-sm mt-1">{review.title}</p>
                        )}
                        {review.content && (
                          <p className="text-sm text-muted-foreground mt-0.5">{review.content}</p>
                        )}
                      </div>
                      <div className="text-right text-xs text-muted-foreground shrink-0">
                        <p>{review.user?.name || "Anonymous"}</p>
                        <p>{new Date(review.created_at).toLocaleDateString()}</p>
                      </div>
                    </div>
                    <Separator className="mt-4" />
                  </div>
                ))}
              </div>
            )}
          </CardContent>
        </Card>
      </div>
    </AuthenticatedLayout>
  );
}
