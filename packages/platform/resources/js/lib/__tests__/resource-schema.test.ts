import { describe, it, expect } from "vitest";
import { buildPayload } from "../../Components/resources/ResourceForm";
import {
    defineResource,
    flattenFields,
    humanizeFieldName,
    isFieldVisible,
    readPath,
    relation,
    RESOURCE_RECORD_PARAM,
    resolveResourceRoute,
    rowMatchesSearchKeys,
    toColumnDef,
    toResource,
    type FieldDefinition,
    type ResourceRecord,
} from "../resource-schema";

/**
 * The schema is pure data, so these tests are pure assertions - no rendering.
 * That is the point of splitting it from the runtime: a column translation or a
 * payload filter is worth testing without a DOM.
 */
describe("defineResource", () => {
    it("returns the definition it was given, unaltered", () => {
        const definition = { key: "test/thing", label: "Things" };

        expect(defineResource(definition)).toBe(definition);
    });

    it("keeps declared row actions and relations attached", () => {
        const rows = {
            key: "test/thing",
            label: "Things",
            table: {
                columns: [],
                rowActions: [{ label: "Archive" }],
            },
            relations: [relation({ key: "children", component: () => null })],
        };

        const resource = defineResource<ResourceRecord>(rows);

        expect(resource.table?.rowActions).toHaveLength(1);
        expect(resource.relations?.[0]?.key).toBe("children");
    });
});

describe("relation", () => {
    it("keeps the component it was given", () => {
        const Component = () => null;

        expect(relation({ key: "lines", component: Component }).component).toBe(Component);
    });
});

describe("toColumnDef", () => {
    /** Read a flag off a translated column; the `ColumnDef` union hides them. */
    const flag = (column: ReturnType<typeof toColumnDef>, key: "accessorKey" | "enableSorting" | "enableHiding") =>
        (column as unknown as Record<string, unknown>)[key];

    it("maps a declared name onto an accessorKey", () => {
        const column = toColumnDef({ name: "email" });

        expect(flag(column, "accessorKey")).toBe("email");
    });

    it("carries alignment into the meta className, since TanStack v9 has no className", () => {
        // Without this the alignment declaration is silently dropped: v9 has no
        // `className` on a ColumnDef, so a consumer that set `align` and read
        // `column.className` would always get undefined.
        const column = toColumnDef({ name: "total", align: "right" });

        expect((column.meta as { className?: string }).className).toContain("text-right");
    });

    it("combines a declared className with the alignment", () => {
        const column = toColumnDef({ name: "total", align: "right", className: "font-mono" });

        expect((column.meta as { className?: string }).className).toBe("text-right font-mono");
    });

    it("defaults to sorting and hiding enabled", () => {
        const column = toColumnDef({ name: "email" });

        expect(flag(column, "enableSorting")).toBe(true);
        expect(flag(column, "enableHiding")).toBe(true);
    });

    it("honours sortable: false and hideable: false", () => {
        const column = toColumnDef({ name: "_actions", sortable: false, hideable: false });

        expect(flag(column, "enableSorting")).toBe(false);
        expect(flag(column, "enableHiding")).toBe(false);
    });

    it("passes the row and the resolved value to a custom cell", () => {
        let seen: unknown[] = [];

        const column = toColumnDef({
            name: "total",
            cell: (row, value) => {
                seen = [row.id, value];

                return "x";
            },
        });

        (column.cell as (context: unknown) => unknown)({ row: { original: { id: 7, total: 12 } } });

        expect(seen).toEqual([7, 12]);
    });
});

describe("flattenFields", () => {
    it("returns an empty list for no sections", () => {
        expect(flattenFields(undefined)).toEqual([]);
    });

    it("flattens every section in order", () => {
        const fields = flattenFields([
            { fields: [{ name: "a", type: "text" }] },
            { title: "More", fields: [{ name: "b", type: "text" }, { name: "c", type: "text" }] },
        ]);

        expect(fields.map((field) => field.name)).toEqual(["a", "b", "c"]);
    });
});

