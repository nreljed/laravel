/**
 * Side effects of switching a product's type.
 *
 * This existed inline in the product form, in two places: the direct branch of
 * `maybeChangeType` and the confirm-dialog branch that opens when the switch
 * would orphan variations or grouped items. The two copies drifted, so switching
 * to `digital` or `service` applied its side effects only when no dialog
 * appeared - the same user action changed different fields depending on whether
 * the product happened to have variations.
 *
 * Returning the patch instead of performing it keeps one source of truth for
 * "what does becoming this type imply" and makes it testable without rendering
 * the form.
 */

export interface TypeChangeEffects {
    /** Form fields the switch writes, excluding the type itself. */
    data: Partial<Record<string, unknown>>;
    /** Whether the product's attributes become variation attributes. */
    attributesAreVariations: boolean;
}

export function typeChangeEffects(newType: string): TypeChangeEffects {
    const data: Partial<Record<string, unknown>> = {};

    // An external product is bought elsewhere and a grouped one is a container,
    // so neither carries its own stock.
    if (newType === "external" || newType === "grouped") {
        data.track_stock = false;
    }

    if (newType === "external") {
        data.virtual = true;
    }

    // `digital` and `service` differ only in whether a download can be attached.
    // Both are non-shipping.
    if (newType === "digital" || newType === "service") {
        data.virtual = true;
    }

    if (newType === "digital") {
        data.downloadable = true;
    }

    if (newType === "service") {
        data.downloadable = false;
    }

    return {
        data,
        // Only a variable product turns its attributes into variation attributes.
        attributesAreVariations: newType === "variable",
    };
}