import { Megaphone } from "lucide-react";
import { registerFeatureIcons, type FeatureIcon } from "@nreljed/platform/lib/icons";

/**
 * Icons contributed by the Marketing feature to the shared feature-icon
 * registry (nav items declare icon names that are resolved from
 * `featureIcons`). Call {@link registerMarketingFeatureIcons} once at app
 * bootstrap.
 */
export const marketingFeatureIcons: Record<string, FeatureIcon> = {
    Megaphone,
};

export function registerMarketingFeatureIcons(): void {
    registerFeatureIcons(marketingFeatureIcons);
}
