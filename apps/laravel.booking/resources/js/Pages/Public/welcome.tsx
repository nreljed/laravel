import { Head, Link, usePage } from "@inertiajs/react";
import { SiteHeader } from "@/Components/frontend/SiteHeader";
import { SiteFooter } from "@/Components/frontend/SiteFooter";

export default function Welcome() {
  const { props } = usePage();
  const brand = (props.brand as { logo?: string; name?: string }) || {};
  const appName = brand.name || "Booking App";

  return (
    <>
      <Head title="Welcome" />
      <div className="flex min-h-screen flex-col bg-background">
        <SiteHeader />

        <main className="flex flex-1 items-center justify-center px-6">
          <div className="mx-auto max-w-2xl text-center">
            <h1 className="text-5xl font-bold tracking-tight text-foreground sm:text-6xl">
              {appName}
            </h1>
            <p className="mt-6 text-lg leading-8 text-muted-foreground">
              Zero-cost SaaS infrastructure. Built with Laravel, React, and shadcn/ui. Manage your
              costs, track analytics, and scale for free.
            </p>
            <div className="mt-10 flex items-center justify-center gap-4">
              {props.auth?.user ? (
                <Link
                  href={route("dashboard.index")}
                  className="rounded-md bg-primary px-6 py-3 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                >
                  Go to Dashboard
                </Link>
              ) : (
                <>
                  <Link
                    href={route("register")}
                    className="rounded-md bg-primary px-6 py-3 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                  >
                    Get Started Free
                  </Link>
                  <Link
                    href={route("login")}
                    className="rounded-md border border-border bg-background px-6 py-3 text-sm font-medium text-foreground hover:bg-muted"
                  >
                    Sign In
                  </Link>
                </>
              )}
            </div>
          </div>
        </main>

        <SiteFooter />
      </div>
    </>
  );
}
