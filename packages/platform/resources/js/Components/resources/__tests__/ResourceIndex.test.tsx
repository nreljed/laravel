import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { render, screen, cleanup, act, fireEvent } from "@testing-library/react";
import type { CellData, ColumnDef } from "@tanstack/react-table";
import { ResourceIndex } from "../ResourceIndex";
import { defineResource, type FieldDefinition, type ResourceDefinition, type ResourceRecord } from "../../../lib/resource-schema";
import { AppTableFeatures } from "../../../lib/table-features";

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
    routerMock: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() },
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
        columns: ColumnDef<AppTableFeatures, Widget, CellData>[];
        data: Widget[];
        bulkActions?: { label: string; value: string; needsInput?: boolean }[];
        onBulkAction?: unknown;
        onBulkInput?: (
            action: { label: string; value: string; needsInput?: boolean; destructive?: boolean },
            ids: (number | string)[],
        ) => void;
        searchKeys?: string[];
        searchKey?: string;
    };
}

/**
 * Read the accessor key off a translated column. The `ColumnDef` union is
 * optional on every member it has, so the strict consumer gate needs the
 * column viewed through its observable fields rather than the union.
 */
function columnAccessor(column: ColumnDef<AppTableFeatures, Widget, CellData>) {
    return String((column as unknown as { accessorKey?: string }).accessorKey);
}

beforeEach(() => {
    dataTableSpy.mockClear();
    routerMock.get.mockClear();
    routerMock.delete.mockClear();
    routerMock.post.mockClear();
    routerMock.put.mockClear();
    routerMock.patch.mockClear();
});

afterEach(() => {
    cleanup();
    vi.clearAllMocks();
});

function makeResource(overrides: Partial<ResourceDefinition<Widget>> = {}) {
    return defineResource<Widget>({
        key: "shop/widget",
        label: "Widgets",
        singular: "widget",
        table: { columns: [{ name: "name" }] },
        ...overrides,
    });
}

