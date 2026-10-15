import { useRef } from "react";
import { Link, usePage } from "@inertiajs/react";
import { ArrowRight, ArrowUpRight, Check, Sparkles } from "lucide-react";
import { useGSAP } from "@gsap/react";
import gsap from "gsap";
import { ScrollTrigger } from "gsap/ScrollTrigger";
import PublicLayout from "../../layouts/PublicLayout";
import { PillButton } from "../../components/site";

const spend = [
  { name: "Cloud infrastructure", amount: 182400, pct: 100, delta: "+12%", down: false },
  { name: "Vendors & SaaS", amount: 96120, pct: 53, delta: "+4%", down: false },
  { name: "Team tools", amount: 54800, pct: 30, delta: "\u22128%", down: true },
  { name: "Data & AI", amount: 31200, pct: 17, delta: "+22%", down: false },
];

const capabilities = [
  {
    n: "01",
    title: "One ledger",
    body: "Every team, project and vendor in a single source of truth \u2014 no more reconciling spreadsheets.",
  },
  {
    n: "02",
    title: "Budgets & alerts",
    body: "Set thresholds per team or vendor and get told before you overspend, not after the invoice lands.",
  },
  {
    n: "03",
    title: "Vendor & SaaS spend",
    body: "See renewals, overlaps and dormant seats in one place, and cut what you quietly stopped using.",
  },
  {
    n: "04",
    title: "Reports finance trusts",
    body: "Clean exports and dashboards built on the same numbers your team actually works from.",
  },
];

const steps = [
  {
    n: "01",
    title: "Connect",
    body: "Bring your teams, projects and vendors into one cost ledger.",
  },
  {
    n: "02",
    title: "Review",
    body: "See where spend is rising, where it is flat, and where it is leaking.",
  },
  {
    n: "03",
    title: "Cut",
    body: "Decide what to break \u2014 then track the saving to the dollar.",
  },
];

const aiPoints = [
  "Your API keys, encrypted at rest",
  "OpenAI, Anthropic, Gemini, Mistral",
  "No platform AI markup",
  "Turn AI off entirely if you prefer",
];

const currency = (value: number) => `$${value.toLocaleString("en-US")}`;

