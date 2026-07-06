import { PropsWithChildren } from "react";
import { usePage } from "@inertiajs/react";
import { SiteHeader } from "@/Components/frontend/SiteHeader";
import { SiteFooter } from "@/Components/frontend/SiteFooter";

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

export default function StoreLayout({ children }: PropsWithChildren) {
  const { props } = usePage();
  const cart = (props.cart as CartData) || { items: [], subtotal: 0, total: 0 };
  const categories = (props.categories as Category[]) || [];

  return (
    <div className="flex min-h-screen flex-col">
      <SiteHeader variant="store" categories={categories} cart={cart} />
      <main className="flex-1">{children}</main>
      <SiteFooter />
    </div>
  );
}
