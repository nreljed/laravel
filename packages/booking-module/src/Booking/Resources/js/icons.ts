import { Calendar } from "lucide-react";
import { registerFeatureIcons, type FeatureIcon } from "@nreljed/platform/lib/icons";

/**
 * Icons contributed by the Bookings feature to the shared feature-icon
 * registry (nav items declare icon names that are resolved from
 * `featureIcons`). Call {@link registerBookingFeatureIcons} once at app
 * bootstrap.
 */
export const bookingFeatureIcons: Record<string, FeatureIcon> = {
    Calendar,
};

export function registerBookingFeatureIcons(): void {
    registerFeatureIcons(bookingFeatureIcons);
}