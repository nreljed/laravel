import { Link } from "@inertiajs/react";
import {
  ArrowRight,
  BarChart3,
  BellRing,
  CircleDollarSign,
  Database,
  ShieldCheck,
} from "lucide-react";
import PublicLayout from "@/Layouts/PublicLayout";
import { Button } from "@/Components/ui/button";
import { Card, CardDescription, CardHeader, CardTitle } from "@/Components/ui/card";

const services = [
  {
    icon: CircleDollarSign,
    title: "Expense tracking",
    description:
      "Capture costs from every team and project in one place, with categories that make sense to your business.",
  },
  {
    icon: BarChart3,
    title: "Cost analytics",
    description:
      "Interactive dashboards and reports that surface trends, outliers, and opportunities the moment they appear.",
  },
  {
    icon: BellRing,
    title: "Budget alerts",
    description:
      "Set thresholds per department or project and get notified before a line item slips past your budget.",
  },
  {
    icon: ShieldCheck,
    title: "Security & access control",
    description:
      "Role-based permissions, audit logs, and session control so the right people see the right numbers.",
  },
  {
    icon: Database,
    title: "AI-assisted insights",
    description:
      "Bring your own AI key and get plain-language summaries of your spending — without sharing your data.",
  },
  {
    icon: CircleDollarSign,
    title: "Zero-cost platform",
    description:
      "No per-seat pricing, no markup on analytics. The tool pays for itself from the first euro it saves.",
  },
];

export default function Services() {
  return (
    <PublicLayout
      title="Services"
      description="Cost-effective, high-performance software solutions tailored to your needs — from tracking to optimisation."
    >
      <section className="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
        <div className="mx-auto max-w-3xl text-center">
          <h1 className="text-4xl font-bold tracking-tight text-foreground sm:text-5xl">
            Our Services
          </h1>
          <p className="mt-6 text-lg leading-8 text-muted-foreground">
            Everything you need to understand, control, and reduce your spending — in one dashboard.
          </p>
        </div>
      </section>

      <section className="mx-auto max-w-7xl px-4 pb-20 sm:px-6 lg:px-8">
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {services.map((service) => (
            <Card key={service.title}>
              <CardHeader>
                <div className="mb-2 inline-flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                  <service.icon className="h-5 w-5" />
                </div>
                <CardTitle className="text-base">{service.title}</CardTitle>
                <CardDescription>{service.description}</CardDescription>
              </CardHeader>
            </Card>
          ))}
        </div>

        <div className="mt-16 flex flex-col items-center justify-between gap-6 rounded-2xl border border-border bg-card p-10 text-center sm:text-left lg:flex-row">
          <div>
            <h2 className="text-2xl font-bold tracking-tight text-foreground">
              Not sure where your money goes?
            </h2>
            <p className="mt-2 text-muted-foreground">
              Start tracking today — it takes less than a minute to set up.
            </p>
          </div>
          <Link href={route("register")}>
            <Button size="lg">
              Get Started Free
              <ArrowRight className="ml-2 h-4 w-4" />
            </Button>
          </Link>
        </div>
      </section>
    </PublicLayout>
  );
}
