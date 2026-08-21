import "../css/app.css";

import createServer from "@inertiajs/react/server";
import { createSSRFoundationApp } from "@dashboard/app";
import { pageConfig } from "./page-config";

import "@/Components/ui/sidebar";

createServer((page) => createSSRFoundationApp(pageConfig, page) as any); // eslint-disable-line @typescript-eslint/no-explicit-any
