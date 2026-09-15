import { defineConfig } from "vitest/config";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { platformAliases } from "../../packages/platform/platform.aliases.js";
import { blogAliases } from "../../packages/blog/blog.aliases.js";

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
        },
    },
});