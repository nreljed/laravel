import { describe, it, expect, vi, beforeEach } from "vitest";
import { render, screen, cleanup, act } from "@testing-library/react";
import type { ColumnDef } from "@tanstack/react-table";
import { ResourceIndex } from "../ResourceIndex";
import { defineResource, type ResourceRecord } from "../../../lib/resource-schema";

/**
 * ResourceIndex owns no data and no persistence, so what it is actually
 * responsible for is the translation: declaration to DataTable props, and row
 * click to the right Inertia call. Both are asserted here against a stubbed
 * DataTable, which is the same approach the DataTableToolbar tests use - stub
 * the table, test the logic above it, rather than asserting on TanStack's own
 * rendering.
 */
/**
 * `vi.mock` is hoisted above the imports, so the doubles have to be created in
 * `vi.hoisted` too - a factory closing over a later `const` throws a TDZ
 * ReferenceError before a single test runs.
 */
const { dataTableSpy, routerMock } = vi.hoisted(() => ({
    dataTableSpy: vi.fn(),
    routerMock: { get: vi.fn(), post: vi.fn(), delete: vi.fn() },
}));

vi.mock("../../ui/data-table", () => ({
    DataTable: (props: Record<string, unknown>) => {
        dataTableSpy(props);

        return <div data-testid="data-table">{String(props.searchPlaceholder ?? "")}</div>;
    },
}));

vi.mock("@inertiajs/react", () => ({
    Link: ({ children, href }: { children?: unknown; href?: string }) => <a href={href}>{children as never}</a>,
    router: routerMock,
}));

interface Widget extends ResourceRecord {
    id: number;
    name: string;
}

const widgets: Widget[] = [
    { id: 1, name: "Bolt" },
    { id: 2, name: "Nut" },
];

function lastDataTableProps() {
    return dataTableSpy.mock.calls.at(-1)?.[0] as {
        columns: ColumnDef<Widget>[];
        data: Widget[];
        bulkActions?: { label: string; action: string }[];
        onBulkAction?: unknown;
    };
}

beforeEach(() => {
    dataTableSpy.mockClear();
    routerMock.get.mockClear();
    routerMock.delete.mockClear();
    routerMock.post.mockClear();
});

afterEach(() => {
    cleanup();
    vi.clearAllMocks();
});

function makeResource(overrides: Parameters<typeof defineResource>[0] = {}) {
    return defineResource({
        key: "shop/widget",
        label: "Widgets",
        singular: "widget",
        table: { columns: [{ name: "name" }] },
        ...overrides,
    } as never);
}

describe("ResourceIndex translation", () => {
    it("passes the declared columns through", () => {
        render(<ResourceIndex resource={makeResource()} data={widgets} />);

        const names = lastDataTableProps().columns.map((column) => String(column.accessorKey));

        expect(names).toContain("name");
    });

    it("adds an action column last when an edit route is given", () => {
        render(<ResourceIndex resource={makeResource()} data={widgets} editRoute={(row) => `/widgets/${row.id}/edit`} />);

        const columns = lastDataTableProps().columns;

        // Last, because a header click reorders and an action cell that can
        // migrate to first column looks like a bug.
        expect(String(columns.at(-1)?.accessorKey)).toBe("_actions");
        expect(columns.at(-1)?.enableSorting).toBe(false);
    });

    it("omits the action column for a read-only table", () => {
        render(<ResourceIndex resource={makeResource()} data={widgets} />);

        expect(lastDataTableProps().columns.map((column) => String(column.accessorKey))).not.toContain("_actions");
    });

    it("keeps the action column when the resource declares its own row actions", () => {
        const resource = makeResource({
            table: { columns: [{ name: "name" }], rowActions: [{ label: "Duplicate" }] },
        });

        render(<ResourceIndex resource={resource} data={widgets} />);

        expect(lastDataTableProps().columns.map((column) => String(column.accessorKey))).toContain("_actions");
    });

    it("renders a custom cell from the declaration", () => {
        const resource = makeResource({
            table: { columns: [{ name: "name", cell: (row) => `#${(row as Widget).id}` }] },
        });

        render(<ResourceIndex resource={resource} data={widgets} />);

        const column = lastDataTableProps().columns[0];
        const rendered = (column.cell as (context: unknown) => unknown)({ row: { original: widgets[0] } });

        expect(rendered).toBe("#1");
    });

    it("forwards the declared bulk actions", () => {
        const resource = makeResource({
            table: { columns: [{ name: "name" }], bulkActions: [{ label: "Delete", action: "delete" }] },
        });

        render(<ResourceIndex resource={resource} data={widgets} onBulkAction={() => undefined} />);

        expect(lastDataTableProps().bulkActions).toEqual([{ label: "Delete", action: "delete" }]);
    });

    it("hides the create button when no create route is given", () => {
        render(<ResourceIndex resource={makeResource()} data={widgets} />);

        expect(screen.queryByRole("link", { name: /New widget/ })).toBeNull();
    });

    it("links the create button to the create route", () => {
        render(<ResourceIndex resource={makeResource()} data={widgets} createRoute="/widgets/create" />);

        expect(screen.getByRole("link", { name: "New widget" }).getAttribute("href")).toBe("/widgets/create");
    });

    it("falls back to a generic new-item label without a singular", () => {
        render(
            <ResourceIndex
                resource={defineResource({ key: "shop/thing", label: "Things", table: { columns: [] } } as never)}
                data={widgets}
                createRoute="/things/create"
            />,
        );

        expect(screen.getByRole("link", { name: "New item" })).toBeDefined();
    });
});

