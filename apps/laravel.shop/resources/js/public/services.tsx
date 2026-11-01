import { Link } from "@inertiajs/react";
import {
  ArrowRight,
  Boxes,
  CheckCircle2,
  CreditCard,
  Headphones,
  Package,
  ShoppingCart,
  Truck,
} from "lucide-react";
import PublicLayout from "@nreljed/platform/Layouts/PublicLayout";
import { Button } from "@nreljed/platform/Components/ui/button";
import { Card, CardContent } from "@nreljed/platform/Components/ui/card";

const services = [
  {
    icon: ShoppingCart,
    title: "Latest smartphones",
    description:
      "The newest models straight from the brand — with official warranties and honest prices.",
    items: [
      "Xiaomi & popular brands",
      "Available in-store and online",
      "Clear, upfront price tags",
    ],
    featured: true,
  },
  {
    icon: Headphones,
    title: "Accessories & gadgets",
    description: "Chargers, power banks, audio, and smart devices to complete your setup.",
    items: [
      "Verified compatible accessories",
      "Fast chargers & cables",
      "Gadgets for home and commute",
    ],
  },
  {
    icon: CreditCard,
    title: "Easy checkout",
    description: "A smooth online checkout with secure payment and clear delivery costs.",
    items: [
      "Guest checkout available",
      "Discounts applied automatically",
      "Order confirmation by email",
    ],
  },
  {
    icon: Truck,
    title: "Delivery across Tunisia",
    description: "We deliver your order right to your door, with tracking from our store to you.",
    items: ["Fast doorstep delivery", "Track your parcel", "Secure packaging"],
  },
  {
    icon: Package,
    title: "Pre-sale advice",
    description:
      "Not sure which model suits you? We'll help you pick the right device for your budget.",
    items: ["Expert product guidance", "Compare options honestly", "Help in Arabic & French"],
  },
  {
    icon: Boxes,
    title: "Warranty & support",
    description:
      "Every device is authentic and supported after the sale — we stand behind what we sell.",
    items: ["Official product warranty", "Honest after-sales support", "Easy to reach us"],
  },
] as const;

export default function Services() {
  const featured = services.find((s) => "featured" in s && s.featured);
  const rest = services.filter((s) => !("featured" in s && s.featured));

  return (
    <PublicLayout
      title="Services"
      description="Smartphones, accessories, delivery, and support — everything you need to buy tech with confidence."
      ogType="website"
    >
      <section className="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8 lg:py-24">
        <div className="mx-auto max-w-3xl text-center">
          <h1 className="text-4xl font-bold tracking-tight sm:text-5xl">What we offer</h1>
          <p className="mt-6 text-lg leading-relaxed text-muted-foreground">
            Everything you need to shop for genuine tech with confidence — from picking the right
            device to getting it delivered to your door.
          </p>
        </div>

        {featured && (
          <Card className="mt-16 overflow-hidden border-primary/20 bg-gradient-to-br from-primary/5 via-background to-background">
            <CardContent className="grid gap-8 p-8 md:grid-cols-[auto_1fr] md:items-center">
              <div className="flex h-16 w-16 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                <featured.icon className="h-8 w-8" />
              </div>
              <div>
                <h2 className="text-2xl font-bold tracking-tight">{featured.title}</h2>
                <p className="mt-2 text-muted-foreground">{featured.description}</p>
                <ul className="mt-5 grid gap-2 sm:grid-cols-3">
                  {featured.items.map((item) => (
                    <li key={item} className="flex items-start gap-2 text-sm">
                      <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                      <span>{item}</span>
                    </li>
                  ))}
                </ul>
              </div>
            </CardContent>
          </Card>
        )}

        <div className="mt-10 grid gap-6 md:grid-cols-2">
          {rest.map((s) => (
            <Card key={s.title} className="flex flex-col transition-shadow hover:shadow-md">
              <CardContent className="flex flex-1 flex-col p-6">
                <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10 text-primary">
                  <s.icon className="h-6 w-6" />
                </div>
                <h2 className="mt-5 text-xl font-semibold">{s.title}</h2>
                <p className="mt-3 text-sm leading-relaxed text-muted-foreground">
                  {s.description}
                </p>
                <ul className="mt-5 space-y-2.5">
                  {s.items.map((item) => (
                    <li key={item} className="flex items-start gap-2 text-sm">
                      <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                      <span>{item}</span>
                    </li>
                  ))}
                </ul>
              </CardContent>
            </Card>
          ))}
        </div>

        <div className="mt-16 flex flex-col items-center gap-4 rounded-xl border bg-muted/30 p-10 text-center">
          <h2 className="text-2xl font-bold tracking-tight">Looking for a specific product?</h2>
          <p className="max-w-xl text-muted-foreground">
            Tell us what you're after and we'll confirm availability and get you a quote.
          </p>
          <Button render={<Link href={route("contact")} />} size="lg">
            Ask us <ArrowRight className="ml-2 h-4 w-4" />
          </Button>
        </div>
      </section>
    </PublicLayout>
  );
}
