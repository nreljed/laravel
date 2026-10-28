import { describe, it, expect } from "vitest";
import { hasInertiaPage } from "../app";

/**
 * The error views load the app entry with `@vite` and no Inertia page. These
 * tests pin the one decision that keeps that from throwing.
 */
describe("hasInertiaPage", () => {
  it("is false for a standalone Blade view, which is the 404 case", () => {
    const doc = document.implementation.createHTMLDocument("error");
    doc.body.innerHTML = "<div class='min-h-screen'>404</div>";

    expect(hasInertiaPage(doc)).toBe(false);
  });

  it("is true for a normal Inertia response", () => {
    const doc = document.implementation.createHTMLDocument("page");
    doc.body.innerHTML = `<div id="app" data-page='{"component":"dashboard/index","props":{}}'></div>`;

    expect(hasInertiaPage(doc)).toBe(true);
  });

  it("is true when the payload sits on a custom mount id", () => {
    const doc = document.implementation.createHTMLDocument("page");
    doc.body.innerHTML = `<div id="root" data-page='{"component":"shop/index","props":{}}'></div>`;

    // A project may mount Inertia anywhere. Refusing to boot because the id
    // was not "app" would be a silent total failure, so the attribute wins.
    expect(hasInertiaPage(doc)).toBe(true);
  });

  it("is false when the mount point exists but carries no payload", () => {
    const doc = document.implementation.createHTMLDocument("half");
    doc.body.innerHTML = '<div id="app"></div>';

    // An empty `data-page` attribute would still throw inside Inertia's
    // initialiser, so its presence alone must not count as bootable.
    expect(hasInertiaPage(doc)).toBe(false);
  });

  it("reads the live document by default, which is what boot uses", () => {
    document.body.innerHTML = '<div id="app" data-page=\'{"component":"x"}\'></div>';
    expect(hasInertiaPage()).toBe(true);

    document.body.innerHTML = "<p>no inertia here</p>";
    expect(hasInertiaPage()).toBe(false);

    document.body.innerHTML = "";
  });
});
