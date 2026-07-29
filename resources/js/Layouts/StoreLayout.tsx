import { PropsWithChildren } from "react";
import { SiteHeader } from "@/Components/frontend/SiteHeader";
import { SiteFooter } from "@/Components/frontend/SiteFooter";

export default function StoreLayout({ children }: PropsWithChildren) {
  return (
    <div className="flex min-h-screen flex-col">
      <SiteHeader />
      <main className="flex-1">{children}</main>
      <SiteFooter />
    </div>
  );
}
