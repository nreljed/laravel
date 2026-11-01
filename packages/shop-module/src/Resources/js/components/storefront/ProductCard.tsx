import { Link } from "@inertiajs/react";
import { ArrowRight, ShoppingCart } from "lucide-react";
import { Badge } from "@nreljed/platform/Components/ui/badge";
import { Card, CardContent, CardFooter } from "@nreljed/platform/Components/ui/card";
import { StarRating } from "../StarRating";
import { WishlistButton } from "../WishlistButton";
import { useCurrency } from "../../lib/currency";

export interface Product {
    id: number;
    name: string;
    slug: string;
    price: number;
    price_range: { min: number | null; max: number | null } | null;
    compare_price: number | null;
    type: string;
    virtual: boolean;
    downloadable: boolean;
    external_url: string | null;
    thumbnail_url: string | null;
    average_rating: number;
    reviews_count: number;
    stock_quantity: number;
    track_stock: boolean;
    backorders: string;
    stock_status: string | null;
    low_stock_threshold: number;
}

export function displayPrice(
    product: { price: number; price_range: { min: number | null; max: number | null } | null },
    money: (amount: number | string | null | undefined) => string,
): string {
    const range = product.price_range;
    if (!product.price && range && range.min !== null && range.max !== null && range.min > 0) {
        return range.min === range.max ? money(range.min) : `${money(range.min)} – ${money(range.max)}`;
    }
    return money(product.price);
}

export function ProductCard({ product, wishlistIds }: { product: Product; wishlistIds: number[] }) {
    const { money } = useCurrency();
    const onSale = !!product.compare_price && product.compare_price > product.price;
    const discount = onSale
        ? Math.round(((product.compare_price! - product.price) / product.compare_price!) * 100)
        : 0;

    return (
        <Link key={product.id} href={route("store.show", product.slug)} className="group block focus-visible:outline-none">
            <Card className="overflow-hidden transition-all duration-200 hover:shadow-md hover:ring-foreground/15 focus-visible:ring-2 focus-visible:ring-ring">
                {/* Image */}
                <div className="relative aspect-square bg-muted">
                    {product.thumbnail_url ? (
                        <img
                            src={product.thumbnail_url}
                            alt={product.name}
                            loading="lazy"
                            decoding="async"
                            className="h-full w-full object-cover"
                        />
                    ) : (
                        <div className="flex h-full w-full items-center justify-center">
                            <ShoppingCart className="h-10 w-10 text-muted-foreground/30" />
                        </div>
                    )}

                    {/* Wishlist */}
                    <div className="absolute top-2 right-2 z-10" onClick={(e) => e.preventDefault()}>
                        <WishlistButton productId={product.id} initiallyActive={wishlistIds?.includes(product.id) ?? false} />
                    </div>

                    {/* Badges */}
                    <div className="absolute top-2 left-2 z-10 flex flex-col gap-1.5">
                        {onSale && (
                            <Badge className="rounded-md bg-destructive px-1.5 py-0.5 text-[10px] font-semibold text-destructive-foreground">
                                -{discount}%
                            </Badge>
                        )}
                        {product.type !== "simple" && (
                            <Badge variant="secondary" className="rounded-md bg-background/80 px-1.5 py-0.5 text-[10px] backdrop-blur">
                                {product.type}
                            </Badge>
                        )}
                        {product.track_stock && product.stock_quantity <= 0 && product.backorders !== "no" && (
                            <Badge variant="secondary" className="rounded-md bg-amber-50/90 px-1.5 py-0.5 text-[10px] text-amber-700 backdrop-blur dark:bg-amber-950/60 dark:text-amber-300">
                                On backorder
                            </Badge>
                        )}
                        {product.track_stock && product.stock_quantity <= 0 && product.backorders === "no" && (
                            <Badge variant="secondary" className="rounded-md bg-destructive/10 px-1.5 py-0.5 text-[10px] text-destructive backdrop-blur">
                                Out of stock
                            </Badge>
                        )}
                        {product.track_stock && product.stock_quantity > 0 && product.stock_quantity <= (product.low_stock_threshold || 5) && (
                            <Badge variant="secondary" className="rounded-md bg-orange-50/90 px-1.5 py-0.5 text-[10px] text-orange-700 backdrop-blur dark:bg-orange-950/60 dark:text-orange-300">
                                Only {product.stock_quantity} left
                            </Badge>
                        )}
                    </div>
                </div>

                <CardContent className="space-y-1.5 p-4">
                    <h3 className="line-clamp-2 text-sm font-medium leading-snug break-words">{product.name || "Untitled product"}</h3>
                    {product.reviews_count > 0 && (
                        <div className="flex items-center gap-1">
                            <StarRating value={product.average_rating} size="sm" readonly />
                            <span className="text-xs text-muted-foreground">({product.reviews_count})</span>
                        </div>
                    )}
                </CardContent>

                <CardFooter className="flex items-baseline gap-2 px-4 pb-4 pt-0 min-w-0">
                    <span className="text-lg font-bold tracking-tight truncate">{displayPrice(product, money)}</span>
                    {onSale && (
                        <span className="text-sm text-muted-foreground line-through">{money(product.compare_price)}</span>
                    )}
                </CardFooter>
            </Card>
        </Link>
    );
}

export function SectionHeader({ title, href, hrefLabel = "View all" }: { title: string; href?: string; hrefLabel?: string }) {
    return (
        <div className="flex items-center justify-between mb-6">
            <h2 className="text-2xl font-bold tracking-tight">{title}</h2>
            {href && (
                <Link href={href} className="inline-flex items-center gap-1 text-sm text-primary hover:underline">
                    {hrefLabel} <ArrowRight className="h-4 w-4" />
                </Link>
            )}
        </div>
    );
}
