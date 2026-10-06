import type { FoundationAppConfig, PageImport } from "@nreljed/platform/app";
import { registerCoreTemplates } from "@nreljed/platform/dashboard";
import { registerBookingPages } from "@nreljed/booking-module/booking/pages";
import { registerPropertiesPages } from "@nreljed/booking-module/properties/pages";

/**
Host-owned public pages under ./Pages/** (public/welcome, public/about,
public/services, public/contact) - the project forked the core starter
theme instead of spreading registerStarterThemePages().
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
Feature module page kits are explicit but modular: each module ships a
`register{Name}Pages()` export that is spread here exactly once. Core ships
the control-panel kit; Booking and Properties contribute their own prefixes
(bookings/*, properties/* + properties/public/*).
*/
export const pageConfig: FoundationAppConfig = {
  basePages: {
    ...registerCoreTemplates(),
    ...registerBookingPages(),
    ...registerPropertiesPages(),
    ...localPages,
  },
};
