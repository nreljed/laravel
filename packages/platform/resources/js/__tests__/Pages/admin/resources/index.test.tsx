import { describe, it, expect, vi, afterEach } from "vitest";
import { render, screen, cleanup } from "@testing-library/react";
import type { SerializedResource } from "../../../../lib/resource-schema";
import ResourceIndexPage from "../../../../Pages/dashboard/admin/resources/index";

/**
 * The kernel index page is the one place a serialized descriptor's route
 * *names* have to become URLs. `ResourceRenderer::renderIndex()` forwards
 * `resource.routes` untouched, so forwarding a name to ResourceIndex as if it
 * were a URL renders the create button as a relative href like
 * "dashboard/shop/dashboard.shop.shipping-zones.create" instead of a path.
 * `toResource` already binds edit/update/destroy to `{record}`, so only the
 * create route (and the breadcrumb) need Ziggy here.
 */
vi.mock("@inertiajs/react", () => ({
    Head: () => null,
    Link: ({ children, href }: { children?: unknown; href?: string }) => <a href={href}>{children as never}</a>,
    usePage: () => ({ props: {} }),
}));

vi.mock("../../../../Layouts/AdminLayout", () => ({
    default: ({ children }: { children?: unknown }) => <div data-testid="admin-layout">{children as never}</div>,
}));

// This page's concern is route resolution, not TanStack; stub the table out the
// way the ResourceIndex suite does.
vi.mock("../../../../Components/ui/data-table", () => ({
    DataTable: () => <div data-testid="data-table" />,
}));

/** The route names these descriptors declare, as Ziggy would resolve them. */
const KNOWN_ROUTES: Record<string, string> = {
    "dashboard.index": "/dashboard",
    "dashboard.shop.shipping-zones.index": "/dashboard/shop/shipping-zones",
    "dashboard.shop.shipping-zones.create": "/dashboard/shop/shipping-zones/create",
    "dashboard.shop.shipping-zones.edit": "/dashboard/shop/shipping-zones/{record}/edit",
};

/** Stands in for Ziggy: route name in, URL out, with `{record}` filled. */
function route(name: string, params?: Record<string, unknown>): string {
    const template = KNOWN_ROUTES[name];

    if (template === undefined) {
        throw new Error(`test route double has no entry for [${name}]`);
    }

    return template.replace("{record}", String(params?.record));
}

function makeResource(overrides: Partial<SerializedResource> = {}): SerializedResource {
    return {
        key: "shop/shipping-zone",
        label: "Shipping Zones",
        singular: "Shipping Zone",
        table: { columns: [{ id: "name", label: "Name", accessor: "name" }] },
        routes: {
            index: "dashboard.shop.shipping-zones.index",
            create: "dashboard.shop.shipping-zones.create",
        },
        ...overrides,
    } as SerializedResource;
}

function renderPage(resource: SerializedResource) {
    vi.stubGlobal("route", route);

    return render(
        <ResourceIndexPage resource={resource} data={[{ id: 1, name: "Tunis" }]} resourceKey="shop/shipping-zone" />,
    );
}

afterEach(() => {
    cleanup();
    vi.unstubAllGlobals();
});

describe("resource index page route resolution", () => {
    it("resolves the declared create route name to a URL", () => {
        renderPage(makeResource());

        expect(screen.getByRole("link", { name: "New Shipping Zone" }).getAttribute("href")).toBe(
            "/dashboard/shop/shipping-zones/create",
        );
    });

    it("hides the create button when the descriptor declares no create route", () => {
        renderPage(makeResource({ routes: { index: "dashboard.shop.shipping-zones.index" } }));

        expect(screen.queryByRole("link", { name: "New Shipping Zone" })).toBeNull();
    });

    it("hands record routes to ResourceIndex as row builders, not names", () => {
        // `toResource` binds edit/update/destroy to `{record}` already, so the
        // page must pass those builders straight through. Re-resolving them here
        // would call Ziggy with a function and throw — this asserts the render
        // survives a descriptor that declares them.
        expect(() =>
            renderPage(
                makeResource({
                    routes: {
                        index: "dashboard.shop.shipping-zones.index",
                        create: "dashboard.shop.shipping-zones.create",
                        edit: "dashboard.shop.shipping-zones.edit",
                    },
                }),
            ),
        ).not.toThrow();

        expect(screen.getByRole("link", { name: "New Shipping Zone" }).getAttribute("href")).toBe(
            "/dashboard/shop/shipping-zones/create",
        );
    });
});
