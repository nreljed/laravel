import { HeartHandshake, LineChart, Lock, Target } from "lucide-react";
import PublicLayout from "../../Layouts/PublicLayout";
import { PillButton } from "../../Components/site/PillButton";

const values = [
  {
    icon: Target,
    title: "Clarity over complexity",
    description:
      "We believe you can't manage what you can't see. Every feature exists to make your spend visible.",
    alt: false,
  },
  {
    icon: Lock,
    title: "Privacy by default",
    description:
      "Zero-cost infrastructure and zero data exploitation. Your financial data never leaves your control.",
    alt: true,
  },
  {
    icon: LineChart,
    title: "Decisions, not dashboards",
    description:
      "We measure ourselves on the decisions our dashboards enable — not the number of charts we ship.",
    alt: false,
  },
];

export default function About() {
  return (
    <PublicLayout
      title="About"
      description="We build modern web applications with cutting-edge technology — and we practice what we preach on cost."
    >
      <section className="mx-auto w-full max-w-site px-4 py-20 sm:px-6 lg:px-8">
        <div className="max-w-3xl">
          <p className="text-sm font-medium tracking-[0.2em] text-site-secondary uppercase">
            About us
          </p>
          <h1 className="mt-4 font-site-display text-4xl font-bold tracking-tight text-site-ink sm:text-5xl">
            About Us
          </h1>
          <p className="mt-6 text-lg leading-8 text-site-ink-soft">
            Cost Breaker 360 started with a simple frustration: cost-management tools are expensive,
            and they charge you more the better they get. We set out to build the opposite — a
            platform that gives you total visibility into your spending, without the platform eating
            your budget.
          </p>
          <p className="mt-4 text-lg leading-8 text-site-ink-soft">
            We run on modern, zero-cost infrastructure. You bring your own AI key if you want
            AI-assisted insights, and everything else stays open, transparent, and yours.
          </p>
        </div>
      </section>

      <section className="border-y border-site-border bg-site-bg">
        <div className="mx-auto grid w-full max-w-site gap-4 px-4 py-12 sm:grid-cols-3 sm:px-6 lg:px-8">
          {values.map((value) => (
            <div
              key={value.title}
              className={`flex flex-col justify-between rounded-site-card p-6 ${
                value.alt ? "bg-site-alt-2 text-site-ink" : "bg-site-surface"
              }`}
            >
              <div className="mb-6 inline-flex h-10 w-10 items-center justify-center rounded-full bg-site-ink text-site-bg-strong">
                <value.icon className="h-5 w-5" />
              </div>
              <div>
                <h2 className="font-site-display text-lg font-bold tracking-tight">
                  {value.title}
                </h2>
                <p className="mt-2 text-sm leading-6 text-site-ink-soft">{value.description}</p>
              </div>
            </div>
          ))}
        </div>
      </section>

      <section className="mx-auto w-full max-w-site px-4 py-20 sm:px-6 lg:px-8">
        <div className="flex flex-col items-center justify-between gap-6 rounded-site-card bg-site-surface p-10 text-center sm:text-left lg:flex-row">
          <div>
            <h2 className="font-site-display text-2xl font-bold tracking-tight text-site-ink">
              Want to know how we keep our own costs down?
            </h2>
            <p className="mt-2 text-site-ink-soft">
              We use the same tool we sell. Dogfooding keeps us honest.
            </p>
          </div>
          <PillButton href={route("services")}>
            <HeartHandshake className="h-4 w-4" />
            Explore Services
          </PillButton>
        </div>
      </section>
    </PublicLayout>
  );
}
