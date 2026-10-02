import { defineConfig, devices } from "@playwright/test";

/**
 * e2e harness for the shop app — login smoke against any served instance.
 * Point it at a local or CI-served app with APP_URL (defaults to the CI-served
 * origin) and run `npm run test:e2e`.
 *
 * Login credentials come from E2E_EMAIL / E2E_PASSWORD and default to the
 * shared demo admin (nstudio.webdesign@gmail.com / azerty123/*).
 *
 * Outputs land under storage/debug/playwright-output (gitignored) — same
 * convention as the Playwright MCP config. CI runs only e2e/auth.spec.ts;
 * the repo-specific specs (e2e/shop-admin-resources.spec.ts) need seeded
 * demo data and run locally.
 */
export default defineConfig({
    testDir: "./e2e",
    timeout: 60_000,
    expect: { timeout: 10_000 },
    fullyParallel: false,
    workers: 1,
    retries: process.env.CI ? 1 : 0,
    reporter: [["list"]],
    outputDir: "storage/debug/playwright-output",
    use: {
        baseURL: process.env.APP_URL ?? "http://127.0.0.1:8010",
        trace: "on-first-retry",
        screenshot: "only-on-failure",
        viewport: { width: 1600, height: 1000 },
    },
    projects: [{ name: "chromium", use: { ...devices["Desktop Chrome"] } }],
});