describe("humanizeFieldName", () => {
    it.each([
        ["created_at", "Created at"],
        ["first-name", "First name"],
        ["name", "Name"],
        ["lineTotal", "Line Total"],
    ])("turns %s into %s", (input, expected) => {
        expect(humanizeFieldName(input)).toBe(expected);
    });

    it("uses only the last segment of a dotted path", () => {
        // A field declared `shipping.address.city` should be labelled "City" -
        // the path is an implementation detail of where the value lives.
        expect(humanizeFieldName("shipping.address.city")).toBe("City");
    });
});

describe("readPath", () => {
    it("reads a flat key", () => {
        expect(readPath({ name: "widget" }, "name")).toBe("widget");
    });

    it("reads a nested path", () => {
        expect(readPath({ shipping: { address: { city: "Tunis" } } }, "shipping.address.city")).toBe("Tunis");
    });

    it("returns undefined rather than throwing on a missing segment", () => {
        // A form that throws while rendering a half-populated record is a form
        // that cannot show its own validation errors.
        expect(readPath({ a: 1 }, "a.b.c")).toBeUndefined();
        expect(readPath({}, "nope")).toBeUndefined();
    });
});

describe("isFieldVisible", () => {
    it("shows a plain field", () => {
        expect(isFieldVisible({ name: "a", type: "text" }, {})).toBe(true);
    });

    it("honours visibleWhen", () => {
        const field: FieldDefinition = {
            name: "reseller",
            type: "text",
            visibleWhen: (record: ResourceRecord) => record.kind === "business",
        };

        expect(isFieldVisible(field, { kind: "business" })).toBe(true);
        expect(isFieldVisible(field, { kind: "consumer" })).toBe(false);
    });

    it("keeps a hidden field visible so it still renders its input", () => {
        // Hidden means "not shown", not "not rendered" - a slug or a token still
        // has to reach the server.
        expect(isFieldVisible({ name: "id", type: "hidden", hidden: true, visibleWhen: () => false }, {})).toBe(true);
    });
});

