import { defineConfig } from "vitest/config";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { platformAliases } from "./vendor/nreljed/platform/platform.aliases.js";
import { blogAliases } from "./vendor/nreljed/blog/blog.aliases.js";

const __dirname = path.dirname(fileURLToPath(import.meta.url));

// Model B: platform/blog are vendor dependencies — their tests live in the
// package repos (nreljed/platform, nreljed/blog), not here.
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
            ...platformAliases(),
            ...blogAliases(),
            "@test-posts": path.resolve(__dirname, "./app/TestPosts/Resources/js"),
        },
    },
});