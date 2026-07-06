import { Link, router, usePage, Head } from '@inertiajs/react'
import { Heart, ShoppingCart, ArrowLeft } from 'lucide-react'
import StoreLayout from '@/Layouts/StoreLayout'
import { Button } from '@/Components/ui/button'
import { Card, CardContent } from '@/Components/ui/card'
import { WishlistButton } from '@modules-shop/components/WishlistButton'

interface ProductItem {
  id: number; name: string; slug: string;
  price: number; thumbnail_url: string | null;
  categories: { id: number; name: string; slug: string }[];
}

interface PageProps {
  products: {
    data: ProductItem[];
    from: number; to: number; total: number;
    links: { url: string | null; label: string; active: boolean }[];
  };
}

export default function WishlistIndex() {
  const { props } = usePage<PageProps>()
  const products = props.products

  return (
    <StoreLayout>
      <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <Head title="My Wishlist">
          <meta name="description" content={products.total > 0 ? `${products.total} saved items in your wishlist` : 'Your wishlist is empty'} />
        </Head>
        <Link href={route('store.index')} className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground mb-6">
          <ArrowLeft className="h-4 w-4" /> Continue Shopping
        </Link>

        <div className="flex items-center gap-3 mb-8">
          <Heart className="h-6 w-6 text-red-500" />
          <h1 className="text-2xl font-bold">My Wishlist</h1>
          {products.total > 0 && (
            <span className="text-sm text-muted-foreground">({products.total} items)</span>
          )}
        </div>

        {products.data.length === 0 ? (
          <div className="text-center py-20">
            <Heart className="mx-auto h-12 w-12 text-muted-foreground/30 mb-4" />
            <h2 className="text-lg font-semibold mb-2">Your wishlist is empty</h2>
            <p className="text-muted-foreground mb-6">Save your favorite items to easily find them later.</p>
            <Link href={route('store.index')}>
              <Button>Browse Products</Button>
            </Link>
          </div>
        ) : (
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            {products.data.map((product) => (
              <Card key={product.id} className="group overflow-hidden transition-shadow hover:shadow-md">
                <Link href={route('store.show', product.id)}>
                  <div className="aspect-square bg-muted flex items-center justify-center relative">
                    {product.thumbnail_url ? (
                      <img src={product.thumbnail_url} alt={product.name} className="h-full w-full object-cover group-hover:scale-105 transition-transform" />
                    ) : (
                      <ShoppingCart className="h-8 w-8 text-muted-foreground/30" />
                    )}
                    <div className="absolute top-2 right-2" onClick={(e) => e.preventDefault()}>
                      <WishlistButton productId={product.id} initiallyActive={true} />
                    </div>
                  </div>
                </Link>
                <CardContent className="p-3">
                  <div className="flex flex-wrap gap-1 mb-1">
                    {product.categories.slice(0, 1).map((cat) => (
                      <Link key={cat.id} href={route('store.category', cat.slug)} className="text-[10px] text-muted-foreground hover:text-foreground">{cat.name}</Link>
                    ))}
                  </div>
                  <Link href={route('store.show', product.id)}>
                    <p className="text-sm font-medium line-clamp-2 hover:text-primary transition-colors">{product.name}</p>
                  </Link>
                  <p className="text-sm font-bold mt-1">{product.price.toFixed(2)} €</p>
                  <Button size="sm" className="w-full mt-2" onClick={() => router.visit(route('store.show', product.id))}>
                    <ShoppingCart className="h-3.5 w-3.5 mr-1" /> View Product
                  </Button>
                </CardContent>
              </Card>
            ))}
          </div>
        )}

        {products.links && products.links.length > 3 && (
          <div className="flex justify-center gap-1 mt-8">
            {products.links.map((link, i) => (
              <Link
                key={i}
                href={link.url || '#'}
                className={`px-3 py-1.5 text-sm rounded border ${link.active ? 'bg-primary text-primary-foreground border-primary' : 'border-border hover:bg-accent'} ${!link.url ? 'pointer-events-none opacity-50' : ''}`}
                dangerouslySetInnerHTML={{ __html: link.label }}
              />
            ))}
          </div>
        )}
      </div>
    </StoreLayout>
  )
}
