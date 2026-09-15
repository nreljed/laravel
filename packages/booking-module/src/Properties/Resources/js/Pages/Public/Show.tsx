import { Head, Link, usePage } from "@inertiajs/react";
import { useState, useCallback } from "react";
import type { PageProps as AppPageProps } from "@nreljed/platform/types";
import {
  Bed,
  Bath,
  Move,
  MapPin,
  ChevronLeft,
  ChevronRight,
  Home,
  Sparkles,
  Phone,
  Mail,
  Ruler,
  Calendar,
  Armchair,
  Snowflake,
  Wifi,
  Tv,
  Droplets,
  UtensilsCrossed,
  Lock,
  Car,
  TreePine,
  Sun,
  Dumbbell,
  Warehouse,
  Layers,
  Image,
} from "lucide-react";
import { cn } from "@nreljed/platform/lib/utils";
import { Button } from "@nreljed/platform/Components/ui/button";
import { Badge } from "@nreljed/platform/Components/ui/badge";
import { Separator } from "@nreljed/platform/Components/ui/separator";
import { SiteHeader } from "@nreljed/platform/Components/frontend/SiteHeader";
import { SiteFooter } from "@nreljed/platform/Components/frontend/SiteFooter";

interface TermItem {
  id: number;
  name: string;
  slug: string;
  icon?: string;
}

interface PropertyImage {
  id: number;
  url: string;
  thumbnail_url: string | null;
  alt_text: string | null;
  is_featured: boolean;
}

interface PropertyFeature {
  id: number;
  name: string;
  icon?: string;
  pivot: { property_id: number; property_feature_id: number };
  group?: string;
}

interface PropertyItem {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  address: string | null;
  price_formatted: string;
  surface_formatted: string;
  featured: boolean;
  video_url: string | null;
  metadata: Record<string, unknown> | null;
  types: TermItem[];
  statuses: TermItem[];
  city: string | null;
  city_obj?: { id: number; name: string; slug: string } | null;
  state: string | null;
  zip: string | null;
  featuredImage: PropertyImage | null;
  images: PropertyImage[];
  features: PropertyFeature[];
  featureGroups: Record<string, PropertyFeature[]>;
  user: { id: number; name: string; email: string; phone?: string } | null;
  similarProperties: PropertyItem[];
  bookingData: Record<string, unknown> | null;
}

function getMetaStr(meta: Record<string, unknown> | null, key: string): string | null {
  if (!meta) return null;
  const val = meta[key];
  if (typeof val === "string") return val;
  if (typeof val === "number") return String(val);
  return null;
}

function getMetaNum(meta: Record<string, unknown> | null, key: string): number | null {
  if (!meta) return null;
  const val = meta[key];
  if (typeof val === "number") return val;
  if (typeof val === "string") {
    const n = Number(val);
    return Number.isNaN(n) ? null : n;
  }
  return null;
}

function getMetaArr(meta: Record<string, unknown> | null, key: string): Record<string, unknown>[] {
  if (!meta) return [];
  const val = meta[key];
  if (Array.isArray(val)) return val as Record<string, unknown>[];
  return [];
}

