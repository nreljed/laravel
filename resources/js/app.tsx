import "../css/app.css";

import { createFoundationApp, type FoundationAppConfig, type PageImport } from "@dashboard/app";
import { registerCoreTemplates } from "@dashboard/dashboard";

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

createFoundationApp(config);
