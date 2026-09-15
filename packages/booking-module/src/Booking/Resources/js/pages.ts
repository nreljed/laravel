import type { ComponentType } from "react";

export type PageImport = () => Promise<{ default: ComponentType<unknown> }>;

export const bookingsPagePrefix = "bookings";

/**
 * The BOOKINGS feature page kit. Returns the bookings module's frontend page
 * map under the `bookings/` prefix - e.g. `bookings/index`, `bookings/form`,
 * `bookings/calendar`, `bookings/settings/index`.
 *
 * Controllers render lowercase names (Index -> `bookings/index`), so these
 * keys must stay lowercase. Consuming apps spread the returned map into their
 * pageConfig alongside `registerCoreTemplates()`, matching the
 * `register{Name}Pages()` convention used by every feature module.
 */
export function registerBookingPages(): Record<string, PageImport> {
    const pageMap: Record<string, PageImport> = {};
    const pages = import.meta.glob("./Pages/**/*.tsx") as Record<string, PageImport>;

    for (const [path, importFn] of Object.entries(pages)) {
        const componentPath = path.match(/\/Pages\/(.+)\.tsx$/)?.[1];

        if (componentPath) {
            pageMap[`${bookingsPagePrefix}/${componentPath.toLowerCase()}`] = importFn;
        }
    }

    return pageMap;
}