import { Link } from "@inertiajs/react";
import { ArrowRight, Clock, Mail, MapPin, MessageSquareText } from "lucide-react";
import PublicLayout from "@/Layouts/PublicLayout";
import { Button } from "@/Components/ui/button";
import { Card, CardDescription, CardHeader, CardTitle } from "@/Components/ui/card";

const channels = [
  {
    icon: Mail,
    title: "Email us",
    description: "support@costbreaker360.com",
    note: "For feature requests, billing, and everything in between.",
  },
  {
    icon: Clock,
    title: "Fast response",
    description: "Within 24 hours",
    note: "On weekdays. Most questions answered the same day.",
  },
  {
    icon: MapPin,
    title: "Built anywhere",
    description: "Remote-first team",
    note: "Working across time zones to serve you whenever you need us.",
  },
];

export default function Contact() {
  return (
    <PublicLayout
      title="Contact"
      description="Get in touch with our team to discuss your project — we'd love to hear from you."
    >
      <section className="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
        <div className="mx-auto max-w-3xl text-center">
          <h1 className="text-4xl font-bold tracking-tight text-foreground sm:text-5xl">
            Contact Us
          </h1>
          <p className="mt-6 text-lg leading-8 text-muted-foreground">
            Questions, feedback, or just curious about what we're building? We'd love to hear from
            you.
          </p>
        </div>
      </section>

      <section className="mx-auto max-w-7xl px-4 pb-20 sm:px-6 lg:px-8">
        <div className="grid gap-4 sm:grid-cols-3">
          {channels.map((channel) => (
            <Card key={channel.title}>
              <CardHeader>
                <div className="mb-2 inline-flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                  <channel.icon className="h-5 w-5" />
                </div>
                <CardTitle className="text-base">{channel.title}</CardTitle>
                <CardDescription>
                  <span className="block font-medium text-foreground">{channel.description}</span>
                  {channel.note}
                </CardDescription>
              </CardHeader>
            </Card>
          ))}
        </div>

        <div className="mt-16 flex flex-col items-center justify-between gap-6 rounded-2xl border border-border bg-card p-10 text-center sm:text-left lg:flex-row">
          <div>
            <h2 className="flex items-center justify-center gap-2 text-2xl font-bold tracking-tight text-foreground sm:justify-start">
              <MessageSquareText className="h-6 w-6 text-primary" />
              Prefer to start on your own?
            </h2>
            <p className="mt-2 text-muted-foreground">
              Create a free account and explore the dashboard before reaching out.
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
