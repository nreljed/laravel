import { Link, router, usePage, Head } from "@inertiajs/react";
import { useState, type ReactNode } from "react";
import type { PageProps } from "@inertiajs/core";
import { Search, ShoppingCart, SlidersHorizontal, ChevronRight } from "lucide-react";
import StoreLayout from "../../Layouts/StoreLayout";
import { Button } from "@nreljed/platform/Components/ui/button";
import { Input } from "@nreljed/platform/Components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@nreljed/platform/Components/ui/select";
import { Pagination, PaginationContent, PaginationItem, PaginationLink, PaginationNext, PaginationPrevious } from "@nreljed/platform/Components/ui/pagination";
import { useCurrency } from "../../lib/currency";
import { Slider } from "@nreljed/platform/Components/ui/slider";
import { cn } from "@nreljed/platform/lib/utils";
import { ProductCard, type Product } from "../../components/storefront/ProductCard";

interface Category {
    id: number;
    name: string;
    slug: string;
    children?: Category[];
}

interface ActiveCategoryChild {
    id: number;
    name: string;
    slug: string;
    products_count: number;
}

interface Brand {
    id: number;
    name: string;
    slug: string;
    products_count: number;
}

interface AttributeTermFilter {
    id: number;
    name: string;
    swatch_value: string | null;
}

interface AttributeFilter {
    id: number;
    name: string;
    display_type: string;
    terms: AttributeTermFilter[];
}

interface Filters {
    search?: string | null;
    category?: string | null;
    brand?: string | null;
    attribute_terms?: number[] | null;
    min_price?: string | null;
    max_price?: string | null;
    sort?: string | null;
}

interface StorePage {
    products: { data: Product[]; current_page: number; last_page: number; prev_page_url: string | null; next_page_url: string | null; links: { url: string | null; label: string; active: boolean }[] }
    categories: Category[];
    brands: Brand[];
    attributeFilters: AttributeFilter[];
    filters: Filters;
    priceRange: [number, number];
    activeCategory?: { id: number; name: string; slug: string; parent_id: number | null; children: ActiveCategoryChild[] } | null;
    breadcrumbs?: { name: string; slug: string }[];
    seo?: { title?: string; description?: string; og_image?: string } | null;
}

function AttributeTermToggle({ term, displayType, active, onToggle }: {
    term: AttributeTermFilter
    displayType: string
    active: boolean
    onToggle: (termId: number) => void
}) {
    if (displayType === "color" || displayType === "image") {
        return (
            <button
                type="button"
                onClick={() => onToggle(term.id)}
                title={term.name}
                aria-label={term.name}
                className={cn(
                    "h-8 w-8 rounded-full border-2 transition-all shrink-0",
                    active ? "ring-2 ring-primary ring-offset-2" : "border-border hover:border-muted-foreground/50"
                )}
                style={displayType === "color" && term.swatch_value ? { backgroundColor: term.swatch_value } : undefined}
            >
                {displayType === "image" && term.swatch_value ? (
                    <img src={term.swatch_value} alt={term.name} className="h-full w-full rounded-full object-cover" />
                ) : null}
            </button>
        );
    }
    return (
        <button
            type="button"
            onClick={() => onToggle(term.id)}
            className={cn(
                "px-3 py-1.5 text-xs rounded-full border transition-colors shrink-0",
                active ? "bg-primary text-primary-foreground border-primary" : "border-border hover:border-muted-foreground/50"
            )}
        >
            {term.name}
        </button>
    );
}

