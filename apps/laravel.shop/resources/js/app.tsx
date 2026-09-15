import "../css/app.css";

import { createFoundationApp } from "@nreljed/platform/app";
import { registerShopFeatureIcons } from "@nreljed/shop-module/icons";
import { pageConfig } from "./page-config";

registerShopFeatureIcons();

createFoundationApp(pageConfig);