describe("ResourceIndex translation", () => {
    it("passes the declared columns through", () => {
        render(<ResourceIndex resource={makeResource()} data={widgets} />);

        const names = lastDataTableProps().columns.map((column) => columnAccessor(column));

        expect(names).toContain("name");
    });

    it("adds an action column last when an edit route is given", () => {
        render(<ResourceIndex resource={makeResource()} data={widgets} editRoute={(row) => `/widgets/${row.id}/edit`} />);

        const columns = lastDataTableProps().columns;

        // Last, because a header click reorders and an action cell that can
        // migrate to first column looks like a bug.
        const last = columns.at(-1) as unknown as { accessorKey?: string; enableSorting?: boolean } | undefined;

        expect(String(last?.accessorKey)).toBe("_actions");
        expect(last?.enableSorting).toBe(false);
    });

    it("omits the action column for a read-only table", () => {
        render(<ResourceIndex resource={makeResource()} data={widgets} />);

        expect(lastDataTableProps().columns.map((column) => columnAccessor(column))).not.toContain("_actions");
    });

    it("keeps the action column when the resource declares its own row actions", () => {
        const resource = makeResource({
            table: { columns: [{ name: "name" }], rowActions: [{ label: "Duplicate" }] },
        });

        render(<ResourceIndex resource={resource} data={widgets} />);

        expect(lastDataTableProps().columns.map((column) => columnAccessor(column))).toContain("_actions");
    });

    it("renders a custom cell from the declaration", () => {
        const resource = makeResource({
            table: { columns: [{ name: "name", cell: (row) => `#${(row as Widget).id}` }] },
        });

        render(<ResourceIndex resource={resource} data={widgets} />);

        const column = lastDataTableProps().columns[0]!;
        const rendered = (column.cell as (context: unknown) => unknown)({ row: { original: widgets[0]! } });

        expect(rendered).toBe("#1");
    });

    it("forwards the declared bulk actions", () => {
        const resource = makeResource({
            table: { columns: [{ name: "name" }], bulkActions: [{ label: "Delete", value: "delete" }] },
        });

        render(<ResourceIndex resource={resource} data={widgets} onBulkAction={() => undefined} />);

        expect(lastDataTableProps().bulkActions).toEqual([{ label: "Delete", value: "delete" }]);
    });

    it("only offers the input path when a submit handler exists", () => {
        const resource = makeResource({
            table: {
                columns: [{ name: "name" }],
                bulkActions: [{ label: "Add Categories", value: "add_category", needsInput: true }],
            },
        });

        render(<ResourceIndex resource={resource} data={widgets} onBulkAction={() => undefined} />);

        /* Without onBulkSubmit there is nowhere to send the collected values, so
         * the dialog must not open - a button that swallows input is worse than
         * an action the descriptor did not declare. */
        expect(lastDataTableProps().onBulkInput).toBeUndefined();
    });

    it("offers the input path for a needsInput action when a submit handler exists", () => {
        const resource = makeResource({
            table: {
                columns: [{ name: "name" }],
                bulkActions: [{ label: "Add Categories", value: "add_category", needsInput: true }],
            },
        });

        render(<ResourceIndex resource={resource} data={widgets} onBulkSubmit={() => undefined} />);

        expect(typeof lastDataTableProps().onBulkInput).toBe("function");
    });

    it("submits the collected values with the action and the selection", () => {
        const onBulkSubmit = vi.fn();
        const resource = makeResource({
            table: {
                columns: [{ name: "name" }],
                bulkActions: [
                    {
                        label: "Add Categories",
                        value: "add_category",
                        needsInput: true,
                        fields: [{ name: "category_ids", type: "relation", label: "Categories" }],
                    },
                ],
            },
        });

        render(<ResourceIndex resource={resource} data={widgets} onBulkSubmit={onBulkSubmit} />);

        /* Drive the callback the DataTable would fire, then submit the dialog. */
        act(() => {
            lastDataTableProps().onBulkInput?.({ label: "Add Categories", value: "add_category", needsInput: true }, [1, 2]);
        });

        /* The dialog's submit button is a plain "Apply" - repeating the action's
         * label there read as though the dialog were still pending. */
        act(() => {
            fireEvent.click(screen.getByRole("button", { name: "Apply" }));
        });

        expect(onBulkSubmit).toHaveBeenCalledWith("add_category", [1, 2], {});
    });

    it("confirms a destructive action that needs input before submitting", () => {
        const onBulkSubmit = vi.fn();
        const resource = makeResource({
            table: {
                columns: [{ name: "name" }],
                bulkActions: [
                    {
                        label: "Remove Categories",
                        value: "remove_category",
                        needsInput: true,
                        destructive: true,
                        fields: [{ name: "category_ids", type: "relation", label: "Categories" }],
                    },
                ],
            },
        });

        render(<ResourceIndex resource={resource} data={widgets} onBulkSubmit={onBulkSubmit} />);

        act(() => {
            lastDataTableProps().onBulkInput?.(
                { label: "Remove Categories", value: "remove_category", needsInput: true, destructive: true },
                [1],
            );
        });

        /* Filling the fields opens the confirm, not the submit: the confirm
         * button is the one carrying the action's own label. */
        act(() => {
            fireEvent.click(screen.getByRole("button", { name: "Apply" }));
        });

        /* Still unconfirmed - nothing may be sent until the user agrees. */
        expect(onBulkSubmit).not.toHaveBeenCalled();

        act(() => {
            fireEvent.click(screen.getByRole("button", { name: "Remove Categories" }));
        });

        expect(onBulkSubmit).toHaveBeenCalledWith("remove_category", [1], {});
    });

    /**
     * Opens a bulk dialog the way the DataTable does: the action handed to
     * `onBulkInput` is the very object the resource declared, `fields` included.
     * A hand-rolled stub without `fields` opens a dialog with no controls at
     * all, so a test written that way would assert against an empty dialog
     * rather than against the component's behaviour.
     */
    function openBulkDialog(action: {
        label: string;
        value: string;
        dialogTitle?: string;
        needsInput?: boolean;
        fields?: FieldDefinition[];
    }) {
        const onBulkSubmit = vi.fn();
        const resource = makeResource({ table: { columns: [{ name: "name" }], bulkActions: [action] } });

        render(<ResourceIndex resource={resource} data={widgets} onBulkSubmit={onBulkSubmit} />);

        act(() => {
            lastDataTableProps().onBulkInput?.(action as never, [1]);
        });

        return { onBulkSubmit };
    }

    /**
     * A dialog whose `required` field starts empty must not be submittable: the
     * controller would reject it on `required_if` anyway, and a disabled button
     * says so before the round-trip instead of after it.
     */
    it("keeps Apply disabled while a required field is empty", () => {
        const { onBulkSubmit } = openBulkDialog({
            label: "Set Price",
            value: "set_price",
            needsInput: true,
            fields: [{ name: "price_value", type: "number", label: "Price", required: true }],
        });

        const apply = screen.getByRole("button", { name: "Apply" }) as HTMLButtonElement;

        expect(apply.disabled).toBe(true);

        act(() => {
            fireEvent.click(apply);
        });

        expect(onBulkSubmit).not.toHaveBeenCalled();
    });

    it("enables Apply once a required field holds a value", () => {
        const { onBulkSubmit } = openBulkDialog({
            label: "Set Price",
            value: "set_price",
            needsInput: true,
            fields: [{ name: "price_value", type: "number", label: "Price", required: true }],
        });

        act(() => {
            /* A required label carries a `*` span, so the accessible name is
             * "Price*" - matching the bare word would find nothing. */
            fireEvent.change(screen.getByLabelText(/^Price/), { target: { value: "19.99" } });
        });

        const apply = screen.getByRole("button", { name: "Apply" }) as HTMLButtonElement;

        expect(apply.disabled).toBe(false);

        act(() => {
            fireEvent.click(apply);
        });

        expect(onBulkSubmit).toHaveBeenCalledWith("set_price", [1], { price_value: "19.99" });
    });

    /** A required multi-value field is satisfied by one tick, not by `[]`. */
    it("keeps Apply disabled while a required checkboxList is empty", () => {
        openBulkDialog({
            label: "Add Categories",
            value: "add_category",
            needsInput: true,
            fields: [
                {
                    name: "category_ids",
                    type: "checkboxList",
                    label: "Categories",
                    required: true,
                    options: [{ label: "Apparel", value: 1 }],
                },
            ],
        });

        const apply = screen.getByRole("button", { name: "Apply" }) as HTMLButtonElement;

        expect(apply.disabled).toBe(true);

        act(() => {
            /* The Base UI control and its hidden input both carry the label, so
             * this is addressed by role: getByLabelText would find two. */
            fireEvent.click(screen.getByRole("checkbox", { name: /Apparel/ }));
        });

        expect(apply.disabled).toBe(false);
    });

    it("does not gate on an optional field", () => {
        openBulkDialog({
            label: "Change Type",
            value: "type",
            needsInput: true,
            fields: [{ name: "type_value", type: "text", label: "Type" }],
        });

        expect((screen.getByRole("button", { name: "Apply" }) as HTMLButtonElement).disabled).toBe(false);
    });

    /**
     * A `defaultValue` has to be seeded when the dialog opens, or a select
     * declaring `defaultValue: "simple"` sends an empty value the controller
     * rejects as `required_if`.
     */
    it("seeds a field default when the dialog opens", () => {
        const { onBulkSubmit } = openBulkDialog({
            label: "Change Type",
            value: "type",
            needsInput: true,
            fields: [
                {
                    name: "type_value",
                    type: "select",
                    label: "Type",
                    required: true,
                    defaultValue: "simple",
                    options: [
                        { label: "Simple", value: "simple" },
                        { label: "Variable", value: "variable" },
                    ],
                },
            ],
        });

        act(() => {
            fireEvent.click(screen.getByRole("button", { name: "Apply" }));
        });

        expect(onBulkSubmit).toHaveBeenCalledWith("type", [1], { type_value: "simple" });
    });

    it("gives an unseeded multi-value field an empty array, not undefined", () => {
        const { onBulkSubmit } = openBulkDialog({
            label: "Add Tags",
            value: "add_tag",
            needsInput: true,
            fields: [{ name: "tag_ids", type: "checkboxList", label: "Tags" }],
        });

        act(() => {
            fireEvent.click(screen.getByRole("button", { name: "Apply" }));
        });

        expect(onBulkSubmit).toHaveBeenCalledWith("add_tag", [1], { tag_ids: [] });
    });

    /** A menu label ending in `...` announces the dialog; the title drops it. */
    it("strips a trailing ellipsis from the dialog title", () => {
        openBulkDialog({
            label: "Add Categories...",
            value: "add_category",
            needsInput: true,
            fields: [{ name: "category_ids", type: "checkboxList", label: "Categories" }],
        });

        expect(screen.getByText("Add Categories")).toBeDefined();
        expect(screen.queryByText("Add Categories...")).toBeNull();
    });

    it("prefers an explicit dialogTitle over the derived one", () => {
        openBulkDialog({
            label: "Add Categories...",
            dialogTitle: "Choose categories to add",
            value: "add_category",
            needsInput: true,
            fields: [{ name: "category_ids", type: "checkboxList", label: "Categories" }],
        });

        expect(screen.getByText("Choose categories to add")).toBeDefined();
    });

    it("renders a plain header action as a button that reports its route", () => {
        const onHeaderAction = vi.fn();
        const resource = makeResource({
            table: {
                columns: [{ name: "name" }],
                headerActions: [{ label: "Export CSV", route: "/products/export", method: "get" }],
            },
        });

        render(<ResourceIndex resource={resource} data={widgets} onHeaderAction={onHeaderAction} />);

        fireEvent.click(screen.getByRole("button", { name: "Export CSV" }));

        expect(onHeaderAction).toHaveBeenCalledWith({
            label: "Export CSV",
            route: "/products/export",
            method: "get",
        });
    });

    it("renders a fileInput header action as a hidden picker, not a link", () => {
        const onHeaderUpload = vi.fn();
        const resource = makeResource({
            table: {
                columns: [{ name: "name" }],
                headerActions: [{ label: "Import", route: "/products/import", method: "post", fileInput: true, accept: ".csv" }],
            },
        });

        const { container } = render(<ResourceIndex resource={resource} data={widgets} onHeaderUpload={onHeaderUpload} />);

        const input = container.querySelector('input[type="file"]');
        expect(input).not.toBeNull();
        expect(input).toHaveAttribute("accept", ".csv");

        const file = new File(["id,name\n1,Bolt"], "products.csv", { type: "text/csv" });
        fireEvent.change(input as HTMLInputElement, { target: { files: [file] } });

        expect(onHeaderUpload).toHaveBeenCalledWith({
            label: "Import",
            route: "/products/import",
            file,
        });
    });

    it("forwards the declared searchKeys for the multi-column search", () => {
        const resource = makeResource({
            table: { columns: [{ name: "name" }], searchKeys: ["name", "email"] },
        });

        render(<ResourceIndex resource={resource} data={widgets} />);

        expect(lastDataTableProps().searchKeys).toEqual(["name", "email"]);
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
                resource={defineResource<Widget>({ key: "shop/thing", label: "Things", table: { columns: [] } })}
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
        row: Widget = widgets[0]!,
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

        act(() => item?.props.onSelect?.({ preventDefault: () => undefined }));

        expect(onRowAction).toHaveBeenCalledOnce();
        expect(routerMock.delete).not.toHaveBeenCalled();
    });

    it("confirms before a destructive action rather than deleting on click", () => {
        const resource = makeResource({ table: { columns: [] } });

        const item = findItem(actionCellFor(resource, { destroyRoute: (row: Widget) => `/widgets/${row.id}` }), "Delete");

        expect(item).not.toBeNull();

        act(() => item?.props.onSelect?.({ preventDefault: () => undefined }));

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

        act(() => findItem(cell, "Delete")?.props.onSelect?.({ preventDefault: () => undefined }));

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

    it("keeps an explicit GET action a link", () => {
        const resource = makeResource({
            table: { columns: [], rowActions: [{ label: "Show", method: "get", route: (row) => `/widgets/${(row as Widget).id}` }] },
        });

        expect(findHref(actionCellFor(resource), "Show")).toBe("/widgets/1");
    });

    it("submits a POST row action through router.post", () => {
        const resource = makeResource({
            table: {
                columns: [],
                rowActions: [{ label: "Approve", method: "post", route: (row) => `/widgets/${(row as Widget).id}/approve` }],
            },
        });

        const item = findItem(actionCellFor(resource), "Approve");

        expect(item).not.toBeNull();

        act(() => item?.props.onSelect?.({ preventDefault: () => undefined }));

        expect(routerMock.post).toHaveBeenCalledWith("/widgets/1/approve", {}, { preserveScroll: true });
    });

    /**
     * A feature that routes a partial update to PATCH answers 405 to a PUT, so
     * the declared verb has to be the verb on the wire - not a PUT standing in
     * for it.
     */
    it("submits a PATCH row action through router.patch", () => {
        const resource = makeResource({
            table: {
                columns: [],
                rowActions: [{ label: "Set status", method: "patch", route: (row) => `/widgets/${(row as Widget).id}/status` }],
            },
        });

        const item = findItem(actionCellFor(resource), "Set status");

        expect(item).not.toBeNull();

        act(() => item?.props.onSelect?.({ preventDefault: () => undefined }));

        expect(routerMock.patch).toHaveBeenCalledWith("/widgets/1/status", {}, { preserveScroll: true });
        expect(routerMock.put).not.toHaveBeenCalled();
    });

    it("submits a PUT row action through router.put", () => {
        const resource = makeResource({
            table: {
                columns: [],
                rowActions: [{ label: "Rename", method: "put", route: (row) => `/widgets/${(row as Widget).id}` }],
            },
        });

        const item = findItem(actionCellFor(resource), "Rename");

        act(() => item?.props.onSelect?.({ preventDefault: () => undefined }));

        expect(routerMock.put).toHaveBeenCalledWith("/widgets/1", {}, { preserveScroll: true });
        expect(routerMock.patch).not.toHaveBeenCalled();
    });

    it("confirms a non-destructive action carrying confirm copy before running", () => {
        const resource = makeResource({
            table: {
                columns: [],
                rowActions: [
                    {
                        label: "Approve",
                        confirm: "Approve widget 1?",
                        method: "post",
                        route: (row) => `/widgets/${(row as Widget).id}/approve`,
                    },
                ],
            },
        });

        const item = findItem(actionCellFor(resource), "Approve");

        act(() => item?.props.onSelect?.({ preventDefault: () => undefined }));

        // Selecting only opens the dialog - none of it runs yet.
        expect(routerMock.post).not.toHaveBeenCalled();
        expect(document.body.textContent).toContain("Approve widget 1?");

        const confirm = screen.getByRole("button", { name: "Approve" });

        act(() => confirm.click());

        expect(routerMock.post).toHaveBeenCalledWith("/widgets/1/approve", {}, { preserveScroll: true });
    });

    it("submits a declared DELETE action without a destroyRoute prop", () => {
        const resource = makeResource({
            table: {
                columns: [],
                rowActions: [{ label: "Hard delete", method: "delete", route: (row) => `/widgets/${(row as Widget).id}/purge` }],
            },
        });

        const item = findItem(actionCellFor(resource), "Hard delete");

        act(() => item?.props.onSelect?.({ preventDefault: () => undefined }));

        expect(routerMock.delete).toHaveBeenCalledWith("/widgets/1/purge", { preserveScroll: true });
    });
});

/**
 * Depth-first search for the menu item carrying the given label.
 */
function findItem(node: unknown, label: string): { props: { onSelect?: (event: { preventDefault: () => void }) => void } } | null {
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