describe("toResource", () => {
    const routeRef = (name: string, params?: Record<string, unknown>) =>
        `${name}${params ? `?${Object.entries(params).map(([k, v]) => `${k}=${v}`).join("&")}` : ""}`;

    it("rehydrates scalar, table and form keys", () => {
        const resource = toResource(
            {
                key: "shop/tax-rates",
                label: "Tax Rates",
                singular: "Tax Rate",
                table: { columns: [{ name: "name", header: "Name" }], searchKey: "name" },
                form: [{ title: "Details", fields: [{ name: "name", type: "text" }] }],
            },
            routeRef,
        );

        expect(resource.key).toBe("shop/tax-rates");
        expect(resource.label).toBe("Tax Rates");
        expect(resource.singular).toBe("Tax Rate");
        expect(resource.table?.searchKey).toBe("name");
        expect(resource.form?.[0]?.fields[0]?.name).toBe("name");
    });

    it("binds edit/update/destroy URLs on the literal record param", () => {
        const resource = toResource(
            {
                key: "shop/tax-rates",
                label: "Tax Rates",
                routes: {
                    index: "dashboard.shop.tax-rates.index",
                    create: "dashboard.shop.tax-rates.create",
                    store: "dashboard.shop.tax-rates.store",
                    edit: "dashboard.shop.tax-rates.edit",
                    update: "dashboard.shop.tax-rates.update",
                    destroy: "dashboard.shop.tax-rates.destroy",
                },
            },
            routeRef,
        );

        expect(resource.routes?.index).toBe("dashboard.shop.tax-rates.index");
        expect(resource.routes?.create).toBe("dashboard.shop.tax-rates.create");
        expect(resource.routes?.store).toBe("dashboard.shop.tax-rates.store");
        expect(resource.routes?.edit?.({ id: 7 })).toBe(`dashboard.shop.tax-rates.edit?${RESOURCE_RECORD_PARAM}=7`);
        expect(resource.routes?.update?.({ id: 7 })).toBe(`dashboard.shop.tax-rates.update?${RESOURCE_RECORD_PARAM}=7`);
        expect(resource.routes?.destroy?.({ id: 7 })).toBe(`dashboard.shop.tax-rates.destroy?${RESOURCE_RECORD_PARAM}=7`);
    });

    it("leaves record routes undefined when the name is absent or empty", () => {
        const resource = toResource(
            { key: "shop/tax-rates", label: "Tax Rates", routes: { edit: "", destroy: undefined } },
            routeRef,
        );

        expect(resource.routes?.edit).toBeUndefined();
        expect(resource.routes?.destroy).toBeUndefined();
    });

    it("binds record routes on a descriptor-declared recordParam", () => {
        const resource = toResource(
            {
                key: "shop.products",
                label: "Products",
                recordParam: "product",
                routes: {
                    edit: "dashboard.shop.products.edit",
                    destroy: "dashboard.shop.products.destroy",
                },
            },
            routeRef,
        );

        expect(resource.routes?.edit?.({ id: 7 })).toBe("dashboard.shop.products.edit?product=7");
        expect(resource.routes?.destroy?.({ id: 7 })).toBe("dashboard.shop.products.destroy?product=7");
    });

    it("falls back to the record param when the declaration is blank", () => {
        const resource = toResource(
            { key: "shop/tax-rates", label: "Tax Rates", recordParam: "  ", routes: { edit: "x.edit" } },
            routeRef,
        );

        expect(resource.routes?.edit?.({ id: 7 })).toBe(`x.edit?${RESOURCE_RECORD_PARAM}=7`);
    });

    it("resolves header action route names into URLs", () => {
        const resource = toResource(
            {
                key: "shop.products",
                label: "Products",
                table: {
                    columns: [{ name: "name" }],
                    headerActions: [
                        { label: "Export CSV", routeName: "dashboard.shop.products.export", method: "get" },
                        { label: "Import", routeName: "dashboard.shop.products.import", method: "post", fileInput: true, accept: ".csv" },
                    ],
                },
            },
            routeRef,
        );

        expect(resource.table?.headerActions).toEqual([
            { label: "Export CSV", route: "dashboard.shop.products.export", method: "get" },
            { label: "Import", route: "dashboard.shop.products.import", method: "post", fileInput: true, accept: ".csv" },
        ]);
    });

    it("carries bulkEndpoint and bulk action fields through untouched", () => {
        const resource = toResource(
            {
                key: "shop.products",
                label: "Products",
                table: {
                    columns: [{ name: "name" }],
                    bulkEndpoint: "dashboard.shop.products.bulk",
                    bulkActions: [
                        { label: "Delete", value: "delete", destructive: true },
                        { label: "Add Categories", value: "add_category", fields: [{ name: "category_ids", type: "relation", label: "Categories" }] },
                    ],
                },
            },
            routeRef,
        );

        expect(resource.table?.bulkEndpoint).toBe("dashboard.shop.products.bulk");
        expect(resource.table?.bulkActions?.[0]?.value).toBe("delete");
        expect(resource.table?.bulkActions?.[1]?.fields?.[0]?.name).toBe("category_ids");
    });

    it("drops relations — a PHP descriptor can never carry a React component", () => {
        const resource = toResource(
            { key: "shop/tax-rates", label: "Tax Rates", relations: [{ key: "lines" }] },
            routeRef,
        );

        expect(resource.relations).toBeUndefined();
    });

    it("resolves serialized row actions into record-bound routes with verb, confirm and destructive flags", () => {
        const resource = toResource(
            {
                key: "shop/reviews",
                label: "Reviews",
                table: {
                    columns: [{ name: "id", header: "ID" }],
                    rowActions: [
                        {
                            label: "Approve",
                            routeName: "dashboard.shop.reviews.approve",
                            method: "post",
                            confirm: "Approve this review?",
                        },
                        { label: "Delete", routeName: "dashboard.shop.reviews.destroy", method: "delete", destructive: true },
                        { label: "Show", routeName: "dashboard.shop.reviews.show" },
                    ],
                },
            },
            routeRef,
        );

        const approve = resource.table?.rowActions?.[0]!;
        const destroy = resource.table?.rowActions?.[1]!;
        const show = resource.table?.rowActions?.[2]!;

        expect(approve.route?.({ id: 7 })).toBe(`dashboard.shop.reviews.approve?${RESOURCE_RECORD_PARAM}=7`);
        expect(approve.method).toBe("post");
        expect(approve.confirm).toBe("Approve this review?");
        expect(approve.destructive).toBeUndefined();

        expect(destroy.route?.({ id: 9 })).toBe(`dashboard.shop.reviews.destroy?${RESOURCE_RECORD_PARAM}=9`);
        expect(destroy.method).toBe("delete");
        expect(destroy.destructive).toBe(true);

        // A serialized action with no verb walks (GET) — same as a TS-declared one.
        expect(show.method).toBe("get");
    });

    it("resolves a relation field optionsUrl route name into a URL", () => {
        const resource = toResource(
            {
                key: "shop/coupons",
                label: "Coupons",
                form: [
                    {
                        title: "Discount",
                        fields: [{ name: "product_ids", type: "relation", optionsUrl: "dashboard.shop.products.options" }],
                    },
                ],
            },
            routeRef,
        );

        // The routeRef appends no args, so a name resolves to exactly itself.
        expect(resource.form?.[0]?.fields[0]?.optionsUrl).toBe("dashboard.shop.products.options");
    });

    it("leaves an already-resolved optionsUrl URL untouched", () => {
        const resource = toResource(
            {
                key: "shop/coupons",
                label: "Coupons",
                form: [
                    {
                        title: "Discount",
                        fields: [{ name: "product_ids", type: "relation", optionsUrl: "/shop/products/options" }],
                    },
                ],
            },
            routeRef,
        );

        expect(resource.form?.[0]?.fields[0]?.optionsUrl).toBe("/shop/products/options");
    });

    it("resolves media upload/delete route names into URLs", () => {
        const resource = toResource(
            {
                key: "shop/products",
                label: "Products",
                form: [
                    {
                        title: "Media",
                        fields: [
                            {
                                name: "featured_image",
                                type: "media",
                                uploadUrl: "dashboard.shop.products.upload-image",
                                deleteUrl: "dashboard.shop.products.destroy-image",
                                subdirectory: "products",
                            },
                        ],
                    },
                ],
            },
            routeRef,
        );

        const media = resource.form?.[0]?.fields[0]!;

        // The routeRef appends no args, so each name resolves to exactly itself.
        expect(media.uploadUrl).toBe("dashboard.shop.products.upload-image");
        expect(media.deleteUrl).toBe("dashboard.shop.products.destroy-image");
        expect(media.subdirectory).toBe("products");
    });

    it("leaves already-resolved media URLs untouched", () => {
        const resource = toResource(
            {
                key: "shop/products",
                label: "Products",
                form: [
                    {
                        title: "Media",
                        fields: [{ name: "banner", type: "media", uploadUrl: "/shop/products/upload" }],
                    },
                ],
            },
            routeRef,
        );

        expect(resource.form?.[0]?.fields[0]?.uploadUrl).toBe("/shop/products/upload");
    });

    it("does not add endpoint keys a media field did not declare", () => {
        const resource = toResource(
            {
                key: "shop/reviews",
                label: "Reviews",
                form: [{ title: "Details", fields: [{ name: "product_image", type: "media" }] }],
            },
            routeRef,
        );

        const media = resource.form?.[0]?.fields[0] as unknown as Record<string, unknown>;

        expect(media.uploadUrl).toBeUndefined();
        expect(media.deleteUrl).toBeUndefined();
        expect(media.subdirectory).toBeUndefined();
    });

    it("carries searchKeys through to the runtime table", () => {
        const resource = toResource(
            {
                key: "shop/orders",
                label: "Orders",
                table: { columns: [{ name: "order_number", header: "Number" }], searchKeys: ["order_number", "customer_email"] },
            },
            routeRef,
        );

        expect(resource.table?.searchKeys).toEqual(["order_number", "customer_email"]);
    });

    it("resolves URL surfaces recursively inside a nested field's rows", () => {
        const resource = toResource(
            {
                key: "shop/zones",
                label: "Zones",
                form: [
                    {
                        title: "Methods",
                        fields: [
                            {
                                name: "methods",
                                type: "nested",
                                rows: [
                                    {
                                        name: "method",
                                        type: "relation",
                                        optionsUrl: "dashboard.shop.methods.options",
                                    },
                                    {
                                        name: "lead_image",
                                        type: "media",
                                        uploadUrl: "dashboard.shop.zones.upload-image",
                                    },
                                ],
                            },
                        ],
                    },
                ],
            },
            routeRef,
        );

        const nested = resource.form?.[0]?.fields[0] as unknown as { rows: Array<Record<string, unknown>> };

        expect(nested.rows[0]?.optionsUrl).toBe("dashboard.shop.methods.options");
        expect(nested.rows[1]?.uploadUrl).toBe("dashboard.shop.zones.upload-image");
    });

    it("leaves already-resolved URLs inside nested rows untouched", () => {
        const resource = toResource(
            {
                key: "shop/zones",
                label: "Zones",
                form: [
                    {
                        title: "Methods",
                        fields: [
                            {
                                name: "methods",
                                type: "nested",
                                rows: [
                                    {
                                        name: "method",
                                        type: "relation",
                                        optionsUrl: "/shop/methods/options",
                                    },
                                ],
                            },
                        ],
                    },
                ],
            },
            routeRef,
        );

        const nested = resource.form?.[0]?.fields[0] as unknown as { rows: Array<Record<string, unknown>> };

        expect(nested.rows[0]?.optionsUrl).toBe("/shop/methods/options");
    });

    it("does not drop a nested field's non-URL row sub-fields", () => {
        const resource = toResource(
            {
                key: "shop/zones",
                label: "Zones",
                form: [
                    {
                        title: "Methods",
                        fields: [
                            {
                                name: "methods",
                                type: "nested",
                                rows: [
                                    {
                                        name: "override_price",
                                        type: "money",
                                    },
                                    {
                                        name: "enabled",
                                        type: "boolean",
                                    },
                                ],
                            },
                        ],
                    },
                ],
            },
            routeRef,
        );

        const nested = resource.form?.[0]?.fields[0] as unknown as { rows: Array<{ name: string; type: string }> };

        expect(nested.rows.map((row) => row.name)).toEqual(["override_price", "enabled"]);
        expect(nested.rows[0]?.type).toBe("money");
    });
});

