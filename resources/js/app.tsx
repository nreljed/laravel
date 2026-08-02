import "../css/app.css";

import { createInertiaApp, router } from "@inertiajs/react";
import { createRoot } from "react-dom/client";
import { ThemeProvider } from "next-themes";
import { TooltipProvider } from "@/Components/ui/tooltip";
import { Toaster, toast } from "@/Components/ui/toast";
import { registerDashboard } from "@dashboard/dashboard";

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
      if (flash.status) toast.add({ title: String(flash.status), type: "success" });
      if (flash.error) toast.add({ title: String(flash.error), type: "error" });
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

const testPostsPages = import.meta.glob("@test-posts/Pages/**/*.tsx") as Record<string, ImportFn>;

const pageMap: PageMap = {
  ...registerDashboard(),
};

for (const [path, importFn] of Object.entries(pages)) {
  const match = path.match(/\.\/Pages\/(.+)\.tsx$/);
  const componentPath = match?.[1];
  if (componentPath) {
    pageMap[componentPath.toLowerCase()] = importFn;
  }
}

function extractComponentPath(fullPath: string, moduleName: string): string | null {
  const normalized = fullPath.replaceAll("\\", "/");

  const fsMarkers: Record<string, string> = {
    TestPosts: "app/TestPosts/Resources/js/Pages/",
  };
  const fsMarker = fsMarkers[moduleName];
  if (fsMarker) {
    const fsIdx = normalized.indexOf(fsMarker);
    if (fsIdx !== -1) {
      const relative = normalized.slice(fsIdx + fsMarker.length);
      return relative.replace(/\.tsx$/, "");
    }
  }

  const aliasPrefixMap: Record<string, string> = {
    TestPosts: "@test-posts",
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

for (const [path, importFn] of Object.entries(testPostsPages)) {
  const componentPath = extractComponentPath(path, "TestPosts");
  if (componentPath) {
    pageMap[`TestPosts/${componentPath}`.toLowerCase()] = importFn;
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
          <Toaster />
        </TooltipProvider>
      </ThemeProvider>,
    );
  },
  progress: {
    color: "#4B5563",
  },
});
