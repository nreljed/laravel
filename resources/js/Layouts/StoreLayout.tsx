import { PropsWithChildren } from "react";
import StorefrontLayout from "@nreljed/platform/Layouts/StorefrontLayout";

/**
 * Cost Breaker's app-level storefront chrome, wired into the seam via
 * `registerStorefrontLayout()` in the client entry. Designers edit this ONE
 * file (e.g. swap the header) to re-theme the storefront; the shop-module
 * pages stay untouched.
 */
export default function StoreLayout({ children }: PropsWithChildren) {
  return <StorefrontLayout>{children}</StorefrontLayout>;
}
