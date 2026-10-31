import { usePage } from "@inertiajs/react";
import type { LucideIcon } from "lucide-react";
import { Clock, Mail, MapPin, Phone, Send } from "lucide-react";
import PublicLayout from "@nreljed/platform/Layouts/PublicLayout";
import { Button } from "@nreljed/platform/Components/ui/button";
import { Card, CardHeader, CardTitle } from "@nreljed/platform/Components/ui/card";

interface ContactPageProps {
  email?: string;
  phone?: string;
  address?: string;
  hours?: string;
}

interface ContactChannel {
  icon: LucideIcon;
  label: string;
  value: string;
  href?: string;
}

export default function Contact() {
  const { props } = usePage();
  const data = (props.contact ?? {}) as ContactPageProps;

  const email = data.email ?? "";
  const phone = data.phone ?? "";
  const address = data.address ?? "";
  const hours = data.hours ?? "";

  const channels: ContactChannel[] = [
    { icon: Mail, label: "Email", value: email, href: email ? `mailto:${email}` : undefined },
    { icon: Phone, label: "Phone", value: phone, href: phone ? `tel:${phone.replace(/[^+\d]/g, "")}` : undefined },
    { icon: MapPin, label: "Address", value: address },
    { icon: Clock, label: "Hours", value: hours },
  ].filter((c) => c.value.length > 0);

  return (
    <PublicLayout title="Contact" description="Get in touch with our team — we'd love to hear from you." ogType="website">
      {/* Hero */}
      <section className="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8 lg:py-24">
        <div className="mx-auto max-w-3xl text-center">
          <h1 className="text-4xl font-bold tracking-tight sm:text-5xl">Get in touch</h1>
          <p className="mt-6 text-lg leading-relaxed text-muted-foreground">
            Questions about a product, an order, or delivery? Reach out through any channel below and
            we'll get back to you promptly.
          </p>
        </div>

        <div className="mt-16 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
          {channels.map((c) => {
            const content = (
              <CardHeader>
                <div className="flex h-11 w-11 items-center justify-center rounded-lg bg-primary/10 text-primary">
                  <c.icon className="h-5 w-5" />
                </div>
                <CardTitle className="mt-3 text-base">{c.label}</CardTitle>
                <div className="mt-1 break-words text-sm text-muted-foreground">{c.value}</div>
              </CardHeader>
            );
            const card = <Card className="h-full transition-colors hover:bg-muted/40">{content}</Card>;
            return c.href ? (
              <a key={c.label} href={c.href} className="block h-full">
                {card}
              </a>
            ) : (
              <div key={c.label} className="h-full">
                {card}
              </div>
            );
          })}
        </div>

        {email.length > 0 && (
          <div className="mt-16 flex flex-col items-center gap-4 rounded-2xl border bg-gradient-to-br from-primary/5 via-background to-background p-10 text-center">
            <Send className="h-8 w-8 text-primary" />
            <h2 className="text-2xl font-bold tracking-tight">Prefer to write to us?</h2>
            <p className="max-w-xl text-muted-foreground">
              Email is the fastest way to reach the team and we typically reply within one business day.
            </p>
            <Button render={<a href={`mailto:${email}`} />} size="lg">
              <Mail className="mr-2 h-4 w-4" /> Email {email}
            </Button>
          </div>
        )}
      </section>
    </PublicLayout>
  );
}
