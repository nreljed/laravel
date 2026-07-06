import { Link, router, usePage } from "@inertiajs/react";
import React, { useState, useEffect } from "react";
import {
  ShoppingCart,
  Menu,
  X,
  Search,
  Package,
  ChevronDown,
  Heart,
  Store,
  Plus,
  Minus,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { Button } from "@/Components/ui/button";
import { Badge } from "@/Components/ui/badge";
import {
  NavigationMenu,
  NavigationMenuList,
  NavigationMenuItem,
  NavigationMenuContent,
  NavigationMenuTrigger,
  NavigationMenuLink,
} from "@/Components/ui/navigation-menu";
import {
  Sheet,
  SheetTrigger,
  SheetContent,
  SheetHeader,
  SheetTitle,
  SheetDescription,
  SheetClose,
} from "@/Components/ui/sheet";
import {
  CommandDialog,
  CommandInput,
  CommandList,
  CommandEmpty,
  CommandGroup,
  CommandItem,
} from "@/Components/ui/command";
import { Separator } from "@/Components/ui/separator";
import { ScrollArea } from "@/Components/ui/scroll-area";
import { BrandLogo } from "@/Components/frontend/BrandLogo";
import { NavUser } from "@/Components/nav-user";
import { CurrencySwitcher } from "@/Components/frontend/CurrencySwitcher";

interface CartItemProduct {
  id: number;
  name: string;
  slug: string;
  thumbnail_url: string | null;
}

interface CartItemVariation {
  id: number;
  image_url: string | null;
}

interface CartItem {
  id: number;
  product_id: number;
  quantity: number;
  unit_price: number;
  variant_label: string | null;
  product: CartItemProduct | null;
  variation: CartItemVariation | null;
}

interface CartData {
  id: number;
  items: CartItem[];
  subtotal: number;
  total: number;
  coupon_code: string | null;
  discount: number;
}

interface Category {
  id: number;
  name: string;
  slug: string;
}

interface SiteHeaderProps {
  variant?: "default" | "store";
  categories?: Category[];
  cart?: CartData;
}

const ListItem = React.forwardRef<
  React.ComponentRef<"a">,
  React.ComponentPropsWithoutRef<"a"> & { title: string }
>(({ className, title, children, ...props }, ref) => {
  return (
    <li>
      <NavigationMenuLink asChild>
        <a
          ref={ref}
          className={cn(
            "block select-none space-y-1 rounded-md p-3 leading-none no-underline outline-none transition-colors hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground",
            className,
          )}
          {...props}
        >
          <div className="text-sm font-medium leading-none">{title}</div>
          <p className="line-clamp-2 text-sm leading-snug text-muted-foreground">{children}</p>
        </a>
      </NavigationMenuLink>
    </li>
  );
});
ListItem.displayName = "ListItem";

export function SiteHeader({ variant = "default", categories = [], cart }: SiteHeaderProps) {
  const { props } = usePage();
  const brand = (props.brand as { logo?: string; name?: string }) || {};
  const appName = brand.name || "Cost Breaker 360";
  const isStore = variant === "store";

  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
  const [cartOpen, setCartOpen] = useState(false);
  const [searchOpen, setSearchOpen] = useState(false);
  const [searchQuery, setSearchQuery] = useState("");

  const currentCart = cart || (props.cart as CartData) || { items: [], subtotal: 0, total: 0 };
  const itemCount = currentCart.items?.length ?? 0;
  const wishlistIds = (props.wishlistIds as number[]) || [];

  useEffect(() => {
    if (!isStore) return;
    const down = (e: KeyboardEvent) => {
      if (!(e.key === "k" && (e.metaKey || e.ctrlKey))) {
        return;
      }

      e.preventDefault();
      setSearchOpen((open) => !open);
    };
    document.addEventListener("keydown", down);
    return () => document.removeEventListener("keydown", down);
  }, [isStore]);

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    if (searchQuery.trim()) {
      router.get(route("store.index"), { search: searchQuery.trim() });
      setSearchOpen(false);
      setSearchQuery("");
    }
  };

  const [cartUpdating, setCartUpdating] = useState<Record<number, boolean>>({});

  const formatPrice = (num: number) =>
    new Intl.NumberFormat("en-US", { style: "currency", currency: "EUR" }).format(num);

  const reloadCart = () => router.reload({ only: ["cart"] });

  const updateQuantity = async (itemId: number, newQty: number) => {
    if (newQty < 0) return;
    setCartUpdating((prev) => ({ ...prev, [itemId]: true }));
    try {
      const token =
        document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
      const res = await fetch(route("store.cart.update-item", itemId), {
        method: "PUT",
        headers: { "Content-Type": "application/json", "X-CSRF-TOKEN": token },
        body: JSON.stringify({ quantity: newQty }),
      });
      if (res.ok) reloadCart();
    } finally {
      setCartUpdating((prev) => ({ ...prev, [itemId]: false }));
    }
  };

  const removeItem = async (itemId: number) => {
    try {
      const token =
        document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
      await fetch(route("store.cart.remove-item", itemId), {
        method: "DELETE",
        headers: { "Content-Type": "application/json", "X-CSRF-TOKEN": token },
      });
      reloadCart();
    } catch {}
  };

  const siteLinks = (
    <>
      <Link
        href={route("store.index")}
        className="px-3 py-2 text-sm font-medium text-muted-foreground hover:text-foreground hover:bg-accent rounded-md transition-colors"
      >
        Store
      </Link>
      <Link
        href={route("about")}
        className="px-3 py-2 text-sm font-medium text-muted-foreground hover:text-foreground hover:bg-accent rounded-md transition-colors"
      >
        About
      </Link>
      <Link
        href={route("services")}
        className="px-3 py-2 text-sm font-medium text-muted-foreground hover:text-foreground hover:bg-accent rounded-md transition-colors"
      >
        Services
      </Link>
      <Link
        href={route("contact")}
        className="px-3 py-2 text-sm font-medium text-muted-foreground hover:text-foreground hover:bg-accent rounded-md transition-colors"
      >
        Contact
      </Link>
    </>
  );

  const cartSheet = (
    <Sheet open={cartOpen} onOpenChange={setCartOpen}>
      <SheetTrigger asChild>
        <Button
          variant="ghost"
          size="icon"
          className="relative text-muted-foreground hover:text-foreground"
        >
          <ShoppingCart className="h-5 w-5" />
          {itemCount > 0 && (
            <Badge className="absolute -top-1 -right-1 h-5 w-5 flex items-center justify-center p-0 text-[10px]">
              {itemCount}
            </Badge>
          )}
        </Button>
      </SheetTrigger>
      <SheetContent className="flex flex-col w-full sm:max-w-md p-0 gap-0">
        <SheetHeader className="px-6 pt-6 pb-4 border-b shrink-0">
          <SheetTitle>Shopping Cart</SheetTitle>
          <SheetDescription>
            {itemCount === 0
              ? "Your cart is empty"
              : `${itemCount} item${itemCount !== 1 ? "s" : ""} in your cart`}
          </SheetDescription>
        </SheetHeader>
        {itemCount === 0 ? (
          <div className="flex flex-col items-center justify-center py-16 text-center flex-1">
            <ShoppingCart className="h-12 w-12 text-muted-foreground mb-4" />
            <p className="text-muted-foreground">Your cart is empty</p>
            <SheetClose asChild>
              <Link href={route("store.index")}>
                <Button variant="outline" className="mt-4">
                  Browse Products
                </Button>
              </Link>
            </SheetClose>
          </div>
        ) : (
          <>
            <ScrollArea className="flex-1 px-6 py-4">
              <div className="space-y-4">
                {currentCart.items.map((item) => {
                  const imgUrl = item.variation?.image_url || item.product?.thumbnail_url || null;
                  const itemTotal = item.unit_price * item.quantity;
                  return (
                    <div key={item.id} className="flex items-start gap-3">
                      {imgUrl && (
                        <img
                          src={imgUrl}
                          alt={item.product?.name || ""}
                          className="h-16 w-16 rounded-md object-cover shrink-0"
                        />
                      )}
                      <div className="flex-1 min-w-0">
                        <Link
                          href={route("store.show", item.product?.slug || item.product_id)}
                          className="text-sm font-medium truncate block hover:underline"
                          onClick={() => setCartOpen(false)}
                        >
                          {item.product?.name || `Product #${item.product_id}`}
                        </Link>
                        {item.variant_label && (
                          <p className="text-xs text-muted-foreground mt-0.5">
                            {item.variant_label}
                          </p>
                        )}
                        <div className="flex items-center gap-1.5 mt-2">
                          <Button
                            variant="outline"
                            size="icon-xs"
                            className="h-6 w-6"
                            disabled={cartUpdating[item.id]}
                            onClick={() => updateQuantity(item.id, item.quantity - 1)}
                          >
                            <Minus className="h-3 w-3" />
                          </Button>
                          <span className="w-6 text-center text-xs tabular-nums font-medium">
                            {item.quantity}
                          </span>
                          <Button
                            variant="outline"
                            size="icon-xs"
                            className="h-6 w-6"
                            disabled={cartUpdating[item.id]}
                            onClick={() => updateQuantity(item.id, item.quantity + 1)}
                          >
                            <Plus className="h-3 w-3" />
                          </Button>
                          <span className="text-xs text-muted-foreground ml-1">
                            {formatPrice(item.unit_price)} each
                          </span>
                        </div>
                      </div>
                      <div className="flex flex-col items-end gap-1 shrink-0">
                        <p className="text-sm font-medium tabular-nums">{formatPrice(itemTotal)}</p>
                        <Button
                          variant="ghost"
                          size="icon-xs"
                          className="h-6 w-6 text-destructive"
                          onClick={() => removeItem(item.id)}
                        >
                          <X className="h-3 w-3" />
                        </Button>
                      </div>
                    </div>
                  );
                })}
              </div>
            </ScrollArea>
            <div className="border-t px-6 py-4 space-y-3 shrink-0 bg-background">
              <div className="flex justify-between text-sm">
                <span className="text-muted-foreground">Subtotal</span>
                <span className="font-medium tabular-nums">
                  {formatPrice(currentCart.subtotal)}
                </span>
              </div>
              {currentCart.discount > 0 && (
                <div className="flex justify-between text-sm">
                  <span className="text-muted-foreground">Discount</span>
                  <span className="tabular-nums text-destructive">
                    -{formatPrice(currentCart.discount)}
                  </span>
                </div>
              )}
              <Separator />
              <div className="flex justify-between font-medium">
                <span>Total</span>
                <span className="tabular-nums">{formatPrice(currentCart.total)}</span>
              </div>
              <SheetClose asChild>
                <Link href={route("store.cart.index")} className="block w-full">
                  <Button className="w-full">View Cart</Button>
                </Link>
              </SheetClose>
              <SheetClose asChild>
                <Link href={route("store.checkout.index")} className="block w-full">
                  <Button variant="outline" className="w-full">
                    Proceed to Checkout
                  </Button>
                </Link>
              </SheetClose>
            </div>
          </>
        )}
      </SheetContent>
    </Sheet>
  );

  const searchDialog = (
    <CommandDialog open={searchOpen} onOpenChange={setSearchOpen}>
      <form onSubmit={handleSearch}>
        <CommandInput
          placeholder="Search products..."
          value={searchQuery}
          onValueChange={setSearchQuery}
        />
      </form>
      <CommandList>
        <CommandEmpty>
          {searchQuery.trim() ? (
            <div className="py-6 text-center text-sm text-muted-foreground">
              <p>No products found for "{searchQuery}"</p>
              <Button
                variant="link"
                size="sm"
                className="mt-2"
                onClick={() => {
                  router.get(route("store.index"), { search: searchQuery.trim() });
                  setSearchOpen(false);
                  setSearchQuery("");
                }}
              >
                View all results
              </Button>
            </div>
          ) : (
            <div className="py-6 text-center text-sm text-muted-foreground">
              Type to search products...
            </div>
          )}
        </CommandEmpty>
        <CommandGroup heading="Quick Links">
          <CommandItem
            onSelect={() => {
              router.get(route("store.index"));
              setSearchOpen(false);
            }}
          >
            <Package className="mr-2 h-4 w-4" />
            <span>Browse all products</span>
          </CommandItem>
          {categories.slice(0, 5).map((cat) => (
            <CommandItem
              key={cat.id}
              onSelect={() => {
                router.get(route("store.category", cat.slug));
                setSearchOpen(false);
              }}
            >
              <ChevronDown className="mr-2 h-4 w-4" />
              <span>{cat.name}</span>
            </CommandItem>
          ))}
        </CommandGroup>
      </CommandList>
    </CommandDialog>
  );

  return (
    <>
      <nav
        className={`border-b border-border bg-background ${isStore ? "sticky top-0 z-50 bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/80" : ""}`}
      >
        <div className="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 sm:px-6 lg:px-8">
          <div className="flex items-center gap-6">
            <Link href="/" className="flex items-center gap-2 shrink-0">
              <BrandLogo logo={brand.logo || null} name={appName} size="sm" showName />
            </Link>
            {isStore ? (
              <div className="hidden md:flex items-center gap-1">
                <NavigationMenu>
                  <NavigationMenuList>
                    <NavigationMenuItem>
                      <NavigationMenuTrigger className="text-sm font-medium text-muted-foreground hover:text-foreground">
                        Shop
                      </NavigationMenuTrigger>
                      <NavigationMenuContent>
                        <ul className="grid w-[400px] gap-3 p-4 md:w-[500px] md:grid-cols-2 lg:w-[600px]">
                          <li className="row-span-3">
                            <NavigationMenuLink asChild>
                              <Link
                                href={route("store.index")}
                                className="flex h-full w-full select-none flex-col justify-end rounded-md bg-gradient-to-b from-muted/50 to-muted p-6 no-underline outline-none focus:shadow-md"
                              >
                                <Store className="h-6 w-6" />
                                <div className="mb-2 mt-4 text-lg font-medium">All Products</div>
                                <p className="text-sm leading-tight text-muted-foreground">
                                  Browse our full product catalog with search, filters, and
                                  categories
                                </p>
                              </Link>
                            </NavigationMenuLink>
                          </li>
                          <ListItem href={route("store.cart.index")} title="Cart">
                            View and manage items in your shopping cart
                          </ListItem>
                          <ListItem href={route("store.wishlist.index")} title="Wishlist">
                            Products you've saved for later
                          </ListItem>
                          <ListItem href={route("store.checkout.index")} title="Checkout">
                            Complete your purchase and place your order
                          </ListItem>
                        </ul>
                      </NavigationMenuContent>
                    </NavigationMenuItem>
                    {categories.length > 0 && (
                      <NavigationMenuItem>
                        <NavigationMenuTrigger className="text-sm font-medium text-muted-foreground hover:text-foreground">
                          Categories
                        </NavigationMenuTrigger>
                        <NavigationMenuContent>
                          <ul className="grid w-56 gap-1 p-3">
                            <li>
                              <NavigationMenuLink asChild>
                                <Link
                                  href={route("store.index")}
                                  className="block rounded-md px-3 py-2 text-sm text-muted-foreground hover:text-foreground hover:bg-accent"
                                >
                                  All Products
                                </Link>
                              </NavigationMenuLink>
                            </li>
                            {categories.map((cat) => (
                              <li key={cat.id}>
                                <NavigationMenuLink asChild>
                                  <Link
                                    href={route("store.category", cat.slug)}
                                    className="block rounded-md px-3 py-2 text-sm text-muted-foreground hover:text-foreground hover:bg-accent"
                                  >
                                    {cat.name}
                                  </Link>
                                </NavigationMenuLink>
                              </li>
                            ))}
                          </ul>
                        </NavigationMenuContent>
                      </NavigationMenuItem>
                    )}
                    <NavigationMenuItem>
                      <Link
                        href={route("about")}
                        className="px-3 py-2 text-sm font-medium text-muted-foreground hover:text-foreground hover:bg-accent rounded-md transition-colors"
                      >
                        About
                      </Link>
                    </NavigationMenuItem>
                    <NavigationMenuItem>
                      <Link
                        href={route("services")}
                        className="px-3 py-2 text-sm font-medium text-muted-foreground hover:text-foreground hover:bg-accent rounded-md transition-colors"
                      >
                        Services
                      </Link>
                    </NavigationMenuItem>
                    <NavigationMenuItem>
                      <Link
                        href={route("contact")}
                        className="px-3 py-2 text-sm font-medium text-muted-foreground hover:text-foreground hover:bg-accent rounded-md transition-colors"
                      >
                        Contact
                      </Link>
                    </NavigationMenuItem>
                  </NavigationMenuList>
                </NavigationMenu>
              </div>
            ) : (
              <div className="hidden sm:flex items-center gap-1">{siteLinks}</div>
            )}
          </div>
          <div className="flex items-center gap-2">
            {isStore && (
              <>
                <Button
                  variant="ghost"
                  size="icon"
                  className="hidden sm:inline-flex text-muted-foreground hover:text-foreground"
                  onClick={() => setSearchOpen(true)}
                  title="Search (Ctrl+K)"
                >
                  <Search className="h-5 w-5" />
                </Button>
                <CurrencySwitcher />
                <Link
                  href={route("store.wishlist.index")}
                  className="hidden sm:inline-flex relative text-muted-foreground hover:text-foreground"
                >
                  <Button
                    variant="ghost"
                    size="icon"
                    className="text-muted-foreground hover:text-foreground"
                  >
                    <Heart className="h-5 w-5" />
                    {wishlistIds.length > 0 && (
                      <Badge className="absolute -top-1 -right-1 h-5 w-5 flex items-center justify-center p-0 text-[10px]">
                        {wishlistIds.length}
                      </Badge>
                    )}
                  </Button>
                </Link>
                {cartSheet}
              </>
            )}
            <div className={isStore ? "hidden sm:flex" : ""}>
              <NavUser variant="basic" />
            </div>
            {isStore && (
              <Button
                variant="ghost"
                size="icon"
                className="md:hidden"
                onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
              >
                {mobileMenuOpen ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
              </Button>
            )}
          </div>
        </div>
        {isStore && mobileMenuOpen && (
          <div className="border-t border-border md:hidden">
            <div className="space-y-1 px-4 py-3">
              <Link
                href={route("store.index")}
                className="block rounded-md px-3 py-2 text-sm font-medium text-foreground hover:bg-accent"
                onClick={() => setMobileMenuOpen(false)}
              >
                Store
              </Link>
              {categories.length > 0 && (
                <>
                  <p className="px-3 py-1 text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                    Categories
                  </p>
                  {categories.map((cat) => (
                    <Link
                      key={cat.id}
                      href={route("store.category", cat.slug)}
                      className="block rounded-md px-3 py-2 text-sm text-muted-foreground hover:text-foreground hover:bg-accent pl-8"
                      onClick={() => setMobileMenuOpen(false)}
                    >
                      {cat.name}
                    </Link>
                  ))}
                </>
              )}
              <Separator className="my-2" />
              <Link
                href={route("about")}
                className="block rounded-md px-3 py-2 text-sm font-medium text-muted-foreground hover:text-foreground hover:bg-accent"
                onClick={() => setMobileMenuOpen(false)}
              >
                About
              </Link>
              <Link
                href={route("services")}
                className="block rounded-md px-3 py-2 text-sm font-medium text-muted-foreground hover:text-foreground hover:bg-accent"
                onClick={() => setMobileMenuOpen(false)}
              >
                Services
              </Link>
              <Link
                href={route("contact")}
                className="block rounded-md px-3 py-2 text-sm font-medium text-muted-foreground hover:text-foreground hover:bg-accent"
                onClick={() => setMobileMenuOpen(false)}
              >
                Contact
              </Link>
              <Separator className="my-2" />
              <NavUser variant="basic" />
            </div>
          </div>
        )}
      </nav>
      {isStore && searchDialog}
    </>
  );
}
