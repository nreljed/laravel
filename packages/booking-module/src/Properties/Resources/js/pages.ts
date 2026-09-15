import type { ComponentType } from "react";

export type PageImport = () => Promise<{ default: ComponentType<unknown> }>;

export const propertiesPagePrefix = "properties";

/**
 * The PROPERTIES feature page kit. Returns the properties module's frontend
 * page map under the `properties/` prefix - e.g. `properties/index`,
 * `properties/form`, `properties/types`, and the guest storefront pages
 * `properties/public/index`, `properties/public/show`.
 *
 * Controllers render lowercase names (Properties/Index -> `properties/index`),
 * so these keys must stay lowercase. Consuming apps spread the returned map
 * into their pageConfig alongside `registerCoreTemplates()`, matching the
 * `register{Name}Pages()` convention used by every feature module.
 */
export function registerPropertiesPages(): Record<string, PageImport> {
    const pageMap: Record<string, PageImport> = {};
    const pages = import.meta.glob("./Pages/**/*.tsx") as Record<string, PageImport>;

    for (const [path, importFn] of Object.entries(pages)) {
        const componentPath = path.match(/\/Pages\/(.+)\.tsx$/)?.[1];

        if (componentPath) {
            pageMap[`${propertiesPagePrefix}/${componentPath.toLowerCase()}`] = importFn;
        }
    }

    return pageMap;
}