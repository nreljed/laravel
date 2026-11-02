import { defineConfig } from "vitest/config";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { platformAliases } from "../../packages/platform/platform.aliases.js";
import { blogAliases } from "../../packages/blog/blog.aliases.js";
import { marketingAliases } from "../../packages/marketing-module/marketing.aliases.js";

const __dirname = path.dirname(fileURLToPath(import.meta.url));

export default defineConfig({
    test: {
        environment: "jsdom",
        globals: true,
        pool: "threads",
        include: [
            "resources/js/**/*.test.{ts,tsx}",
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
            ...platformAliases(),
            ...blogAliases(),
            "@dashboard-themes": path.resolve(__dirname, "../../packages/platform/resources/themes"),
            "@shop": path.resolve(__dirname, "../../packages/shop-module/src/Resources/js"),
            "@nreljed/platform": path.resolve(__dirname, "../../packages/platform/resources/js"),
            "@nreljed/platform/*": path.resolve(__dirname, "../../packages/platform/resources/js"),
            "@nreljed/blog": path.resolve(__dirname, "../../packages/blog/src/Resources/js"),
            "@nreljed/blog/*": path.resolve(__dirname, "../../packages/blog/src/Resources/js"),
            "@nreljed/marketing-module": path.resolve(__dirname, "../../packages/marketing-module/src/Resources/js"),
            "@nreljed/marketing-module/*": path.resolve(__dirname, "../../packages/marketing-module/src/Resources/js"),
            "@nreljed/shop-module": path.resolve(__dirname, "../../packages/shop-module/src/Resources/js"),
            "@nreljed/shop-module/*": path.resolve(__dirname, "../../packages/shop-module/src/Resources/js"),
            ...marketingAliases(),
        },
    },
});
