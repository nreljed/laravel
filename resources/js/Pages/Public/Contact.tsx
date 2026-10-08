import { ArrowRight, Clock, Mail, MapPin, MessageSquareText } from "lucide-react";
import PublicLayout from "../../Layouts/PublicLayout";
import { PillButton } from "../../Components/site/PillButton";

const channels = [
  {
    icon: Mail,
    title: "Email us",
    description: "support@costbreaker360.com",
    note: "For feature requests, billing, and everything in between.",
    alt: false,
  },
  {
    icon: Clock,
    title: "Fast response",
    description: "Within 24 hours",
    note: "On weekdays. Most questions answered the same day.",
    alt: true,
  },
  {
    icon: MapPin,
    title: "Built anywhere",
    description: "Remote-first team",
    note: "Working across time zones to serve you whenever you need us.",
    alt: false,
  },
];

export default function Contact() {
  return (
    <PublicLayout
      title="Contact"
      description="Get in touch with our team to discuss your project — we'd love to hear from you."
    >
      <section className="mx-auto w-full max-w-site px-4 py-20 sm:px-6 lg:px-8">
        <div className="text-center">
          <p className="text-sm font-medium tracking-[0.2em] text-site-secondary uppercase">
            Contact
          </p>
          <h1 className="mt-4 font-site-display text-4xl font-bold tracking-tight text-site-ink sm:text-5xl">
            Contact Us
          </h1>
          <p className="mx-auto mt-6 max-w-2xl text-lg leading-8 text-site-ink-soft">
            Questions, feedback, or just curious about what we're building? We'd love to hear from
            you.
          </p>
        </div>
      </section>

      <section className="mx-auto w-full max-w-site px-4 pb-20 sm:px-6 lg:px-8">
        <div className="grid gap-4 sm:grid-cols-3">
          {channels.map((channel) => (
            <div
              key={channel.title}
              className={`flex flex-col justify-between rounded-site-card p-6 ${
                channel.alt ? "bg-site-alt-2 text-site-ink" : "bg-site-surface"
              }`}
            >
              <div className="mb-6 inline-flex h-10 w-10 items-center justify-center rounded-full bg-site-ink text-site-bg-strong">
                <channel.icon className="h-5 w-5" />
              </div>
              <div>
                <h2 className="font-site-display text-lg font-bold tracking-tight">
                  {channel.title}
                </h2>
                <p className="mt-1 text-sm font-medium text-site-ink">{channel.description}</p>
                <p className="mt-1 text-sm leading-6 text-site-ink-soft">{channel.note}</p>
              </div>
            </div>
          ))}
        </div>

        <div className="mt-16 flex flex-col items-center justify-between gap-6 rounded-site-card bg-site-alt-1 p-10 text-center sm:text-left lg:flex-row">
          <div>
            <h2 className="flex items-center justify-center gap-2 font-site-display text-2xl font-bold tracking-tight text-site-ink sm:justify-start">
              <MessageSquareText className="h-6 w-6" />
              Prefer to start on your own?
            </h2>
            <p className="mt-2 text-site-ink-soft">
              Create a free account and explore the dashboard before reaching out.
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
