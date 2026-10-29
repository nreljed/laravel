import { describe, it, expect, vi, afterEach } from "vitest";
import { render, screen, cleanup, fireEvent, waitFor } from "@testing-library/react";
import { ResourceField, getFieldType, registerFieldType, registeredFieldTypes } from "../fields";
import type { FieldContext, FieldDefinition, ResourceRecord } from "../../../lib/resource-schema";
import type { ServerGalleryImage } from "../../../types/gallery";

/**
 * The field registry and the field renderer.
 *
 * A registry with no tests is a registry that quietly loses its built-ins: the
 * registration loop at the bottom of fields.tsx is module-scope side effect, and
 * "which types are actually available" is a question a consuming app needs
 * answered, not assumed.
 */
function contextFor(field: FieldDefinition, record: ResourceRecord = {}, value: unknown = undefined): FieldContext {
    return {
        field,
        record,
        value: value ?? (record[field.name] as unknown),
        setValue: () => undefined,
        setField: () => undefined,
    };
}

afterEach(cleanup);

describe("field type registry", () => {
    it.each([
        "text",
        "textarea",
        "number",
        "money",
        "select",
        "boolean",
        "date",
        "datetime",
        "slug",
        "json",
        "media",
        "password",
        "hidden",
        "relation",
        "checkboxList",
        "nested",
    ])("has a built-in control for %s", (type) => {
        expect(getFieldType(type as never)?.control).toBeDefined();
    });

    it("does not register 'dynamic', which is handled by the field's own render", () => {
        // `dynamic` with no `render` renders nothing on purpose, so giving it a
        // built-in control would quietly paper over a missing render function.
        expect(getFieldType("dynamic")).toBeUndefined();
    });

    it("exposes the registered types", () => {
        expect(registeredFieldTypes()).toContain("money");
    });

    it("lets a consuming app add a type", () => {
        const Custom = () => null;

        // A name no built-in or other test uses: the registry is module state, so
        // registering `custom` here would make the "no control registered"
        // assertion below order-dependent.
        registerFieldType("colour-swatch" as never, { control: Custom });

        expect(getFieldType("colour-swatch" as never)?.control).toBe(Custom);
    });

    it("lets a consuming app override a built-in", () => {
        const Replacement = () => null;
        const original = getFieldType("text")?.control;

        registerFieldType("text", { control: Replacement });

        expect(getFieldType("text")?.control).toBe(Replacement);

        // Restored so the override test does not leak into the others.
        registerFieldType("text", { control: original! });
    });
});

describe("ResourceField", () => {
    it("labels a field from its name when none is declared", () => {
        render(<ResourceField context={contextFor({ name: "created_at", type: "text" })} />);

        expect(screen.getByText("Created at")).toBeDefined();
    });

    it("prefers a declared label", () => {
        render(<ResourceField context={contextFor({ name: "created_at", type: "text", label: "Added" })} />);

        expect(screen.getByText("Added")).toBeDefined();
    });

    it("marks a required field", () => {
        const { container } = render(<ResourceField context={contextFor({ name: "name", type: "text", required: true })} />);

        expect(container.querySelector(".text-destructive")).not.toBeNull();
    });

    it("renders a server error as an alert", () => {
        render(<ResourceField context={{ ...contextFor({ name: "name", type: "text" }), error: "Name is required." }} />);

        expect(screen.getByRole("alert").textContent).toContain("Name is required.");
    });

    it("swaps help text for the error when both are present", () => {
        // Showing "Your public URL" under a field that is already telling you it
        // is taken is noise that pushes the actual message down.
        render(
            <ResourceField
                context={{ ...contextFor({ name: "name", type: "text", help: "Your public URL" }), error: "Already taken." }}
            />,
        );

        expect(screen.queryByText("Your public URL")).toBeNull();
        expect(screen.getByRole("alert").textContent).toContain("Already taken.");
    });

    it("renders a select with its options", () => {
        render(
            <ResourceField
                context={contextFor({
                    name: "status",
                    type: "select",
                    options: [
                        { label: "Draft", value: "draft" },
                        { label: "Live", value: "live" },
                    ],
                })}
            />,
        );

        expect(screen.getByText("Draft")).toBeDefined();
        expect(screen.getByText("Live")).toBeDefined();
    });

    it("keeps a hidden field rendered but unlabelled", () => {
        const { container } = render(
            <ResourceField context={contextFor({ name: "confirmation_token", type: "hidden", hidden: true })} />,
        );

        expect(container.querySelector('[name="confirmation_token"]')).not.toBeNull();
        expect(screen.queryByText("Confirmation token")).toBeNull();
    });

    it("prefers a field's own render over the registered control", () => {
        // The escape hatch is documented as winning; if the registry ever took
        // precedence, a field could never locally override its own type.
        render(<ResourceField context={contextFor({ name: "name", type: "text", render: () => <span>custom control</span> })} />);

        expect(screen.getByText("custom control")).toBeDefined();
    });

    it("renders a dynamic field's own render", () => {
        render(<ResourceField context={contextFor({ name: "variations", type: "dynamic", render: () => <span>variation table</span> })} />);

        expect(screen.getByText("variation table")).toBeDefined();
    });

    it("names the field and type when no control is registered", () => {
        // A silent empty cell would be read as "this field is intentionally
        // blank" rather than "this app forgot to register a type".
        render(<ResourceField context={contextFor({ name: "colour", type: "dynamic" })} />);

        expect(screen.getByText(/No control registered for field type/)).toBeDefined();
    });
});

