import { Link } from "@inertiajs/react";
import { HeartHandshake, LineChart, Lock, Target } from "lucide-react";
import PublicLayout from "@/Layouts/PublicLayout";
import { Button } from "@/Components/ui/button";
import { Card, CardDescription, CardHeader, CardTitle } from "@/Components/ui/card";

const values = [
  {
    icon: Target,
    title: "Clarity over complexity",
    description:
      "We believe you can't manage what you can't see. Every feature exists to make your spend visible.",
  },
  {
    icon: Lock,
    title: "Privacy by default",
    description:
      "Zero-cost infrastructure and zero data exploitation. Your financial data never leaves your control.",
  },
  {
    icon: LineChart,
    title: "Decisions, not dashboards",
    description:
      "We measure ourselves on the decisions our dashboards enable — not the number of charts we ship.",
  },
];

export default function About() {
  return (
    <PublicLayout
      title="About"
      description="We build modern web applications with cutting-edge technology — and we practice what we preach on cost."
    >
      <section className="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
        <div className="mx-auto max-w-3xl">
          <h1 className="text-4xl font-bold tracking-tight text-foreground sm:text-5xl">
            About Us
          </h1>
          <p className="mt-6 text-lg leading-8 text-muted-foreground">
            Cost Breaker 360 started with a simple frustration: cost-management tools are expensive,
            and they charge you more the better they get. We set out to build the opposite — a
            platform that gives you total visibility into your spending, without the platform eating
            your budget.
          </p>
          <p className="mt-4 text-lg leading-8 text-muted-foreground">
            We run on modern, zero-cost infrastructure. You bring your own AI key if you want
            AI-assisted insights, and everything else stays open, transparent, and yours.
          </p>
        </div>
      </section>

      <section className="border-y border-border bg-muted/40">
        <div className="mx-auto grid max-w-7xl gap-4 px-4 py-12 sm:grid-cols-3 sm:px-6 lg:px-8">
          {values.map((value) => (
            <Card key={value.title}>
              <CardHeader>
                <div className="mb-2 inline-flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                  <value.icon className="h-5 w-5" />
                </div>
                <CardTitle className="text-base">{value.title}</CardTitle>
                <CardDescription>{value.description}</CardDescription>
              </CardHeader>
            </Card>
          ))}
        </div>
      </section>

      <section className="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
        <div className="flex flex-col items-center justify-between gap-6 rounded-2xl border border-border bg-card p-10 text-center sm:text-left lg:flex-row">
          <div>
            <h2 className="text-2xl font-bold tracking-tight text-foreground">
              Want to know how we keep our own costs down?
            </h2>
            <p className="mt-2 text-muted-foreground">
              We use the same tool we sell. Dogfooding keeps us honest.
            </p>
          </div>
          <Link href={route("services")}>
            <Button size="lg">
              <HeartHandshake className="mr-2 h-4 w-4" />
              Explore Services
            </Button>
          </Link>
        </div>
      </section>
    </PublicLayout>
  );
}
