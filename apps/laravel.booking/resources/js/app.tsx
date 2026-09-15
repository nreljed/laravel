import "../css/app.css";
import "leaflet/dist/leaflet.css";
import "./bootstrap";

import { createFoundationApp } from "@dashboard/app";
import { registerBookingFeatureIcons } from "@booking/icons";
import { registerPropertiesFeatureIcons } from "@properties/icons";
import { pageConfig } from "./page-config";

registerBookingFeatureIcons();
registerPropertiesFeatureIcons();

createFoundationApp(pageConfig);
