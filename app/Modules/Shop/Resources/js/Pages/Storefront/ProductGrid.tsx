import { Link, router, usePage, Head } from '@inertiajs/react'
import { useState, useRef } from 'react'
import { Search, ShoppingCart, SlidersHorizontal, Heart } from 'lucide-react'
import StoreLayout from '@/Layouts/StoreLayout'
import { Button } from '@/Components/ui/button'
import { Card, CardContent, CardFooter } from '@/Components/ui/card'
import { Input } from '@/Components/ui/input'
import { Badge } from '@/Components/ui/badge'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select'
import { Pagination, PaginationContent, PaginationItem, PaginationNext, PaginationPrevious } from '@/Components/ui/pagination'
import { StarRating } from '@modules-shop/components/StarRating'
import { WishlistButton } from '@modules-shop/components/WishlistButton'
import { Slider } from '@/Components/ui/slider'

interface Product {
    id: number
    name: string
    slug: string
    price: number
    compare_price: number | null
    type: string
    virtual: boolean
    downloadable: boolean
    external_url: string | null
    thumbnail_url: string | null
    average_rating: number
    reviews_count: number
    stock_quantity: number
    track_stock: boolean
    backorders: string
    stock_status: string | null
    low_stock_threshold: number
}

interface Category {
    id: number
    name: string
    slug: string
}

interface StorePage {
    products: { data: Product[]; current_page: number; last_page: number; prev_page_url: string | null; next_page_url: string | null; links: { url: string | null; label: string; active: boolean }[] }
    categories: Category[]
    filters: Record<string, string | null>
    priceRange: [number, number]
    activeCategory?: { id: number; name: string; slug: string } | null
}

