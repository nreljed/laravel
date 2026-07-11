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
    const showFlash = (event?: {
      detail?: { page?: { props?: { flash?: Record<string, unknown> } } };
    }) => {
      const page =
        event?.detail?.page ??
        ((router as { page?: unknown }).page as
          { props?: { flash?: Record<string, unknown> } } | undefined);
      if (!page) return;
      const flash: Record<string, unknown> = page.props?.flash ?? {};
      if (flash.status) toast.success(String(flash.status));
      if (flash.error) toast.error(String(flash.error));
    };
    showFlash();
    return router.on("navigate", () => showFlash());
  }, []);
  return null;
}

const appName = import.meta.env.VITE_APP_NAME || "Laravel";

type ImportFn = () => Promise<{ default: React.ComponentType<unknown> }>;
type PageMap = Record<string, ImportFn | { default: React.ComponentType<unknown> }>;

const pages = import.meta.glob("./Pages/**/*.tsx") as Record<string, ImportFn>;

const postsPages = import.meta.glob("@blog/Pages/**/*.tsx") as Record<string, ImportFn>;

const pageMap: PageMap = {};

for (const [path, importFn] of Object.entries(pages)) {
  const match = path.match(/\.\/Pages\/(.+)\.tsx$/);
  const componentPath = match?.[1];
  if (componentPath) {
    pageMap[componentPath.toLowerCase()] = importFn;
  }
}

function extractComponentPath(fullPath: string, moduleName: string): string | null {
  const normalized = fullPath.replaceAll("\\", "/");

  const fsMarker = `app/Blog/Resources/js/Pages/`;
  const fsIdx = normalized.indexOf(fsMarker);
  if (fsIdx !== -1) {
    const relative = normalized.slice(fsIdx + fsMarker.length);
    return relative.replace(/\.tsx$/, "");
  }

  const aliasPrefixMap: Record<string, string> = {
    Blog: "@blog",
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

for (const [path, importFn] of Object.entries(postsPages)) {
  const componentPath = extractComponentPath(path, "Blog");
  if (componentPath) {
    pageMap[`Blog/${componentPath}`.toLowerCase()] = importFn;
  }
}

createInertiaApp({
  title: (title) => `${title} - ${appName}`,
  resolve: async (name) => {
    const importPage = pageMap[name.toLowerCase()];
    if (!importPage) {
      throw new Error(`Page not found: ${name}`);
    }
    const result = typeof importPage === "function" ? importPage() : importPage;
    const module = await result;
    return module.default || module;
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
