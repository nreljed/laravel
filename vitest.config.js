import { defineConfig } from "vitest/config";
import path from "node:path";

// Model A: platform/blog/marketing-module consumed via vendor/nreljed/* +
// Vite/TS aliases (same as the app build). App vitest suite covers the app
// shell only; package tests live in their own repos.
export default defineConfig({
    test: {
        environment: "jsdom",
        globals: true,
        passWithNoTests: true,
        include: [
            "resources/js/**/*.test.{ts,tsx}",
        ],
        setupFiles: ["./resources/js/test-setup.ts"],
    },
    resolve: {
        alias: {
            "@nreljed/platform": path.resolve(__dirname, "vendor/nreljed/platform/resources/js"),
            "@nreljed/blog": path.resolve(__dirname, "vendor/nreljed/blog/src/Resources/js"),
            "@nreljed/marketing-module": path.resolve(__dirname, "vendor/nreljed/marketing-module/src/Resources/js"),
        },
    },
});