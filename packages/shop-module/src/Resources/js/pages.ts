import type { ComponentType } from "react";

export type PageImport = () => Promise<{ default: ComponentType<unknown> }>;

export const shopPagePrefix = "shop";

/**
 * The SHOP feature page kit. Returns the shop module's frontend page map
 * under the `shop/` prefix — e.g. `shop/products/index`, `shop/orders/checkout`,
 * `shop/storefront/home`, `shop/dashboard`.
 *
 * Controllers render lowercase names (Products/Index → `shop/products/index`),
 * so these keys must stay lowercase. Consuming apps spread the returned map
 * into their pageConfig alongside `registerCoreTemplates()`, matching the
 * `register{Name}Pages()` convention used by every feature module.
 */
export function registerShopPages(): Record<string, PageImport> {
    const pageMap: Record<string, PageImport> = {};
    const pages = import.meta.glob("./Pages/**/*.tsx") as Record<string, PageImport>;

    for (const [path, importFn] of Object.entries(pages)) {
        const componentPath = path.match(/\/Pages\/(.+)\.tsx$/)?.[1];

        if (componentPath) {
            pageMap[`${shopPagePrefix}/${componentPath.toLowerCase()}`] = importFn;
        }
    }

    return pageMap;
}