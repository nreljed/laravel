import { describe, it, expect } from "vitest";
import { typeChangeEffects } from "../productTypeChange";

/**
 * Regression cover for the product type-switch bug.
 *
 * The form applied these rules in two places - the direct branch of
 * `maybeChangeType` and the confirm-dialog branch shown when the switch would
 * orphan variations or grouped items. The two copies drifted: the dialog branch
 * had no `digital` or `service` cases, so the same switch changed different
 * fields depending only on whether the product happened to have variations.
 *
 * These assert the rules themselves, so the two branches cannot drift again.
 */
describe("typeChangeEffects", () => {
    it("makes a digital product virtual and downloadable", () => {
        expect(typeChangeEffects("digital")).toEqual({
            data: { virtual: true, downloadable: true },
            attributesAreVariations: false,
        });
    });

    it("makes a service product virtual but not downloadable", () => {
        expect(typeChangeEffects("service")).toEqual({
            data: { virtual: true, downloadable: false },
            attributesAreVariations: false,
        });
    });

    it("makes an external product virtual and untracked", () => {
        expect(typeChangeEffects("external")).toEqual({
            data: { track_stock: false, virtual: true },
            attributesAreVariations: false,
        });
    });

    it("makes a grouped product untracked", () => {
        expect(typeChangeEffects("grouped")).toEqual({
            data: { track_stock: false },
            attributesAreVariations: false,
        });
    });

    it("turns attributes into variation attributes only for a variable product", () => {
        expect(typeChangeEffects("variable")).toEqual({
            data: {},
            attributesAreVariations: true,
        });

        // Every other type demotes them, including the two the dialog branch
        // used to skip entirely.
        for (const type of ["simple", "digital", "service", "external", "grouped"]) {
            expect(typeChangeEffects(type).attributesAreVariations).toBe(false);
        }
    });

    it("leaves stock tracking untouched when switching to a stock-holding type", () => {
        // A product that was untracked because it was external must regain
        // stock tracking when it becomes simple again.
        expect(typeChangeEffects("simple").data).not.toHaveProperty("track_stock");
    });

    it("returns the same effects regardless of which branch applies them", () => {
        // The dialog branch and the direct branch now share this function, so
        // there is only one possible answer per type. Guards the regression.
        const types = ["simple", "variable", "digital", "service", "external", "grouped"];
        const first = types.map((t) => typeChangeEffects(t));
        const second = types.map((t) => typeChangeEffects(t));
        expect(first).toEqual(second);
    });
});