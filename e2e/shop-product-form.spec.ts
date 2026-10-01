import { test, expect } from "@playwright/test";
import { login } from "./helpers/auth";

/**
 * Product form write-path regressions.
 *
 *   $env:APP_URL = "http://shop-laravel.local"; pnpm test:e2e
 *
 * Requires seeded demo data (php artisan demo:generate) - the type-switch case
 * needs a variable product that actually has variations, because that is the
 * condition that used to open the confirm dialog and skip the type's side
 * effects.
 */

/** Finds the variable demo product's edit screen. */
async function openVariableProduct(page: import("@playwright/test").Page): Promise<void> {
    await login(page);
    await page.goto("/dashboard/shop/products", { waitUntil: "networkidle" });

    const row = page.locator("table tbody tr").filter({ hasText: "Variable" }).first();
    await expect(row).toBeVisible();
    await row.locator("a").first().click();
    await page.waitForURL(/\/dashboard\/shop\/products\/\d+/, { timeout: 20_000 });
    await page.waitForLoadState("networkidle");
}

test("switching a variable product to digital applies virtual and downloadable through the confirm dialog", async ({ page }) => {
    await openVariableProduct(page);

    // Baseline: a variable product is neither virtual nor downloadable.
    await expect(page.getByRole("switch", { name: /virtual/i })).toHaveAttribute("aria-checked", "false");
    await expect(page.getByRole("switch", { name: /downloadable/i })).toHaveAttribute("aria-checked", "false");

    // This switch opens the confirm dialog because the product has variations.
    // The dialog branch used to apply only the `type` field, so both switches
    // stayed off no matter how the user got here.
    await page.getByRole("button", { name: "Digital", exact: true }).click();

    const dialog = page.getByRole("alertdialog");
    await expect(dialog).toBeVisible();
    await expect(dialog).toContainText("variation");
    await dialog.getByRole("button", { name: /switch/i }).click();

    await expect(dialog).toBeHidden();

    await expect(page.getByRole("switch", { name: /virtual/i })).toHaveAttribute("aria-checked", "true");
    await expect(page.getByRole("switch", { name: /downloadable/i })).toHaveAttribute("aria-checked", "true");
});

test("switching a variable product to service keeps it virtual but not downloadable", async ({ page }) => {
    await openVariableProduct(page);

    await page.getByRole("button", { name: "Service", exact: true }).click();

    const dialog = page.getByRole("alertdialog");
    await expect(dialog).toBeVisible();
    await dialog.getByRole("button", { name: /switch/i }).click();
    await expect(dialog).toBeHidden();

    await expect(page.getByRole("switch", { name: /virtual/i })).toHaveAttribute("aria-checked", "true");
    await expect(page.getByRole("switch", { name: /downloadable/i })).toHaveAttribute("aria-checked", "false");
});

test("cancelling the type switch leaves the product untouched", async ({ page }) => {
    await openVariableProduct(page);

    await page.getByRole("button", { name: "Digital", exact: true }).click();
    const dialog = page.getByRole("alertdialog");
    await expect(dialog).toBeVisible();
    await dialog.getByRole("button", { name: "Cancel" }).click();
    await expect(dialog).toBeHidden();

    // Cancel must not apply any of the digital side effects.
    await expect(page.getByRole("switch", { name: /virtual/i })).toHaveAttribute("aria-checked", "false");
    await expect(page.getByRole("switch", { name: /downloadable/i })).toHaveAttribute("aria-checked", "false");
});

test("the dead Save All control is gone from the variations toolbar", async ({ page }) => {
    await openVariableProduct(page);

    // `dirty` was never populated anywhere in VariationsManager, so the
    // "Save All (N)" button and the "N unsaved" badge were unreachable. Cell
    // edits persist immediately through variations.update instead.
    await expect(page.locator("body")).not.toContainText("Save All");
    await expect(page.locator("body")).not.toContainText("unsaved");

    // The live bulk-edit path must survive.
    await expect(page.getByRole("button", { name: /Bulk Edit/i })).toBeVisible();
});

test("a product whose images have no storage path still opens", async ({ page }) => {
    await openVariableProduct(page);

    // Every seeded image is a remote url with `path: null`. The gallery
    // adapter used to split that path unconditionally, which threw inside a
    // render map and replaced the whole edit page with the Inertia error
    // boundary - so every product became unopenable.
    await expect(page.locator("body")).not.toContainText("Something went wrong");
    await expect(page.getByText("Product Gallery")).toBeVisible();
    await expect(page.getByRole("button", { name: "Digital", exact: true })).toBeVisible();
});