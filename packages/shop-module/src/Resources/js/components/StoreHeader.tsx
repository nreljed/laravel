import { Link, usePage } from "@inertiajs/react";
import { useState } from "react";
import { Menu, X, ShoppingCart } from "lucide-react";
import { Button } from "@nreljed/platform/Components/ui/button";
import { Separator } from "@nreljed/platform/Components/ui/separator";
import { BrandLogo } from "@nreljed/platform/Components/frontend/BrandLogo";
import { NavUser } from "@nreljed/platform/Components/navigation/nav-user";
import { cn } from "@nreljed/platform/lib/utils";

interface CartItem {
    quantity: number;
}

interface CartData {
    items: CartItem[];
}

interface PageProps {
    sharedData?: {
        cart?: CartData;
    };
    brand?: { logo?: string; favicon?: string; name?: string };
}

export function StoreHeader() {
    const { props } = usePage<PageProps>();
    const brand = props.brand || {};
    const cart = props.sharedData?.cart || { items: [] };
    const itemCount = (cart.items || []).reduce((sum, item) => sum + item.quantity, 0);
    const appName = brand.name || "";

    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);

    const isActive = (name: string) => route().current(name) || false;

    const linkClass = (active: boolean) =>
        cn(
            "px-3 py-2 text-sm font-medium rounded-md transition-colors",
            active ? "text-foreground bg-accent" : "text-muted-foreground hover:text-foreground hover:bg-accent"
        );

    // Marketing pages (about/services/contact) are optional host features (PublicSite)
    // provided by the platform; only render the link when the consumer actually
    // registers the route so shop-module stays portable across store-only and
    // store+marketing apps.
    const siteNav = [
        { name: "home", label: "Home" },
        { name: "store.index", label: "Store" },
        { name: "store.products", label: "Products" },
        { name: "blog.index", label: "Blog" },
        { name: "about", label: "About" },
        { name: "services", label: "Services" },
        { name: "contact", label: "Contact" },
    ].filter((item) => route().has(item.name));

    const siteLinks = (
        <>
            {siteNav.map((item) => (
                <Link
                    key={item.name}
                    href={route(item.name)}
                    className={linkClass(isActive(item.name))}
                >
                    {item.label}
                </Link>
            ))}
        </>
    );

    return (
        <nav className="border-b border-border bg-background">
            <div className="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 sm:px-6 lg:px-8">
                <div className="flex items-center gap-6">
                    {route().has("home") && (
                        <Link href={route("home")} className="flex items-center gap-2 shrink-0">
                            <BrandLogo
                                logo={brand.logo || null}
                                favicon={brand.favicon || null}
                                name={appName}
                                size="sm"
                                showName
                            />
                        </Link>
                    )}
                    <div className="hidden sm:flex items-center gap-1">{siteLinks}</div>
                </div>
                <div className="flex items-center gap-2">
                    <div className="hidden sm:flex">
                        <NavUser variant="basic" />
                    </div>
                    <Link
                        href={route("store.cart.index")}
                        className="relative flex items-center justify-center rounded-md p-2 text-muted-foreground hover:text-foreground hover:bg-accent transition-colors"
                        aria-label={`Shopping cart, ${itemCount} item${itemCount !== 1 ? "s" : ""}`}
                    >
                        <ShoppingCart className="h-5 w-5" />
                        {itemCount > 0 && (
                            <span className="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[10px] font-semibold text-primary-foreground">
                                {itemCount}
                            </span>
                        )}
                    </Link>
                    <Button
                        variant="ghost"
                        size="icon"
                        className="sm:hidden"
                        aria-label={mobileMenuOpen ? "Close menu" : "Open menu"}
                        aria-expanded={mobileMenuOpen}
                        aria-controls="store-mobile-menu"
                        onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
                    >
                        {mobileMenuOpen ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
                    </Button>
                </div>
            </div>
            {mobileMenuOpen && (
                <div id="store-mobile-menu" className="border-t border-border sm:hidden">
                    <div className="space-y-1 px-4 py-3">
                        {siteNav.map((item) => (
                            <Link
                                key={item.name}
                                href={route(item.name)}
                                className="block rounded-md px-3 py-2 text-sm font-medium text-muted-foreground hover:text-foreground hover:bg-accent"
                                onClick={() => setMobileMenuOpen(false)}
                            >
                                {item.label}
                            </Link>
                        ))}
                        <Separator className="my-2" />
                        <div className="flex items-center justify-between">
                            <NavUser variant="basic" />
                            <Link
                                href={route("store.cart.index")}
                                className="relative flex items-center justify-center rounded-md p-2 text-muted-foreground hover:text-foreground hover:bg-accent transition-colors"
                                aria-label={`Shopping cart, ${itemCount} item${itemCount !== 1 ? "s" : ""}`}
                            >
                                <ShoppingCart className="h-5 w-5" />
                                {itemCount > 0 && (
                                    <span className="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[10px] font-semibold text-primary-foreground">
                                        {itemCount}
                                    </span>
                                )}
                            </Link>
                        </div>
                    </div>
                </div>
            )}
        </nav>
    );
}

export default StoreHeader;
