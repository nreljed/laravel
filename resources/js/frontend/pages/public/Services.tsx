import {
  ArrowRight,
  BarChart3,
  BellRing,
  CircleDollarSign,
  Database,
  ShieldCheck,
} from "lucide-react";
import PublicLayout from "../../layouts/PublicLayout";
import { PillButton } from "../../components/site/PillButton";

const services = [
  {
    icon: CircleDollarSign,
    title: "Expense tracking",
    description:
      "Capture costs from every team and project in one place, with categories that make sense to your business.",
    alt: false,
  },
  {
    icon: BarChart3,
    title: "Cost analytics",
    description:
      "Interactive dashboards and reports that surface trends, outliers, and opportunities the moment they appear.",
    alt: true,
  },
  {
    icon: BellRing,
    title: "Budget alerts",
    description:
      "Set thresholds per department or project and get notified before a line item slips past your budget.",
    alt: false,
  },
  {
    icon: ShieldCheck,
    title: "Security & access control",
    description:
      "Role-based permissions, audit logs, and session control so the right people see the right numbers.",
    alt: true,
  },
  {
    icon: Database,
    title: "AI-assisted insights",
    description:
      "Bring your own AI key and get plain-language summaries of your spending — without sharing your data.",
    alt: false,
  },
  {
    icon: CircleDollarSign,
    title: "Zero-cost platform",
    description:
      "No per-seat pricing, no markup on analytics. The tool pays for itself from the first euro it saves.",
    alt: true,
  },
];

export default function Services() {
  return (
    <PublicLayout
      title="Services"
      description="Cost-effective, high-performance software solutions tailored to your needs — from tracking to optimisation."
    >
      <section className="mx-auto w-full max-w-site px-4 py-20 sm:px-6 lg:px-8">
        <div className="text-center">
          <p className="text-sm font-medium tracking-[0.2em] text-site-secondary uppercase">
            Our services
          </p>
          <h1 className="mt-4 font-site-display text-4xl font-bold tracking-tight text-site-ink sm:text-5xl">
            Our Services
          </h1>
          <p className="mx-auto mt-6 max-w-2xl text-lg leading-8 text-site-ink-soft">
            Everything you need to understand, control, and reduce your spending — in one dashboard.
          </p>
        </div>
      </section>

      <section className="mx-auto w-full max-w-site px-4 pb-20 sm:px-6 lg:px-8">
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {services.map((service) => (
            <div
              key={service.title}
              className={`flex flex-col justify-between rounded-site-card p-6 ${
                service.alt ? "bg-site-alt-2 text-site-ink" : "bg-site-surface"
              }`}
            >
              <div className="mb-6 inline-flex h-10 w-10 items-center justify-center rounded-full bg-site-ink text-site-bg-strong">
                <service.icon className="h-5 w-5" />
              </div>
              <div>
                <h2 className="font-site-display text-lg font-bold tracking-tight">
                  {service.title}
                </h2>
                <p className="mt-2 text-sm leading-6 text-site-ink-soft">{service.description}</p>
              </div>
            </div>
          ))}
        </div>

        <div className="mt-16 flex flex-col items-center justify-between gap-6 rounded-site-card bg-site-alt-1 p-10 text-center sm:text-left lg:flex-row">
          <div>
            <h2 className="font-site-display text-2xl font-bold tracking-tight text-site-ink">
              Not sure where your money goes?
            </h2>
            <p className="mt-2 text-site-ink-soft">
              Start tracking today — it takes less than a minute to set up.
            </p>
          </div>
          <PillButton href={route("register")}>
            Get Started Free
            <ArrowRight className="h-4 w-4" />
          </PillButton>
        </div>
      </section>
    </PublicLayout>
  );
}
