import "../css/app.css";

import { createFoundationApp } from "@nreljed/platform/app";
import { registerStorefrontLayout } from "@nreljed/platform/lib/storefront-layout";
import { registerMarketingFeatureIcons } from "@nreljed/marketing-module/icons";
import { pageConfig } from "./page-config";
import StoreLayout from "./frontend/layouts/StoreLayout";

registerStorefrontLayout(StoreLayout);
registerMarketingFeatureIcons();

createFoundationApp(pageConfig);
