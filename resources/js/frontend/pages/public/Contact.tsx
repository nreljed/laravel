import { useRef } from "react";
import { ArrowRight, Clock, Mail, MapPin, MessageSquareText } from "lucide-react";
import PublicLayout from "../../layouts/PublicLayout";
import { PillButton, useReveal } from "../../components/site";

const channels = [
  {
    n: "01",
    icon: Mail,
    title: "Email us",
    value: "support@costbreaker360.com",
    note: "Feature requests, billing, and everything in between.",
  },
  {
    n: "02",
    icon: Clock,
    title: "Fast response",
    value: "Within 24 hours",
    note: "On weekdays. Most questions answered the same day.",
  },
  {
    n: "03",
    icon: MapPin,
    title: "Built anywhere",
    value: "Remote-first team",
    note: "Working across time zones to serve you whenever you need us.",
  },
];

export default function Contact() {
  const rootRef = useRef<HTMLDivElement>(null);
  useReveal(rootRef, "[data-hero]", { group: true });
  useReveal(rootRef);

  return (
    <PublicLayout
      title="Contact"
      description="Questions, feedback, or want a hand mapping your spend? Get in touch with the Cost Breaker 360 team \u2014 we answer within 24 hours on weekdays."
    >
      <div ref={rootRef}>
        {/* ---------- HERO ---------- */}
        <section className="px-site-lg pt-site-4xl pb-site-4xl sm:px-site-2xl sm:pt-site-5xl">
          <div className="mx-auto grid max-w-site items-end gap-site-2xl lg:grid-cols-[1.2fr_0.8fr] lg:gap-20">
            <div>
              <p
                data-hero
                className="inline-flex items-center gap-site-xs text-xs font-bold tracking-[0.2em] text-site-secondary uppercase"
              >
                <span className="h-1.5 w-1.5 rounded-full bg-site-accent" />
                Contact
              </p>
              <h1 data-hero className="site-display mt-site-lg max-w-[16ch]">
                Tell us where the money leaks.
              </h1>
            </div>
            <p data-hero className="site-lead max-w-[42ch]">
              Questions, feedback, or just curious about what we&apos;re building? We&apos;d love to
              hear from you.
            </p>
          </div>
        </section>

        {/* ---------- CHANNELS ---------- */}
        <section className="px-site-lg pb-site-5xl sm:px-site-2xl">
          <div className="mx-auto max-w-site">
            <p className="text-xs font-bold tracking-[0.2em] text-site-ink-faint uppercase">
              Ways to reach us
            </p>
            <div className="mt-site-2xl grid gap-px overflow-hidden rounded-site-card border border-site-border bg-site-border sm:grid-cols-3">
              {channels.map((channel) => (
                <div key={channel.n} data-reveal className="bg-site-surface p-site-xl">
                  <div className="flex items-center justify-between">
                    <span className="font-site-display text-sm font-bold text-site-accent">
                      {channel.n}
                    </span>
                    <channel.icon className="h-5 w-5 text-site-ink-faint" />
                  </div>
                  <h3 className="site-title-sm mt-site-lg">{channel.title}</h3>
                  <p className="mt-site-xs text-sm font-semibold text-site-ink">{channel.value}</p>
                  <p className="mt-site-xs leading-relaxed text-site-ink-soft">{channel.note}</p>
                </div>
              ))}
            </div>
          </div>
        </section>

        {/* ---------- CLOSING ---------- */}
        <section className="px-site-lg pb-site-5xl sm:px-site-2xl">
          <div
            data-reveal
            className="mx-auto flex max-w-site flex-col items-start justify-between gap-site-lg rounded-site-card border border-site-border bg-site-alt-1 p-site-xl sm:p-14 lg:flex-row lg:items-center"
          >
            <div>
              <h2 className="site-title flex items-center gap-site-sm">
                <MessageSquareText className="h-7 w-7 text-site-accent" />
                Prefer to start on your own?
              </h2>
              <p className="site-lead mt-site-md max-w-[46ch]">
                Create a free account and explore the dashboard before reaching out.
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