describe("relation field", () => {
    /** A relation context whose `setValue` is observable. */
    function relationContext(field: FieldDefinition, value: unknown = []) {
        const setValue = vi.fn();
        const context: FieldContext = {
            field,
            record: { [field.name]: value } as ResourceRecord,
            value,
            setValue,
            setField: () => undefined,
        };

        return { context, setValue };
    }

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it("renders the selected values as chips", () => {
        const { context } = relationContext(
            {
                name: "events",
                type: "relation",
                label: "Events",
                options: [
                    { label: "Order created", value: "order.created" },
                    { label: "Order paid", value: "order.paid" },
                ],
            },
            ["order.paid"],
        );

        render(<ResourceField context={context} />);

        expect(screen.getByText("Order paid")).toBeDefined();
        // The unselected option is only a popup item, not a chip.
        expect(screen.queryByText("Order created")).toBeNull();
    });

    it("lists the declared options and stores the picked values as scalars", async () => {
        const { context, setValue } = relationContext({
            name: "events",
            type: "relation",
            label: "Events",
            options: [
                { label: "Order created", value: "order.created" },
                { label: "Order paid", value: "order.paid" },
            ],
        });

        render(<ResourceField context={context} />);

        fireEvent.mouseDown(screen.getByRole("combobox"));

        const option = await screen.findByText("Order created");

        fireEvent.click(option);

        expect(setValue).toHaveBeenCalledWith(["order.created"]);
    });

    it("loads options from optionsUrl and hands the scalars to setValue", async () => {
        const fetchSpy = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => [
                { label: "T-Shirt", value: 1 },
                { label: "Mug", value: 2 },
            ],
        });
        vi.stubGlobal("fetch", fetchSpy);

        const { context, setValue } = relationContext({
            name: "product_ids",
            type: "relation",
            label: "Products",
            optionsUrl: "/shop/products/options",
        });

        render(<ResourceField context={context} />);

        await waitFor(() => {
            expect(fetchSpy).toHaveBeenCalledWith("/shop/products/options", expect.objectContaining({ headers: expect.anything() }));
        });

        fireEvent.mouseDown(screen.getByRole("combobox"));

        const option = await screen.findByText("T-Shirt");

        fireEvent.click(option);

        expect(setValue).toHaveBeenCalledWith([1]);
    });

    it("surfaces a failed options load without breaking the field", async () => {
        vi.stubGlobal("fetch", vi.fn().mockRejectedValue(new Error("Failed to load options (500)")));

        const { context } = relationContext({
            name: "product_ids",
            type: "relation",
            label: "Products",
            optionsUrl: "/shop/products/options",
        });

        render(<ResourceField context={context} />);

        const error = await screen.findByText("Failed to load options (500)");

        expect(error).toBeDefined();
    });
});

/**
 * `checkboxList` is `relation`'s twin: same scalar-array value, same option
 * sources, different shape. The distinction that matters is that a list shows
 * its whole catalog at once, so it is asserted against the things a combobox
 * cannot do - no search affordance, and a tick per option.
 */
