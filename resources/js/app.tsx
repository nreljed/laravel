import "../css/app.css";
import "leaflet/dist/leaflet.css";
import "./bootstrap";

import { createInertiaApp, router } from "@inertiajs/react";
import { createRoot } from "react-dom/client";
import { ThemeProvider } from "next-themes";
import { TooltipProvider } from "@/Components/ui/tooltip";
import { Toaster } from "@/Components/ui/sonner";
import { toast } from "sonner";

import "@/Components/ui/sidebar";

import { useEffect } from "react";

function InertiaFlashListener() {
  useEffect(() => {
    const showFlash = (event?: any) => {
      const page = event?.detail?.page ?? (router as any).page;
      if (!page) return;
      const flash: Record<string, unknown> = page.props?.flash ?? {};
      if (flash.status) toast.success(String(flash.status));
      if (flash.error) toast.error(String(flash.error));
    };
    showFlash();
    return router.on("navigate", showFlash);
  }, []);
  return null;
}

const appName = import.meta.env.VITE_APP_NAME || "Laravel";

type ImportFn = () => Promise<{ default: React.ComponentType<unknown> }>;
type PageMap = Record<string, ImportFn | { default: React.ComponentType<unknown> }>;

const pages = import.meta.glob("./Pages/**/*.tsx") as Record<string, ImportFn>;

const propertyPages = import.meta.glob(
  "../../app/Modules/Properties/Resources/js/Pages/**/*.tsx",
) as Record<string, ImportFn>;

const bookingPages = import.meta.glob(
  "../../app/Modules/Booking/Resources/js/Pages/**/*.tsx",
) as Record<string, ImportFn>;

const shopPages = import.meta.glob("@modules-shop/Pages/**/*.tsx") as Record<
  string,
  ImportFn
>;

const postsPages = import.meta.glob("@modules-posts/Pages/**/*.tsx") as Record<string, ImportFn>;

const pageMap: PageMap = {};

Object.keys(pages).forEach((path) => {
  const match = path.match(/\.\/Pages\/(.+)\.tsx$/);
  const componentPath = match?.[1];
  if (componentPath) {
    pageMap[componentPath.toLowerCase()] = pages[path]!;
  }
});

function extractComponentPath(fullPath: string, moduleName: string): string | null {
  const normalized = fullPath.replaceAll("\\", "/");

  const fsMarker = `app/Modules/${moduleName}/Resources/js/Pages/`;
  const fsIdx = normalized.indexOf(fsMarker);
  if (fsIdx !== -1) {
    const relative = normalized.slice(fsIdx + fsMarker.length);
    return relative.replace(/\.tsx$/, "");
  }

  const aliasPrefixMap: Record<string, string> = {
    Properties: "@modules-properties",
    Booking: "@modules-booking",
    Shop: "@modules-shop",
    Posts: "@modules-posts",
  };
  const alias = aliasPrefixMap[moduleName];
  if (alias) {
    const aliasMarker = `${alias}/Pages/`;
    const aliasIdx = normalized.indexOf(aliasMarker);
    if (aliasIdx !== -1) {
      const relative = normalized.slice(aliasIdx + aliasMarker.length);
      return relative.replace(/\.tsx$/, "");
    }
  }

  return null;
}

Object.keys(propertyPages).forEach((path) => {
  const componentPath = extractComponentPath(path, "Properties");
  if (componentPath) {
    pageMap[`Modules/Properties/${componentPath}`.toLowerCase()] = propertyPages[path]!;
  }
});

Object.keys(bookingPages).forEach((path) => {
  const componentPath = extractComponentPath(path, "Booking");
  if (componentPath) {
    pageMap[`Modules/Bookings/${componentPath}`.toLowerCase()] = bookingPages[path]!;
  }
});

Object.keys(shopPages).forEach((path) => {
  const componentPath = extractComponentPath(path, "Shop");
  if (componentPath) {
    pageMap[`Modules/Shop/${componentPath}`.toLowerCase()] = shopPages[path]!;
  }
});

Object.keys(postsPages).forEach((path) => {
  const componentPath = extractComponentPath(path, "Posts");
  if (componentPath) {
    pageMap[`Modules/Posts/${componentPath}`.toLowerCase()] = postsPages[path]!;
  }
});

createInertiaApp({
  title: (title) => `${title} - ${appName}`,
  resolve: (name) => {
    const importPage = pageMap[name.toLowerCase()];
    if (!importPage) {
      throw new Error(`Page not found: ${name}`);
    }
    const result = typeof importPage === "function" ? importPage() : importPage;
    return Promise.resolve(result).then((module) => module.default || module);
  },
  setup({ el, App, props }) {
    const root = createRoot(el);

    root.render(
      <ThemeProvider attribute="class" defaultTheme="system" enableSystem disableTransitionOnChange>
        <TooltipProvider>
          <App {...props} />
          <InertiaFlashListener />
          <Toaster position="top-right" />
        </TooltipProvider>
      </ThemeProvider>,
    );
  },
  progress: {
    color: "#4B5563",
  },
});
