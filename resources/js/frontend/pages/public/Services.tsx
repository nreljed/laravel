import { useRef } from "react";
import {
  ArrowRight,
  BarChart3,
  BellRing,
  CircleDollarSign,
  ShieldCheck,
  Sparkles,
  Wallet,
} from "lucide-react";
import PublicLayout from "../../layouts/PublicLayout";
import { PillButton, useReveal } from "../../components/site";

const services = [
  {
    n: "01",
    icon: CircleDollarSign,
    title: "Expense tracking",
    body: "Capture costs from every team and project in one place, with categories that make sense to your business.",
  },
  {
    n: "02",
    icon: BarChart3,
    title: "Cost analytics",
    body: "Interactive dashboards and reports that surface trends, outliers and opportunities the moment they appear.",
  },
  {
    n: "03",
    icon: BellRing,
    title: "Budget alerts",
    body: "Set thresholds per department or project and get told before a line item slips past your budget.",
  },
  {
    n: "04",
    icon: ShieldCheck,
    title: "Security & access control",
    body: "Role-based permissions, audit logs and session control so the right people see the right numbers.",
  },
  {
    n: "05",
    icon: Sparkles,
    title: "AI-assisted insights",
    body: "Bring your own AI key and get plain-language summaries of your spending \u2014 without sharing your data.",
  },
  {
    n: "06",
    icon: Wallet,
    title: "Zero-cost platform",
    body: "No per-seat pricing, no markup on analytics. The tool pays for itself from the first euro it saves.",
  },
];

export default function Services() {
  const rootRef = useRef<HTMLDivElement>(null);
  useReveal(rootRef, "[data-hero]", { group: true });
  useReveal(rootRef);

  return (
    <PublicLayout
      title="Services"
      description="Everything you need to understand, control and reduce spending \u2014 tracking, analytics, budget alerts, access control and bring-your-own-key AI in one zero-cost platform."
    >
      <div ref={rootRef}>
        {/* ---------- HERO ---------- */}
        <section className="px-6 pt-16 pb-16 sm:px-10 sm:pt-24">
          <div className="mx-auto grid max-w-site items-end gap-10 lg:grid-cols-[1.2fr_0.8fr] lg:gap-20">
            <div>
              <p
                data-hero
                className="inline-flex items-center gap-2 text-xs font-bold tracking-[0.2em] text-site-secondary uppercase"
              >
                <span className="h-1.5 w-1.5 rounded-full bg-site-accent" />
                Services
              </p>
              <h1 data-hero className="site-display mt-6 max-w-[16ch]">
                Everything you need to break every cost.
              </h1>
            </div>
            <p data-hero className="site-lead max-w-[42ch]">
              Understand, control and reduce spend from one ledger \u2014 no per-seat pricing and no
              markup on the analytics that do the work.
            </p>
          </div>
        </section>

        {/* ---------- SERVICE GRID ---------- */}
        <section className="px-6 pb-24 sm:px-10">
          <div className="mx-auto max-w-site">
            <p className="text-xs font-bold tracking-[0.2em] text-site-ink-faint uppercase">
              What&apos;s included
            </p>
            <div className="mt-10 grid gap-px overflow-hidden rounded-site-card border border-site-border bg-site-border sm:grid-cols-2 lg:grid-cols-3">
              {services.map((service) => (
                <div key={service.n} data-reveal className="bg-site-surface p-8">
                  <div className="flex items-center justify-between">
                    <span className="font-site-display text-sm font-bold text-site-accent">
                      {service.n}
                    </span>
                    <service.icon className="h-5 w-5 text-site-ink-faint" />
                  </div>
                  <h3 className="site-title-sm mt-6">{service.title}</h3>
                  <p className="mt-3 leading-relaxed text-site-ink-soft">{service.body}</p>
                </div>
              ))}
            </div>
          </div>
        </section>

        {/* ---------- CLOSING ---------- */}
        <section className="px-6 pb-24 sm:px-10">
          <div
            data-reveal
            className="mx-auto flex max-w-site flex-col items-start justify-between gap-6 rounded-site-card border border-site-border bg-site-alt-1 p-8 sm:p-14 lg:flex-row lg:items-center"
          >
            <div>
              <h2 className="site-title max-w-[18ch]">Not sure where your money goes?</h2>
              <p className="site-lead mt-4 max-w-[46ch]">
                Start tracking today \u2014 it takes less than a minute to set up.
              </p>
            </div>
            <PillButton href={route("register")}>
              Start free
              <ArrowRight className="h-4 w-4" />
            </PillButton>
          </div>
        </section>
      </div>
    </PublicLayout>
  );
}
