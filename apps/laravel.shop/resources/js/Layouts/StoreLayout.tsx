import { PropsWithChildren } from "react";
import StorefrontLayout from "@nreljed/platform/Layouts/StorefrontLayout";
import { StoreHeader } from "@nreljed/shop-module/components/StoreHeader";
import CurrencySwitcher from "@nreljed/shop-module/components/CurrencySwitcher";

/**
 * Shop app's white-label storefront chrome, registered via
 * `registerStorefrontLayout()` in the client entry. A genuinely different feel
 * from the mono app's default site-header chrome — same shop-module pages,
 * app-owned layout.
 */
export default function StoreLayout({ children }: PropsWithChildren) {
  return (
    <StorefrontLayout
      header={
        <>
          <div className="border-b bg-muted/40">
            <div className="mx-auto flex h-9 max-w-7xl items-center justify-end gap-4 px-4 sm:px-6 lg:px-8">
              <CurrencySwitcher />
            </div>
          </div>
          <StoreHeader />
        </>
      }
    >
      {children}
    </StorefrontLayout>
  );
}
