import { Link, router, usePage, Head } from "@inertiajs/react";
import { ArrowRight, Package, Sparkles } from "lucide-react";
import StoreLayout from "../../Layouts/StoreLayout";
import { Button } from "@nreljed/platform/Components/ui/button";
import { Card, CardContent } from "@nreljed/platform/Components/ui/card";
import { Badge } from "@nreljed/platform/Components/ui/badge";
import { ProductCard, SectionHeader, type Product } from "../../components/storefront/ProductCard";

interface HomepageCategory {
    id: number;
    name: string;
    slug: string;
    products_count: number;
}

interface HomepageData {
    hero: { title: string; subtitle: string; cta_label: string };
    featured: Product[];
    newArrivals: Product[];
    categories: HomepageCategory[];
}

interface HomePage {
    homepage: HomepageData | null;
    wishlistIds: number[];
    seo?: { title?: string; description?: string; og_image?: string } | null;
}

export default function StoreHome() {
    const { props } = usePage<HomePage>();
    const { homepage, wishlistIds, seo } = props;

    if (!homepage) {
        return (
            <StoreLayout>
                <Head title="Store">
                    <meta name="description" content={seo?.description || "Browse our store"} />
                </Head>
                <div className="mx-auto max-w-7xl px-4 py-16 text-center">
                    <p className="text-muted-foreground">The store is not available right now.</p>
                    <Link href={route("home")} className="mt-4 inline-block text-sm text-primary hover:underline">
                        Back to home
                    </Link>
                </div>
            </StoreLayout>
        );
    }

    const absoluteUrl = (path: string) => {
        if (typeof window === "undefined") return "";
        return /^https?:\/\//i.test(path) ? path : `${window.location.origin}${path}`;
    };

    return (
        <StoreLayout>
            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <Head title={seo?.title || "Store"}>
                    <meta name="description" content={seo?.description || homepage.hero.subtitle} />
                    <link rel="canonical" href={absoluteUrl(route("store.index"))} />
                    <meta property="og:title" content={seo?.title || "Store"} />
                    <meta property="og:description" content={seo?.description || homepage.hero.subtitle} />
                    <meta property="og:type" content="website" />
                    <meta property="og:url" content={absoluteUrl(route("store.index"))} />
                    {seo?.og_image && <meta property="og:image" content={seo.og_image} />}
                    <meta name="twitter:card" content="summary_large_image" />
                </Head>

                {/* Hero */}
                <section className="relative overflow-hidden rounded-3xl border bg-gradient-to-br from-primary/10 via-background to-background px-6 py-16 sm:px-12 sm:py-24">
                    <div className="relative z-10 max-w-xl">
                        <Badge variant="secondary" className="rounded-full mb-4">
                            <Sparkles className="h-3 w-3 mr-1" /> New season
                        </Badge>
                        <h1 className="text-4xl font-bold tracking-tight sm:text-5xl">{homepage.hero.title}</h1>
                        <p className="mt-4 text-muted-foreground text-lg">{homepage.hero.subtitle}</p>
                        <div className="mt-8 flex flex-wrap gap-3">
                            <Button size="lg" className="rounded-full" onClick={() => router.get(route("store.products"))}>
                                {homepage.hero.cta_label} <ArrowRight className="ml-2 h-4 w-4" />
                            </Button>
                            {homepage.categories.length > 0 && (
                                <Button
                                    size="lg"
                                    variant="outline"
                                    className="rounded-full"
                                    onClick={() => {
                                        const el = document.getElementById("shop-categories");
                                        el?.scrollIntoView({ behavior: "smooth" });
                                    }}
                                >
                                    Browse categories
                                </Button>
                            )}
                        </div>
                    </div>
                    <div className="absolute -right-16 -top-16 h-64 w-64 rounded-full bg-primary/10 blur-3xl" />
                    <div className="absolute -bottom-20 -left-10 h-72 w-72 rounded-full bg-primary/5 blur-3xl" />
                </section>

                {/* Featured */}
                {homepage.featured.length > 0 && (
                    <section className="mt-16">
                        <SectionHeader title="Featured products" href={route("store.products")} />
                        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 sm:gap-6">
                            {homepage.featured.map((product) => (
                                <ProductCard key={product.id} product={product} wishlistIds={wishlistIds} />
                            ))}
                        </div>
                    </section>
                )}

                {/* New Arrivals */}
                {homepage.newArrivals.length > 0 && (
                    <section className="mt-16">
                        <SectionHeader title="New arrivals" href={route("store.products")} />
                        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 sm:gap-6">
                            {homepage.newArrivals.map((product) => (
                                <ProductCard key={product.id} product={product} wishlistIds={wishlistIds} />
                            ))}
                        </div>
                    </section>
                )}

                {/* Category Grid */}
                {homepage.categories.length > 0 && (
                    <section id="shop-categories" className="mt-16 scroll-mt-24">
                        <SectionHeader title="Shop by category" href={route("store.products")} />
                        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                            {homepage.categories.map((cat) => (
                                <Link key={cat.id} href={route("store.category", cat.slug)} className="group">
                                    <Card className="overflow-hidden transition-shadow hover:shadow-lg">
                                        <div className="aspect-[3/2] bg-gradient-to-br from-primary/10 to-muted flex items-center justify-center">
                                            <Package className="h-8 w-8 text-primary/40 group-hover:scale-110 transition-transform" />
                                        </div>
                                        <CardContent className="p-4 flex items-center justify-between">
                                            <span className="font-semibold text-sm">{cat.name}</span>
                                            <span className="text-xs text-muted-foreground">{cat.products_count} item{cat.products_count !== 1 ? "s" : ""}</span>
                                        </CardContent>
                                    </Card>
                                </Link>
                            ))}
                        </div>
                    </section>
                )}
            </div>
        </StoreLayout>
    );
}
