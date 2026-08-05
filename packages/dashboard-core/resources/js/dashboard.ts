import type { ComponentType } from "react";

export type PageImport = () => Promise<{ default: ComponentType<unknown> }>;

const featurePrefixes: Record<string, string> = {
  Blog: "blog",
  PublicSite: "public",
};

export function registerDashboard(): Record<string, PageImport> {
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
