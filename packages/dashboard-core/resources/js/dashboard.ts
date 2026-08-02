import type { ComponentType } from "react";

export type PageImport = () => Promise<{ default: ComponentType<unknown> }>;

export function registerDashboard(): Record<string, PageImport> {
  const pageMap: Record<string, PageImport> = {};

  const corePages = import.meta.glob("./Pages/**/*.tsx") as Record<string, PageImport>;

  for (const [path, importFn] of Object.entries(corePages)) {
    const componentPath = path.match(/\.\/Pages\/(.+)\.tsx$/)?.[1];

    if (componentPath) {
      pageMap[componentPath.toLowerCase()] = importFn;
    }
  }

  const blogPages = import.meta.glob("../../src/Blog/Resources/js/Pages/**/*.tsx") as Record<
    string,
    PageImport
  >;

  for (const [path, importFn] of Object.entries(blogPages)) {
    const componentPath = path.match(/Resources\/js\/Pages\/(.+)\.tsx$/)?.[1];

    if (componentPath) {
      pageMap[`blog/${componentPath.toLowerCase()}`] = importFn;
    }
  }

  return pageMap;
}
