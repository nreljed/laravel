import { Link, router, usePage, Head } from '@inertiajs/react'
import { useState, useMemo, useCallback, useEffect } from 'react'
import { ArrowLeft, Minus, Plus, ShoppingBag, ShoppingCart, Star, Heart, ExternalLink, FileDown, Package, Expand, X, ChevronLeft, ChevronRight } from 'lucide-react'
import StoreLayout from '@/Layouts/StoreLayout'
import { Button } from '@/Components/ui/button'
import { Card, CardContent } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import { Separator } from '@/Components/ui/separator'
import { Dialog, DialogContent, DialogClose } from '@/Components/ui/dialog'
import { Accordion, AccordionItem, AccordionTrigger, AccordionContent } from '@/Components/ui/accordion'
import { StarRating } from '@modules-shop/components/StarRating'
import { WishlistButton } from '@modules-shop/components/WishlistButton'
import { AttributeSelector } from '../../components/AttributeSelector'
import { cn } from '@/lib/utils'
import { toast } from 'sonner'

interface Review {
    id: number; rating: number; title: string | null; content: string | null;
    user: { name: string } | null; created_at: string;
}

interface AttributeTerm { id: number; name: string; slug: string; swatch_value?: string | null }
interface AttributeDef { id: number; name: string; slug: string; display_type?: string; terms: AttributeTerm[] }
interface VariationAttr { attribute_id: number; attribute_term_id: number; name: string }

interface Variation {
    id: number; sku: string | null; price: string | null;
    compare_price: string | null; sale_price: string | null; active_price: string | number | null;
    stock_quantity: number; manage_stock: string; backorders: string; stock_status: string | null;
    image_url: string | null; attributes: VariationAttr[] | null; is_active: boolean;
}

interface DownloadItem {
    id: number; name: string; file: string;
    download_limit: number; download_expiry: number;
}

interface GroupedChild {
    id: number; name: string; slug: string; price: number | null;
    thumbnail_url: string | null; pivot: { quantity: number };
}

interface GalleryImage {
    url: string; path?: string; featured?: boolean;
}

interface ProductDetail {
    id: number; name: string; slug: string; short_description: string | null; description: string | null;
    price: number; compare_price: number | null; active_price: number; on_sale: boolean; sku: string | null;
    stock_quantity: number; track_stock: boolean; backorders: string; stock_status: string | null;
    incoming_stock: number | null; low_stock_threshold: number;
    type: string;
    virtual: boolean; downloadable: boolean;
    external_url: string | null; button_text: string | null;
    sold_individually: boolean; min_quantity: number | null; max_quantity: number | null;
    images: GalleryImage[]; thumbnail_url: string | null; average_rating: number; reviews_count: number;
    categories: { id: number; name: string; slug: string }[];
    reviews: Review[];
    downloads?: DownloadItem[];
    groupedItems?: GroupedChild[];
}

interface VisibleAttr {
    name: string;
    display_type?: string;
    terms: { name: string; swatch_value?: string | null }[];
}
interface ShowPage {
    product: ProductDetail;
    related: ProductDetail[];
    variations: Variation[];
    attributes: AttributeDef[];
    priceRange: { min: number; max: number };
    defaultVariation: Variation | null;
    visibleAttributes: VisibleAttr[];
}