function ImageGallery({ images }: { images: PropertyImage[] }) {
  const [currentIdx, setCurrentIdx] = useState(0);

  if (images.length === 0) {
    return (
      <div className="flex aspect-[16/10] items-center justify-center rounded-xl bg-muted">
        <Home className="h-16 w-16 text-muted-foreground/40" />
      </div>
    );
  }

  const current = images[currentIdx];
  if (!current) return null;

  return (
    <div className="space-y-3">
      <div className="relative aspect-[16/10] overflow-hidden rounded-xl bg-muted">
        <img
          src={current.url}
          alt={current.alt_text || (currentIdx === 0 ? "Featured" : `Photo ${currentIdx}`)}
          className="h-full w-full object-cover"
        />
        {images.length > 1 && (
          <>
            <div className="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/40 to-transparent p-4">
              <div className="flex items-center justify-between">
                <Badge variant="secondary" className="gap-1 bg-background/90 backdrop-blur-sm">
                  <Image className="h-3 w-3" />
                  {currentIdx + 1} / {images.length}
                </Badge>
                <div className="flex gap-1">
                  <Button
                    size="icon"
                    variant="secondary"
                    className="h-8 w-8 bg-background/90 backdrop-blur-sm"
                    onClick={() => setCurrentIdx((i) => (i + images.length - 1) % images.length)}
                  >
                    <ChevronLeft className="h-4 w-4" />
                  </Button>
                  <Button
                    size="icon"
                    variant="secondary"
                    className="h-8 w-8 bg-background/90 backdrop-blur-sm"
                    onClick={() => setCurrentIdx((i) => (i + 1) % images.length)}
                  >
                    <ChevronRight className="h-4 w-4" />
                  </Button>
                </div>
              </div>
            </div>
          </>
        )}
      </div>
      {images.length > 1 && (
        <div className="flex gap-2 overflow-x-auto pb-1">
          {images.map((img, i) => (
            <button
              key={img.id}
              onClick={() => setCurrentIdx(i)}
              className={cn(
                "relative h-16 w-24 shrink-0 overflow-hidden rounded-lg border-2 transition-all",
                i === currentIdx
                  ? "border-primary ring-1 ring-primary"
                  : "border-transparent opacity-70 hover:opacity-100",
              )}
            >
              <img
                src={img.thumbnail_url || img.url}
                alt={img.alt_text || `Thumbnail ${i}`}
                className="h-full w-full object-cover"
              />
            </button>
          ))}
        </div>
      )}
    </div>
  );
}

const FEATURE_ICONS: Record<string, React.ReactNode> = {
  air_conditioning: <Snowflake className="h-4 w-4" />,
  wifi: <Wifi className="h-4 w-4" />,
  television: <Tv className="h-4 w-4" />,
  pool: <Droplets className="h-4 w-4" />,
  kitchen: <UtensilsCrossed className="h-4 w-4" />,
  security: <Lock className="h-4 w-4" />,
  parking: <Car className="h-4 w-4" />,
  garden: <TreePine className="h-4 w-4" />,
  terrace: <Sun className="h-4 w-4" />,
  gym: <Dumbbell className="h-4 w-4" />,
  storage: <Warehouse className="h-4 w-4" />,
};

const LABEL_MAP: Record<string, string> = {
  bedrooms: "Bedrooms",
  bathrooms: "Bathrooms",
  surface: "Surface",
  rooms: "Rooms",
  year_built: "Year Built",
  parking: "Parking",
  floor: "Floor",
  furnished: "Furnished",
  floor_level: "Floor",
  garage: "Parking",
  rental_period: "Rental Period",
};

const STAT_KEYS = ["bedrooms", "bathrooms", "rooms", "year_built", "floor_level", "garage"];

function PropertyDetailStat({
  icon,
  label,
  value,
}: {
  icon?: React.ReactNode;
  label: string;
  value: string;
}) {
  return (
    <div className="flex items-center gap-3 rounded-lg border border-border bg-card p-4">
      {icon && <div className="text-primary">{icon}</div>}
      <div>
        <p className="text-xs text-muted-foreground">{label}</p>
        <p className="font-semibold">{value}</p>
      </div>
    </div>
  );
}

