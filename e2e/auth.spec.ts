import { test, expect } from "@playwright/test";
import { collectConsoleErrors, login } from "./helpers/auth";

test("unauthenticated dashboard redirects to login", async ({ page }) => {
    await page.goto("/dashboard", { waitUntil: "networkidle" });
    await expect(page).toHaveURL(/\/login/);
});

test("admin login lands on the dashboard with no page errors", async ({ page }) => {
    const errors: string[] = [];
    collectConsoleErrors(page, errors);

    await login(page);

    await expect(page).toHaveURL(/\/dashboard/);
    expect(errors).toEqual([]);
});