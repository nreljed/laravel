import { Head, Link } from "@inertiajs/react";
import PublicLayout from "@/Layouts/PublicLayout";
import { MapPin, Calendar, ArrowLeft } from "lucide-react";
import { Map, MapTileLayer, MapMarker, MapControls } from "@/Components/shared/map";

interface Author {
  id: number;
  name: string;
}

interface GalleryImage {
  url: string;
  alt?: string;
  caption?: string;
  order?: number;
}

interface AddressData {
  street?: string;
  housenumber?: string;
  city?: string;
  state?: string;
  postcode?: string;
  country?: string;
  fullAddress?: string;
  displayName?: string;
}

interface TestPost {
  id: number;
  title: string;
  slug: string;
  content: string | null;
  excerpt: string | null;
  featured_image: string | null;
  gallery_images: GalleryImage[] | null;
  published_at: string | null;
  latitude: number | null;
  longitude: number | null;
  address: AddressData | null;
  map_zoom: number | null;
  user: Author;
}

interface TestPostsShowProps {
  testPost: TestPost;
}

function formatAddress(address: AddressData | null): string | null {
  if (!address) return null;
  if (address.fullAddress) return address.fullAddress;
  if (address.displayName) return address.displayName;

  const parts = [
    [address.housenumber, address.street].filter(Boolean).join(" "),
    address.city,
    address.state,
    address.postcode,
    address.country,
  ].filter(Boolean);

  return parts.length > 0 ? parts.join(", ") : null;
}

export default function TestPostsPublicShow({ testPost }: TestPostsShowProps) {
  const hasLocation = testPost.latitude != null && testPost.longitude != null;
  const center: [number, number] = hasLocation
    ? [testPost.latitude!, testPost.longitude!]
    : [36.8065, 10.1815];
  const displayAddress = formatAddress(testPost.address);

  return (
    <PublicLayout
      title={testPost.title}
      description={testPost.excerpt || undefined}
      ogImage={testPost.featured_image || undefined}
      ogType="article"
      articlePublishedTime={testPost.published_at || undefined}
      articleAuthor={testPost.user?.name}
      twitterCard="summary_large_image"
    >
      <Head>
        {hasLocation && (
          <script
            type="application/ld+json"
            dangerouslySetInnerHTML={{
              __html: JSON.stringify({
                "@context": "https://schema.org",
                "@type": "Article",
                headline: testPost.title,
                description: testPost.excerpt || undefined,
                image: testPost.featured_image || undefined,
                datePublished: testPost.published_at || undefined,
                author: testPost.user ? { "@type": "Person", name: testPost.user.name } : undefined,
                ...(hasLocation && {
                  "@type": "Article",
                  spatialCoverage: {
                    "@type": "Place",
                    geo: {
                      "@type": "GeoCoordinates",
                      latitude: testPost.latitude,
                      longitude: testPost.longitude,
                    },
                    ...(displayAddress && { address: displayAddress }),
                  },
                }),
              }),
            }}
          />
        )}
      </Head>

      <article className="mx-auto max-w-4xl px-4 py-16 sm:px-6 lg:px-8">
        <header className="mb-8">
          <h1 className="text-4xl font-bold tracking-tight text-foreground sm:text-5xl">
            {testPost.title}
          </h1>
          <div className="mt-4 flex flex-wrap items-center gap-3 text-sm text-muted-foreground">
            <span>{testPost.user.name}</span>
            {testPost.published_at && (
              <>
                <span>&middot;</span>
                <time className="flex items-center gap-1">
                  <Calendar className="h-3.5 w-3.5" />
                  {new Date(testPost.published_at).toLocaleDateString("en-US", {
                    year: "numeric",
                    month: "long",
                    day: "numeric",
                  })}
                </time>
              </>
            )}
            {hasLocation && (
              <>
                <span>&middot;</span>
                <span className="flex items-center gap-1">
                  <MapPin className="h-3.5 w-3.5" />
                  {displayAddress ||
                    `${testPost.latitude?.toFixed(4)}, ${testPost.longitude?.toFixed(4)}`}
                </span>
              </>
            )}
          </div>
        </header>

        {testPost.featured_image && (
          <div className="mb-8 overflow-hidden rounded-lg">
            <img
              src={testPost.featured_image}
              alt={testPost.title}
              className="h-auto w-full object-cover"
            />
          </div>
        )}

        {testPost.excerpt && (
          <p className="mb-8 text-lg leading-relaxed text-muted-foreground">{testPost.excerpt}</p>
        )}

        {hasLocation && (
          <div className="mb-8 overflow-hidden rounded-lg border border-border">
            <Map
              center={center}
              zoom={testPost.map_zoom || 15}
              className="h-80 w-full"
              scrollWheelZoom={false}
            >
              <MapTileLayer />
              <MapMarker position={center} />
              <MapControls watch={false} />
            </Map>
          </div>
        )}

        {testPost.gallery_images && testPost.gallery_images.length > 0 && (
          <div className="mb-8">
            <h2 className="mb-4 text-xl font-semibold text-foreground">Gallery</h2>
            <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4">
              {testPost.gallery_images
                .toSorted((a, b) => (a.order ?? 0) - (b.order ?? 0))
                .map((image, index) => (
                  <div
                    key={index}
                    className="group relative overflow-hidden rounded-lg border border-border"
                  >
                    <img
                      src={image.url}
                      alt={image.alt || testPost.title}
                      className="aspect-square w-full object-cover transition-transform group-hover:scale-105"
                    />
                    {image.caption && (
                      <div className="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/60 to-transparent p-2">
                        <p className="text-xs text-white line-clamp-2">{image.caption}</p>
                      </div>
                    )}
                  </div>
                ))}
            </div>
          </div>
        )}

        {testPost.content && (
          <div
            className="prose prose-neutral dark:prose-invert max-w-none mb-8"
            dangerouslySetInnerHTML={{ __html: testPost.content }}
          />
        )}

        <div className="mt-12 border-t border-border pt-8">
          <Link
            href={route("test-posts.index")}
            className="inline-flex items-center gap-2 text-sm font-medium text-primary hover:text-primary/80"
          >
            <ArrowLeft className="h-4 w-4" />
            Back to all test posts
          </Link>
        </div>
      </article>
    </PublicLayout>
  );
}