describe("rowMatchesSearchKeys", () => {
    it("keeps every row for an empty query", () => {
        expect(rowMatchesSearchKeys({ name: "Bolt" }, "", ["name"])).toBe(true);
        expect(rowMatchesSearchKeys({ name: "Bolt" }, "   ", ["name"])).toBe(true);
    });

    it("keeps a row matching any listed key", () => {
        const row = { name: "Jane", email: "jane@example.test", order_number: 1042 };

        // "jane" is only in `name` and "1042" only in `order_number`: either
        // match keeps the row, which is the OR a single column filter cannot do.
        expect(rowMatchesSearchKeys(row, "jane", ["email", "name"])).toBe(true);
        expect(rowMatchesSearchKeys(row, "1042", ["email", "name"])).toBe(false);
        expect(rowMatchesSearchKeys(row, "1042", ["email", "order_number"])).toBe(true);
    });

    it("matches case-insensitively", () => {
        expect(rowMatchesSearchKeys({ email: "Jane@Example.TEST" }, "jane", ["email"])).toBe(true);
    });

    it("skips keys that are absent or hold non-strings", () => {
        expect(rowMatchesSearchKeys({ name: "Bolt" }, "nut", ["name", "sku"])).toBe(false);
        expect(rowMatchesSearchKeys({ count: 7 }, "7", ["count"])).toBe(true);
    });

    it("reads nested keys through dotted paths", () => {
        expect(rowMatchesSearchKeys({ customer: { email: "jane@example.test" } }, "jane", ["customer.email"])).toBe(true);
    });

    it("keeps every row when no keys are listed", () => {
        expect(rowMatchesSearchKeys({ name: "Bolt" }, "bolt", [])).toBe(true);
    });
});

