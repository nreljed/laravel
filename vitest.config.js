import { defineConfig } from "vitest/config";

// The app vitest suite covers the app shell; the package globs below cover the
// workspace packages' own suites, which live beside the code they test.
//
// These package suites used to be dead weight: twenty test files across
// packages/platform, packages/blog and packages/shop-module shipped with no
// glob that reached them, so nothing ran them and nothing failed when they
// rotted. They are in the root suite on purpose — a consuming app is where the
// workspace toolchain (and `check:ci`) already lives, so this is the only place
// they would ever execute.
//
// Two layouts exist across the workspace: platform keeps its tests under
// `resources/js/`, while blog and shop-module keep theirs under
// `src/Resources/js/` (Laravel's package convention). Both need a glob.
export default defineConfig({
    test: {
        environment: "jsdom",
        globals: true,
        // The suite is only meaningful while it finds something. With this on,
        // a glob that stops matching (a package moved, a mirror went stale)
        // reports success while running zero tests — the exact failure this
        // config's package globs were added to prevent.
        passWithNoTests: false,
        include: [
            "resources/js/**/*.test.{ts,tsx}",
            "packages/*/resources/js/**/*.test.{ts,tsx}",
            "packages/*/src/Resources/js/**/*.test.{ts,tsx}",
        ],
        setupFiles: ["./resources/js/test-setup.ts"],
    },
});