function ProductGallery({ images, thumbnailUrl, productName, variationImageUrl }: { images: GalleryImage[]; thumbnailUrl: string | null; productName: string; variationImageUrl?: string | null }) {
    const all = useMemo(() => {
        const imgs: { url: string; isFeatured: boolean }[] = [];
        if (variationImageUrl) imgs.push({ url: variationImageUrl, isFeatured: true });
        if (thumbnailUrl && thumbnailUrl !== variationImageUrl) imgs.push({ url: thumbnailUrl, isFeatured: true });
        images.forEach((img) => {
            if (img.url !== variationImageUrl && img.url !== thumbnailUrl) imgs.push({ url: img.url, isFeatured: !!img.featured });
        });
        return imgs.length > 0 ? imgs : [{ url: '', isFeatured: false }];
    }, [images, thumbnailUrl, variationImageUrl]);
    const [activeIndex, setActiveIndex] = useState(0);

    useEffect(() => { setActiveIndex(0); }, [variationImageUrl]);
    const [lightboxOpen, setLightboxOpen] = useState(false);
    const active = all[activeIndex];
    const hasNav = all.length > 1;

    function prev() { setActiveIndex((activeIndex - 1 + all.length) % all.length); }
    function next() { setActiveIndex((activeIndex + 1) % all.length); }

    return (
        <>
            <div className="space-y-3">
                <div className="relative aspect-square bg-muted rounded-xl overflow-hidden group">
                    {active.url ? (
                        <>
                            <img
                                src={active.url}
                                alt={`${productName} - Image ${activeIndex + 1}`}
                                className="h-full w-full object-cover transition-transform duration-500 group-hover:scale-110"
                            />
                            {/* Arrows — shown on hover (desktop) or always (touch via opacity) */}
                            {hasNav && (
                                <>
                                    <button type="button" onClick={prev}
                                        className="absolute left-2 top-1/2 -translate-y-1/2 h-9 w-9 rounded-full bg-background/70 backdrop-blur-sm flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity shadow-sm hover:bg-background/90">
                                        <ChevronLeft className="h-5 w-5" />
                                    </button>
                                    <button type="button" onClick={next}
                                        className="absolute right-2 top-1/2 -translate-y-1/2 h-9 w-9 rounded-full bg-background/70 backdrop-blur-sm flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity shadow-sm hover:bg-background/90">
                                        <ChevronRight className="h-5 w-5" />
                                    </button>
                                </>
                            )}
                            {/* Expand button */}
                            <button type="button" onClick={() => setLightboxOpen(true)}
                                className="absolute top-3 right-3 h-8 w-8 rounded-full bg-background/80 backdrop-blur-sm flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity shadow-sm">
                                <Expand className="h-4 w-4" />
                            </button>
                            {/* Dots */}
                            {hasNav && (
                                <div className="absolute bottom-3 left-1/2 -translate-x-1/2 flex gap-1.5">
                                    {all.map((_, idx) => (
                                        <button key={idx} type="button" onClick={() => setActiveIndex(idx)}
                                            className={cn("h-2 w-2 rounded-full transition-all", idx === activeIndex ? "bg-foreground w-5" : "bg-foreground/30 hover:bg-foreground/50")} />
                                    ))}
                                </div>
                            )}
                        </>
                    ) : (
                        <ShoppingCart className="h-24 w-24 text-muted-foreground/30 absolute inset-0 m-auto" />
                    )}
                </div>
                {/* Thumbnails */}
                {hasNav && (
                    <div className="flex gap-2 overflow-x-auto pb-1">
                        {all.map((img, idx) => (
                            <button key={idx} type="button" onClick={() => setActiveIndex(idx)}
                                className={cn(
                                    "h-16 w-16 rounded-lg border-2 overflow-hidden shrink-0 transition-all",
                                    idx === activeIndex ? "border-foreground ring-1 ring-foreground" : "border-border hover:border-muted-foreground/40"
                                )}>
                                {img.url ? (
                                    <img src={img.url} alt="" className="h-full w-full object-cover" />
                                ) : (
                                    <div className="h-full w-full bg-muted flex items-center justify-center">
                                        <ShoppingCart className="h-4 w-4 text-muted-foreground/30" />
                                    </div>
                                )}
                            </button>
                        ))}
                    </div>
                )}
            </div>
            <Dialog open={lightboxOpen} onOpenChange={setLightboxOpen}>
                <DialogContent className="max-w-4xl p-0 bg-black/95 border-none">
                    <div className="relative flex items-center justify-center min-h-[60vh]">
                        {active.url && (
                            <img src={active.url} alt={`${productName} - Image ${activeIndex + 1}`} className="max-h-[85vh] max-w-full object-contain" />
                        )}
                        {hasNav && (
                            <>
                                <button type="button" onClick={prev}
                                    className="absolute left-4 top-1/2 -translate-y-1/2 h-10 w-10 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center text-white transition-colors">
                                    <ChevronLeft className="h-5 w-5" />
                                </button>
                                <button type="button" onClick={next}
                                    className="absolute right-4 top-1/2 -translate-y-1/2 h-10 w-10 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center text-white transition-colors">
                                    <ChevronRight className="h-5 w-5" />
                                </button>
                            </>
                        )}
                        <div className="absolute bottom-4 left-1/2 -translate-x-1/2 flex gap-1.5">
                            {all.map((_, idx) => (
                                <button key={idx} type="button" onClick={() => setActiveIndex(idx)}
                                    className={cn("h-2 w-2 rounded-full transition-all", idx === activeIndex ? "bg-white w-4" : "bg-white/40")} />
                            ))}
                        </div>
                    </div>
                    <DialogClose className="absolute top-4 right-4 h-8 w-8 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center text-white">
                        <X className="h-4 w-4" />
                    </DialogClose>
                </DialogContent>
            </Dialog>
        </>
    );
}

