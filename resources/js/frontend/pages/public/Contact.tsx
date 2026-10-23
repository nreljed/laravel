import { useEffect, useRef, useState } from "react";
import { useForm, usePage } from "@inertiajs/react";
import {
  ArrowRight,
  CheckCircle2,
  Clock,
  Loader2,
  Mail,
  MapPin,
  MessageSquareText,
  Send,
} from "lucide-react";
import { toast } from "@nreljed/platform/Components/ui/toast";
import PublicLayout from "../../layouts/PublicLayout";
import { PillButton, SiteCardGrid, SiteFeatureCard, useReveal } from "../../components/site";

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

interface ContactFormData {
  name: string;
  email: string;
  subject: string;
  message: string;
  website: string;
}

const fieldClass =
  "w-full rounded-site-card border border-site-border bg-site-surface px-site-md py-3 text-[0.95rem] text-site-ink placeholder:text-site-ink-faint transition-colors focus:border-site-accent focus:outline-none";

const labelClass = "block text-xs font-bold uppercase tracking-[0.16em] text-site-secondary";

const errorClass = "mt-1.5 block text-sm text-site-danger";

export default function Contact() {
  const rootRef = useRef<HTMLDivElement>(null);
  useReveal(rootRef, "[data-hero]", { group: true });
  useReveal(rootRef);

  const pageProps = usePage().props as { flash?: { status?: string } };
  const [sent, setSent] = useState(pageProps.flash?.status === "contact-sent");

  const { data, setData, post, processing, errors, reset } = useForm<ContactFormData>({
    name: "",
    email: "",
    subject: "",
    message: "",
    website: "",
  });

  useEffect(() => {
    if (pageProps.flash?.status === "contact-sent") {
      setSent(true);
    }
  }, [pageProps.flash?.status]);

  const submit = (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (processing) return;

    post(route("contact.store"), {
      preserveScroll: true,
      onSuccess: () => {
        reset();
        setSent(true);
      },
      onError: () =>
        toast.add({
          title: "We couldn't send that just yet",
          description: "Check the highlighted fields and try again.",
          type: "error",
        }),
    });
  };

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
            <SiteCardGrid className="mt-site-2xl sm:grid-cols-3">
              {channels.map((channel) => (
                <SiteFeatureCard
                  key={channel.n}
                  index={channel.n}
                  icon={channel.icon}
                  title={channel.title}
                  value={channel.value}
                  reveal
                >
                  {channel.note}
                </SiteFeatureCard>
              ))}
            </SiteCardGrid>
          </div>
        </section>

        {/* ---------- MESSAGE FORM ---------- */}
        <section className="px-site-lg pb-site-5xl sm:px-site-2xl">
          <div className="mx-auto grid max-w-site items-start gap-site-2xl lg:grid-cols-[0.85fr_1.15fr] lg:gap-20">
            <div data-reveal>
              <p className="text-xs font-bold tracking-[0.2em] text-site-ink-faint uppercase">
                Send a message
              </p>
              <h2 className="site-title mt-site-md max-w-[14ch]">Tell us the whole story.</h2>
              <p className="site-lead mt-site-md max-w-[42ch]">
                Fill this in and it lands straight in our support inbox. No chatbot, no ticket maze
                — a person reads every one.
              </p>
              <p className="mt-site-lg max-w-[40ch] text-[0.95rem] text-site-ink-soft">
                Prefer email? Write to{" "}
                <a
                  href="mailto:support@costbreaker360.com"
                  className="underline decoration-site-accent decoration-2 underline-offset-4 transition-colors hover:text-site-accent"
                >
                  support@costbreaker360.com
                </a>{" "}
                instead — both reach the same desk.
              </p>
            </div>

            <div data-reveal>
              {sent ? (
                <div className="rounded-site-card border border-site-border bg-site-surface p-site-xl sm:p-14">
                  <CheckCircle2 className="h-9 w-9 text-site-accent" aria-hidden="true" />
                  <h3 className="site-title mt-site-md">Message received.</h3>
                  <p className="site-lead mt-site-md max-w-[44ch]">
                    Thanks for reaching out. We read everything and reply within 24 hours on
                    weekdays — usually much sooner.
                  </p>
                  <button
                    type="button"
                    onClick={() => {
                      setSent(false);
                      reset();
                    }}
                    className="group mt-site-xl inline-flex h-[53px] items-center gap-site-xs rounded-site-pill bg-site-ink px-site-xl text-site-bg-strong transition-colors duration-200 hover:bg-site-ink/90"
                  >
                    Send another message
                    <ArrowRight className="h-4 w-4 transition-transform duration-300 group-hover:translate-x-1.5" />
                  </button>
                </div>
              ) : (
                <form
                  onSubmit={submit}
                  noValidate
                  className="relative rounded-site-card border border-site-border bg-site-surface p-site-xl sm:p-14"
                >
                  {/* Honeypot — off-screen for humans, filled by naive bots. */}
                  <div
                    aria-hidden="true"
                    className="pointer-events-none absolute top-0 left-0 h-px w-px overflow-hidden opacity-0"
                  >
                    <label htmlFor="website">Leave this field empty</label>
                    <input
                      id="website"
                      name="website"
                      type="text"
                      tabIndex={-1}
                      autoComplete="off"
                      value={data.website}
                      onChange={(event) => setData("website", event.target.value)}
                    />
                  </div>

                  <div className="grid gap-site-lg sm:grid-cols-2">
                    <div>
                      <label htmlFor="contact-name" className={labelClass}>
                        Your name
                      </label>
                      <input
                        id="contact-name"
                        name="name"
                        type="text"
                        required
                        maxLength={120}
                        autoComplete="name"
                        placeholder="Ada Lovelace"
                        className={`mt-2 ${fieldClass}`}
                        value={data.name}
                        onChange={(event) => setData("name", event.target.value)}
                        aria-invalid={Boolean(errors.name)}
                        aria-describedby={errors.name ? "contact-name-error" : undefined}
                      />
                      {errors.name && (
                        <span id="contact-name-error" className={errorClass}>
                          {errors.name}
                        </span>
                      )}
                    </div>

                    <div>
                      <label htmlFor="contact-email" className={labelClass}>
                        Email address
                      </label>
                      <input
                        id="contact-email"
                        name="email"
                        type="email"
                        required
                        maxLength={190}
                        autoComplete="email"
                        placeholder="ada@example.com"
                        className={`mt-2 ${fieldClass}`}
                        value={data.email}
                        onChange={(event) => setData("email", event.target.value)}
                        aria-invalid={Boolean(errors.email)}
                        aria-describedby={errors.email ? "contact-email-error" : undefined}
                      />
                      {errors.email && (
                        <span id="contact-email-error" className={errorClass}>
                          {errors.email}
                        </span>
                      )}
                    </div>

                    <div className="sm:col-span-2">
                      <label htmlFor="contact-subject" className={labelClass}>
                        Subject <span className="normal-case">(optional)</span>
                      </label>
                      <input
                        id="contact-subject"
                        name="subject"
                        type="text"
                        maxLength={190}
                        placeholder="What's this about?"
                        className={`mt-2 ${fieldClass}`}
                        value={data.subject}
                        onChange={(event) => setData("subject", event.target.value)}
                        aria-invalid={Boolean(errors.subject)}
                        aria-describedby={errors.subject ? "contact-subject-error" : undefined}
                      />
                      {errors.subject && (
                        <span id="contact-subject-error" className={errorClass}>
                          {errors.subject}
                        </span>
                      )}
                    </div>

                    <div className="sm:col-span-2">
                      <label htmlFor="contact-message" className={labelClass}>
                        Message
                      </label>
                      <textarea
                        id="contact-message"
                        name="message"
                        required
                        rows={6}
                        minLength={10}
                        maxLength={5000}
                        placeholder="Tell us what you're trying to solve…"
                        className={`mt-2 resize-y ${fieldClass}`}
                        value={data.message}
                        onChange={(event) => setData("message", event.target.value)}
                        aria-invalid={Boolean(errors.message)}
                        aria-describedby={errors.message ? "contact-message-error" : undefined}
                      />
                      {errors.message && (
                        <span id="contact-message-error" className={errorClass}>
                          {errors.message}
                        </span>
                      )}
                    </div>
                  </div>

                  <div className="mt-site-xl flex flex-wrap items-center justify-between gap-site-md">
                    <p className="max-w-[34ch] text-sm text-site-ink-faint">
                      We only use your details to reply. Nothing is added to a mailing list.
                    </p>
                    <button
                      type="submit"
                      disabled={processing}
                      className="group inline-flex h-[53px] items-center gap-site-xs rounded-site-pill bg-site-ink px-site-xl text-site-bg-strong transition-colors duration-200 hover:bg-site-ink/90 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                      {processing ? (
                        <>
                          Sending
                          <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
                        </>
                      ) : (
                        <>
                          Send message
                          <Send className="h-4 w-4 transition-transform duration-300 group-hover:translate-x-1.5" />
                        </>
                      )}
                    </button>
                  </div>
                </form>
              )}
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
