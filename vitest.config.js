import { defineConfig } from "vitest/config";
import path from "node:path";

// Model A post-workspace: platform/blog are workspace packages (packages/*) —
// consumed via the pnpm symlink + their package.json exports. The app vitest
// suite only covers app shell + TestPosts; package tests live in packages/*.
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
            "@test-posts": path.resolve(import.meta.dirname, "./app/TestPosts/Resources/js"),
        },
    },
});