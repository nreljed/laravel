import "../css/app.css";
import "leaflet/dist/leaflet.css";
import "./bootstrap";

import { createFoundationApp } from "@nreljed/platform/app";
import { registerBookingFeatureIcons } from "@nreljed/booking-module/booking/icons";
import { registerPropertiesFeatureIcons } from "@nreljed/booking-module/properties/icons";
import { pageConfig } from "./page-config";

registerBookingFeatureIcons();
registerPropertiesFeatureIcons();

createFoundationApp(pageConfig);