export default function StorefrontIndex() {
    const { props } = usePage<StorePage & { wishlistIds: number[] }>()
    const { products, categories, filters, priceRange, activeCategory, wishlistIds } = props
    const [priceValue, setPriceValue] = useState<[number, number]>([priceRange[0], priceRange[1]])
    const [applyingPrice, setApplyingPrice] = useState(false)
    const debounceRef = useRef<ReturnType<typeof setTimeout>>()

    function applyPriceFilter() {
        setApplyingPrice(true)
        applyFilter('min_price', String(priceValue[0]))
        setTimeout(() => {
            applyFilter('max_price', String(priceValue[1]))
            setApplyingPrice(false)
        }, 50)
    }

    function applyFilter(key: string, value: string | null) {
        router.get(route('store.index'), { ...filters, [key]: value || '' }, { preserveState: true, preserveScroll: true })
    }

    function handleSearch(e: React.FormEvent) {
        e.preventDefault()
        const form = e.target as HTMLFormElement
        const search = (new FormData(form).get('search') as string) || ''
        router.get(route('store.index'), { ...filters, search }, { preserveState: true, preserveScroll: true })
    }

    const pageTitle = activeCategory ? activeCategory.name : (filters.search ? `Search: ${filters.search}` : 'Store')

    return (
        <StoreLayout>
            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <Head title={pageTitle}>
                    <meta name="description" content={pageTitle === 'Store' ? 'Browse our product catalog' : `Browse ${pageTitle} products`} />
                </Head>
                <div className="flex items-center justify-between mb-8">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">
                            {activeCategory ? activeCategory.name : 'Store'}
                        </h1>
                        <p className="text-muted-foreground mt-1">
                            {products.data.length} product{products.data.length !== 1 ? 's' : ''}
                        </p>
                    </div>
                    <form onSubmit={handleSearch} className="flex gap-2">
                        <Input name="search" defaultValue={filters.search ?? ''} placeholder="Search products..." className="w-64" />
                        <Button type="submit" variant="outline" size="icon"><Search className="h-4 w-4" /></Button>
                    </form>
                </div>

                <div className="flex gap-8">
                    <aside className="w-56 shrink-0 space-y-6">
                        <div>
                            <h3 className="text-sm font-semibold mb-3 flex items-center gap-2">
                                <SlidersHorizontal className="h-4 w-4" /> Categories
                            </h3>
                            <div className="space-y-1">
                                <Link href={route('store.index')}
                                    className={`block text-sm px-3 py-1.5 rounded-md ${!filters.category ? 'bg-primary/10 text-primary font-medium' : 'text-muted-foreground hover:text-foreground'}`}>
                                    All Products
                                </Link>
                                {categories.map((cat) => (
                                    <Link key={cat.id} href={route('store.category', cat.slug)}
                                        className={`block text-sm px-3 py-1.5 rounded-md ${filters.category === cat.slug ? 'bg-primary/10 text-primary font-medium' : 'text-muted-foreground hover:text-foreground'}`}>
                                        {cat.name}
                                    </Link>
                                ))}
                            </div>
                        </div>

                        {priceRange[0] < priceRange[1] && (
                            <div>
                                <h3 className="text-sm font-semibold mb-3">Price Range</h3>
                                <Slider
                                    min={priceRange[0]}
                                    max={priceRange[1]}
                                    step={1}
                                    value={priceValue}
                                    onValueChange={(v) => setPriceValue(v as [number, number])}
                                    onValueCommit={() => applyPriceFilter()}
                                    className="mb-2"
                                />
                                <div className="flex justify-between text-xs text-muted-foreground">
                                    <span>{priceValue[0].toFixed(2)} €</span>
                                    <span>{priceValue[1].toFixed(2)} €</span>
                                </div>
                            </div>
                        )}

                        <div>
                            <h3 className="text-sm font-semibold mb-3">Sort By</h3>
                            <Select value={filters.sort ?? 'newest'}
                                onValueChange={(v) => applyFilter('sort', v)}>
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="newest">Newest</SelectItem>
                                    <SelectItem value="price_asc">Price: Low to High</SelectItem>
                                    <SelectItem value="price_desc">Price: High to Low</SelectItem>
                                    <SelectItem value="name">Name</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </aside>

                    <div className="flex-1">
                        {products.data.length === 0 ? (
                            <div className="text-center py-16">
                                <ShoppingCart className="mx-auto h-12 w-12 text-muted-foreground/40" />
                                <h3 className="mt-4 text-lg font-semibold">No products found</h3>
                                <p className="text-sm text-muted-foreground mt-1">Try adjusting your search or filters.</p>
                            </div>
                        ) : (
                            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                                {products.data.map((product) => (
                                        <Link key={product.id} href={route('store.show', product.id)} className="group">
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
                                                {product.type !== 'simple' && (
                                                    <Badge variant="outline" className="text-[10px] px-1.5 py-0.5 bg-background/80">{product.type}</Badge>
                                                )}
                                                {product.track_stock && product.stock_quantity <= 0 && product.backorders !== 'no' && (
                                                    <>
                                                        <Badge variant="secondary" className="text-[10px] px-1.5 py-0.5 text-amber-600 border-amber-300 bg-amber-50 dark:bg-amber-950/20 dark:border-amber-800">
                                                            On backorder
                                                        </Badge>
                                                        {product.backorders === 'notify' && (
                                                            <span className="text-[10px] text-muted-foreground">Will ship when back in stock</span>
                                                        )}
                                                    </>
                                                )}
                                                {product.track_stock && product.stock_quantity <= 0 && product.backorders === 'no' && (
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
                                                <span className="text-lg font-bold">{product.price.toFixed(2)} €</span>
                                                {product.compare_price && product.compare_price > product.price && (
                                                    <span className="text-sm text-muted-foreground line-through">{product.compare_price.toFixed(2)} €</span>
                                                )}
                                            </CardFooter>
                                        </Card>
                                    </Link>
                                ))}
                            </div>
                        )}

                        {products.last_page > 1 && (
                            <Pagination className="mt-8">
                                <PaginationContent>
                                    {products.prev_page_url && (
                                        <PaginationItem>
                                            <PaginationPrevious href={products.prev_page_url} />
                                        </PaginationItem>
                                    )}
                                    {Array.from({ length: products.last_page }, (_, i) => i + 1).map((page) => (
                                        <PaginationItem key={page}>
                                            <PaginationLink href={products.links?.[page]?.url} isActive={page === products.current_page} />
                                        </PaginationItem>
                                    ))}
                                    {products.next_page_url && (
                                        <PaginationItem>
                                            <PaginationNext href={products.next_page_url} />
                                        </PaginationItem>
                                    )}
                                </PaginationContent>
                            </Pagination>
                        )}
                    </div>
                </div>
            </div>
        </StoreLayout>
    )
}
