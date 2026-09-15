import type { FoundationAppConfig, PageImport } from "@dashboard/app";
import { registerCoreTemplates } from "@dashboard/dashboard";
import { registerBlogPages } from "@blog/pages";
import { registerBookingPages } from "@booking/pages";
import { registerPropertiesPages } from "@properties/pages";

/**
 * Host-owned public pages under ./Pages/** (public/welcome, public/about,
 * public/services, public/contact) - the project forked the core starter
 * theme instead of spreading registerStarterThemePages().
 */
const localPages: Record<string, PageImport> = Object.fromEntries(
  Object.entries(import.meta.glob("./Pages/**/*.tsx") as Record<string, PageImport>)
    .map(([path, importFn]) => [
      path.match(/\/Pages\/(.+)\.tsx$/)?.[1]?.toLowerCase() ?? "",
      importFn,
    ])
    .filter(([key]) => key !== ""),
);

/**
 * Feature module page kits are explicit but modular: each module ships a
 * `register{Name}Pages()` export that is spread here exactly once. Core ships
 * the control-panel kit; Booking and Properties contribute their own prefixes
 * (bookings/*, properties/* + properties/public/*).
 */
export const pageConfig: FoundationAppConfig = {
  basePages: {
    ...registerCoreTemplates(),
    ...registerBlogPages(),
    ...registerBookingPages(),
    ...registerPropertiesPages(),
    ...localPages,
  },
};