describe("ResourceIndex row actions", () => {
    /** Renders the action cell for one row and returns the rendered tree. */
    function actionCellFor(
        resource: ReturnType<typeof makeResource>,
        props: Record<string, unknown> = {},
        row: Widget = widgets[0],
    ) {
        render(<ResourceIndex resource={resource} data={widgets} {...props} />);

        const column = lastDataTableProps().columns.at(-1)!;

        return (column.cell as (context: unknown) => unknown)({ row: { original: row } });
    }

    it("makes a non-destructive route a real link to the built URL", () => {
        const resource = makeResource({
            table: { columns: [], rowActions: [{ label: "Duplicate", route: (row) => `/widgets/${(row as Widget).id}/duplicate` }] },
        });

        // The whole item is the link, not just its text: otherwise clicking the
        // item's padding silently does nothing.
        const href = findHref(actionCellFor(resource), "Duplicate");

        expect(href).toBe("/widgets/1/duplicate");
    });

    it("gives the generated edit action a link, not a confirm dialog", () => {
        const resource = makeResource({ table: { columns: [] } });

        expect(findHref(actionCellFor(resource, { editRoute: (row: Widget) => `/widgets/${row.id}/edit` }), "Edit")).toBe("/widgets/1/edit");
    });

    it("prefers the page's own onRowAction over the built-in routes", () => {
        const onRowAction = vi.fn();
        const resource = makeResource({ table: { columns: [], rowActions: [{ label: "Archive" }] } });

        const item = findItem(actionCellFor(resource, { onRowAction }), "Archive");

        expect(item).not.toBeNull();

        act(() => item?.props.onSelect({ preventDefault: () => undefined }));

        expect(onRowAction).toHaveBeenCalledOnce();
        expect(routerMock.delete).not.toHaveBeenCalled();
    });

    it("confirms before a destructive action rather than deleting on click", () => {
        const resource = makeResource({ table: { columns: [] } });

        const item = findItem(actionCellFor(resource, { destroyRoute: (row: Widget) => `/widgets/${row.id}` }), "Delete");

        expect(item).not.toBeNull();

        act(() => item?.props.onSelect({ preventDefault: () => undefined }));

        // Selecting must only open the dialog. Deleting on click is the bug the
        // confirm step exists to prevent.
        expect(routerMock.delete).not.toHaveBeenCalled();
        expect(document.body.textContent).toContain("Delete this widget?");
    });

    it("deletes once the confirm dialog is accepted", () => {
        const resource = makeResource({ table: { columns: [] } });

        render(<ResourceIndex resource={resource} data={widgets} destroyRoute={(row: Widget) => `/widgets/${row.id}`} />);

        const column = lastDataTableProps().columns.at(-1)!;
        const cell = (column.cell as (context: unknown) => unknown)({ row: { original: widgets[0] } });

        act(() => findItem(cell, "Delete")?.props.onSelect({ preventDefault: () => undefined }));

        const confirm = screen.getByRole("button", { name: "Delete" });

        act(() => confirm.click());

        expect(routerMock.delete).toHaveBeenCalledWith("/widgets/1", { preserveScroll: true });
    });

    it("hides the delete action entirely when no destroy route is given", () => {
        const resource = makeResource({ table: { columns: [], rowActions: [{ label: "Archive" }] } });

        const labels = collectLabels(actionCellFor(resource));

        expect(labels).not.toContain("Delete");
        expect(labels).toContain("Archive");
    });

    it("hides a row action that its show predicate rejects", () => {
        const resource = makeResource({
            table: { columns: [], rowActions: [{ label: "Refund", show: (row) => (row as Widget).name === "Bolt" }] },
        });

        // For a row the predicate rejects, the cell renders nothing at all
        // rather than an empty dropdown.
        expect(actionCellFor(resource, {}, { id: 2, name: "Nut" })).toBeNull();
        expect(collectLabels(actionCellFor(resource, {}, { id: 1, name: "Bolt" }))).toContain("Refund");
    });
});

/** Depth-first search for the menu item carrying the given label. */
function findItem(node: unknown, label: string): { props: Record<string, (...args: never[]) => void> } | null {
    if (!node || typeof node !== "object") {
        return null;
    }

    const element = node as { props?: { children?: unknown; onSelect?: unknown } };

    if (typeof element.props?.onSelect === "function" && collectLabels(node).includes(label)) {
        return element as never;
    }

    const children = (element.props?.children ?? []) as unknown[];

    for (const child of Array.isArray(children) ? children : [children]) {
        const found = findItem(child, label);

        if (found) {
            return found;
        }
    }

    return null;
}

/**
 * The href of the link carrying the given label, if that label is a link.
 *
 * Read off the element tree rather than the DOM, because the item is a
 * `DropdownMenuItem render={<Link ... />}`. Base UI's `render` prop means the
 * href sits on `props.render.props.href`; the item only becomes a real anchor
 * once mounted, and mounting it needs a portal and a layout effect.
 */
function findHref(node: unknown, label: string): string | null {
    if (!node || typeof node !== "object") {
        return null;
    }

    const props = (node as { props?: { children?: unknown; href?: string; render?: { props?: { href?: string } } } }).props;
    const href = props?.href ?? props?.render?.props?.href;

    if (typeof href === "string" && collectLabels(node).includes(label)) {
        return href;
    }

    const children = (props?.children ?? []) as unknown[];

    for (const child of Array.isArray(children) ? children : [children]) {
        const found = findHref(child, label);

        if (found) {
            return found;
        }
    }

    return null;
}

/** Every string rendered anywhere in a subtree. */
function collectLabels(node: unknown, found: string[] = []): string[] {
    if (typeof node === "string") {
        found.push(node);
    } else if (Array.isArray(node)) {
        node.forEach((child) => collectLabels(child, found));
    } else if (node && typeof node === "object") {
        collectLabels((node as { props?: { children?: unknown } }).props?.children, found);
    }

    return found;
}