describe("checkboxList field", () => {
    function listContext(field: FieldDefinition, value: unknown = []) {
        const setValue = vi.fn();
        const context: FieldContext = {
            field,
            record: { [field.name]: value } as ResourceRecord,
            value,
            setValue,
            setField: () => undefined,
        };

        return { context, setValue };
    }

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it("lists every declared option at once, with no search box", () => {
        const { context } = listContext({
            name: "category_ids",
            type: "checkboxList",
            label: "Categories",
            options: [
                { label: "Apparel", value: 1 },
                { label: "Mugs", value: 2 },
            ],
        });

        render(<ResourceField context={context} />);

        expect(screen.getByText("Apparel")).toBeDefined();
        expect(screen.getByText("Mugs")).toBeDefined();
        // The combobox a `relation` renders is the affordance this type exists
        // to avoid - if one appears, the two have drifted into each other.
        expect(screen.queryByRole("combobox")).toBeNull();
    });

    it("adds a ticked value to the array and removes an unticked one", () => {
        const { context, setValue } = listContext(
            {
                name: "category_ids",
                type: "checkboxList",
                label: "Categories",
                options: [
                    { label: "Apparel", value: 1 },
                    { label: "Mugs", value: 2 },
                ],
            },
            [1],
        );

        render(<ResourceField context={context} />);

        /* Addressed by role: a Base UI Checkbox ships a hidden input carrying
         * the same label, so getByLabelText would match two elements. */
        fireEvent.click(screen.getByRole("checkbox", { name: "Mugs" }));

        expect(setValue).toHaveBeenCalledWith([1, 2]);
    });

    it("drops a value whose option is unticked", () => {
        const { context, setValue } = listContext(
            {
                name: "category_ids",
                type: "checkboxList",
                label: "Categories",
                options: [
                    { label: "Apparel", value: 1 },
                    { label: "Mugs", value: 2 },
                ],
            },
            [1, 2],
        );

        render(<ResourceField context={context} />);

        fireEvent.click(screen.getByRole("checkbox", { name: "Apparel" }));

        expect(setValue).toHaveBeenCalledWith([2]);
    });

    it("reports how many are selected", () => {
        const { context } = listContext(
            {
                name: "category_ids",
                type: "checkboxList",
                label: "Categories",
                options: [{ label: "Apparel", value: 1 }],
            },
            [1],
        );

        render(<ResourceField context={context} />);

        expect(screen.getByText("1 selected")).toBeDefined();
    });

    it("loads options from optionsUrl like relation does", async () => {
        const fetchSpy = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => [
                { label: "Apparel", value: 1 },
                { label: "Mugs", value: 2 },
            ],
        });

        vi.stubGlobal("fetch", fetchSpy);

        const { context, setValue } = listContext({
            name: "category_ids",
            type: "checkboxList",
            label: "Categories",
            optionsUrl: "/shop/categories/options",
        });

        render(<ResourceField context={context} />);

        const option = await screen.findByRole("checkbox", { name: "Apparel" });

        fireEvent.click(option);

        expect(setValue).toHaveBeenCalledWith([1]);
    });

    it("explains an empty catalog instead of rendering an empty box", async () => {
        const { context } = listContext({
            name: "category_ids",
            type: "checkboxList",
            label: "Categories",
            options: [],
        });

        render(<ResourceField context={context} />);

        expect(screen.getByText(/nothing to pick here yet/i)).toBeDefined();
    });
});

