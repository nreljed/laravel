import { defineConfig } from "vitest/config";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));

export default defineConfig({
    test: {
        environment: "jsdom",
        globals: true,
        include: [
            "resources/js/**/*.test.{ts,tsx}",
            "packages/dashboard-core/resources/js/**/*.test.{ts,tsx}",
        ],
        setupFiles: ["./resources/js/test-setup.ts"],
        coverage: {
            provider: "v8",
            reporter: ["text", "lcov"],
            include: [
                "resources/js/**/*.{ts,tsx}",
                "packages/dashboard-core/resources/js/**/*.{ts,tsx}",
            ],
            exclude: [
                "resources/js/**/__tests__/**",
                "resources/js/**/*.test.*",
                "resources/js/Components/ui/**",
                "packages/dashboard-core/resources/js/**/__tests__/**",
                "packages/dashboard-core/resources/js/**/*.test.*",
                "packages/dashboard-core/resources/js/Components/ui/**",
            ],
        },
    },
    resolve: {
        alias: {
            "@": path.resolve(__dirname, "packages/dashboard-core/resources/js"),
        },
    },
});
