import { Building2 } from "lucide-react";
import { registerFeatureIcons, type FeatureIcon } from "@nreljed/platform/lib/icons";

/**
 * Icons contributed by the Properties feature to the shared feature-icon
 * registry (nav items declare icon names that are resolved from
 * `featureIcons`). Call {@link registerPropertiesFeatureIcons} once at app
 * bootstrap.
 */
export const propertiesFeatureIcons: Record<string, FeatureIcon> = {
    Building2,
};

export function registerPropertiesFeatureIcons(): void {
    registerFeatureIcons(propertiesFeatureIcons);
}