describe("select field with lazy optionsUrl", () => {
    function selectContext(field: FieldDefinition, value: unknown = "") {
        const setValue = vi.fn();
        const context: FieldContext = {
            field,
            record: { [field.name]: value } as ResourceRecord,
            value,
            setValue,
            setField: () => undefined,
        };

        return { context, setValue };
    }

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it("loads options from optionsUrl and stores the picked value", async () => {
        const fetchSpy = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => [
                { label: "Standard", value: 1 },
                { label: "Express", value: 2 },
            ],
        });
        vi.stubGlobal("fetch", fetchSpy);

        const { context, setValue } = selectContext({
            name: "method_id",
            type: "select",
            label: "Method",
            optionsUrl: "/shop/shipping-methods/options",
        });

        render(<ResourceField context={context} />);

        await waitFor(() => {
            expect(fetchSpy).toHaveBeenCalledWith("/shop/shipping-methods/options", expect.objectContaining({ headers: expect.anything() }));
        });

        const option = await screen.findByText("Express");

        fireEvent.change(screen.getByRole("combobox"), { target: { value: "2" } });

        expect(setValue).toHaveBeenCalledWith("2");
        expect(option).toBeDefined();
    });

    it("surfaces a failed options load without breaking the select", async () => {
        vi.stubGlobal("fetch", vi.fn().mockRejectedValue(new Error("Failed to load options (500)")));

        const { context } = selectContext({
            name: "method_id",
            type: "select",
            label: "Method",
            optionsUrl: "/shop/shipping-methods/options",
        });

        render(<ResourceField context={context} />);

        const error = await screen.findByText("Failed to load options (500)");

        expect(error).toBeDefined();
    });

    it("keeps working from a static options list when no optionsUrl is declared", () => {
        const { context, setValue } = selectContext({
            name: "method_id",
            type: "select",
            label: "Method",
            options: [
                { label: "Standard", value: 1 },
                { label: "Express", value: 2 },
            ],
        });

        render(<ResourceField context={context} />);

        expect(screen.getByText("Standard")).toBeDefined();

        fireEvent.change(screen.getByRole("combobox"), { target: { value: "1" } });

        expect(setValue).toHaveBeenCalledWith("1");
    });
});

describe("money field", () => {
    function moneyContext(field: FieldDefinition, record: ResourceRecord = {}, value: unknown = undefined) {
        const setValue = vi.fn();
        const setField = vi.fn();
        const context: FieldContext = {
            field,
            record: { ...record, unit_price: value },
            value,
            setValue,
            setField,
        };

        return { context, setValue, setField };
    }

    it("renders a currency select from declared options", () => {
        const { context, setField } = moneyContext(
            {
                name: "unit_price",
                type: "money",
                options: [
                    { label: "EUR", value: "EUR" },
                    { label: "USD", value: "USD" },
                ],
            },
            { unit_price_currency: "EUR" },
        );

        render(<ResourceField context={context} />);

        const select = screen.getByLabelText("Unit price currency") as HTMLSelectElement;

        expect(select.value).toBe("EUR");
        expect(screen.getByRole("option", { name: "USD" })).toBeDefined();

        fireEvent.change(select, { target: { value: "USD" } });

        expect(setField).toHaveBeenCalledWith("unit_price_currency", "USD");
    });

    it("falls back to a free-text currency input when no options are declared", () => {
        const { context } = moneyContext(
            { name: "legacy_price", type: "money" },
            { legacy_price_currency: "TND" },
        );

        render(<ResourceField context={context} />);

        const input = screen.getByLabelText("Legacy price currency") as HTMLInputElement;

        expect(input.tagName).toBe("INPUT");
        expect(input.value).toBe("TND");
        expect(input.placeholder).toBe("EUR");
    });

    it("keeps a declared currency selector render separate from the amount", () => {
        // The amount keeps editing as a plain string; the currency owns its own
        // form key and must never travel through the amount's onChange.
        const { context, setValue } = moneyContext({
            name: "unit_price",
            type: "money",
            options: [{ label: "EUR", value: "EUR" }],
        });

        render(<ResourceField context={context} />);

        fireEvent.change(screen.getByLabelText("Unit price"), { target: { value: "12.50" } });

        expect(setValue).toHaveBeenCalledWith("12.50");
    });
});