function ProductInfo({ product, selectedVariation, priceRange, isVariable, isExternal, isGrouped }: {
    product: ProductDetail;
    selectedVariation: Variation | null;
    priceRange: { min: number; max: number };
    isVariable: boolean;
    isExternal: boolean;
    isGrouped: boolean;
}) {
    const displayPrice = useMemo(() => {
        if (isExternal || isGrouped) {
            return { current: product.price, original: null, compareAt: null };
        }
        if (selectedVariation) {
            const active = selectedVariation.active_price ?? selectedVariation.price ?? 0;
            const hasVarSale = selectedVariation.sale_price && selectedVariation.price && parseFloat(selectedVariation.sale_price) < parseFloat(selectedVariation.price);
            return {
                current: parseFloat(String(active)),
                original: hasVarSale ? parseFloat(selectedVariation.price) : null,
                compareAt: !hasVarSale && selectedVariation.compare_price ? parseFloat(selectedVariation.compare_price) : null,
            };
        }
        if (isVariable) {
            return priceRange.min < priceRange.max
                ? { current: priceRange.min, original: priceRange.max, compareAt: null }
                : { current: priceRange.min, original: null, compareAt: null };
        }
        if (product.on_sale) {
            return { current: product.active_price, original: product.price, compareAt: null };
        }
        return { current: product.price, original: null, compareAt: (product.compare_price && product.compare_price > product.price) ? product.compare_price : null };
    }, [selectedVariation, product, priceRange, isVariable, isExternal, isGrouped]);

    const wasPrice = displayPrice.original ?? displayPrice.compareAt ?? null;
    const hasSale = wasPrice !== null && wasPrice > displayPrice.current;
    const savingsPercent = hasSale ? Math.round(((wasPrice! - displayPrice.current) / wasPrice!) * 100) : 0;

    return (
        <div className="flex items-baseline gap-3">
            <span className={cn("text-3xl font-bold tracking-tight", hasSale && "text-destructive")}>
                {displayPrice.current.toFixed(2)} €
            </span>
            {hasSale && (
                <>
                    <span className="text-xl text-muted-foreground line-through">{wasPrice!.toFixed(2)} €</span>
                    <Badge variant="destructive" className="text-xs">-{savingsPercent}%</Badge>
                </>
            )}
        </div>
    );
}

