import { describe, it, expect, vi, afterEach } from "vitest";
import { render, screen, cleanup, fireEvent, waitFor } from "@testing-library/react";
import { ResourceField, getFieldType, registerFieldType, registeredFieldTypes } from "../fields";
import type { FieldContext, FieldDefinition, ResourceRecord } from "../../../lib/resource-schema";

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
