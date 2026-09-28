import { describe, it, expect } from "vitest";
import { buildPayload } from "../../Components/resources/ResourceForm";
import {
    defineResource,
    flattenFields,
    humanizeFieldName,
    isFieldVisible,
    readPath,
    relation,
    toColumnDef,
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
        const rows = { key: "test/thing", label: "Things", rowActions: [{ label: "Archive" }], relations: [relation({ key: "children", component: () => null })] };

        const resource = defineResource(rows as never);

        expect(resource.rowActions).toHaveLength(1);
        expect(resource.relations?.[0].key).toBe("children");
    });
});

describe("relation", () => {
    it("keeps the component it was given", () => {
        const Component = () => null;

        expect(relation({ key: "lines", component: Component }).component).toBe(Component);
    });
});

describe("toColumnDef", () => {
    it("maps a declared name onto an accessorKey", () => {
        const column = toColumnDef({ name: "email" });

        expect(column.accessorKey).toBe("email");
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

        expect(column.enableSorting).toBe(true);
        expect(column.enableHiding).toBe(true);
    });

    it("honours sortable: false and hideable: false", () => {
        const column = toColumnDef({ name: "_actions", sortable: false, hideable: false });

        expect(column.enableSorting).toBe(false);
        expect(column.enableHiding).toBe(false);
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
        const field = { name: "reseller", type: "text", visibleWhen: (record: ResourceRecord) => record.kind === "business" };

        expect(isFieldVisible(field, { kind: "business" })).toBe(true);
        expect(isFieldVisible(field, { kind: "consumer" })).toBe(false);
    });

    it("keeps a hidden field visible so it still renders its input", () => {
        // Hidden means "not shown", not "not rendered" - a slug or a token still
        // has to reach the server.
        expect(isFieldVisible({ name: "id", type: "hidden", hidden: true, visibleWhen: () => false }, {})).toBe(true);
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
