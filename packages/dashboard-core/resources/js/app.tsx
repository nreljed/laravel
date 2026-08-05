import { createInertiaApp, router } from "@inertiajs/react";
import { createRoot } from "react-dom/client";
import { ThemeProvider } from "next-themes";
import { TooltipProvider } from "@/Components/ui/tooltip";
import { Toaster, toast } from "@/Components/ui/toast";
import { registerDashboard, type PageImport } from "./dashboard";

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

export type FoundationAppConfig = {
  projectPages?: Record<string, PageImport>;
  projectPrefix?: string;
};

export type { PageImport } from "./dashboard";

export function createFoundationApp(config: FoundationAppConfig = {}): void {
  const pageMap: Record<string, PageImport> = {
    ...registerDashboard(),
  };

  if (config.projectPages) {
    const prefix = (config.projectPrefix ?? "project").toLowerCase();

    for (const [path, importFn] of Object.entries(config.projectPages)) {
      const componentPath = path.match(/\/Pages\/(.+)\.tsx$/)?.[1];

      if (componentPath) {
        pageMap[`${prefix}/${componentPath.toLowerCase()}`] = importFn;
      }
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
        <ThemeProvider
          attribute="class"
          defaultTheme="system"
          enableSystem
          disableTransitionOnChange
        >
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
}
