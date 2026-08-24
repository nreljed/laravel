import { defineConfig } from "vitest/config";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));

// Model B: dashboard-core is a vendor dependency — its tests live in the
// package repo (nreljed/dashboard-core), not here.
export default defineConfig({
    test: {
        environment: "jsdom",
        globals: true,
        passWithNoTests: true,
        include: [
            "resources/js/**/*.test.{ts,tsx}",
            "app/TestPosts/Resources/js/**/*.test.{ts,tsx}",
        ],
        setupFiles: ["./resources/js/test-setup.ts"],
    },
    resolve: {
        alias: {
            "@": path.resolve(__dirname, "vendor/nreljed/dashboard-core/resources/js"),
        },
    },
});
