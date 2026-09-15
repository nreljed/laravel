import { ShoppingCart } from "lucide-react";
import { registerFeatureIcons, type FeatureIcon } from "@nreljed/platform/lib/icons";

/**
 * Icons contributed by the Shop feature to the shared feature-icon registry
 * (nav items declare icon names that are resolved from `featureIcons`).
 * Call {@link registerShopFeatureIcons} once at app bootstrap.
 */
export const shopFeatureIcons: Record<string, FeatureIcon> = {
    ShoppingCart,
};

export function registerShopFeatureIcons(): void {
    registerFeatureIcons(shopFeatureIcons);
}