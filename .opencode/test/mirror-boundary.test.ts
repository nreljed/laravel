/**
 * Behavioural tests for the mirror-boundary plugin.
 *
 * Kept out of plugins/ on purpose -- OpenCode auto-loads every file in that
 * directory as a plugin.
 *
 * These exist because "the plugin loaded" is not evidence that it works. A
 * star-slash sequence inside a block comment in mirror-boundary.ts silently
 * truncated the module; the plugin still appeared in `opencode debug config`,
 * because that command only reports discovered file paths. Parsing plus a real
 * behavioural assertion is the only trustworthy check.
 *
 * These were plain scripts (`console.log` + `process.exit`) and were matched by
 * no vitest glob, so they never ran in CI. They are vitest suites now, and
 * vitest.config.js includes this directory -- see the note there.
 */

import { describe, expect, it } from "vitest";

import {
    isMirrorPath,
    relativeToWorktree,
} from "../plugins/mirror-boundary.ts";

const WORKTREE = "C:/Users/nrelj/Local Sites/laravel/app";

const cases: [string, boolean, string][] = [
    ["packages/platform/composer.json", true, "mirror file"],
    ["packages\\shop-module\\src\\A.php", true, "Windows backslash path"],
    ["packages", true, "mirror root itself"],
    [
        "apps/laravel.shop/composer.json",
        false,
        "apps/ unblocked (mirror, but root-Pint-covered)",
    ],
    ["apps/laravel.booking/routes/web.php", false, "apps/ allowed for the same reason"],
    ["app/Models/User.php", false, "app/ allowed"],
    ["resources/js/app.tsx", false, "resources/ allowed"],
    ["packagesfoo/x.php", false, "prefix must be a real path segment"],
];

describe("relativeToWorktree", () => {
    it("strips the worktree prefix and normalises separators", () => {
        expect(
            relativeToWorktree(
                `${WORKTREE}/packages/blog/composer.json`,
                WORKTREE,
            ),
        ).toBe("packages/blog/composer.json");
        expect(
            relativeToWorktree(
                `${WORKTREE}\\packages\\blog\\composer.json`,
                WORKTREE,
            ),
        ).toBe("packages/blog/composer.json");
    });

    it("leaves a path outside the worktree untouched", () => {
        const outside = relativeToWorktree(
            "C:/other/place/packages/x.php",
            WORKTREE,
        );
        expect(outside).toBe("C:/other/place/packages/x.php");
        expect(isMirrorPath(outside)).toBe(false);
    });
});

describe("isMirrorPath", () => {
    it.each(cases)(
        "%s -> %s (%s)",
        (path: string, expected: boolean) => {
            expect(isMirrorPath(relativeToWorktree(path, WORKTREE))).toBe(
                expected,
            );
        },
    );
});