export default function Home() {
  const { props } = usePage();
  const brand = (props.brand as { name?: string }) || {};
  const appName = brand.name || "Cost Breaker 360";
  const rootRef = useRef<HTMLDivElement>(null);

  useGSAP(
    () => {
      const mm = gsap.matchMedia();
      gsap.registerPlugin(ScrollTrigger);

      mm.add("(prefers-reduced-motion: no-preference)", () => {
        gsap.from("[data-hero-item]", {
          y: 24,
          autoAlpha: 0,
          duration: 0.8,
          stagger: 0.08,
          ease: "power3.out",
        });

        gsap.from("[data-ledger]", {
          y: 40,
          autoAlpha: 0,
          duration: 0.9,
          ease: "power2.out",
          delay: 0.15,
        });

        gsap.from("[data-ledger-bar]", {
          scaleX: 0,
          transformOrigin: "left center",
          duration: 1,
          stagger: 0.08,
          ease: "power2.out",
          delay: 0.45,
        });

        const reveals = gsap.utils.toArray<HTMLElement>("[data-reveal]");
        for (const element of reveals) {
          gsap.from(element, {
            y: 40,
            autoAlpha: 0,
            duration: 0.8,
            ease: "power2.out",
            scrollTrigger: { trigger: element, start: "top 85%", once: true },
          });
        }
      });

      return () => mm.revert();
    },
    { scope: rootRef },
  );

  return (
    <PublicLayout
      title="Break every cost"
      description="Cost Breaker 360 brings every team, project and vendor into one cost ledger, then shows finance and ops exactly where to cut."
    >
      <div ref={rootRef}>
        {/* ---------- HERO ---------- */}
        <section className="px-6 pt-14 pb-20 sm:px-10 sm:pt-20">
          <div className="mx-auto grid max-w-site items-center gap-14 lg:grid-cols-[1.05fr_0.95fr] lg:gap-20">
            <div>
              <p
                data-hero-item
                className="inline-flex items-center gap-2 text-xs font-bold tracking-[0.2em] text-site-secondary uppercase"
              >
                <span className="h-1.5 w-1.5 rounded-full bg-site-accent" />
                Cost management for finance &amp; ops
              </p>

              <h1 data-hero-item className="site-hero mt-6">
                Break every cost.
              </h1>

              <p data-hero-item className="site-lead mt-7 max-w-[46ch]">
                {appName} puts every team, project and vendor into one plain-spoken ledger \u2014
                then shows you where the money actually goes, and what to cut first.
              </p>

              <div data-hero-item className="mt-10 flex flex-wrap items-center gap-5">
                <PillButton href={route("register")}>
                  Start free
                  <ArrowRight className="h-4 w-4" />
                </PillButton>
                <a
                  href="#how"
                  className="inline-flex items-center gap-2 text-base font-bold tracking-tight text-site-ink"
                >
                  See how it works
                  <ArrowUpRight className="h-4 w-4" />
                </a>
              </div>
            </div>

            {/* Signature element: the cost ledger */}
            <div
              data-ledger
              className="rounded-site-card border border-site-border bg-site-surface p-6 sm:p-8"
            >
              <div className="flex items-start justify-between gap-4">
                <div>
                  <p className="text-xs font-bold tracking-[0.2em] text-site-ink-faint uppercase">
                    Q3 spend review
                  </p>
                  <p className="mt-1 text-sm text-site-ink-soft">All teams &middot; illustrative</p>
                </div>
                <span className="rounded-site-pill border border-site-border px-3 py-1 text-xs font-semibold text-site-secondary">
                  Review
                </span>
              </div>

              <ul className="mt-7 space-y-5">
                {spend.map((row) => (
                  <li key={row.name}>
                    <div className="flex items-baseline justify-between gap-4">
                      <span className="text-sm font-medium">{row.name}</span>
                      <span className="font-site-display text-base font-bold tabular-nums">
                        {currency(row.amount)}
                      </span>
                    </div>
                    <div className="mt-2 flex items-center gap-3">
                      <div className="h-1.5 flex-1 overflow-hidden rounded-full bg-site-ink/10">
                        <div
                          data-ledger-bar
                          className={`h-full rounded-full ${
                            row.down ? "bg-site-accent" : "bg-site-ink/35"
                          }`}
                          style={{ width: `${row.pct}%` }}
                        />
                      </div>
                      <span
                        className={`w-12 text-right text-xs font-semibold tabular-nums ${
                          row.down ? "text-site-accent" : "text-site-ink-faint"
                        }`}
                      >
                        {row.delta}
                      </span>
                    </div>
                  </li>
                ))}
              </ul>

              <div className="mt-7 flex items-end justify-between border-t border-site-border pt-5">
                <span className="text-sm font-medium text-site-ink-soft">Total</span>
                <div className="text-right">
                  <span className="font-site-display block text-3xl font-bold tabular-nums">
                    $364,520
                  </span>
                  <span className="text-xs font-semibold text-site-accent">
                    18% identified to cut
                  </span>
                </div>
              </div>
            </div>
          </div>
        </section>

        {/* ---------- THE PROBLEM ---------- */}
        <section className="px-6 pb-6 sm:px-10">
          <div className="mx-auto grid max-w-site gap-10 lg:grid-cols-[1.1fr_1fr] lg:items-end lg:gap-20">
            <h2 data-reveal className="site-title max-w-[18ch]">
              Most teams can&apos;t say where a third of their spend goes.
            </h2>
            <p data-reveal className="site-lead max-w-[52ch]">
              It hides in renewals nobody remembers signing, seats nobody uses, and invoices spread
              across a dozen tools. {appName} puts it in one ledger and turns &ldquo;we should look
              at that&rdquo; into a number you can act on.
            </p>
          </div>
        </section>

        {/* ---------- CAPABILITIES ---------- */}
        <section className="px-6 py-24 sm:px-10">
          <div className="mx-auto max-w-site">
            <p className="text-xs font-bold tracking-[0.2em] text-site-ink-faint uppercase">
              What you get
            </p>
            <div className="mt-10 grid gap-px overflow-hidden rounded-site-card border border-site-border bg-site-border sm:grid-cols-2">
              {capabilities.map((capability) => (
                <div key={capability.n} data-reveal className="bg-site-surface p-8 sm:p-10">
                  <span className="font-site-display text-sm font-bold text-site-accent">
                    {capability.n}
                  </span>
                  <h3 className="site-title-sm mt-4">{capability.title}</h3>
                  <p className="mt-3 leading-relaxed text-site-ink-soft">{capability.body}</p>
                </div>
              ))}
            </div>
          </div>
        </section>

        {/* ---------- HOW IT WORKS ---------- */}
        <section id="how" className="scroll-mt-24 px-6 py-20 sm:px-10">
          <div className="mx-auto max-w-site">
            <h2 data-reveal className="site-title max-w-[20ch]">
              Three moves to a leaner budget.
            </h2>
            <ol className="mt-14 grid gap-10 sm:grid-cols-3">
              {steps.map((step) => (
                <li key={step.n} data-reveal className="border-t border-site-border pt-6">
                  <span className="font-site-display text-5xl font-bold tracking-tight text-site-ink/15">
                    {step.n}
                  </span>
                  <h3 className="site-title-sm mt-4">{step.title}</h3>
                  <p className="mt-2 leading-relaxed text-site-ink-soft">{step.body}</p>
                </li>
              ))}
            </ol>
          </div>
        </section>

        {/* ---------- AI (BYOK) ---------- */}
        <section className="px-6 py-20 sm:px-10">
          <div className="mx-auto max-w-site">
            <div
              data-reveal
              className="overflow-hidden rounded-site-card border border-site-border bg-site-alt-1 p-8 sm:p-14"
            >
              <span className="inline-flex items-center gap-2 text-xs font-bold tracking-[0.2em] text-site-secondary uppercase">
                <Sparkles className="h-4 w-4" />
                Bring your own AI
              </span>
              <h2 className="site-title mt-5 max-w-[24ch]">
                Ask your spend a question. Keep your keys.
              </h2>
              <p className="site-lead mt-5 max-w-[54ch]">
                Query the ledger in plain language and get answers built on your own numbers.{" "}
                {appName} is bring-your-own-key \u2014 your models, your data, no platform AI
                markup.
              </p>
              <ul className="mt-8 grid gap-3 sm:grid-cols-2">
                {aiPoints.map((point) => (
                  <li key={point} className="flex items-center gap-2.5 text-sm font-medium">
                    <Check className="h-4 w-4 shrink-0 text-site-accent" />
                    {point}
                  </li>
                ))}
              </ul>
            </div>
          </div>
        </section>

        {/* ---------- CLOSING STATEMENT ---------- */}
        <section className="px-6 pt-10 pb-24 sm:px-10">
          <div className="mx-auto max-w-site text-center">
            <h2 data-reveal className="site-title">
              Break every cost.
            </h2>
            <p data-reveal className="site-lead mx-auto mt-6 max-w-[46ch]">
              Start with one ledger and see what your budget looks like when the guesswork is gone.
            </p>
            <div data-reveal className="mt-10 flex flex-wrap items-center justify-center gap-5">
              <PillButton href={route("register")}>
                Start free
                <ArrowRight className="h-4 w-4" />
              </PillButton>
              <Link
                href={route("contact")}
                className="inline-flex items-center gap-2 text-base font-bold tracking-tight"
              >
                Talk to us
                <ArrowUpRight className="h-4 w-4" />
              </Link>
            </div>
          </div>
        </section>
      </div>
    </PublicLayout>
  );
}