export default function StorefrontProducts() {
    const { props } = usePage<PageProps & StorePage & { wishlistIds: number[] }>()
    const { products, categories, brands, attributeFilters, filters, priceRange, activeCategory, wishlistIds, seo } = props
    const breadcrumbs = props.breadcrumbs ?? []
    const { money } = useCurrency();
    const [priceValue, setPriceValue] = useState<[number, number]>([priceRange[0], priceRange[1]])
    
    /* Both bounds have to land in the query together, so they are applied in a
     * single visit rather than one after the other - two visits would race and
     * the second could drop the first bound. */
    function applyPriceFilter() {
        applyFilters({ min_price: String(priceValue[0]), max_price: String(priceValue[1]) })
    }

    function applyFilter(key: string, value: string | null) {
        applyFilters({ [key]: value || "" })
    }

    function applyFilters(overrides: Record<string, unknown>) {
        router.get(route("store.products"), { ...filters, ...overrides }, { preserveState: true, preserveScroll: true })
    }

    function toggleAttributeTerm(termId: number) {
        const current: number[] = Array.isArray(filters.attribute_terms) ? filters.attribute_terms : []
        const next = current.includes(termId) ? current.filter((t) => t !== termId) : [...current, termId]
        router.get(route("store.products"), { ...filters, attribute_terms: next }, { preserveState: true, preserveScroll: true })
    }

    function handleSearch(e: React.FormEvent) {
        e.preventDefault()
        const form = e.target as HTMLFormElement
        const search = (new FormData(form).get("search") as string) || ""
        router.get(route("store.products"), { ...filters, search }, { preserveState: true, preserveScroll: true })
    }

    const pageTitle = activeCategory ? activeCategory.name : (filters.search ? `Search: ${filters.search}` : "All Products")
    const activeTermIds: number[] = Array.isArray(filters.attribute_terms) ? filters.attribute_terms : []

    function renderCategoryTree(list: Category[], depth: number): ReactNode {
        return (
            <div className="space-y-1">
                {list.map((cat) => {
                    const active = filters.category === cat.slug || activeCategory?.slug === cat.slug
                    return (
                        <div key={cat.id}>
                            <Link href={route("store.category", cat.slug)}
                                className={`flex gap-1 text-sm px-3 py-1.5 rounded-md ${active ? "bg-primary/10 text-primary font-medium" : "text-muted-foreground hover:text-foreground"}`}
                                style={{ paddingLeft: `${12 + depth * 14}px` }}>
                                {cat.name}
                            </Link>
                            {cat.children && cat.children.length > 0 && renderCategoryTree(cat.children, depth + 1)}
                        </div>
                    )
                })}
            </div>
        )
    }

    const absoluteUrl = (path: string) => {
        if (typeof window === "undefined") return ""
        return /^https?:\/\//i.test(path) ? path : `${window.location.origin}${path}`
    }
    const pageUrl = activeCategory ? absoluteUrl(route("store.category", activeCategory.slug)) : absoluteUrl(route("store.products"))
    const siteDescription = seo?.description || ""
    const pageDescription = activeCategory
        ? `Browse ${activeCategory.name} products`
        : (filters.search ? `Search results for "${filters.search}"` : (siteDescription || "Browse our product catalog"))
    const ogImage = seo?.og_image || ""

    const itemListLd = JSON.stringify({
        "@context": "https://schema.org/",
        "@type": "ItemList",
        itemListElement: (products.data || []).slice(0, 50).map((p, i) => ({
            "@type": "ListItem",
            position: i + 1,
            name: p.name,
            item: absoluteUrl(route("store.show", p.slug)),
        })),
    })

    return (
        <StoreLayout>
            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <Head title={pageTitle}>
                    <meta name="description" content={pageDescription} />
                    <link rel="canonical" href={pageUrl} />
                    <meta property="og:title" content={pageTitle} />
                    <meta property="og:description" content={pageDescription} />
                    <meta property="og:type" content="website" />
                    <meta property="og:url" content={pageUrl} />
                    {ogImage && <meta property="og:image" content={ogImage} />}
                    <meta name="twitter:card" content="summary_large_image" />
                    <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: itemListLd }} />
                </Head>

                {breadcrumbs.length > 0 && (
                    <nav aria-label="Breadcrumb" className="flex flex-wrap items-center gap-1 mt-8 text-sm text-muted-foreground">
                        <Link href={route("store.products")} className="hover:text-foreground">
                            Home
                        </Link>
                        {breadcrumbs.map((crumb, index) => {
                            const isLast = index === breadcrumbs.length - 1
                            return (
                                <span key={crumb.slug} className="flex items-center gap-1">
                                    <ChevronRight className="h-3 w-3" />
                                    {isLast ? (
                                        <span aria-current="page" className="font-medium text-foreground">{crumb.name}</span>
                                    ) : (
                                        <Link href={route("store.category", crumb.slug)} className="hover:text-foreground">
                                            {crumb.name}
                                        </Link>
                                    )}
                                </span>
                            )
                        })}
                    </nav>
                )}

                <div className="flex items-center justify-between mt-8 mb-8">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">
                            {activeCategory ? activeCategory.name : "All Products"}
                        </h1>
                        <p className="text-muted-foreground mt-1">
                            {products.data.length} product{products.data.length !== 1 ? "s" : ""}
                        </p>
                    </div>
                    <form onSubmit={handleSearch} className="flex gap-2">
                        <Input name="search" defaultValue={filters.search ?? ""} placeholder="Search products..." className="w-64" />
                        <Button type="submit" variant="outline" size="icon"><Search className="h-4 w-4" /></Button>
                    </form>
                </div>

                {activeCategory && activeCategory.children.length > 0 && (
                    <div className="flex flex-wrap gap-2 mb-6">
                        {activeCategory.children.map((child) => (
                            <Link key={child.id} href={route("store.category", child.slug)}
                                className="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm transition-colors hover:bg-accent">
                                {child.name}
                                <span className="text-xs text-muted-foreground">({child.products_count})</span>
                            </Link>
                        ))}
                    </div>
                )}

                <div className="flex gap-8">
                    <aside className="hidden lg:block w-56 shrink-0 space-y-6">
                        <div>
                            <h3 className="text-sm font-semibold mb-3 flex items-center gap-2">
                                <SlidersHorizontal className="h-4 w-4" /> Categories
                            </h3>
                            <div className="space-y-1">
                                <Link href={route("store.products")}
                                    className={`block text-sm px-3 py-1.5 rounded-md ${!filters.category && !activeCategory ? "bg-primary/10 text-primary font-medium" : "text-muted-foreground hover:text-foreground"}`}>
                                    All Products
                                </Link>
                                {renderCategoryTree(categories, 0)}
                            </div>
                        </div>

                        {brands.length > 0 && (
                            <div>
                                <h3 className="text-sm font-semibold mb-3">Brands</h3>
                                <div className="space-y-1">
                                    <button type="button" onClick={() => applyFilter("brand", null)}
                                        className={`block w-full text-left text-sm px-3 py-1.5 rounded-md ${!filters.brand ? "bg-primary/10 text-primary font-medium" : "text-muted-foreground hover:text-foreground"}`}>
                                        All Brands
                                    </button>
                                    {brands.map((brand) => (
                                        <button key={brand.id} type="button" onClick={() => applyFilter("brand", brand.slug)}
                                            className={`block w-full text-left text-sm px-3 py-1.5 rounded-md ${filters.brand === brand.slug ? "bg-primary/10 text-primary font-medium" : "text-muted-foreground hover:text-foreground"}`}>
                                            {brand.name}
                                            <span className="text-xs text-muted-foreground ml-1">({brand.products_count})</span>
                                        </button>
                                    ))}
                                </div>
                            </div>
                        )}

                        {attributeFilters.length > 0 && (
                            <div className="space-y-5">
                                {attributeFilters.map((attr) => (
                                    <div key={attr.id}>
                                        <h3 className="text-sm font-semibold mb-3">{attr.name}</h3>
                                        <div className="flex flex-wrap gap-2">
                                            {attr.terms.map((term) => (
                                                <AttributeTermToggle
                                                    key={term.id}
                                                    term={term}
                                                    displayType={attr.display_type}
                                                    active={activeTermIds.includes(term.id)}
                                                    onToggle={toggleAttributeTerm}
                                                />
                                            ))}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}

                        {priceRange[0] < priceRange[1] && (
                            <div>
                                <h3 className="text-sm font-semibold mb-3">Price Range</h3>
                                <Slider
                                    min={priceRange[0]}
                                    max={priceRange[1]}
                                    step={1}
                                    value={priceValue}
                                    onValueChange={(v) => setPriceValue(v as [number, number])}
                                    /* Base UI names the commit callback
                                     * `onValueCommitted`; the old
                                     * `onValueCommit` was silently dropped, so
                                     * releasing the slider never applied the
                                     * range. */
                                    onValueCommitted={() => applyPriceFilter()}
                                    className="mb-2"
                                />
                                <div className="flex justify-between text-xs text-muted-foreground">
                                    <span>{money(priceValue[0])}</span>
                                    <span>{money(priceValue[1])}</span>
                                </div>
                            </div>
                        )}

                        <div>
                            <h3 className="text-sm font-semibold mb-3">Sort By</h3>
                            <Select value={filters.sort ?? "newest"}
                                onValueChange={(v) => applyFilter("sort", v)}>
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
                                    <ProductCard key={product.id} product={product} wishlistIds={wishlistIds} />
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
                                            <PaginationLink href={products.links?.[page]?.url ?? undefined} isActive={page === products.current_page} />
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
