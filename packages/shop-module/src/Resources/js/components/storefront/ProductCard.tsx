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

function displayPrice(product: Product, money: (amount: number | string | null | undefined) => string): string {
    const range = product.price_range;

    // Variable and grouped parents carry no price of their own, so fall back to
    // the derived range instead of rendering a misleading 0.00.
    if (!product.price && range && range.min !== null && range.max !== null && range.min > 0) {
        return range.min === range.max ? money(range.min) : `${money(range.min)} – ${money(range.max)}`;
    }

    return money(product.price);
}

export function ProductCard({ product, wishlistIds }: { product: Product; wishlistIds: number[] }) {
    const { money } = useCurrency();
    return (
        <Link key={product.id} href={route("store.show", product.slug)} className="group">
            <Card className="overflow-hidden transition-shadow hover:shadow-lg relative">
                <div className="absolute top-2 right-2 z-10" onClick={(e) => e.preventDefault()}>
                    <WishlistButton productId={product.id} initiallyActive={wishlistIds?.includes(product.id) ?? false} />
                </div>
                <div className="absolute top-2 left-2 z-10 flex flex-col gap-1">
                    {product.compare_price && product.compare_price > product.price && (
                        <Badge variant="destructive" className="text-[10px] px-1.5 py-0.5">
                            -{Math.round(((product.compare_price - product.price) / product.compare_price) * 100)}%
                        </Badge>
                    )}
                    {product.type !== "simple" && (
                        <Badge variant="outline" className="text-[10px] px-1.5 py-0.5 bg-background/80">{product.type}</Badge>
                    )}
                    {product.track_stock && product.stock_quantity <= 0 && product.backorders !== "no" && (
                        <>
                            <Badge variant="secondary" className="text-[10px] px-1.5 py-0.5 text-amber-600 border-amber-300 bg-amber-50 dark:bg-amber-950/20 dark:border-amber-800">
                                On backorder
                            </Badge>
                            {product.backorders === "notify" && (
                                <span className="text-[10px] text-muted-foreground">Will ship when back in stock</span>
                            )}
                        </>
                    )}
                    {product.track_stock && product.stock_quantity <= 0 && product.backorders === "no" && (
                        <Badge variant="secondary" className="text-[10px] px-1.5 py-0.5 text-destructive">
                            Out of stock
                        </Badge>
                    )}
                    {product.track_stock && product.stock_quantity > 0 && product.stock_quantity <= (product.low_stock_threshold || 5) && (
                        <Badge variant="secondary" className="text-[10px] px-1.5 py-0.5 text-orange-600 border-orange-300 bg-orange-50 dark:bg-orange-950/20 dark:border-orange-800">
                            Only {product.stock_quantity} left
                        </Badge>
                    )}
                </div>
                <div className="aspect-square bg-muted flex items-center justify-center overflow-hidden">
                    {product.thumbnail_url ? (
                        <img src={product.thumbnail_url} alt={product.name} className="h-full w-full object-cover group-hover:scale-105 transition-transform" />
                    ) : (
                        <ShoppingCart className="h-12 w-12 text-muted-foreground/30" />
                    )}
                </div>
                <CardContent className="p-4">
                    <h3 className="font-semibold text-sm line-clamp-2">{product.name}</h3>
                    {product.reviews_count > 0 && (
                        <div className="flex items-center gap-1 mt-1">
                            <StarRating value={product.average_rating} size="sm" readonly />
                            <span className="text-xs text-muted-foreground">({product.reviews_count})</span>
                        </div>
                    )}
                </CardContent>
                <CardFooter className="p-4 pt-0 flex items-center gap-2">
                    <span className="text-lg font-bold">{displayPrice(product, money)}</span>
                    {product.compare_price && product.compare_price > product.price && (
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
