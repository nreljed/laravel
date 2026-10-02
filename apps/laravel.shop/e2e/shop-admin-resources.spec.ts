import { test, expect } from "@playwright/test";
import { login } from "./helpers/auth";

/**
 * Shop admin render smoke — run against the served shop app:
 *
 *   $env:APP_URL = "http://shop-laravel.local"; npm run test:e2e
 *
 * Verifies the kernel generic resource pages (dashboard/admin/resources/*)
 * render the shop-module descriptors shipped on the 0.1.73 kernel path:
 * customers + orders indices and the coupon restrictions product combobox.
 * Requires seeded demo data (php artisan demo:generate). Not part of CI —
 * CI runs only e2e/auth.spec.ts.
 */
test("customers resource renders descriptor columns and a row action", async ({ page }) => {
  await login(page);
  await page.goto("/dashboard/shop/customers", { waitUntil: "networkidle" });

  const headers = await page.locator("table th").allInnerTexts();
  for (const col of ["Name", "Email", "Orders", "Total Spent", "Joined"]) {
    expect(headers).toContain(col);
  }

  const labels = await page.locator("main button").allInnerTexts();
  const buttons = labels.map((b) => b.trim());
  expect(buttons).toContain("View");
});

test("orders resource renders descriptor columns and the status filter", async ({ page }) => {
  await login(page);
  await page.goto("/dashboard/shop/orders", { waitUntil: "networkidle" });

  const headers = await page.locator("table th").allInnerTexts();
  for (const col of ["Order", "Customer", "Email", "Status", "Total", "Currency", "Payment"]) {
    expect(headers).toContain(col);
  }

  const labels = await page.locator("main button").allInnerTexts();
  const buttons = labels.map((b) => b.trim());
  expect(buttons).toContain("Status");
  expect(buttons).toContain("View");
});

test("coupons create lazy-loads product options into the restrictions field", async ({ page }) => {
  await login(page);
  await page.goto("/dashboard/shop/coupons/create", { waitUntil: "networkidle" });

  await expect(page.locator("body")).toContainText("Restrictions");
  await page.locator('main input[role="combobox"]').first().click();
  await page.waitForTimeout(1200);

  const options = await page.locator("[role='option']").allInnerTexts();
  expect(options.some((o) => o.includes("Redmi"))).toBe(true);
});
