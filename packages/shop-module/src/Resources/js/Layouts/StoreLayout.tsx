import { PropsWithChildren } from "react";
import { SiteFooter } from "@nreljed/platform/Components/frontend/SiteFooter";
import { StoreHeader } from "../components/StoreHeader";
import CurrencySwitcher from "../components/CurrencySwitcher";

export default function StoreLayout({ children }: PropsWithChildren) {
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
