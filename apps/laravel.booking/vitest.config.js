import { defineConfig } from "vitest/config";
import path from "node:path";
import { realpathSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { platformAliases } from "../../packages/platform/platform.aliases.js";
import { blogAliases } from "../../packages/blog/blog.aliases.js";

const __dirname = path.dirname(fileURLToPath(import.meta.url));

export default defineConfig({
    server: {
        fs: {
            allow: [realpathSync(path.resolve(__dirname, "../../packages/platform"))],
        },
    },
    test: {
        environment: "jsdom",
        globals: true,
        passWithNoTests: true,
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
            "@booking": path.resolve(
                __dirname,
                "../../packages/booking-module/src/Booking/Resources/js"
            ),
            "@properties": path.resolve(
                __dirname,
                "../../packages/booking-module/src/Properties/Resources/js"
            ),
        },
    },
});