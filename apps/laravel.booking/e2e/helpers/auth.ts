import type { Page } from "@playwright/test";

export const ADMIN_EMAIL = process.env.E2E_EMAIL ?? "nstudio.webdesign@gmail.com";
export const ADMIN_PASSWORD = process.env.E2E_PASSWORD ?? "azerty123/*";

/**
 * Logs the demo admin into any workspace app. The platform auth form uses
 * name-less inputs, so fields are matched by type and position; the submit
 * button carries the Inertia redirect, awaited via waitForURL.
 *
 * The budget is 45s, not 20s: `waitForURL` only fires once Inertia has the
 * redirect *and* the fully rendered dashboard, and the fleet's runners all
 * share one laptop, so concurrent e2e jobs can stretch that past 20s. The
 * test timeout is 60s, so this still fails fast on a real auth break.
 */
export async function login(page: Page): Promise<void> {
  await page.goto("/login", { waitUntil: "networkidle" });
  await page.locator('input[type="text"]').first().fill(ADMIN_EMAIL);
  await page.locator('input[type="password"]').first().fill(ADMIN_PASSWORD);
  await Promise.all([
    page.waitForURL(/\/dashboard/, { timeout: 45_000 }),
    page.locator('button[type="submit"]').first().click(),
  ]);
}

/**
Attaches console/pageerror listeners and returns the collected messages.
*/
export function collectConsoleErrors(page: Page, bucket: string[]): void {
  page.on("pageerror", (e) => {
    bucket.push(`pageerror: ${e}`);
  });
  page.on("console", (m) => {
    if (m.type() === "error") {
      bucket.push(m.text());
    }
  });
}
