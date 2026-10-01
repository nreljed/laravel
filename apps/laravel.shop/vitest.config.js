import { defineConfig } from "vitest/config";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));

export default defineConfig({
    test: {
        environment: "jsdom",
        globals: true,
        pool: "threads",
        include: [
            "resources/js/**/*.test.{ts,tsx}",
            // shop-module's own frontend suites. The package ships no vitest
            // config of its own, so without this glob its tests are dead weight
            // that nothing runs and nothing fails when they rot - this app is the
            // module's deploy target, and it already resolves the package
            // aliases below.
            "../../packages/shop-module/src/Resources/js/**/*.test.{ts,tsx}",
        ],
        setupFiles: ["./resources/js/test-setup.ts"],
        coverage: {
            provider: "v8",
            reporter: ["text", "lcov"],
            include: [
                "resources/js/**/*.{ts,tsx}",
            ],
            exclude: [
                "resources/js/**/__tests__/**",
                "resources/js/**/*.test.*",
                "resources/js/Components/ui/**",
            ],
        },
    },
    resolve: {
        alias: {
            "@nreljed/platform": path.resolve(__dirname, "../../packages/platform/resources/js"),
            "@nreljed/platform/*": path.resolve(__dirname, "../../packages/platform/resources/js"),
            "@nreljed/blog": path.resolve(__dirname, "../../packages/blog/src/Resources/js"),
            "@nreljed/blog/*": path.resolve(__dirname, "../../packages/blog/src/Resources/js"),
            "@nreljed/shop-module": path.resolve(__dirname, "../../packages/shop-module/src/Resources/js"),
            "@nreljed/shop-module/*": path.resolve(__dirname, "../../packages/shop-module/src/Resources/js"),
        },
    },
});