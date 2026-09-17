import { PropsWithChildren } from "react";
import { SiteFooter } from "@nreljed/platform/Components/frontend/SiteFooter";
import { resolveStorefrontLayout } from "@nreljed/platform/lib/storefront-layout";
import { StoreHeader } from "../components/StoreHeader";
import CurrencySwitcher from "../components/CurrencySwitcher";

/**
 * The storefront layout seam. Pages in this module import this component
 * (unchanged); it defers to the chrome a white-label app registered via
 * `registerStorefrontLayout()` (their own `Layouts/StoreLayout.tsx`), falling
 * back to the module's default store chrome (currency topbar + store header +
 * footer) when none is registered.
 */
export default function StoreLayout({ children }: PropsWithChildren) {
  const AppLayout = resolveStorefrontLayout();

  if (AppLayout) {
    return <AppLayout>{children}</AppLayout>;
  }

  return (
    <div className="flex min-h-screen flex-col">
      <div className="border-b bg-muted/40">
        <div className="mx-auto flex h-9 max-w-7xl items-center justify-end gap-4 px-4 sm:px-6 lg:px-8">
          <CurrencySwitcher />
        </div>
      </div>
      <StoreHeader />
      <main className="flex-1">{children}</main>
      <SiteFooter />
    </div>
  );
}
