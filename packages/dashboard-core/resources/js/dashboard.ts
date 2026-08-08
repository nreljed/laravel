import type { ComponentType } from "react";

export type PageImport = () => Promise<{ default: ComponentType<unknown> }>;

const featurePrefixes: Record<string, string> = {
  Blog: "blog",
  PublicSite: "public",
};

/**
 * The opt-in CORE page kit. Returns the standard page map (dashboard, admin,
 * account, auth, error, blog + template public pages). Consuming apps decide
 * which core pages they use by spreading this into their own pageMap — core
 * never auto-registers pages.
 */
export function registerCoreTemplates(): Record<string, PageImport> {
  const pageMap: Record<string, PageImport> = {};

  const corePages = import.meta.glob("./Pages/**/*.tsx") as Record<string, PageImport>;

  for (const [path, importFn] of Object.entries(corePages)) {
    const componentPath = path.match(/\.\/Pages\/(.+)\.tsx$/)?.[1];

    if (componentPath) {
      pageMap[componentPath.toLowerCase()] = importFn;
    }
  }

  const featurePages = import.meta.glob("../../src/*/Resources/js/Pages/**/*.tsx") as Record<
    string,
    PageImport
  >;

  for (const [path, importFn] of Object.entries(featurePages)) {
    const match = path.match(/src\/([^/]+)\/Resources\/js\/Pages\/(.+)\.tsx$/);

    if (!match) {
      continue;
    }

    const featureDir = match[1] ?? "";
    const componentPath = match[2] ?? "";
    const prefix = featurePrefixes[featureDir] ?? featureDir.toLowerCase();

    if (componentPath !== "") {
      pageMap[`${prefix}/${componentPath.toLowerCase()}`] = importFn;
    }
  }

  return pageMap;
}
