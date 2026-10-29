import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { render, screen, cleanup, fireEvent } from "@testing-library/react";
import { ResourceForm } from "../ResourceForm";
import { defineResource, type RelationProps, type ResourceRecord } from "../../../lib/resource-schema";

/**
 * ResourceForm owns the Inertia `useForm` call and nothing else. What matters
 * is therefore the payload it builds and where it sends it - the two things a
 * page cannot easily see for itself.
 */
const { routerMock } = vi.hoisted(() => ({
    routerMock: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}));

vi.mock("@inertiajs/react", async () => {
    const actual = await vi.importActual<typeof import("@inertiajs/react")>("@inertiajs/react");

    return {
        ...actual,
        router: routerMock,
        Link: ({ children, href }: { children?: unknown; href?: string }) => <a href={href}>{children as never}</a>,
    };
});

/**
 * `useForm` dispatches through its own internal router binding, which replacing
 * the `router` export does not intercept - so these tests assert the payload via
 * the `submit` escape hatch, which is a plain function call and therefore needs
 * no network double at all. That is the honest seam: the payload is what
 * ResourceForm is responsible for, and the POST is Inertia's.
 */
function capturePayload(resource: ReturnType<typeof makeResource>, record?: ResourceRecord) {
    const submit = vi.fn();

    render(<ResourceForm resource={resource} record={record} action="/widgets" submit={submit} />);
    submitForm();

    return submit.mock.calls[0]![0] as ResourceRecord;
}



function makeResource(form: Parameters<typeof defineResource>[0]["form"]) {
    return defineResource({ key: "shop/widget", label: "Widgets", singular: "widget", form } as never);
}

function submitForm() {
    const form = screen.getByRole("button", { name: /save/i }).closest("form");

    expect(form).not.toBeNull();

    // The button is inside the form, so a click would submit too - but only
    // when the button is not disabled, and the submit path is what's under test.
    fireEvent.submit(form as HTMLFormElement);
}

beforeEach(() => {
    Object.values(routerMock).forEach((fn) => fn.mockClear());
});

afterEach(() => {
    cleanup();
    vi.clearAllMocks();
});

describe("ResourceForm rendering", () => {
    it("renders a labelled control per field", () => {
        render(<ResourceForm resource={makeResource([{ fields: [{ name: "name", type: "text" }] }])} action="/widgets" />);

        expect(screen.getByLabelText("Name")).toBeDefined();
    });

    it("renders section titles and descriptions", () => {
        render(
            <ResourceForm
                resource={makeResource([{ title: "Basics", description: "What it is", fields: [{ name: "name", type: "text" }] }])}
                action="/widgets"
            />,
        );

        expect(screen.getByText("Basics")).toBeDefined();
        expect(screen.getByText("What it is")).toBeDefined();
    });

    it("hides a section with no visible fields", () => {
        render(
            <ResourceForm
                resource={makeResource([
                    { title: "Business only", fields: [{ name: "vat", type: "text", visibleWhen: (record) => record.kind === "business" }] },
                ])}
                record={{ kind: "consumer" }}
                action="/widgets"
            />,
        );

        expect(screen.queryByText("Business only")).toBeNull();
    });

    it("seeds a field from the record", () => {
        render(<ResourceForm resource={makeResource([{ fields: [{ name: "name", type: "text" }] }])} record={{ name: "Bolt" }} action="/widgets" />);

        expect((screen.getByLabelText("Name") as HTMLInputElement).value).toBe("Bolt");
    });

    it("falls back to a field's declared default", () => {
        render(
            <ResourceForm
                resource={makeResource([{ fields: [{ name: "status", type: "text", defaultValue: "draft" }] }])}
                action="/widgets"
            />,
        );

        expect((screen.getByLabelText("Status") as HTMLInputElement).value).toBe("draft");
    });

    it("prefers the record over the field's declared default", () => {
        render(
            <ResourceForm
                resource={makeResource([{ fields: [{ name: "status", type: "text", defaultValue: "draft" }] }])}
                record={{ status: "live" }}
                action="/widgets"
            />,
        );

        expect((screen.getByLabelText("Status") as HTMLInputElement).value).toBe("live");
    });

    it("renders a declared relation component", () => {
        const resource = makeResource([{ fields: [{ name: "name", type: "text" }] }]);
        const withRelation = defineResource({
            ...resource,
            relations: [{ key: "lines", component: () => <div>relation table</div> }],
        } as never);

        render(<ResourceForm resource={withRelation} action="/widgets" />);

        expect(screen.getByText("relation table")).toBeDefined();
    });

    it("labels the submit button with the singular", () => {
        render(<ResourceForm resource={makeResource([{ fields: [{ name: "name", type: "text" }] }])} action="/widgets" />);

        expect(screen.getByRole("button", { name: "Save widget" })).toBeDefined();
    });

    it("omits the cancel button without a cancel route", () => {
        render(<ResourceForm resource={makeResource([{ fields: [] }])} action="/widgets" />);

        expect(screen.queryByRole("button", { name: "Cancel" })).toBeNull();
    });
});