export default function StorefrontShow() {
    const { props } = usePage<ShowPage & { wishlistIds: number[] }>()
    const { product, related, variations, attributes, priceRange, defaultVariation, visibleAttributes, wishlistIds } = props
    const [quantity, setQuantity] = useState(product.min_quantity || 1)
    const [addingToCart, setAddingToCart] = useState(false)
    const [selectedVariation, setSelectedVariation] = useState<Variation | null>(null)
    const [variationImage, setVariationImage] = useState<string | null>(null)
    const [notifyEmail, setNotifyEmail] = useState('')
    const [notifySent, setNotifySent] = useState(false)
    const [notifying, setNotifying] = useState(false)

    const handleNotify = async () => {
        if (!notifyEmail.trim() || notifying) return
        setNotifying(true)
        try {
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            await fetch(route('store.notify-back-in-stock'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify({
                    email: notifyEmail.trim(),
                    product_id: product.id,
                    variant_id: selectedVariation?.id ?? null,
                }),
            })
            setNotifySent(true)
            toast.success('We\'ll notify you when this product is back in stock!')
        } catch {
            toast.error('Failed to submit notification. Please try again.')
        } finally {
            setNotifying(false)
        }
    }

    const isVariable = product.type === 'variable'
    const isExternal = product.type === 'external'
    const isGrouped = product.type === 'grouped'
    const isDigital = product.type === 'digital'
    const isService = product.type === 'service'
    const isVirtual = product.virtual || isExternal || isDigital || isService

    const inStock = useMemo(() => {
        if (isExternal || isGrouped) return true
        if (selectedVariation) {
            if (selectedVariation.manage_stock !== 'true') return selectedVariation.stock_status !== 'outofstock'
            return selectedVariation.stock_quantity > 0 || selectedVariation.backorders !== 'no'
        }
        if (!product.track_stock) return product.stock_status !== 'outofstock'
        return product.stock_quantity > 0 || product.backorders !== 'no'
    }, [selectedVariation, product, isExternal, isGrouped])

    const isOnBackorder = useMemo(() => {
        if (selectedVariation) {
            return selectedVariation.manage_stock === 'true' && selectedVariation.stock_quantity <= 0 && selectedVariation.backorders !== 'no'
        }
        return product.track_stock && product.stock_quantity <= 0 && product.backorders !== 'no'
    }, [selectedVariation, product])

    const isLowStock = useMemo(() => {
        const threshold = product.low_stock_threshold || 5
        if (selectedVariation) {
            return selectedVariation.manage_stock === 'true' && selectedVariation.stock_quantity > 0 && selectedVariation.stock_quantity <= threshold
        }
        return product.track_stock && product.stock_quantity > 0 && product.stock_quantity <= threshold
    }, [selectedVariation, product])

    const displayStock = selectedVariation
        ? (selectedVariation.manage_stock === 'true' ? selectedVariation.stock_quantity : null)
        : (product.track_stock ? product.stock_quantity : null)

    const displaySku = selectedVariation?.sku || product.sku

    const handleVariationChange = useCallback((variation: Variation | null) => {
        setSelectedVariation(variation)
    }, [])

    const handleImageChange = useCallback((imageUrl: string | null) => {
        setVariationImage(imageUrl)
    }, [])

    const avgRating = product.average_rating ?? 0

    const metaDescription = useMemo(() => {
        const raw = product.short_description
            || (product.description ? product.description.replace(/<[^>]*>/g, '').trim().slice(0, 160) : null)
        return raw || `Shop ${product.name} at ${window.location.hostname}`
    }, [product.short_description, product.description, product.name])

    async function addToCart() {
        setAddingToCart(true)
        const payload: Record<string, unknown> = {
            product_id: product.id,
            quantity,
        }
        if (selectedVariation) {
            payload.variant_id = selectedVariation.id
            payload.variant_label = selectedVariation.attributes
                ?.map((a) => a.name).join(' / ') || null
        }
        try {
            const res = await fetch(route('store.cart.add-item'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '' },
                body: JSON.stringify(payload),
            })
            if (res.ok) {
                router.reload({ only: ['cart'] })
                toast.success('Added to cart!')
            } else {
                const data = await res.json()
                toast.error(data.error || 'Failed to add to cart')
            }
        } catch {
            toast.error('Failed to add to cart')
        } finally {
            setAddingToCart(false)
        }
    }

    const allImages = product.images || []

    return (
        <StoreLayout>
            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <Head title={product.name}>
                    <meta name="description" content={metaDescription} />
                    <meta property="og:title" content={product.name} />
                    <meta property="og:description" content={metaDescription} />
                    {product.thumbnail_url && <meta property="og:image" content={product.thumbnail_url} />}
                    <meta property="og:type" content="product" />
                    <meta property="og:url" content={typeof window !== 'undefined' ? window.location.href : ''} />
                    <meta name="twitter:card" content="summary_large_image" />
                </Head>
                <Link href={route('store.index')} className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground mb-6">
                    <ArrowLeft className="h-4 w-4" /> Back to store
                </Link>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-14">
                    {/* Left Column — Gallery */}
                    <ProductGallery images={allImages} thumbnailUrl={product.thumbnail_url} productName={product.name} variationImageUrl={variationImage} />

                    {/* Right Column — Product Info */}
                    <div className="flex flex-col gap-6">
                        {/* Breadcrumb / Categories */}
                        <div className="flex flex-wrap items-center gap-2">
                            {product.categories.map((cat) => (
                                <Link key={cat.id} href={route('store.category', cat.slug)}>
                                    <Badge variant="secondary" className="rounded-full">{cat.name}</Badge>
                                </Link>
                            ))}
                            {product.type !== 'simple' && (
                                <Badge variant="outline" className="rounded-full text-xs">{product.type}</Badge>
                            )}
                        </div>

                        {/* Title + Wishlist */}
                        <div className="flex items-start justify-between gap-4">
                            <div>
                                <h1 className="text-2xl font-bold tracking-tight lg:text-3xl">{product.name}</h1>
                                {product.short_description && (
                                    <p className="mt-1 text-sm text-muted-foreground">{product.short_description}</p>
                                )}
                            </div>
                            <WishlistButton productId={product.id} initiallyActive={wishlistIds?.includes(product.id) ?? false} />
                        </div>

                        {/* Rating */}
                        {product.reviews_count > 0 && (
                            <div className="flex items-center gap-2">
                                <StarRating value={avgRating} size="sm" readonly />
                                <span className="text-sm text-muted-foreground">
                                    {avgRating.toFixed(1)} ({product.reviews_count} review{product.reviews_count !== 1 ? 's' : ''})
                                </span>
                            </div>
                        )}

                        {/* Separator */}
                        <Separator />

                        {/* Price */}
                        <ProductInfo product={product} selectedVariation={selectedVariation} priceRange={priceRange} isVariable={isVariable} isExternal={isExternal} isGrouped={isGrouped} />

                        {displaySku && (
                            <p className="text-xs text-muted-foreground">SKU: {displaySku}</p>
                        )}

                        {/* Variation Selector */}
                        {isVariable && attributes.length > 0 && (
                            <div>
                                <AttributeSelector
                                    attributes={attributes}
                                    variations={variations}
                                    defaultVariation={defaultVariation}
                                    onVariationChange={handleVariationChange}
                                    onImageChange={handleImageChange}
                                />
                            </div>
                        )}

                        {/* Stock Status */}
                        {!isExternal && (
                            <div className="flex flex-wrap items-center gap-3">
                                {isOnBackorder ? (
                                    <Badge variant="secondary" className="text-amber-600 border-amber-300 bg-amber-50 dark:bg-amber-950/20 dark:border-amber-800 rounded-full">
                                        On backorder
                                    </Badge>
                                ) : isLowStock ? (
                                    <Badge variant="secondary" className="text-orange-600 border-orange-300 bg-orange-50 dark:bg-orange-950/20 dark:border-orange-800 rounded-full">
                                        Only {displayStock} left
                                    </Badge>
                                ) : inStock ? (
                                    <div className="flex items-center gap-2">
                                        <span className="h-2 w-2 rounded-full bg-green-500" />
                                        <span className="text-sm text-green-600">
                                            {displayStock !== null ? `${displayStock} in stock` : 'In stock'}
                                            {product.incoming_stock > 0 && ` (${product.incoming_stock} incoming)`}
                                        </span>
                                    </div>
                                ) : (
                                    <div className="space-y-2">
                                        <div className="flex items-center gap-2">
                                            <span className="h-2 w-2 rounded-full bg-destructive" />
                                            <span className="text-sm text-destructive">Out of stock</span>
                                        </div>
                                        {product.track_stock && !notifySent && (
                                            <div className="flex gap-2 max-w-sm">
                                                <input
                                                    type="email"
                                                    placeholder="Your email"
                                                    value={notifyEmail}
                                                    onChange={(e) => setNotifyEmail(e.target.value)}
                                                    className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm"
                                                />
                                                <Button type="button" variant="outline" size="sm" onClick={handleNotify} disabled={notifying || !notifyEmail.trim()}>
                                                    {notifying ? 'Sending...' : 'Notify me'}
                                                </Button>
                                            </div>
                                        )}
                                        {notifySent && (
                                            <p className="text-xs text-green-600">We'll email you when back in stock!</p>
                                        )}
                                    </div>
                                )}
                                {isOnBackorder && product.backorders === 'notify' && (
                                    <span className="text-xs text-muted-foreground">Will ship when back in stock</span>
                                )}
                            </div>
                        )}

                        {/* Add to Cart / Buy Now */}
                        <div className="space-y-3">
                            {isExternal ? (
                                <a href={product.external_url || '#'} target="_blank" rel="noopener noreferrer" className="inline-block w-full">
                                    <Button size="lg" className="w-full rounded-full">
                                        <ExternalLink className="mr-2 h-4 w-4" />
                                        {product.button_text || 'Buy on external site'}
                                    </Button>
                                </a>
                            ) : isGrouped ? null : (
                                <div className="flex items-center gap-3">
                                    {!product.sold_individually && (
                                        <div className="flex items-center border rounded-full">
                                            <button className="p-2.5 hover:bg-muted transition-colors rounded-full"
                                                onClick={() => setQuantity(Math.max(product.min_quantity || 1, quantity - 1))}>
                                                <Minus className="h-4 w-4" />
                                            </button>
                                            <span className="px-4 text-sm font-medium min-w-[3ch] text-center">{quantity}</span>
                                            <button className="p-2.5 hover:bg-muted transition-colors rounded-full"
                                                onClick={() => setQuantity(product.max_quantity ? Math.min(product.max_quantity, quantity + 1) : quantity + 1)}>
                                                <Plus className="h-4 w-4" />
                                            </button>
                                        </div>
                                    )}
                                    <Button size="lg" className="flex-1 rounded-full"
                                        disabled={!inStock || addingToCart || (isVariable && !selectedVariation)}
                                        onClick={addToCart}>
                                        <ShoppingBag className="mr-2 h-4 w-4" />
                                        {!inStock ? 'Out of Stock'
                                            : isVariable && !selectedVariation ? 'Select options'
                                            : 'Add to Cart'}
                                    </Button>
                                </div>
                            )}
                        </div>

                        {/* Accordion — Collapsible Details */}
                        <Accordion type="multiple" defaultValue={['description']} className="border-t">
                            {product.description && (
                                <AccordionItem value="description">
                                    <AccordionTrigger>Description</AccordionTrigger>
                                    <AccordionContent>
                                        <div className="prose prose-sm max-w-none text-muted-foreground">{product.description}</div>
                                    </AccordionContent>
                                </AccordionItem>
                            )}
                            {visibleAttributes && visibleAttributes.length > 0 && (
                                <AccordionItem value="additional-info">
                                    <AccordionTrigger>Additional Information</AccordionTrigger>
                                    <AccordionContent>
                                        <table className="w-full text-sm">
                                            <tbody>
                                                {visibleAttributes.map((attr, i) => (
                                                    <tr key={i} className={i % 2 === 0 ? 'bg-muted/50' : ''}>
                                                        <td className="py-2 px-3 font-medium text-muted-foreground w-1/3">{attr.name}</td>
                                                        <td className="py-2 px-3">
                                                            <div className="flex flex-wrap gap-2 items-center">
                                                                {attr.terms.map((t, j) => (
                                                                    <span key={j} className="flex items-center gap-1 text-sm">
                                                                        {(attr.display_type === 'color' || attr.display_type === 'image') && t.swatch_value && (
                                                                            <span className={cn("inline-block h-3 w-3 rounded-full border", attr.display_type === 'image' ? 'bg-cover bg-center' : '')}
                                                                                style={attr.display_type === 'image' ? { backgroundImage: `url(${t.swatch_value})` } : { backgroundColor: t.swatch_value }} />
                                                                        )}
                                                                        {t.name}
                                                                    </span>
                                                                ))}
                                                            </div>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </AccordionContent>
                                </AccordionItem>
                            )}
                            <AccordionItem value="shipping">
                                <AccordionTrigger>Shipping &amp; Returns</AccordionTrigger>
                                <AccordionContent>
                                    <div className="text-sm text-muted-foreground space-y-2">
                                        {isVirtual ? (
                                            <p>This is a digital or service product. No physical shipping required. Delivery details will be provided after purchase.</p>
                                        ) : (
                                            <>
                                                <p>Free shipping on orders over 50 €. Standard delivery within 3–5 business days.</p>
                                                <p>Easy returns within 30 days of delivery. Items must be unused and in original packaging.</p>
                                            </>
                                        )}
                                    </div>
                                </AccordionContent>
                            </AccordionItem>
                            {product.downloadable && product.downloads && product.downloads.length > 0 && (
                                <AccordionItem value="downloads">
                                    <AccordionTrigger>Downloads</AccordionTrigger>
                                    <AccordionContent>
                                        <div className="space-y-2">
                                            {product.downloads.map((d) => (
                                                <div key={d.id} className="flex items-center gap-3 rounded-lg border p-3">
                                                    <FileDown className="h-4 w-4 text-muted-foreground" />
                                                    <span className="text-sm flex-1">{d.name}</span>
                                                    <span className="text-xs text-muted-foreground whitespace-nowrap">
                                                        {d.download_limit === -1 ? 'Unlimited' : `Max ${d.download_limit}`}
                                                        {d.download_expiry > 0 ? ` · ${d.download_expiry}d expiry` : ''}
                                                    </span>
                                                </div>
                                            ))}
                                        </div>
                                    </AccordionContent>
                                </AccordionItem>
                            )}
                        </Accordion>

                        {/* Grouped Products */}
                        {isGrouped && product.groupedItems && product.groupedItems.length > 0 && (
                            <div>
                                <h3 className="font-semibold text-sm mb-3">This group includes</h3>
                                <div className="divide-y rounded-lg border">
                                    {product.groupedItems.map((child) => (
                                        <Link key={child.id} href={route('store.show', child.id)} className="flex items-center gap-3 p-3 hover:bg-muted/50 transition-colors">
                                            <div className="h-12 w-12 rounded bg-muted flex items-center justify-center overflow-hidden shrink-0">
                                                {child.thumbnail_url ? (
                                                    <img src={child.thumbnail_url} alt={child.name} className="h-full w-full object-cover" />
                                                ) : (
                                                    <Package className="h-5 w-5 text-muted-foreground/50" />
                                                )}
                                            </div>
                                            <div className="flex-1 min-w-0">
                                                <p className="text-sm font-medium truncate">{child.name}</p>
                                                <p className="text-xs text-muted-foreground">Qty: {child.pivot.quantity}</p>
                                            </div>
                                            {child.price && (
                                                <span className="text-sm font-bold">{parseFloat(String(child.price)).toFixed(2)} €</span>
                                            )}
                                        </Link>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                </div>

                {/* Reviews Section */}
                {product.reviews.length > 0 && (
                    <section className="mt-16">
                        <div className="flex items-center gap-4 mb-8">
                            <h2 className="text-xl font-bold">Customer Reviews</h2>
                            <Badge variant="secondary">{product.reviews_count} review{product.reviews_count !== 1 ? 's' : ''}</Badge>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 max-w-4xl">
                            {product.reviews.map((review) => (
                                <Card key={review.id}>
                                    <CardContent className="p-4 space-y-2">
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-2">
                                                <div className="h-7 w-7 rounded-full bg-muted flex items-center justify-center text-xs font-medium">
                                                    {review.user?.name?.charAt(0).toUpperCase() ?? 'A'}
                                                </div>
                                                <span className="text-sm font-medium">{review.user?.name ?? 'Anonymous'}</span>
                                            </div>
                                        </div>
                                        <StarRating value={review.rating} size="sm" readonly />
                                        {review.title && <p className="font-semibold text-sm">{review.title}</p>}
                                        {review.content && <p className="text-sm text-muted-foreground">{review.content}</p>}
                                        <p className="text-xs text-muted-foreground">{review.created_at}</p>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    </section>
                )}

                {/* Related Products */}
                {related.length > 0 && (
                    <section className="mt-16">
                        <h2 className="text-xl font-bold mb-6">You might also like</h2>
                        <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
                            {related.map((r) => (
                                <Link key={r.id} href={route('store.show', r.id)} className="group">
                                    <Card className="overflow-hidden transition-shadow hover:shadow-md">
                                        <div className="aspect-square bg-muted flex items-center justify-center">
                                            {r.thumbnail_url ? (
                                                <img src={r.thumbnail_url} alt={r.name} className="h-full w-full object-cover group-hover:scale-105 transition-transform" />
                                            ) : (
                                                <ShoppingCart className="h-8 w-8 text-muted-foreground/30" />
                                            )}
                                        </div>
                                        <CardContent className="p-3 space-y-1">
                                            <p className="text-sm font-medium line-clamp-2">{r.name}</p>
                                            <p className="text-sm font-bold">{r.price.toFixed(2)} €</p>
                                        </CardContent>
                                    </Card>
                                </Link>
                            ))}
                        </div>
                    </section>
                )}
            </div>
        </StoreLayout>
    )
}
