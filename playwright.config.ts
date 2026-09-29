import { defineConfig, devices } from "@playwright/test";

/**
 * Workspace e2e harness — one runner for every app in this workspace.
 * Point it at any served app with APP_URL (e.g. http://shop-laravel.local,
 * http://laravel.local, the booking site) and run `pnpm test:e2e`.
 *
 * Login credentials come from E2E_EMAIL / E2E_PASSWORD and default to the
 * shared demo admin (nstudio.webdesign@gmail.com / azerty123/*).
 */
export default defineConfig({
    testDir: "./e2e",
    timeout: 60_000,
    expect: { timeout: 10_000 },
    fullyParallel: false,
    workers: 1,
    retries: process.env.CI ? 1 : 0,
    reporter: [["list"]],
    use: {
        baseURL: process.env.APP_URL ?? "http://127.0.0.1:8000",
        trace: "on-first-retry",
        screenshot: "only-on-failure",
        viewport: { width: 1600, height: 1000 },
    },
    projects: [{ name: "chromium", use: { ...devices["Desktop Chrome"] } }],
});