describe("nested field", () => {
    /** A nested context whose `setValue` is observable. */
    function nestedContext(
        rows: FieldDefinition[],
        value: unknown = [],
        options: { errors?: Record<string, string>; rowTitleField?: string } = {},
    ) {
        const setValue = vi.fn();
        const context: FieldContext = {
            field: {
                name: "methods",
                type: "nested",
                label: "Shipping methods",
                rows,
                rowTitleField: options.rowTitleField,
            },
            record: { methods: value } as ResourceRecord,
            value,
            setValue,
            setField: () => undefined,
            errors: options.errors,
        };

        return { context, setValue };
    }

    const methodRows: FieldDefinition[] = [
        { name: "method_id", type: "relation", label: "Method", options: [{ label: "Flat rate", value: "flat" }] },
        { name: "name", type: "text", label: "Label" },
        { name: "enabled", type: "boolean", label: "Enabled" },
    ];

    it("renders every sub-field for every row", () => {
        const { context } = nestedContext(
            methodRows,
            [
                { id: 1, method_id: ["flat"], name: "Standard", enabled: true },
                { id: 2, method_id: [], name: "Express", enabled: false },
            ],
            { rowTitleField: "name" },
        );

        render(<ResourceField context={context} />);

        expect(screen.getByText("Standard")).toBeDefined();
        expect(screen.getByText("Express")).toBeDefined();

fireEvent.click(screen.getByText("Standard"));
        fireEvent.click(screen.getByText("Express"));

        expect(screen.getAllByLabelText("Label")).toHaveLength(2);
        expect(screen.getAllByRole("combobox")).toHaveLength(2);
        expect(screen.getAllByRole("switch")).toHaveLength(2);
    });

    it("shows the empty state and adds a defaulted row", () => {
        const { context, setValue } = nestedContext([{ name: "name", type: "text", label: "Label" }]);

        render(<ResourceField context={context} />);

        expect(screen.getByText(/No shipping methods yet/)).toBeDefined();

        fireEvent.click(screen.getByText(/^Add shipping methods/));

        expect(setValue).toHaveBeenCalledTimes(1);

        const added = setValue.mock.calls[0]![0] as ResourceRecord[];
        expect(added).toHaveLength(1);
        expect(added[0]!.name).toBeUndefined();
    });

    it("propagates a sub-field edit as a whole-row update", () => {
        const { context, setValue } = nestedContext(
            [{ name: "name", type: "text", label: "Label" }],
            [{ id: 7, name: "Standard" }, { id: 8, name: "Express" }],
        );

        render(<ResourceField context={context} />);

        fireEvent.click(screen.getByText("Standard"));
        fireEvent.change(screen.getAllByLabelText("Label")[0]!, { target: { value: "Priority" } });

        expect(setValue).toHaveBeenCalledWith([{ id: 7, name: "Priority" }, { id: 8, name: "Express" }]);
    });

    it("resolves a sub-field server error from the dotted key it arrives under", () => {
        const { context } = nestedContext(
            [{ name: "name", type: "text", label: "Label" }],
            [{ name: "" }],
            { errors: { "methods.0.name": "The Label field is required." } },
        );

        render(<ResourceField context={context} />);

        fireEvent.click(screen.getByText("Item 1"));
        expect(screen.getByRole("alert").textContent).toContain("The Label field is required.");
    });
});

/**
 * `featuredField` is how a record keeps a gallery's cover as a URL of its own -
 * a product's `thumbnail_url` beside its `gallery`. The gallery itself cannot do
 * that: it only knows the image list, so the write has to come from the field.
 */
describe("media field featuredField", () => {
    function galleryContext(field: FieldDefinition, images: ServerGalleryImage[], setField = vi.fn()) {
        return {
            context: { ...contextFor(field, {}, images), setField } satisfies FieldContext,
            setField,
        };
    }

    it("writes the cover image url into the declared companion field", () => {
        const { context, setField } = galleryContext(
            { name: "gallery", type: "media", multiple: true, featuredField: "thumbnail_url" },
            [
                { url: "/storage/first.webp" },
                { url: "/storage/second.webp" },
            ],
        );

        render(<ResourceField context={context} />);

        // The second image is not the cover yet, so it offers "set as cover".
        fireEvent.click(screen.getByTitle("Set as cover image"));

        expect(setField).toHaveBeenCalledWith("thumbnail_url", "/storage/second.webp");
    });

    it("leaves the companion field alone when the field declares no companion", () => {
        const { context, setField } = galleryContext({ name: "gallery", type: "media", multiple: true }, [
            { url: "/storage/first.webp" },
            { url: "/storage/second.webp" },
        ]);

        render(<ResourceField context={context} />);

        fireEvent.click(screen.getByTitle("Set as cover image"));

        // Reordering is still the gallery's business; without a declared
        // companion there is simply no sibling field to write.
        expect(setField).not.toHaveBeenCalled();
    });

    it("does not offer set-as-cover on the single-image control", () => {
        const setField = vi.fn();
        const context = { ...contextFor({ name: "thumbnail_url", type: "media", featuredField: "gallery" }, {}, "/storage/first.webp"), setField };

        render(<ResourceField context={context} />);

        // A single picker has no list to reorder, so "cover" is its only image
        // and the companion declaration has nothing to mirror.
        expect(screen.queryByTitle("Set as cover image")).toBeNull();
        expect(setField).not.toHaveBeenCalled();
    });
});
