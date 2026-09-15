import "../css/app.css";

import { createFoundationApp } from "@dashboard/app";
import { registerShopFeatureIcons } from "@shop/icons";
import { pageConfig } from "./page-config";

registerShopFeatureIcons();

createFoundationApp(pageConfig);
