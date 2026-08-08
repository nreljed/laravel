import "../css/app.css";

import { createInertiaApp } from "@inertiajs/react";
import createServer from "@inertiajs/react/server";
import { renderToString } from "react-dom/server";
import { ThemeProvider } from "next-themes";
import { TooltipProvider } from "@/Components/ui/tooltip";
import { Toaster } from "@/Components/ui/toast";
import { ErrorBoundary } from "@/Components/ErrorBoundary";
import { buildPageMap, type FoundationAppConfig, type PageImport } from "@dashboard/app";
import { registerCoreTemplates } from "@dashboard/dashboard";

import "@/Components/ui/sidebar";

const config: FoundationAppConfig = {
  basePages: registerCoreTemplates(),
  projectPages: import.meta.glob("@test-posts/Pages/**/*.tsx") as Record<string, PageImport>,
  projectPrefix: "testposts",
  pageOverrides: {
    "public/welcome": () => import("./Pages/Public/Welcome"),
    "public/about": () => import("./Pages/Public/About"),
    "public/services": () => import("./Pages/Public/Services"),
    "public/contact": () => import("./Pages/Public/Contact"),
  },
};

const pageMap = buildPageMap(config);

const appName = import.meta.env.VITE_APP_NAME || "Laravel";

type ZiggyGlobal = typeof globalThis & { Ziggy?: object };

function setZiggyConfig(configValue: object): void {
  Object.defineProperty(globalThis as ZiggyGlobal, "Ziggy", {
    value: configValue,
    configurable: true,
    writable: true,
  });
}

createServer((page) => {
  const ziggy = page.props?.ziggy;
  if (typeof ziggy === "object" && ziggy !== null) {
    setZiggyConfig(ziggy);
  }

  return createInertiaApp({
    page,
    render: renderToString,
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
    setup({ App, props }) {
      return (
        <ThemeProvider
          attribute="class"
          defaultTheme="system"
          enableSystem
          disableTransitionOnChange
        >
          <TooltipProvider>
            <ErrorBoundary key={props.initialPage.url}>
              <App {...props} />
            </ErrorBoundary>
            <Toaster />
          </TooltipProvider>
        </ThemeProvider>
      );
    },
  });
});
