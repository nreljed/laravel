import { usePage } from "@inertiajs/react";

export function SiteFooter() {
  const { props } = usePage();
  const brand = (props.brand as { logo?: string; name?: string }) || {};
  const appName = brand.name || "Cost Breaker 360";

  return (
    <footer className="border-t border-border bg-background">
      <div className="mx-auto max-w-7xl px-4 py-8 text-center text-sm text-muted-foreground sm:px-6 lg:px-8">
        &copy; {new Date().getFullYear()} {appName}. All rights reserved.
      </div>
    </footer>
  );
}
