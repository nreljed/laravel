import type { ComponentType } from "react";

export type PageImport = () => Promise<{ default: ComponentType<unknown> }>;

export const marketingPagePrefix = "marketing";

/**
 * The MARKETING feature page kit. Returns the marketing module's frontend
 * page map under the `marketing/` prefix - e.g. `marketing/dashboard`.
 *
 * Controllers render lowercase names (Marketing/Dashboard → `marketing/dashboard`),
 * so these keys must stay lowercase. Consuming apps spread the returned map
 * into their pageConfig alongside `registerCoreTemplates()`, matching the
 * `register{Name}Pages()` convention used by every feature module.
 */
export function registerMarketingPages(): Record<string, PageImport> {
    const pageMap: Record<string, PageImport> = {};
    const pages = import.meta.glob("./Pages/**/*.tsx") as Record<string, PageImport>;

    for (const [path, importFn] of Object.entries(pages)) {
        const componentPath = path.match(/\/Pages\/(.+)\.tsx$/)?.[1];

        if (componentPath) {
            pageMap[`${marketingPagePrefix}/${componentPath.toLowerCase()}`] = importFn;
        }
    }

    return pageMap;
}
