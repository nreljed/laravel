import type { ComponentType } from "react";

export type PageImport = () => Promise<{ default: ComponentType<unknown> }>;

/**
 * The opt-in "starter" theme page kit.
 *
 * A hand-off, fork-me template theme so consuming apps don't start from a blank
 * page map. It ships the template/marketing pages (public marketing site + blog
 * public templates). The PLATFORM control-panel pages live in
 * `registerCoreTemplates()` — themes never register platform pages and the
 * platform never auto-registers template pages.
 *
 * Ways to use it:
 *  1. Spread into `basePages` as-is:  { ...registerStarterThemePages() }
 *  2. Fork it: copy the `Pages/` tree into the project, import your own files,
 *     and skip this function entirely (the normal project path).
 *  3. Override: keep it in `basePages` and wrap a `pageOverrides` entry around
 *     any page you customize (applied last, wins).
 *
 * Page keys are lowercased Inertia names, e.g. `blog/public/index` →
 * `Blog/Public/Index.tsx`.
 */
export function registerStarterThemePages(): Record<string, PageImport> {
    const pageMap: Record<string, PageImport> = {};

    const themePages = import.meta.glob("./Pages/**/*.tsx") as Record<string, PageImport>;

    for (const [path, importFn] of Object.entries(themePages)) {
        const componentPath = path.match(/\/Pages\/(.+)\.tsx$/)?.[1];

        if (componentPath) {
            pageMap[componentPath.toLowerCase()] = importFn;
        }
    }

    return pageMap;
}