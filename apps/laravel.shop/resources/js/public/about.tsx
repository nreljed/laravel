import { Link } from "@inertiajs/react";
import {
  ArrowRight,
  Award,
  HeartHandshake,
  Lightbulb,
  Rocket,
  ShieldCheck,
  Target,
} from "lucide-react";
import PublicLayout from "@nreljed/platform/Layouts/PublicLayout";
import { Button } from "@nreljed/platform/Components/ui/button";
import { Card, CardContent } from "@nreljed/platform/Components/ui/card";

const values = [
  { icon: Rocket, title: "Fast & modern", description: "We stock the latest phones and gadgets and ship every order the business day after you place it." },
  { icon: ShieldCheck, title: "Genuine, guaranteed", description: "100% authentic products with official warranties — every device is verified before it leaves our store." },
  { icon: HeartHandshake, title: "Support that helps", description: "Real people answer your questions before and after the sale — in Arabic and French." },
  { icon: Target, title: "Fair pricing", description: "Competitive prices, honest discounts, and transparent delivery costs. No surprises at checkout." },
] as const;

const milestones = [
  { year: "Today", title: "Your local tech store", description: "Phones, accessories, and smart gadgets delivered across Tunisia, from our shop in Tunis." },
  { year: "Soon", title: "A full online shop", description: "A modern storefront where browsing, ordering, and tracking your delivery is effortless." },
] as const;

export default function About() {
  return (
    <PublicLayout title="About Us" description="Meet the store behind your favourite tech — genuine devices, fair prices, and support you can rely on across Tunisia." ogType="website">
      {/* Hero */}
      <section className="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8 lg:py-24">
        <div className="grid items-center gap-12 lg:grid-cols-[1.1fr_1fr] lg:gap-16">
          <div>
            <div className="inline-flex items-center gap-2 rounded-full border bg-muted/40 px-3 py-1 text-sm text-muted-foreground">
              <Award className="h-4 w-4" />
              <span>Our story</span>
            </div>
            <h1 className="mt-6 text-4xl font-bold tracking-tight sm:text-5xl">About our store</h1>
            <p className="mt-6 text-lg leading-relaxed text-muted-foreground">
              We're a Tunisian electronics retailer built around one promise: give people genuine gear
              at a fair price, with service that doesn't disappear after the sale. From smartphones to
              power banks, we stock what we'd buy ourselves.
            </p>
            <div className="mt-10 flex flex-wrap items-center gap-4">
              <Button render={<Link href={route("services")} />} size="lg">
                See what we offer <ArrowRight className="ml-2 h-4 w-4" />
              </Button>
              <Button render={<Link href={route("contact")} />} size="lg" variant="outline">
                Get in touch
              </Button>
            </div>
          </div>

          <div className="relative">
            <div className="aspect-[4/3] overflow-hidden rounded-3xl border bg-gradient-to-br from-primary/10 via-background to-muted/40" />
            <div className="absolute -bottom-4 -right-4 hidden rounded-2xl border bg-background px-4 py-3 shadow-lg sm:block">
              <p className="text-xs text-muted-foreground">Trusted by</p>
              <p className="text-lg font-bold">Customers across Tunisia</p>
            </div>
          </div>
        </div>
      </section>

      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="h-px bg-border" />
      </div>

      {/* Why + values */}
      <section className="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8 lg:py-24">
        <div className="grid gap-10 lg:grid-cols-2 lg:gap-16">
          <div>
            <h2 className="text-3xl font-bold tracking-tight">Why shop with us</h2>
            <p className="mt-4 text-lg leading-relaxed text-muted-foreground">
              Browsing tech shouldn't mean guessing whether you'll get what you paid for. We make it
              easy to buy with confidence.
            </p>
            <div className="mt-8 space-y-4">
              {milestones.map((m) => (
                <div key={m.title} className="flex gap-4 rounded-lg border bg-card p-4">
                  <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary">
                    <Lightbulb className="h-5 w-5" />
                  </div>
                  <div>
                    <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{m.year}</p>
                    <h3 className="font-semibold">{m.title}</h3>
                    <p className="mt-1 text-sm text-muted-foreground">{m.description}</p>
                  </div>
                </div>
              ))}
            </div>
          </div>

          <div>
            <h2 className="text-3xl font-bold tracking-tight">What we stand for</h2>
            <p className="mt-4 text-lg text-muted-foreground">The principles behind every order.</p>
            <div className="mt-8 grid gap-4 sm:grid-cols-2">
              {values.map((v) => (
                <Card key={v.title} className="transition-shadow hover:shadow-md">
                  <CardContent className="p-5">
                    <div className="flex h-10 w-10 items-center justify-center rounded-md bg-primary/10 text-primary">
                      <v.icon className="h-5 w-5" />
                    </div>
                    <h3 className="mt-4 font-semibold">{v.title}</h3>
                    <p className="mt-2 text-sm text-muted-foreground">{v.description}</p>
                  </CardContent>
                </Card>
              ))}
            </div>
          </div>
        </div>
      </section>
    </PublicLayout>
  );
}
