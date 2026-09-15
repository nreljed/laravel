import { Link, usePage } from "@inertiajs/react";
import PublicLayout from "@nreljed/platform/Layouts/PublicLayout";

export default function Welcome() {
  const { props } = usePage();
  const brand = (props.brand as { logo?: string; name?: string }) || {};
  const appName = brand.name || "";
  const registration = (props.registration as { enabled?: boolean }) || {};
  const registrationEnabled = registration.enabled !== false;

  return (
    <PublicLayout
      title="Welcome"
      description="A starting point for your SaaS built with Laravel, React, Inertia, and shadcn/ui."
    >
      <div className="flex flex-1 items-center justify-center px-6 py-24">
        <div className="mx-auto max-w-2xl text-center">
          <h1 className="text-5xl font-bold tracking-tight text-foreground sm:text-6xl">
            {appName}
          </h1>
          <p className="mt-6 text-lg leading-8 text-muted-foreground">
            Your all-in-one starter kit. Built on Laravel, React, Inertia, and shadcn/ui. Powerful
            modules you can enable and extend to fit your product.
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
