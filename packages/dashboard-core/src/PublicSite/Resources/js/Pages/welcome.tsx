import { Link, usePage } from "@inertiajs/react";
import PublicLayout from "@/Layouts/PublicLayout";

export default function Welcome() {
  const { props } = usePage();
  const brand = (props.brand as { logo?: string; name?: string }) || {};
  const appName = brand.name || "Cost Breaker 360";
  const registration = (props.registration as { enabled?: boolean }) || {};
  const registrationEnabled = registration.enabled !== false;

  return (
    <PublicLayout
      title="Welcome"
      description="Zero-cost SaaS infrastructure built with Laravel, React, and shadcn/ui."
    >
      <div className="flex flex-1 items-center justify-center px-6 py-24">
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
                href={route("dashboard")}
                className="rounded-md bg-primary px-6 py-3 text-sm font-medium text-primary-foreground hover:bg-primary/90"
              >
                Go to Dashboard
              </Link>
            ) : (
              <>
                {registrationEnabled ? (
                  <Link
                    href={route("register")}
                    className="rounded-md bg-primary px-6 py-3 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                  >
                    Get Started Free
                  </Link>
                ) : null}
                <Link
                  href={route("login")}
                  className={`rounded-md px-6 py-3 text-sm font-medium ${
                    registrationEnabled
                      ? "border border-border bg-background text-foreground hover:bg-muted"
                      : "bg-primary text-primary-foreground hover:bg-primary/90"
                  }`}
                >
                  Sign In
                </Link>
              </>
            )}
          </div>
        </div>
      </div>
    </PublicLayout>
  );
}
