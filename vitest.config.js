import { defineConfig } from "vitest/config";
import path from "node:path";

// Model A post-workspace: platform/blog are workspace packages (packages/*) —
// consumed via the pnpm symlink + their package.json exports. The app vitest
// suite only covers the app shell; package tests live in packages/*.
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
            "@nreljed/platform": path.resolve(__dirname, "packages/platform/resources/js"),
            "@nreljed/blog": path.resolve(__dirname, "packages/blog/src/Resources/js"),
            "@nreljed/marketing-module": path.resolve(__dirname, "packages/marketing-module/src/Resources/js"),
        },
    },
});