import "../css/app.css";

import { createFoundationApp } from "@nreljed/platform/app";
import { registerStorefrontLayout } from "@nreljed/platform/lib/storefront-layout";
import { pageConfig } from "./page-config";
import StoreLayout from "./frontend/layouts/StoreLayout";

registerStorefrontLayout(StoreLayout);

createFoundationApp(pageConfig);