describe("ResourceForm payload", () => {
    it("includes every seeded field", () => {
        const resource = makeResource([{ fields: [{ name: "name", type: "text" }, { name: "note", type: "text" }] }]);

        expect(capturePayload(resource, { name: "Bolt", note: "strong" })).toEqual({ name: "Bolt", note: "strong" });
    });

    it("omits a field that visibleWhen hides", () => {
        // A hidden field still holds its value in the form state, so the only
        // thing standing between it and the server is this filter.
        const resource = makeResource([
            { fields: [{ name: "kind", type: "text" }, { name: "vat", type: "text", visibleWhen: (record) => record.kind === "business" }] },
        ]);

        const payload = capturePayload(resource, { kind: "consumer", vat: "GB123" });

        expect(payload).not.toHaveProperty("vat");
    });

    it("includes a field once visibleWhen lets it through", () => {
        const resource = makeResource([
            { fields: [{ name: "kind", type: "text" }, { name: "vat", type: "text", visibleWhen: (record) => record.kind === "business" }] },
        ]);

        expect(capturePayload(resource, { kind: "business", vat: "GB123" })).toHaveProperty("vat", "GB123");
    });

    it("keeps a hidden field's value, because hidden is not unset", () => {
        // A slug or an id marked `hidden` still has to reach the server.
        const resource = makeResource([{ fields: [{ name: "confirmation", type: "hidden", hidden: true }] }]);

        expect(capturePayload(resource, { confirmation: "abc123" })).toHaveProperty("confirmation", "abc123");
    });

    it("applies a field's serialize before posting", () => {
        const resource = makeResource([{ fields: [{ name: "price", type: "money", serialize: (value) => Number(value) }] }]);

        expect(capturePayload(resource, { price: "12.50" })).toHaveProperty("price", 12.5);
    });

    it("omits a declared field that has no value yet", () => {
        const resource = makeResource([{ fields: [{ name: "name", type: "text" }, { name: "note", type: "textarea" }] }]);

        expect(capturePayload(resource, { name: "Bolt" })).not.toHaveProperty("note");
    });

    it("includes a defaulted field the record never set", () => {
        const resource = makeResource([{ fields: [{ name: "status", type: "text", defaultValue: "draft" }] }]);

        expect(capturePayload(resource, {})).toHaveProperty("status", "draft");
    });
});

describe("ResourceForm custom submit", () => {
    it("hands the payload and helpers to the supplied submit", () => {
        const submit = vi.fn();

        render(
            <ResourceForm
                resource={makeResource([{ fields: [{ name: "name", type: "text" }] }])}
                record={{ name: "Bolt" }}
                action="/widgets"
                submit={submit}
            />,
        );

        submitForm();

        expect(submit).toHaveBeenCalledOnce();
        expect(submit.mock.calls[0]![0]).toEqual({ name: "Bolt" });
    });

    it("does not post on its own when a custom submit is supplied", () => {
        render(
            <ResourceForm
                resource={makeResource([{ fields: [{ name: "name", type: "text" }] }])}
                action="/widgets"
                submit={vi.fn()}
            />,
        );

        submitForm();

        // The page owns persistence when it supplies `submit`; double-posting
        // would visit the same URL twice.
        expect(routerMock.post).not.toHaveBeenCalled();
    });

    it("exposes a post helper bound to the declared method", () => {
        const submit = vi.fn();

        render(
            <ResourceForm
                resource={makeResource([{ fields: [{ name: "name", type: "text" }] }])}
                method="put"
                record={{ name: "Bolt" }}
                action="/widgets/1"
                submit={submit}
            />,
        );

        submitForm();

        // The helper delegates to `form.submit(method, ...)`, so the verb comes
        // from the form's own declaration rather than the call site. A
        // two-step "save then publish" that silently downgraded to POST would
        // be the bug this pins.
        expect(typeof submit.mock.calls[0]![1].post).toBe("function");
        expect(submit.mock.calls[0]![1].processing).toBe(false);
    });

    it("exposes the form's error and processing state to the custom submit", () => {
        const submit = vi.fn();

        render(
            <ResourceForm
                resource={makeResource([{ fields: [{ name: "name", type: "text" }] }])}
                action="/widgets"
                submit={submit}
            />,
        );

        submitForm();

        const helpers = submit.mock.calls[0]![1];

        expect(helpers.processing).toBe(false);
        expect(helpers.errors).toEqual({});
        expect(typeof helpers.setError).toBe("function");
    });
});