function PropertyCard({ property }: { property: PropertyItem }) {
  const imgUrl = property.featuredImage?.url || property.images?.[0]?.url || null;
  const locationName = property.city_obj?.name || property.city || null;
  const firstStatus = property.statuses?.[0] || null;
  const bedrooms = getMetaNum(property.metadata, "bedrooms");
  const bathrooms = getMetaNum(property.metadata, "bathrooms");

  return (
    <Link
      href={route("properties.show", property.slug)}
      className="group block overflow-hidden rounded-xl border border-border bg-card transition-all hover:shadow-lg hover:-translate-y-0.5"
    >
      <div className="relative aspect-[4/3] overflow-hidden bg-muted">
        {imgUrl ? (
          <img
            src={imgUrl}
            alt={property.name}
            className="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
          />
        ) : (
          <div className="flex h-full items-center justify-center">
            <Home className="h-12 w-12 text-muted-foreground/40" />
          </div>
        )}
        {property.featured && (
          <Badge className="absolute left-2 top-2 gap-1 bg-amber-500 text-white hover:bg-amber-500">
            <Sparkles className="h-3 w-3" />
            Featured
          </Badge>
        )}
        {firstStatus && (
          <Badge
            variant="secondary"
            className="absolute bottom-2 left-2 bg-background/90 backdrop-blur-sm"
          >
            {firstStatus.name}
          </Badge>
        )}
      </div>
      <div className="space-y-2 p-4">
        <h3 className="truncate text-base font-semibold text-foreground">{property.name}</h3>
        {locationName && (
          <p className="flex items-center gap-1 text-sm text-muted-foreground">
            <MapPin className="h-3.5 w-3.5 shrink-0" />
            <span className="truncate">{locationName}</span>
          </p>
        )}
        <div className="flex flex-wrap items-center gap-3 text-sm text-muted-foreground">
          {bedrooms !== null && (
            <span className="flex items-center gap-1">
              <Bed className="h-3.5 w-3.5" />
              {bedrooms}
            </span>
          )}
          {bathrooms !== null && (
            <span className="flex items-center gap-1">
              <Bath className="h-3.5 w-3.5" />
              {bathrooms}
            </span>
          )}
          {property.surface_formatted && (
            <span className="flex items-center gap-1">
              <Move className="h-3.5 w-3.5" />
              {property.surface_formatted}
            </span>
          )}
        </div>
        {property.price_formatted && (
          <p className="text-lg font-bold text-primary">{property.price_formatted}</p>
        )}
      </div>
    </Link>
  );
}

