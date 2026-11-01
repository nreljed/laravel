import { Link, usePage } from "@inertiajs/react";
import { ArrowRight, ShieldCheck, Truck, Zap } from "lucide-react";
import PublicLayout from "@nreljed/platform/Layouts/PublicLayout";
import { Button } from "@nreljed/platform/Components/ui/button";

const highlights = [
  { icon: Zap, title: "Latest tech", body: "The newest phones and gadgets, in stock and ready to ship." },
  { icon: ShieldCheck, title: "Genuine & guaranteed", body: "100% authentic products with official warranties." },
  { icon: Truck, title: "Delivered to your door", body: "Fast delivery across Tunisia, with tracking." },
];

export default function Welcome() {
  const { props } = usePage();
  const brand = (props.brand as { logo?: string; name?: string }) || {};
  const appName = brand.name || "Our store";
  const registration = (props.registration as { enabled?: boolean }) || {};
  const registrationEnabled = registration.enabled !== false;

  return (
    <PublicLayout title="Welcome" description={`${appName} — genuine tech, fair prices, delivered across Tunisia.`}>
      {/* Hero */}
      <section className="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8 lg:py-24">
        <div className="grid items-center gap-12 lg:grid-cols-2 lg:gap-16">
          <div>
            <span className="inline-flex items-center gap-2 rounded-full border bg-muted/40 px-3 py-1 text-xs font-medium uppercase tracking-wide text-muted-foreground">
              Welcome to {appName}
            </span>
            <h1 className="mt-6 text-4xl font-bold tracking-tight sm:text-5xl lg:text-6xl">
              Genuine tech, fair prices, delivered to your door.
            </h1>
            <p className="mt-6 max-w-xl text-lg leading-relaxed text-muted-foreground">
              Shop the latest smartphones, accessories and gadgets with confidence — authentic
              products, honest pricing, and support that doesn't disappear after the sale.
            </p>
            <div className="mt-8 flex flex-wrap items-center gap-3">
              {props.auth?.user ? (
                <Button render={<Link href={route("dashboard.index")} />} size="lg">
                  Go to Dashboard <ArrowRight className="ml-2 h-4 w-4" />
                </Button>
              ) : (
                <>
                  {registrationEnabled && (
                    <Button render={<Link href={route("register")} />} size="lg">
                      Get Started Free <ArrowRight className="ml-2 h-4 w-4" />
                    </Button>
                  )}
                  <Button
                    render={<Link href={route("login")} />}
                    size="lg"
                    variant={registrationEnabled ? "outline" : "default"}
                  >
                    Sign In
                  </Button>
                  <Button render={<Link href={route("store.index")} />} size="lg" variant="ghost">
                    Browse the store
                  </Button>
                </>
              )}
            </div>
          </div>

          <div className="relative">
            <div className="aspect-[4/3] overflow-hidden rounded-3xl border bg-gradient-to-br from-primary/15 via-background to-muted/40 p-8 shadow-sm">
              <div className="flex h-full flex-col justify-between">
                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                  <span className="h-2 w-2 rounded-full bg-primary" />
                  New arrivals this week
                </div>
                <div className="grid grid-cols-3 gap-3">
                  {[0, 1, 2].map((i) => (
                    <div
                      key={i}
                      className="aspect-square rounded-2xl bg-background/60 backdrop-blur ring-1 ring-border"
                    />
                  ))}
                </div>
                <div className="flex items-center justify-between text-sm">
                  <span className="font-medium">Shop the collection</span>
                  <ArrowRight className="h-4 w-4" />
                </div>
              </div>
            </div>
            <div className="absolute -bottom-6 -left-6 hidden h-24 w-24 rounded-2xl border bg-background shadow-lg sm:block" />
          </div>
        </div>
      </section>

      {/* Highlights */}
      <section className="border-t bg-muted/30">
        <div className="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
          <div className="grid gap-8 md:grid-cols-3">
            {highlights.map((h) => (
              <div key={h.title} className="flex gap-4">
                <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                  <h.icon className="h-5 w-5" />
                </div>
                <div>
                  <h3 className="font-semibold">{h.title}</h3>
                  <p className="mt-1 text-sm text-muted-foreground">{h.body}</p>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>
    </PublicLayout>
  );
}
