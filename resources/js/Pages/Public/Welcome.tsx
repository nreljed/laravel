import { Link, usePage } from "@inertiajs/react";
import {
  ArrowRight,
  BarChart3,
  CircleDollarSign,
  Database,
  ShieldCheck,
  Wallet,
} from "lucide-react";
import PublicLayout from "@nreljed/platform/Layouts/PublicLayout";
import { Button } from "@nreljed/platform/Components/ui/button";
import { Card, CardDescription, CardHeader, CardTitle } from "@nreljed/platform/Components/ui/card";

const features = [
  {
    icon: CircleDollarSign,
    title: "Track every cost",
    description: "Consolidate expenses from every team, project, and vendor into one clean view.",
  },
  {
    icon: BarChart3,
    title: "Insightful analytics",
    description: "Spot trends and anomalies with dashboards and reports that update in real time.",
  },
  {
    icon: Wallet,
    title: "Smart budgets",
    description: "Set budgets per department or project and get alerted before you overshoot.",
  },
  {
    icon: Database,
    title: "One source of truth",
    description: "Your data stays yours — owned, encrypted, and always under your control.",
  },
];

const stats = [
  { value: "360°", label: "Visibility into your spend" },
  { value: "0€", label: "Platform cost — BYO AI key" },
  { value: "24/7", label: "Monitoring & alerts" },
];

export default function Welcome() {
  const { props } = usePage();
  const brand = (props.brand as { logo?: string; name?: string }) || {};
  const appName = brand.name || "Cost Breaker 360";
  const registration = (props.registration as { enabled?: boolean }) || {};
  const registrationEnabled = registration.enabled !== false;

  return (
    <PublicLayout
      title="Welcome"
      description="Cost Breaker 360 — the cost management platform that gives you 360° visibility into every euro you spend."
    >
      <section className="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
        <div className="mx-auto max-w-3xl text-center">
          <h1 className="text-4xl font-bold tracking-tight text-foreground sm:text-6xl">
            Break every cost with {appName}
          </h1>
          <p className="mt-6 text-lg leading-8 text-muted-foreground">
            Track, analyse, and optimise your spending across teams and projects — all in one
            dashboard. Know exactly where your money goes, before it goes.
          </p>
          <div className="mt-10 flex flex-wrap items-center justify-center gap-4">
            {props.auth?.user ? (
              <Link href={route("dashboard.index")}>
                <Button size="lg">
                  Go to Dashboard
                  <ArrowRight className="ml-2 h-4 w-4" />
                </Button>
              </Link>
            ) : (
              <>
                {registrationEnabled ? (
                  <Link href={route("register")}>
                    <Button size="lg">
                      Get Started Free
                      <ArrowRight className="ml-2 h-4 w-4" />
                    </Button>
                  </Link>
                ) : null}
                <Link href={route("login")}>
                  <Button size="lg" variant={registrationEnabled ? "outline" : "default"}>
                    Sign In
                  </Button>
                </Link>
              </>
            )}
          </div>
        </div>
      </section>

      <section className="border-y border-border bg-muted/40">
        <div className="mx-auto grid max-w-7xl gap-4 px-4 py-12 sm:grid-cols-2 sm:px-6 lg:grid-cols-4 lg:px-8">
          {features.map((feature) => (
            <Card key={feature.title}>
              <CardHeader>
                <div className="mb-2 inline-flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                  <feature.icon className="h-5 w-5" />
                </div>
                <CardTitle className="text-base">{feature.title}</CardTitle>
                <CardDescription>{feature.description}</CardDescription>
              </CardHeader>
            </Card>
          ))}
        </div>
      </section>

      <section className="mx-auto grid max-w-7xl gap-8 px-4 py-16 sm:grid-cols-3 sm:px-6 lg:px-8">
        {stats.map((stat) => (
          <div key={stat.label} className="text-center">
            <p className="text-4xl font-bold tracking-tight text-foreground">{stat.value}</p>
            <p className="mt-2 text-sm text-muted-foreground">{stat.label}</p>
          </div>
        ))}
      </section>

      <section className="mx-auto max-w-7xl px-4 pb-20 sm:px-6 lg:px-8">
        <div className="flex flex-col items-center justify-between gap-6 rounded-2xl border border-border bg-card p-10 text-center sm:text-left lg:flex-row">
          <div>
            <h2 className="text-2xl font-bold tracking-tight text-foreground">
              Ready to take control of your costs?
            </h2>
            <p className="mt-2 text-muted-foreground">
              Join the teams that stopped guessing about their spend.
            </p>
          </div>
          <div className="flex items-center gap-2">
            <ShieldCheck className="h-5 w-5 text-muted-foreground" />
            <span className="text-sm text-muted-foreground">
              Secure by default. Your data stays yours.
            </span>
          </div>
        </div>
      </section>
    </PublicLayout>
  );
}
