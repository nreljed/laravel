import { useRef } from "react";
import { ArrowRight, HeartHandshake, LineChart, Lock, Target } from "lucide-react";
import PublicLayout from "../../layouts/PublicLayout";
import { PillButton, useReveal } from "../../components/site";

const principles = [
  {
    n: "01",
    icon: Target,
    title: "Clarity over complexity",
    body: "You can't manage what you can't see. Every screen exists to make a number obvious \u2014 not to show off a chart.",
  },
  {
    n: "02",
    icon: Lock,
    title: "Privacy by default",
    body: "Zero-cost infrastructure, zero data exploitation. Your financial data stays under your control and never becomes someone else's product.",
  },
  {
    n: "03",
    icon: LineChart,
    title: "Decisions, not dashboards",
    body: "We measure ourselves on the decisions the ledger enables, not on the number of charts we ship.",
  },
];

export default function About() {
  const rootRef = useRef<HTMLDivElement>(null);
  useReveal(rootRef, "[data-hero]", { group: true });
  useReveal(rootRef);

  return (
    <PublicLayout
      title="About"
      description="Cost Breaker 360 was built to make spend visible without the platform becoming a cost of its own \u2014 zero-cost infrastructure, bring-your-own-key AI, and your data kept under your control."
    >
      <div ref={rootRef}>
        {/* ---------- HERO ---------- */}
        <section className="px-6 pt-16 pb-16 sm:px-10 sm:pt-24">
          <div className="mx-auto max-w-site">
            <p
              data-hero
              className="inline-flex items-center gap-2 text-xs font-bold tracking-[0.2em] text-site-secondary uppercase"
            >
              <span className="h-1.5 w-1.5 rounded-full bg-site-accent" />
              About
            </p>
            <h1 data-hero className="site-display mt-6 max-w-[18ch]">
              We built the cost tool our own budgets needed.
            </h1>
            <p data-hero className="site-lead mt-7 max-w-[56ch]">
              Cost Breaker 360 started from a frustration we kept hitting ourselves: the better a
              cost-management tool gets, the more it costs to run \u2014 until the platform itself
              is the expense. So we built the opposite.
            </p>
          </div>
        </section>

        {/* ---------- STORY ---------- */}
        <section className="px-6 pb-8 sm:px-10">
          <div className="mx-auto grid max-w-site gap-10 lg:grid-cols-[1.1fr_1fr] lg:items-start lg:gap-20">
            <h2 data-reveal className="site-title max-w-[16ch]">
              A cost platform that doesn&apos;t eat your budget.
            </h2>
            <div data-reveal className="space-y-5 text-lg leading-8 text-site-ink-soft">
              <p>
                We run on modern, zero-cost infrastructure and pass that discipline straight to you.
                No per-seat pricing, no markup on analytics, no surprise tiers \u2014 just the
                ledger and the numbers it holds.
              </p>
              <p>
                Bring your own AI key and the platform stays out of the middle: your models, your
                keys, your data. Everything else is open, transparent, and yours.
              </p>
            </div>
          </div>
        </section>

        {/* ---------- PRINCIPLES ---------- */}
        <section className="px-6 py-24 sm:px-10">
          <div className="mx-auto max-w-site">
            <p className="text-xs font-bold tracking-[0.2em] text-site-ink-faint uppercase">
              What we hold to
            </p>
            <div className="mt-10 grid gap-px overflow-hidden rounded-site-card border border-site-border bg-site-border sm:grid-cols-3">
              {principles.map((principle) => (
                <div key={principle.n} data-reveal className="bg-site-surface p-8 sm:p-10">
                  <div className="flex items-center justify-between">
                    <span className="font-site-display text-sm font-bold text-site-accent">
                      {principle.n}
                    </span>
                    <principle.icon className="h-5 w-5 text-site-ink-faint" />
                  </div>
                  <h3 className="site-title-sm mt-6">{principle.title}</h3>
                  <p className="mt-3 leading-relaxed text-site-ink-soft">{principle.body}</p>
                </div>
              ))}
            </div>
          </div>
        </section>

        {/* ---------- DOGFOOD ---------- */}
        <section className="px-6 pb-24 sm:px-10">
          <div
            data-reveal
            className="mx-auto max-w-site overflow-hidden rounded-site-card border border-site-border bg-site-alt-1 p-8 sm:p-14"
          >
            <div className="grid gap-8 lg:grid-cols-[1.2fr_0.8fr] lg:items-center">
              <div>
                <span className="inline-flex items-center gap-2 text-xs font-bold tracking-[0.2em] text-site-secondary uppercase">
                  <HeartHandshake className="h-4 w-4" />
                  Dogfooding
                </span>
                <h2 className="site-title mt-5 max-w-[20ch]">We run our own books on it.</h2>
                <p className="site-lead mt-5 max-w-[50ch]">
                  Every cost we help you cut, we cut in our own stack first. Dogfooding keeps the
                  advice honest.
                </p>
              </div>
              <div className="flex lg:justify-end">
                <PillButton href={route("services")}>
                  Explore services
                  <ArrowRight className="h-4 w-4" />
                </PillButton>
              </div>
            </div>
          </div>
        </section>
      </div>
    </PublicLayout>
  );
}
