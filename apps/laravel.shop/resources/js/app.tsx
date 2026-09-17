import "../css/app.css";

import { createFoundationApp } from "@nreljed/platform/app";
import { registerStorefrontLayout } from "@nreljed/platform/lib/storefront-layout";
import { registerShopFeatureIcons } from "@nreljed/shop-module/icons";
import { pageConfig } from "./page-config";
import StoreLayout from "./Layouts/StoreLayout";

registerShopFeatureIcons();

registerStorefrontLayout(StoreLayout);

createFoundationApp(pageConfig);
