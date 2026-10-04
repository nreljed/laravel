/**
 * Behavioural tests for the session-context plugin.
 *
 * Same rationale as mirror-boundary.test.ts: OpenCode reporting a plugin in
 * `opencode debug config` only proves the file was DISCOVERED. A syntactically
 * truncated module still appears there. These tests import the module and
 * exercise the hook, which is the only trustworthy check.
 *
 * The scope assertions matter as much as the hook assertion. An earlier revision
 * asserted that apps/laravel.shop was "the SERVED tree" and laravel.shop was
 * "the DEPLOY tree". That is true of the shop app and FALSE of this repo, so it
 * was actively misleading injected guidance. These checks stop it creeping back.
 *
 * The ownership assertions cover a second, later regression: the injected text
 * claimed the module packages and both apps/ trees were "WORKSPACE-ONLY and
 * MONO-OWNED ... not separate repositories", which was false for all six and was
 * re-injected on every compaction.
 *
 * These were plain scripts (`console.log` + `process.exit`) and were matched by
 * no vitest glob, so they never ran in CI. They are vitest suites now, and
 * vitest.config.js includes this directory -- see the note there.
 */

import { describe, expect, it } from "vitest";

import { SessionContextPlugin } from "../plugins/session-context.ts";

const FLEET_REPO =
    /\b(?:platform|blog|shop-module|booking-module|laravel\.shop|laravel\.booking)\b/;

async function injectedContext(): Promise<string> {
    const hooks = (await SessionContextPlugin({})) as Record<
        string,
        (input: unknown, output: unknown) => Promise<void>
    >;
    const compacting = hooks["experimental.session.compacting"];

    expect(typeof compacting).toBe("function");

    const output = { context: ["pre-existing context"] };
    await compacting({}, output);
    const context = output.context as string[];

    expect(context[0]).toBe("pre-existing context");
    return context[1] ?? "";
}

describe("experimental.session.compacting", () => {
    it("appends fleet context without discarding existing context", async () => {
        const injected = await injectedContext();
        expect(injected.length).toBeGreaterThan(0);
    });

    it("tolerates a non-array context without throwing", async () => {
        const hooks = (await SessionContextPlugin({})) as Record<
            string,
            (input: unknown, output: unknown) => Promise<void>
        >;
        await expect(
            hooks["experimental.session.compacting"]({}, { context: "not-an-array" }),
        ).resolves.toBeUndefined();
    });
});

describe("injected fleet context: repo scope", () => {
    it("states this repo is the root laravel app", async () => {
        expect(await injectedContext()).toMatch(/root `?laravel`? app/i);
    });

    it("names the consumed upstream packages", async () => {
        const injected = await injectedContext();
        expect(injected).toContain("nreljed/platform");
        expect(injected).toContain("nreljed/blog");
    });
});

describe("injected fleet context: ownership of mirrored trees", () => {
    it("keeps the packages/** mirror rule", async () => {
        expect(await injectedContext()).toContain("packages/**");
    });

    it("names each module package as an upstream mirror", async () => {
        const injected = await injectedContext();
        expect(injected).toContain("packages/shop-module");
        expect(injected).toContain("nreljed/shop-module");
        expect(injected).toContain("packages/booking-module");
        expect(injected).toContain("nreljed/booking-module");
    });

    it("names each workspace app as an upstream mirror", async () => {
        const injected = await injectedContext();
        expect(injected).toContain("nreljed/laravel.shop");
        expect(injected).toContain("nreljed/laravel.booking");
    });

    // Match the CLAIM, not the bare word: the corrected text legitimately
    // contains "NOT workspace-only code", so /workspace-only/ alone
    // false-positives on its own negation. The old claim's tell is the copula.
    it("does NOT claim the mirrored trees are workspace-only", async () => {
        expect(await injectedContext()).not.toMatch(
            /\b(?:are|is)\s+WORKSPACE-ONLY\b/i,
        );
    });

    it("does NOT use the term MONO-OWNED", async () => {
        expect(await injectedContext()).not.toMatch(/MONO-OWNED/i);
    });

    it("does NOT claim a mirrored tree has no upstream repository", async () => {
        const injected = await injectedContext();
        expect(injected).not.toMatch(/not separate repositories/i);
        expect(injected).not.toMatch(/no upstream repo/i);
    });

    it("warns that a fix authored in a mirror does not ship", async () => {
        expect(await injectedContext()).toMatch(/does NOT ship/i);
    });

    it("preserves the packages/** vs apps/** boundary nuance", async () => {
        expect(await injectedContext()).toMatch(
            /deliberately governed DIFFERENTLY/i,
        );
    });
});

describe("injected fleet context: external paths", () => {
    // Naming the upstream clones is useful (an agent fixing platform needs its
    // real source), so external paths are permitted -- what is NOT permitted is
    // a path that points somewhere unidentified. Every line carrying an external
    // clone path must also name the repo it refers to, so a future edit cannot
    // leave a bare or stale directory behind. Containment-by-repo, not a
    // prohibition on paths: a naive "no Local Sites/ anywhere" rule would also
    // flag the legitimate pre-existing platform/blog clone lines.
    it("documents clone locations, each tied to a named repo", async () => {
        const injected = await injectedContext();
        const pathLines = injected
            .split("\n")
            .filter((l) => l.includes("Local Sites/"));

        expect(pathLines.length).toBeGreaterThan(0);

        const unidentified = pathLines.filter((l) => !FLEET_REPO.test(l));
        expect(
            unidentified,
            `external clone path(s) with no repo name: ${unidentified.join(" | ")}`,
        ).toEqual([]);
    });

    it("does NOT claim apps/laravel.shop is the served tree", async () => {
        expect(await injectedContext()).not.toMatch(/served tree/i);
    });

    it("does NOT direct the agent at shop:env-check", async () => {
        expect(await injectedContext()).not.toMatch(/shop:env-check/);
    });
});

describe("injected fleet context: release chain and traps", () => {
    it("keeps the composer.lock pin-of-record rule", async () => {
        expect(await injectedContext()).toContain("composer.lock");
    });

    it("keeps the public/hot trap", async () => {
        expect(await injectedContext()).toContain("public/hot");
    });

    it("keeps the path-repository trap", async () => {
        const injected = await injectedContext();
        expect(injected).toMatch(/path/i);
        expect(injected).toMatch(/lock claims a release/i);
    });
});