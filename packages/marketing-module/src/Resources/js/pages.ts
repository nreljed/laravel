import type { ComponentType } from "react";

export type PageImport = () => Promise<{ default: ComponentType<unknown> }>;

export const marketingPagePrefix = "marketing";

/**
 * Convert a `Pages/**` component path to the Inertia page key the controllers
 * render: every segment is kebab-cased and lowercased.
 *
 * Controllers render dashed names - `Inertia::render('marketing/sync-health')`
 * and `Inertia::render('marketing/workflows/builder')` - so `SyncHealth.tsx`
 * must map to `marketing/sync-health`, not `marketing/synchealth`. Lowercasing
 * alone silently produced keys no controller ever asks for, which surfaced as
 * a runtime "Page not found: marketing/sync-health" error in the browser while
 * the build still succeeded.
 */
function toPageKey(componentPath: string): string {
    return componentPath
        .split("/")
        .map((segment) =>
            segment
                .replace(/([a-z0-9])([A-Z])/g, "$1-$2")
                .replace(/[_\s]+/g, "-")
                .toLowerCase(),
        )
        .join("/");
}

/**
 * The MARKETING feature page kit. Returns the marketing module's frontend
 * page map under the `marketing/` prefix - e.g. `marketing/dashboard` and
 * `marketing/sync-health`.
 *
 * Consuming apps spread the returned map into their pageConfig alongside
 * `registerCoreTemplates()`, matching the `register{Name}Pages()` convention
 * used by every feature module.
 */
export function registerMarketingPages(): Record<string, PageImport> {
    const pageMap: Record<string, PageImport> = {};
    const pages = import.meta.glob("./Pages/**/*.tsx") as Record<string, PageImport>;

    for (const [path, importFn] of Object.entries(pages)) {
        const componentPath = path.match(/\/Pages\/(.+)\.tsx$/)?.[1];

        if (componentPath) {
            pageMap[`${marketingPagePrefix}/${toPageKey(componentPath)}`] = importFn;
        }
    }

    return pageMap;
}