describe("resolveResourceRoute", () => {
    const routeRef = (name: string) => `/${name.replaceAll(".", "/")}`;

    it("builds a URL from a declared route name", () => {
        expect(resolveResourceRoute("dashboard.shop.tax-rates.index", undefined, routeRef)).toBe(
            "/dashboard/shop/tax-rates/index",
        );
    });

    it("returns undefined for an absent name", () => {
        expect(resolveResourceRoute(undefined, undefined, routeRef)).toBeUndefined();
        expect(resolveResourceRoute("", undefined, routeRef)).toBeUndefined();
    });

    it("returns undefined rather than throwing for an unknown name", () => {
        expect(
            resolveResourceRoute(
                "dashboard.ghost.index",
                undefined,
                () => {
                    throw new Error("Ziggy: route not found");
                },
            ),
        ).toBeUndefined();
    });
});

describe("buildPayload", () => {
    it("passes through only the fields it was given", () => {
        const payload = buildPayload({ a: "1", b: "2", stray: "3" }, [{ name: "a" }, { name: "b" }]);

        expect(payload).toEqual({ a: "1", b: "2" });
    });

    it("omits a declared field that is absent from the record", () => {
        // Sending `undefined` for an untouched field would put a key in the
        // payload that the server has to guess the meaning of.
        const payload = buildPayload({ a: "1" }, [{ name: "a" }, { name: "missing" }]);

        expect(payload).toEqual({ a: "1" });
    });

    it("omits a field that is present but undefined", () => {
        // The form seeds every declared field from its default, so an untouched
        // field is a key holding `undefined` rather than a missing key.
        const payload = buildPayload({ a: "1", b: undefined }, [{ name: "a" }, { name: "b" }]);

        expect(payload).toEqual({ a: "1" });
    });

    it("keeps a field the user deliberately emptied", () => {
        // A cleared text input is `""`, which is a value the server must see;
        // conflating it with `undefined` would silently keep the old value.
        const payload = buildPayload({ note: "" }, [{ name: "note" }]);

        expect(payload).toEqual({ note: "" });
    });

    it("keeps an explicit null", () => {
        expect(buildPayload({ note: null }, [{ name: "note" }])).toEqual({ note: null });
    });

    it("applies a field's own serialize function", () => {
        const payload = buildPayload({ price: "12.50" }, [{ name: "price", serialize: (value) => Number(value) }]);

        expect(payload).toEqual({ price: 12.5 });
    });

    it("runs serialize after the visible-field filter, not before", () => {
        const calls: string[] = [];

        buildPayload(
            { a: "1" },
            [
                {
                    name: "a",
                    serialize: () => {
                        calls.push("a");

                        return "A";
                    },
                },
            ],
        );

        expect(calls).toEqual(["a"]);
    });
});