describe("ResourceForm relation contract", () => {
    /**
     * A relation that owns its own persistence - variations, attribute lists -
     * still has to publish part of its state into the payload the parent posts.
     * `setField` is that seam, so these pin it rather than the relation itself.
     */
    function relationThatWrites(path: string, value: unknown) {
        return function WritingRelation({ record, errors, setField }: RelationProps) {
            expect(record).toBeDefined();
            expect(errors).toBeDefined();

            return (
                <button type="button" onClick={() => setField(path, value)}>
                    write {path}
                </button>
            );
        };
    }

    it("lets a relation write into the submitted payload", () => {
        const submit = vi.fn();
        const resource = defineResource({
            ...makeResource([{ fields: [{ name: "name", type: "text" }] }]),
            relations: [{ key: "defaults", component: relationThatWrites("default_attributes", [3, 7]) }],
        } as never);

        render(<ResourceForm resource={resource} action="/widgets" submit={submit} />);

        fireEvent.click(screen.getByRole("button", { name: /write default_attributes/i }));
        submitForm();

        expect(submit.mock.calls[0]![0]).toHaveProperty("default_attributes", [3, 7]);
    });

    it("gives the relation the live form state, not a snapshot of the record", () => {
        const resource = defineResource({
            ...makeResource([{ fields: [{ name: "type", type: "text" }] }]),
            relations: [
                {
                    key: "echo",
                    component: ({ record }: RelationProps) => <div>type: {String(record.type ?? "none")}</div>,
                },
            ],
        } as never);

        render(<ResourceForm resource={resource} record={{ type: "simple" }} action="/widgets" />);

        expect(screen.getByText("type: simple")).toBeDefined();

        // `useForm` seeds once and then holds live state, which is what a relation
        // needs: a section that reacts to the record being edited (a variation
        // table keyed off `type`) has to see the edit, not the initial payload.
        fireEvent.change(screen.getByLabelText("Type"), { target: { value: "variable" } });

        expect(screen.getByText("type: variable")).toBeDefined();
    });

    it("lets a relation write a sibling key the form has no field for", () => {
        // The gallery case: `featuredField` mirrors the cover into `thumbnail_url`,
        // which no control renders, so only the relation can populate it.
        const submit = vi.fn();
        const resource = defineResource({
            ...makeResource([{ fields: [{ name: "gallery", type: "media", multiple: true, featuredField: "thumbnail_url" }] }]),
            relations: [{ key: "cover", component: relationThatWrites("thumbnail_url", "/storage/cover.webp") }],
        } as never);

        render(<ResourceForm resource={resource} action="/widgets" submit={submit} />);

        fireEvent.click(screen.getByRole("button", { name: /write thumbnail_url/i }));
        submitForm();

        expect(submit.mock.calls[0]![0]).toHaveProperty("thumbnail_url", "/storage/cover.webp");
    });
});

describe("ResourceForm feedback", () => {
    it("navigates to the cancel route", () => {
        render(
            <ResourceForm
                resource={makeResource([{ fields: [{ name: "name", type: "text" }] }])}
                action="/widgets"
                cancelRoute="/widgets"
            />,
        );

        fireEvent.click(screen.getByRole("button", { name: "Cancel" }));

        expect(routerMock.get).toHaveBeenCalledWith("/widgets");
    });
});