export default function PropertyShow() {
  const { props } = usePage<AppPageProps<{ property: PropertyItem }>>();
  const { property } = props;
  const [showPhone, setShowPhone] = useState(false);

  const meta = property.metadata;
  const locationParts = [
    property.city_obj?.name || property.city,
    property.state,
    property.zip,
  ].filter(Boolean);

  const rentalPeriod = getMetaStr(meta, "rental_period");
  const furnished = getMetaStr(meta, "furnished");
  const additionalDetails = getMetaArr(meta, "additional_details");
  const floorPlans = getMetaArr(meta, "floor_plans");

  const handleBookClick = useCallback(() => {
    if (property.bookingData) {
      alert("Booking functionality coming soon.");
    }
  }, [property]);

  return (
    <>
      <Head title={`${property.name} - Properties`} />
      <div className="flex min-h-screen flex-col bg-background">
        <SiteHeader />
        <main className="flex-1">
          <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <nav className="mb-6 flex items-center gap-2 text-sm text-muted-foreground">
              <Link href="/" className="hover:text-foreground">
                Home
              </Link>
              <span>/</span>
              <Link href={route("properties.index")} className="hover:text-foreground">
                Properties
              </Link>
              <span>/</span>
              <span className="font-medium text-foreground">{property.name}</span>
            </nav>
            <div className="lg:grid lg:grid-cols-3 lg:gap-10">
              <div className="lg:col-span-2">
                <div className="space-y-1">
                  <div className="flex flex-wrap items-center gap-2">
                    {property.statuses?.map((s) => (
                      <Badge key={s.id} variant="secondary">
                        {s.name}
                      </Badge>
                    ))}
                    {property.types?.map((t) => (
                      <Badge key={t.id} variant="outline">
                        {t.name}
                      </Badge>
                    ))}
                    {property.featured && (
                      <Badge className="gap-1 bg-amber-500 text-white hover:bg-amber-500">
                        <Sparkles className="h-3 w-3" />
                        Featured
                      </Badge>
                    )}
                  </div>
                  <h1 className="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                    {property.name}
                  </h1>
                  <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                    <MapPin className="h-4 w-4" />
                    <span>{[property.address, ...locationParts].filter(Boolean).join(", ")}</span>
                    <span className="text-xs">ID: {property.id}</span>
                  </div>
                </div>
                <div className="mt-6">
                  <ImageGallery images={property.images} />
                </div>
                <div className="mt-6 flex flex-wrap items-center gap-2">
                  <Link
                    href={route("properties.index")}
                    className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                  >
                    <ChevronLeft className="h-4 w-4" />
                    Back to properties
                  </Link>
                </div>
                <div className="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                  {STAT_KEYS.map((key) => {
                    const valNum = getMetaNum(meta, key);
                    if (valNum === null) return null;
                    const label = LABEL_MAP[key] || key.replaceAll("_", " ");
                    const icons: Record<string, React.ReactNode> = {
                      bedrooms: <Bed className="h-5 w-5" />,
                      bathrooms: <Bath className="h-5 w-5" />,
                      rooms: <Layers className="h-5 w-5" />,
                      year_built: <Calendar className="h-5 w-5" />,
                      floor_level: <Move className="h-5 w-5" />,
                      garage: <Car className="h-5 w-5" />,
                    };
                    return (
                      <PropertyDetailStat
                        key={key}
                        icon={icons[key]}
                        label={label}
                        value={String(valNum)}
                      />
                    );
                  })}
                  {furnished && (
                    <PropertyDetailStat
                      icon={<Armchair className="h-5 w-5" />}
                      label="Furnished"
                      value={furnished}
                    />
                  )}
                  {property.surface_formatted && (
                    <PropertyDetailStat
                      icon={<Ruler className="h-5 w-5" />}
                      label="Surface"
                      value={property.surface_formatted}
                    />
                  )}
                </div>
                {property.description && (
                  <section className="mt-10">
                    <h2 className="text-xl font-semibold">About this property</h2>
                    <div className="mt-3 whitespace-pre-line text-muted-foreground leading-relaxed">
                      {property.description}
                    </div>
                  </section>
                )}
                {property.featureGroups && Object.keys(property.featureGroups).length > 0 && (
                  <section className="mt-10">
                    <h2 className="text-xl font-semibold">Amenities & Features</h2>
                    <div className="mt-4 grid gap-4 sm:grid-cols-2">
                      {Object.entries(property.featureGroups).map(([group, feats]) => (
                        <div key={group} className="rounded-xl border border-border bg-card p-5">
                          <h3 className="mb-3 text-sm font-semibold uppercase tracking-wider text-muted-foreground">
                            {group}
                          </h3>
                          <ul className="space-y-2">
                            {feats.map((f) => (
                              <li key={f.id} className="flex items-center gap-2 text-sm">
                                <div className="text-primary">
                                  {FEATURE_ICONS[f.icon || ""] || <Home className="h-4 w-4" />}
                                </div>
                                {f.name}
                              </li>
                            ))}
                          </ul>
                        </div>
                      ))}
                    </div>
                  </section>
                )}
                {additionalDetails.length > 0 && (
                  <section className="mt-10">
                    <h2 className="text-xl font-semibold">Additional Details</h2>
                    <div className="mt-3 divide-y divide-border rounded-xl border border-border">
                      {additionalDetails.map((detail, i) => (
                        <div key={i} className="flex justify-between px-4 py-3 text-sm">
                          <span className="text-muted-foreground">
                            {String(detail.label || detail.key || "")}
                          </span>
                          <span className="font-medium">{String(detail.value || "")}</span>
                        </div>
                      ))}
                    </div>
                  </section>
                )}
                {floorPlans.length > 0 && (
                  <section className="mt-10">
                    <h2 className="text-xl font-semibold">Floor Plans</h2>
                    <div className="mt-4 grid gap-4 sm:grid-cols-2">
                      {floorPlans.map((plan, i) => {
                        const planUrl = String(plan.image || plan.url || "");
                        return (
                          <div
                            key={i}
                            className="overflow-hidden rounded-xl border border-border bg-card"
                          >
                            {planUrl ? (
                              <img
                                src={planUrl}
                                alt={String(plan.name || `Floor Plan ${i + 1}`)}
                                className="h-48 w-full object-contain bg-muted p-4"
                              />
                            ) : (
                              <div className="flex h-48 items-center justify-center bg-muted">
                                <Layers className="h-12 w-12 text-muted-foreground/40" />
                              </div>
                            )}
                            <div className="border-t border-border px-4 py-2 text-sm font-medium">
                              {String(plan.name || `Floor Plan ${i + 1}`)}
                            </div>
                          </div>
                        );
                      })}
                    </div>
                  </section>
                )}
                {property.video_url && (
                  <section className="mt-10">
                    <h2 className="text-xl font-semibold">Video Tour</h2>
                    <div className="mt-3 aspect-video overflow-hidden rounded-xl">
                      <iframe
                        src={property.video_url.replace("watch?v=", "embed/")}
                        title="Video tour"
                        className="h-full w-full"
                        allowFullScreen
                      />
                    </div>
                  </section>
                )}
                {property.similarProperties?.length > 0 && (
                  <section className="mt-12">
                    <h2 className="text-xl font-semibold">Similar Properties</h2>
                    <div className="mt-4 grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
                      {property.similarProperties.slice(0, 3).map((sp) => (
                        <PropertyCard key={sp.id} property={sp} />
                      ))}
                    </div>
                  </section>
                )}
              </div>
              <aside className="mt-8 lg:mt-0">
                <div className="sticky top-24 space-y-4">
                  <div className="rounded-xl border border-border bg-card p-6">
                    <div className="flex items-baseline gap-2">
                      <span className="text-2xl font-bold text-primary">
                        {property.price_formatted}
                      </span>
                      {rentalPeriod && (
                        <span className="text-sm text-muted-foreground">/ {rentalPeriod}</span>
                      )}
                    </div>
                    <Separator className="my-4" />
                    <div className="space-y-3">
                      <Button className="w-full" size="lg" onClick={handleBookClick}>
                        Book Now
                      </Button>
                      <Button
                        render={<a href={`mailto:${property.user?.email || ""}?subject=Inquiry about ${property.name}`} />}
                        variant="outline" className="w-full" size="lg"
                      >
                        Send Inquiry
                      </Button>
                    </div>
                  </div>
                  {property.user && (
                    <div className="rounded-xl border border-border bg-card p-6">
                      <h3 className="text-sm font-semibold uppercase tracking-wider text-muted-foreground">
                        Agent
                      </h3>
                      <div className="mt-3 flex items-center gap-3">
                        <div className="flex h-12 w-12 items-center justify-center rounded-full bg-primary/10 text-primary font-semibold">
                          {property.user.name.charAt(0).toUpperCase()}
                        </div>
                        <div>
                          <p className="font-medium">{property.user.name}</p>
                          {property.user.email && (
                            <p className="flex items-center gap-1 text-sm text-muted-foreground">
                              <Mail className="h-3.5 w-3.5" />
                              {property.user.email}
                            </p>
                          )}
                        </div>
                      </div>
                      <Separator className="my-3" />
                      <div className="space-y-2">
                        {property.user.email && (
                          <Button render={<a href={`mailto:${property.user.email}`} />} variant="outline" size="sm" className="w-full gap-2">
                            <Mail className="h-4 w-4" />
                            Email Agent
                          </Button>
                        )}
                        <Button
                          variant="outline"
                          size="sm"
                          className="w-full gap-2"
                          onClick={() => setShowPhone(true)}
                        >
                          <Phone className="h-4 w-4" />
                          {showPhone ? property.user.phone || "No phone available" : "Show Phone"}
                        </Button>
                      </div>
                    </div>
                  )}
                  <div className="rounded-xl border border-border bg-card p-6 text-center">
                    <p className="text-sm text-muted-foreground">Property ID</p>
                    <p className="text-lg font-bold">{property.id}</p>
                  </div>
                </div>
              </aside>
            </div>
          </div>
        </main>
        <SiteFooter />
      </div>
    </>
  );
}
